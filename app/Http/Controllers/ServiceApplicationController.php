<?php

namespace App\Http\Controllers;

use App\Support\ServiceApplicationService;
use App\Support\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ServiceApplicationController extends Controller
{
    public function __construct(
        protected ServiceApplicationService $applications,
        protected WalletService $wallet,
    ) {
    }

    /**
     * List the authenticated user's service applications.
     */
    public function index(Request $request)
    {
        return view('services.applications', [
            'rows' => $this->applications->myApplications($request->user()),
        ]);
    }

    /**
     * Show the "Apply for Service" form.
     */
    public function applyForm(Request $request, string $slug)
    {
        $service = DB::table('services')->where('slug', $slug)->whereNull('deleted_at')->first();

        if (! $service) {
            abort(404, 'Service not found.');
        }

        return view('services.application-form', [
            'service' => $service,
            'balance' => $this->wallet->balance($request->user()),
        ]);
    }

    /**
     * Submit an application for a service. Paid services debit the wallet.
     */
    public function apply(Request $request, string $slug)
    {
        $service = DB::table('services')->where('slug', $slug)->whereNull('deleted_at')->first();

        if (! $service) {
            abort(404, 'Service not found.');
        }

        $price = (float) ($service->price ?? 0);

        $result = $this->applications->createApplication(
            $request->user(),
            $service->id,
            $request->input('data', []),
            $price,
        );

        if ($result['error'] === 'insufficient_balance') {
            return redirect()->route('wallet.recharge')
                ->with('error', 'আপনার ওয়ালেটে যথেষ্ট টাকা নেই। অনুগ্রহয়াে আগে রিচার্জ করুন।');
        }

        if ($result['error'] === 'already_applied') {
            return back()->with('error', 'আপনি ইতোমধ্যে এই সেবাটির জন্য আবেদন করেছেন।');
        }

        return redirect()->route('services.applications')
            ->with('status', 'আপনার আবেদনটি জমা দেওয়া হয়েছে।');
    }

    /**
     * Show a single application.
     */
    public function show(Request $request, int $id)
    {
        $application = DB::table('service_applications as a')
            ->join('services as s', 's.id', '=', 'a.service_id')
            ->where('a.user_id', $request->user()->id)
            ->where('a.id', $id)
            ->select('a.*', 's.name as service_name', 's.price as service_price')
            ->firstOrFail();

        $payment = DB::table('service_application_payments')
            ->where('application_id', $id)
            ->first();

        return view('services.application-show', [
            'application' => $application,
            'payment' => $payment,
        ]);
    }

    /**
     * Cancel an application — refunds the wallet for paid services.
     */
    public function cancel(Request $request, int $id)
    {
        $this->applications->cancel(
            $request->user(),
            $id,
            $request->input('reason', ''),
        );

        return back()->with('status', 'আবেদনটি বাতিল করা হয়েছে। যদি পেমেন্ট করা হয়ে থাকে, টাকা ওয়ালেটে ফেরত যাবে।');
    }
}
