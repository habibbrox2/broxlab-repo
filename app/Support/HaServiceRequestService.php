<?php

namespace App\Support;

use App\Models\HaCustomer;
use App\Models\HaServiceCategory;
use App\Models\HaServiceRequest;
use App\Models\HaServiceStatusHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Digital service request lifecycle: tracking IDs, server-side validation,
 * guest-friendly creation, status flow and customer/admin notes.
 */
class HaServiceRequestService
{
    public const STATUSES = HaServiceRequest::STATUSES;

    public const OPEN_STATUSES = ['pending', 'submitted', 'under_review', 'processing', 'waiting_customer', 'waiting_external'];

    public function __construct(private HaDocumentService $documents)
    {
    }

    public function trackingId(): string
    {
        do {
            $code = sprintf('DS-%d-%05d', (int) date('Y'), random_int(0, 99999));
        } while (HaServiceRequest::where('tracking_id', $code)->exists());

        return $code;
    }

    /**
     * @param array{name:string,mobile:string,email?:?string,address?:?string,description?:?string,
     *              contact_method?:string,form_data?:array} $data
     * @param array $files raw $_FILES-style Laravel uploaded files
     */
    public function create(HaServiceCategory $category, array $data, array $files = []): HaServiceRequest
    {
        $data = $this->validated($category, $data);

        // Flatten any nesting (documents.0, other field names, ...) then
        // validate up-front so bad files surface as validation errors,
        // not exceptions: allow-listed types only, 5 MB max each.
        $flat = [];
        array_walk_recursive($files, function ($f) use (&$flat) {
            if ($f instanceof \Illuminate\Http\UploadedFile) {
                $flat[] = $f;
            }
        });
        if ($flat !== []) {
            validator(
                ['documents' => $flat],
                ['documents.*' => ['bail', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120']]
            )->validate();
        }

        return DB::transaction(function () use ($category, $data, $files, $flat) {
            $haCustomerId = null;
            if ($existing = HaCustomer::where('mobile', $data['mobile'])->first()) {
                $haCustomerId = $existing->id;
            }

            $request = HaServiceRequest::create([
                'tracking_id' => $this->trackingId(),
                'category_id' => $category->id,
                'user_id' => auth()->id(),
                'ha_customer_id' => $haCustomerId,
                'name' => $data['name'],
                'mobile' => $data['mobile'],
                'email' => $data['email'] ?? null,
                'address' => $data['address'] ?? null,
                'description' => $data['description'] ?? null,
                'form_data' => $data['form_data'] ?? null,
                'contact_method' => $data['contact_method'] ?? 'phone',
                'fee' => (float) $category->base_fee,
                'status' => 'pending',
                'payment_status' => 'unpaid',
            ]);

            if ($flat !== []) {
                $this->documents->storeAll($request, $flat);
            }

            HaServiceStatusHistory::create([
                'service_request_id' => $request->id,
                'from_status' => null,
                'to_status' => 'pending',
                'note' => 'Request received',
                'actor_id' => auth()->id(),
            ]);

            return $request;
        });
    }

    public function validated(HaServiceCategory $category, array $data): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'mobile' => ['required', 'string', 'max:20', 'regex:/^01[3-9][0-9]{8}$/'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'contact_method' => ['nullable', 'in:phone,email,whatsapp'],
        ];

        foreach ((array) ($category->form_fields ?? []) as $field) {
            $key = $field['name'] ?? null;
            if (!is_string($key) || $key === '') {
                continue;
            }
            $rule = ['nullable', 'string', 'max:500'];
            if (($field['required'] ?? false) === true) {
                $rule[0] = 'required';
            }
            $rules['field_' . $key] = $rule;
        }

        validator($data, $rules)->validate();

        $formData = [];
        foreach ((array) ($category->form_fields ?? []) as $field) {
            $key = $field['name'] ?? null;
            if (is_string($key) && isset($data['field_' . $key]) && $data['field_' . $key] !== '') {
                $formData[$key] = (string) $data['field_' . $key];
            }
        }

        return array_merge($data, ['form_data' => $formData]);
    }

    public function transition(HaServiceRequest $request, string $toStatus, ?string $note = null, ?int $actorId = null): HaServiceRequest
    {
        if (!in_array($toStatus, self::STATUSES, true)) {
            throw new \InvalidArgumentException("Unknown status [{$toStatus}]");
        }
        if (!in_array($request->status, self::OPEN_STATUSES, true)) {
            throw new \DomainException('Closed requests cannot change status.');
        }
        if ($toStatus === $request->status) {
            return $request;
        }

        return DB::transaction(function () use ($request, $toStatus, $note, $actorId) {
            $from = $request->status;

            HaServiceStatusHistory::create([
                'service_request_id' => $request->id,
                'from_status' => $from,
                'to_status' => $toStatus,
                'note' => $note,
                'actor_id' => $actorId ?? auth()->id(),
            ]);

            $request->update([
                'status' => $toStatus,
                'completed_at' => $toStatus === 'completed' ? now() : $request->completed_at,
            ]);

            return $request;
        });
    }

    public function assign(HaServiceRequest $request, int $staffId): HaServiceRequest
    {
        $request->update(['assigned_staff_id' => $staffId]);

        return $request;
    }

    public function track(string $code): ?HaServiceRequest
    {
        $code = strtoupper(trim($code));
        if ($code === '' || !preg_match('/^DS-\d{4}-\d{5}$/', $code)) {
            return null;
        }

        return HaServiceRequest::with(['category', 'history'])
            ->where('tracking_id', $code)
            ->first();
    }
}
