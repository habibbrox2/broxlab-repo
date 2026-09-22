<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AppSettings;
use App\Support\MailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Security settings — authentication, reCAPTCHA and SMTP, all persisted to the
 * single app_settings row (columns ARE the keys, same store MailService and the
 * registration/2FA flows read).
 *
 * Replaces the earlier redirect stubs: the sub-screens are real forms now, and
 * the overview (/admin/security) still shows the current values with Configure
 * links into each tab.
 *
 * SMTP secrets are masked on read (never echoed back in full) and a "Send test
 * email" action exercises the exact MailService path production uses.
 */
class AdminSecurityController extends Controller
{
    /** Whitelist: which request keys may be persisted, per screen. */
    protected const FIELDS = [
        'auth' => [
            'allow_user_registration' => 'bool',
            'require_email_verification' => 'bool',
            'enable_2fa' => 'bool',
            'max_login_attempts' => 'int',
        ],
        'recaptcha' => [
            'recaptcha_enabled' => 'bool',
            'recaptcha_site_key' => 'string',
            'recaptcha_secret_key' => 'string',
            'recaptcha_threshold' => 'float',
        ],
        'smtp' => [
            'smtp_host' => 'string',
            'smtp_port' => 'int',
            'smtp_username' => 'string',
            'smtp_password' => 'string',
            'smtp_encryption' => 'string',
            'mail_from_address' => 'string',
            'mail_from_name' => 'string',
        ],
    ];

    public function __construct(
        protected AppSettings $settings,
        protected MailService $mailer,
    ) {}

    /** Overview: current values of the three groups. */
    public function index(): View
    {
        return $this->view('admin.security.index', [
            'title' => 'Security Settings',
            'header_title' => 'Security Settings',
        ]);
    }

    public function auth(): View
    {
        return $this->view('admin.security.auth', [
            'title' => 'Authentication Settings',
            'header_title' => 'Authentication Settings',
        ]);
    }

    public function recaptcha(): View
    {
        return $this->view('admin.security.recaptcha', [
            'title' => 'reCAPTCHA Settings',
            'header_title' => 'reCAPTCHA Settings',
        ]);
    }

    public function smtp(): View
    {
        return $this->view('admin.security.smtp', [
            'title' => 'SMTP Settings',
            'header_title' => 'SMTP Settings',
        ]);
    }

    /**
     * Persist one screen's fields onto the app_settings row.
     *
     * The section is parsed from the path (the POST routes are concrete paths
     * like /admin/security/auth, not wildcard-parameterized), so the action
     * signature stays zero-parameter.
     */
    public function update(Request $request): RedirectResponse
    {
        $section = str_replace('admin/security/', '', $request->path());
        if (! isset(self::FIELDS[$section])) {
            return redirect('/admin/security')->with('error', 'Unknown settings section.');
        }

        $rules = [
            'max_login_attempts' => ['nullable', 'integer', 'min:1', 'max:100'],
            'recaptcha_threshold' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'smtp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_encryption' => ['nullable', 'in:tls,ssl,none'],
            'mail_from_address' => ['nullable', 'email', 'max:190'],
            'recaptcha_site_key' => ['nullable', 'string', 'max:190'],
            'recaptcha_secret_key' => ['nullable', 'string', 'max:190'],
            'smtp_host' => ['nullable', 'string', 'max:190'],
            'smtp_username' => ['nullable', 'string', 'max:190'],
            'smtp_password' => ['nullable', 'string', 'max:190'],
            'mail_from_name' => ['nullable', 'string', 'max:190'],
        ];

        $data = [];
        foreach (self::FIELDS[$section] as $field => $type) {
            $value = $request->input($field);

            switch ($type) {
                case 'bool':
                    $data[$field] = $request->boolean($field) ? 1 : 0;
                    continue 2;
                case 'int':
                    if (($value ?? '') === '') {
                        $data[$field] = null;
                        continue 2;
                    }
                    $data[$field] = (int) $value;
                    break;
                case 'float':
                    if (($value ?? '') === '') {
                        $data[$field] = null;
                        continue 2;
                    }
                    $data[$field] = (float) $value;
                    break;
                default:
                    $data[$field] = trim((string) ($value ?? ''));
            }

            // Per-field validation from the shared rules above.
            if (isset($rules[$field])) {
                $validated = validator([$field => $value], [$field => $rules[$field]])->validate();
                $data[$field] = $validated[$field] === null ? null : $data[$field];
            }
        }

        DB::table('app_settings')->where('id', 1)->update($data + ['updated_at' => now()]);

        // The AppSettings helper caches the row — drop it so the change is live.
        \Illuminate\Support\Facades\Cache::forget('app_settings:row');

        $this->logActivity('Security Settings Updated', $section);

        return redirect("/admin/security/{$section}")
            ->with('status', ucfirst($section).' settings saved.');
    }

    /** Send a test email through the production MailService path. */
    public function testMail(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'test_email' => ['required', 'email', 'max:190'],
        ]);

        $html = '<p>This is a test message sent from BroxLab admin security settings at '.now()->format('Y-m-d H:i:s').'.</p>';
        $ok = $this->mailer->send($validated['test_email'], 'BroxLab SMTP test', $html);

        return redirect('/admin/security/smtp')->with(
            $ok ? 'status' : 'error',
            $ok
                ? 'Test email queued/sent to '.$validated['test_email'].'.'
                : 'Test email failed — check the SMTP settings and the log.'
        );
    }

    /**
     * Shared view data: the settings row plus masked secrets for the forms.
     *
     * @param  array<string, mixed>  $data
     */
    protected function view(string $view, array $data): View
    {
        $row = DB::table('app_settings')->where('id', 1)->first();
        $s = $row ? (array) $row : [];

        $data['appSettings'] = $s;
        // Show only a masked hint for secrets; a blank field means "keep".
        $data['smtp_password_masked'] = $this->mask((string) ($s['smtp_password'] ?? ''));
        $data['recaptcha_secret_masked'] = $this->mask((string) ($s['recaptcha_secret_key'] ?? ''));

        return view($view, $data);
    }

    protected function mask(string $secret): string
    {
        if ($secret === '') {
            return '';
        }

        return str_repeat('•', min(10, max(4, strlen($secret) - 4))).substr($secret, -4);
    }

    protected function logActivity(string $action, string $section): void
    {
        try {
            DB::table('activity_logs')->insert([
                'user_id' => (int) auth()->id(),
                'role' => app(\App\Support\UserProfileService::class)->rbacFor((int) auth()->id())['roles'][0] ?? 'admin',
                'action' => $action,
                'resource_type' => 'security_settings',
                'resource_id' => 0,
                'status' => 'success',
                'ip_address' => request()?->ip() ?? '0.0.0.0',
                'user_agent' => mb_substr((string) (request()?->userAgent() ?? ''), 0, 500),
                'details' => json_encode(['section' => $section], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('security settings activity log failed: '.$e->getMessage());
        }
    }
}
