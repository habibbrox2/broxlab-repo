<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HaCustomer;
use App\Support\ActivityLogger;
use App\Support\HaCashRegisterService;
use App\Support\HaCustomerLedgerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Hero Alif CRM: customers, due ledger, collections.
 */
class HaCustomerController extends Controller
{
    public function __construct(
        protected HaCustomerLedgerService $ledger,
    ) {}

    public function index(Request $r): View
    {
        $q = HaCustomer::query()->orderByDesc('id');

        if ($search = trim((string) $r->query('search', ''))) {
            $term = '%'.$search.'%';
            $q->where(function ($w) use ($term) {
                $w->where('name', 'like', $term)->orWhere('mobile', 'like', $term);
            });
        }
        if ($r->query('due') === '1') {
            $q->where('due_balance', '>', 0);
        }

        return view('admin.ha.customers', [
            'customers' => $q->paginate(50)->withQueryString(),
            'search' => $r->query('search', ''),
            'dueOnly' => $r->query('due') === '1',
        ]);
    }

    public function store(Request $r): RedirectResponse
    {
        $data = $r->validate([
            'name' => ['required', 'string', 'max:160'],
            'mobile' => ['required', 'string', 'max:32', 'unique:ha_customers,mobile'],
            'email' => ['nullable', 'email', 'max:160'],
            'address' => ['nullable', 'string', 'max:1000'],
            'customer_type' => ['required', 'in:retail,wholesale'],
            'opening_due' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $customer = HaCustomer::query()->create([
            'name' => $data['name'],
            'mobile' => $data['mobile'],
            'email' => $data['email'] ?? null,
            'address' => $data['address'] ?? null,
            'customer_type' => $data['customer_type'],
            'notes' => $data['notes'] ?? null,
            'due_balance' => 0,
        ]);

        if (($data['opening_due'] ?? 0) > 0) {
            \Illuminate\Support\Facades\DB::transaction(function () use ($customer, $data) {
                $this->ledger->postEntry($customer->id, 'opening_due', (float) $data['opening_due'], 'manual', null, 'Opening due', auth()->id());
            });
        }

        ActivityLogger::log('ha_customer', $customer->id, 'created', ['name' => $data['name']]);

        return redirect('/admin/ha/customers')->with('status', 'Customer added');
    }

    public function show(int $id): View
    {
        $customer = HaCustomer::query()->findOrFail($id);

        return view('admin.ha.customer-show', [
            'customer' => $customer,
            'sales' => $customer->sales()->orderByDesc('id')->limit(25)->get(),
            'payments' => $customer->payments()->limit(25)->get(),
            'statement' => $this->ledger->statement($id),
        ]);
    }

    public function update(Request $r, int $id): RedirectResponse
    {
        $customer = HaCustomer::query()->findOrFail($id);

        $data = $r->validate([
            'name' => ['required', 'string', 'max:160'],
            'mobile' => ['required', 'string', 'max:32', 'unique:ha_customers,mobile,'.$id],
            'email' => ['nullable', 'email', 'max:160'],
            'address' => ['nullable', 'string', 'max:1000'],
            'customer_type' => ['required', 'in:retail,wholesale'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $customer->update($data);
        ActivityLogger::log('ha_customer', $id, 'updated', ['name' => $data['name']]);

        return redirect('/admin/ha/customers/'.$id)->with('status', 'Customer updated');
    }

    public function collect(Request $r, int $id): RedirectResponse
    {
        $data = $r->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'in:cash,bkash,nagad,bank,other_mbanking'],
            'reference' => ['nullable', 'string', 'max:64'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $register = app(HaCashRegisterService::class)->current();

        try {
            $result = $this->ledger->collectPayment(
                $id,
                (float) $data['amount'],
                $data['method'],
                null,
                $data['reference'] ?? null,
                $data['note'] ?? null,
                (int) auth()->id(),
                $register
            );
        } catch (\Throwable $e) {
            return redirect('/admin/ha/customers/'.$id)->with('error', 'Collection failed: '.$e->getMessage());
        }

        return redirect('/admin/ha/customers/'.$id)
            ->with('status', "Collected ৳{$result['amount']}; remaining due ৳{$result['remaining_due']}");
    }
}
