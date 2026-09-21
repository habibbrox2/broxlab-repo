<?php

namespace App\Http\Controllers;

use App\Support\SecurityService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * User-facing 2FA enrollment (port of the legacy UserSecurityController
 * /user/security/2fa/* routes) using the legacy native session — the same
 * session the login flow's pending_2fa challenge uses.
 */
class UserSecurityController extends Controller
{
    /** Session key holding the un-confirmed secret between setup steps. */
    protected const TEMP_SECRET_KEY = 'temp_2fa_secret';

    public function __construct(
        protected SecurityService $security,
    ) {}

    /** GET /user/security/2fa — overview: status + entry points. */
    public function show2FA(Request $request): View
    {
        $user = $request->user();
        $row = \Illuminate\Support\Facades\DB::table('user_security')
            ->where('user_id', $user->id)
            ->first(['twofa_enabled', 'twofa_method', 'twofa_verified_at']);

        return view('user.security-2fa', [
            'title' => '2FA সেটিংস',
            'twofaEnabled' => (bool) ($row->twofa_enabled ?? false),
            'twofaMethod' => $row->twofa_method ?? null,
            'twofaVerifiedAt' => $row->twofa_verified_at ?? null,
        ]);
    }

    /** GET /user/security/2fa/setup — QR + secret, staged in session. */
    public function setup(Request $request): View
    {
        $user = $request->user();

        // Reuse the staged secret across reloads until confirmed (legacy parity).
        $secret = session(self::TEMP_SECRET_KEY);
        if (! is_string($secret) || $secret === '') {
            $secret = $this->security->generateBase32Secret();
            session([self::TEMP_SECRET_KEY => $secret]);
        }

        $otpauthUri = $this->security->otpauthUri((string) $user->email, $secret);

        return view('user.security-2fa-setup', [
            'title' => '2FA সেটআপ',
            'secret' => $secret,
            'otpauthUri' => $otpauthUri,
            'qrDataUri' => $this->security->qrDataUriForSecret($otpauthUri),
        ]);
    }

    /** POST /user/security/2fa/verify — confirm a live TOTP code, then enable. */
    public function verify(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => 'required|string',
        ]);

        $secret = session(self::TEMP_SECRET_KEY);

        if (! is_string($secret) || $secret === '') {
            return redirect()
                ->route('user.security.2fa.setup')
                ->with('error', '2FA সেশনের মেয়াদ শেষ। আবার শুরু করুন।');
        }

        $code = trim($validated['code']);

        if (strlen($code) !== 6 || ! ctype_digit($code)) {
            return redirect()
                ->route('user.security.2fa.setup')
                ->with('error', 'কোডটি ৬ ডিজিটের সংখ্যা হতে হবে।');
        }

        if (! $this->security->verifyTOTPCode($code, $secret)) {
            $this->security->logActivityPublic('2FA Enrollment Failed - Invalid Code', (int) $request->user()->id, 'failure');

            return redirect()
                ->route('user.security.2fa.setup')
                ->with('error', 'কোডটি সঠিক নয়। আবার চেষ্টা করুন।');
        }

        $result = $this->security->enable2FA((int) $request->user()->id, $secret);

        if (! $result['success']) {
            return redirect()
                ->route('user.security.2fa.setup')
                ->with('error', $result['error'] ?? '2FA চালু করা যায়নি।');
        }

        // Confirmed — drop the staged secret and hand the user their codes.
        $request->session()->forget(self::TEMP_SECRET_KEY);

        return redirect()
            ->route('user.security.2fa.backup')
            ->with('backup_codes', $result['backup_codes']);
    }

    /** GET /user/security/2fa/backup — one-time backup code display. */
    public function backup(Request $request): View|RedirectResponse
    {
        $codes = session('backup_codes');

        if (! is_array($codes) || $codes === []) {
            return redirect()->route('user.security.2fa');
        }

        return view('user.security-2fa-backup', [
            'title' => 'ব্যাকআপ কোড',
            'backupCodes' => $codes,
        ]);
    }

    /** POST /user/security/2fa/disable — turn 2FA off. */
    public function disable(Request $request): RedirectResponse
    {
        $this->security->disable2FA((int) $request->user()->id);

        return redirect()
            ->route('user.security.2fa')
            ->with('status', '2FA বন্ধ করা হয়েছে।');
    }
}
