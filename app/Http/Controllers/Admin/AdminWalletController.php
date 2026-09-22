<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserRecharge;
use App\Models\UserWalletTransaction;
use App\Support\WalletService;
use Illuminate\Http\Request;

class AdminWalletController extends Controller
{
    public function __construct(protected WalletService $wallet)
    {
    }

    /** All recharge requests (pending first). */
    public function recharges(Request $request)
    {
        $rows = UserRecharge::query()
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest('id')
            ->paginate(20);

        return view('admin.wallet.recharges', ['rows' => $rows]);
    }

    /** Approve a pending recharge → credits the user's wallet. */
    public function approveRecharge(Request $request, int $id)
    {
        $ok = $this->wallet->confirmRecharge($id, $request->input('note'));

        if (! $ok) {
            return back()->with('error', 'রিচার্জটি পাওয়া যায়নি বা ইতোমধ্যে প্রক্রিয়াকরণে আছে।');
        }

        return back()->with('status', 'রিচার্জ অনুমোদিত — ওয়ালেটের ব্যালেন্সে টাকা জমে গেছে।');
    }

    /** Reject a pending recharge. */
    public function rejectRecharge(Request $request, int $id)
    {
        $ok = $this->wallet->rejectRecharge($id, (string) $request->input('note', ''));

        if (! $ok) {
            return back()->with('error', 'রিচার্জটি পাওয়া যায়নি বা ইতোমধ্যে নিষ্পত্তি হয়েছে।');
        }

        return back()->with('status', 'রিচার্জ ব্যর্থ ঘোষণা করা হয়েছে।');
    }

    /** Ledger across all users. */
    public function transactions(Request $request)
    {
        $rows = UserWalletTransaction::query()
            ->when($request->user_id, fn ($q, $u) => $q->where('user_id', $u))
            ->when($request->type, fn ($q, $t) => $q->where('type', $t))
            ->latest('id')
            ->paginate(25);

        return view('admin.wallet.transactions', ['rows' => $rows]);
    }

    /** Users list with balances. */
    public function users(Request $request)
    {
        $rows = User::query()
            ->when($request->q, fn ($q, $s) => $q->where(function ($qq) use ($s) {
                $qq->where('username', 'like', "%{$s}%")
                    ->orWhere('email', 'like', "%{$s}%");
            }))
            ->orderBy('id', 'desc')
            ->paginate(25);

        return view('admin.wallet.users', ['rows' => $rows]);
    }

    /** Credit (+) or debit (-) a user's wallet manually. */
    public function adjustBalance(Request $request, int $id)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'not_in:0'],
            'note' => ['nullable', 'string', 'max:512'],
        ]);

        $user = User::findOrFail($id);

        try {
            $this->wallet->adjustBalance($user, (float) $data['amount'], $data['note'] ?? null);
        } catch (\RuntimeException $e) {
            return back()->with('error', 'সমন্বয় করা যায়নি: ' . $e->getMessage());
        }

        return back()->with('status', 'ওয়ালেট ব্যালেন্স আপডেট করা হয়েছে।');
    }
}
