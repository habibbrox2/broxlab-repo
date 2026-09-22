<?php

namespace App\Http\Controllers;

use App\Models\UserRecharge;
use App\Models\UserWalletTransaction;
use App\Support\WalletService;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function __construct(protected WalletService $wallet)
    {
    }

    /**
     * Wallet home: current balance + recent activity + pending recharges.
     */
    public function dashboard(Request $request)
    {
        $user = $request->user();
        $wallet = $this->wallet;

        return view('wallet.dashboard', [
            'balance' => $wallet->balance($user),
            'recent' => $wallet->recentTransactions($user, 8),
            'pending' => $wallet->pendingRecharges($user),
        ]);
    }

    /** Show the recharge form. */
    public function rechargeForm(Request $request)
    {
        return view('wallet.recharge');
    }

    /** Submit a recharge request (created pending — credited after admin review). */
    public function recharge(Request $request)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:100000'],
            'method' => ['required', 'string', 'max:30'],
            'payer_phone' => ['nullable', 'string', 'max:30'],
            'transaction_id' => ['nullable', 'string', 'max:100'],
        ]);

        $this->wallet->createRecharge($request->user(), $data);

        return redirect()->route('wallet.recharges')
            ->with('status', 'আপনার রিচার্জ অনুরোধটি জমা দেওয়া হয়েছে। পর্যালোচনার পর আপনার ওয়ালেটের ভারি থেকে টাকা জমে যাবে।');
    }

    /** Paginated ledger. */
    public function transactions(Request $request)
    {
        $rows = UserWalletTransaction::where('user_id', $request->user()->id)
            ->latest('id')
            ->paginate(15);

        return view('wallet.transactions', ['rows' => $rows]);
    }

    /** Paginated recharge history. */
    public function recharges(Request $request)
    {
        $rows = UserRecharge::where('user_id', $request->user()->id)
            ->latest('id')
            ->paginate(15);

        return view('wallet.recharges', ['rows' => $rows]);
    }

    /** Single recharge detail. */
    public function showRecharge(Request $request, int $id)
    {
        $recharge = UserRecharge::where('user_id', $request->user()->id)->findOrFail($id);

        return view('wallet.recharge-view', ['recharge' => $recharge]);
    }
}
