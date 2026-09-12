<?php

namespace App\Http\Controllers;

use App\Support\UserProfileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Ported from legacy app/Controllers/ProfileController.php — profile view,
 * edit form and password change for the logged-in user.
 *
 * Parity: reserved usernames, username/email uniqueness, optional social URL
 * validation, profile-picture upload into uploads/profiles (same dirs as the
 * legacy UploadService, DB row when media-library categories do it — profile
 * uploads are filesystem-only in legacy too), owner notification + activity
 * log on success. DOB/gender normalized like the legacy closures.
 */
class ProfileController extends Controller
{
    /** Legacy reserved username list (ProfileController). */
    protected const RESERVED_USERNAMES = [
        'admin', 'administrator', 'root', 'superadmin', 'sysadmin', 'system', 'operator',
        'support', 'owner', 'master', 'admin1', 'admin01', 'admin123', 'admin2024',
        'admin2025', 'root1', 'root123', 'superuser', 'supervisor', 'manager1',
        'cmsadmin', 'siteadmin', 'webadmin', 'portaladmin', 'mainadmin', 'control',
        'dashboard', 'panel', 'controlpanel', 'moderator', 'manager', 'usermanager',
        'itadmin', 'dbadmin', 'netadmin', 'devadmin', 'sysop', 'hostmaster',
        'webmaster', 'security', 'john_admin', 'alice_admin', 'mike_admin',
        'admin_mary', 'super_jane', 'testadmin', 'demo_admin', 'qa_admin',
        'defaultadmin', 'guestadmin', 'service', 'rootadmin', 'adm', 'adm1',
        'systemadmin',
    ];

    public function __construct(
        protected UserProfileService $users,
    ) {}

    public function show(): View|RedirectResponse
    {
        $user = $this->users->getProfile((int) Auth::id());
        if (! $user) {
            Auth::logout();

            return redirect('/login');
        }

        return view('user.profile', [
            'title' => 'Your Profile',
            'header_title' => 'Profile Details',
            'user' => $user,
            'roles' => $this->users->getRoles((int) $user->id),
        ]);
    }

    public function edit(): View|RedirectResponse
    {
        $user = $this->users->getProfile((int) Auth::id());
        if (! $user) {
            Auth::logout();

            return redirect('/login');
        }

        return view('user.profile-edit', [
            'title' => 'Edit Profile',
            'header_title' => 'Update Your Information',
            'user' => $user,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $userId = (int) Auth::id();
        $user = $this->users->getProfile($userId);
        if (! $user) {
            Auth::logout();

            return redirect('/login');
        }

        $validated = $request->validate([
            'username' => ['required', 'string', 'max:30', 'regex:/^[a-zA-Z0-9._-]+$/'],
            'email' => ['required', 'email', 'max:190'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            // Legacy normalizeDob accepted Y-m-d and d-m-Y; the form picker sends Y-m-d.
            'dob' => ['nullable', 'date_format:Y-m-d', 'before:today'],
            'phone' => ['nullable', 'string', 'max:30'],
            'alternate_phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'zipcode' => ['nullable', 'string', 'max:20'],
            'facebook_url' => ['nullable', 'url', 'max:255'],
            'twitter_url' => ['nullable', 'url', 'max:255'],
            'instagram_url' => ['nullable', 'url', 'max:255'],
            'linkedin_url' => ['nullable', 'url', 'max:255'],
            'profile_pic' => ['nullable', 'image', 'max:2048'], // legacy: 2 MB profile cap
        ]);

        $username = $validated['username'];
        $email = mb_strtolower(trim($validated['email']));

        if (in_array(mb_strtolower($username), self::RESERVED_USERNAMES, true)) {
            return back()->withInput()->withErrors(['username' => 'This username is not allowed. Please choose another.']);
        }

        if ($username !== (string) $user->username
            && DB::table('users')->where('username', $username)->where('id', '!=', $userId)->whereNull('deleted_at')->exists()) {
            return back()->withInput()->withErrors(['username' => 'Username already taken']);
        }

        if ($email !== (string) $user->email
            && DB::table('users')->where('email', $email)->where('id', '!=', $userId)->whereNull('deleted_at')->exists()) {
            return back()->withInput()->withErrors(['email' => 'Email already in use']);
        }

        $data = [
            'username' => $username,
            'email' => $email,
            'first_name' => $validated['first_name'] ?? '',
            'last_name' => $validated['last_name'] ?? '',
            'gender' => $validated['gender'] ?? null,
            'dob' => $validated['dob'] ?? null,
            'phone' => $validated['phone'] ?? '',
            'alternate_phone' => $validated['alternate_phone'] ?? '',
            'address' => $validated['address'] ?? '',
            'city' => $validated['city'] ?? '',
            'state' => $validated['state'] ?? '',
            'country' => $validated['country'] ?? '',
            'zipcode' => $validated['zipcode'] ?? '',
            'facebook_url' => $validated['facebook_url'] ?? null,
            'twitter_url' => $validated['twitter_url'] ?? null,
            'instagram_url' => $validated['instagram_url'] ?? null,
            'linkedin_url' => $validated['linkedin_url'] ?? null,
        ];

        // Profile picture upload — same public/uploads/profiles dir as
        // the legacy UploadService 'profiles' category (filesystem-only).
        if ($request->hasFile('profile_pic') && $request->file('profile_pic')->isValid()) {
            try {
                $file = $request->file('profile_pic');
                $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
                $filename = 'profile_'.$userId.'_'.bin2hex(random_bytes(6)).'.'.$ext;
                $path = 'profiles/'.$filename;
                Storage::disk('uploads')->makeDirectory('profiles');
                $file->move(Storage::disk('uploads')->path('profiles'), $filename);
                $data['profile_pic'] = '/uploads/profiles/'.$filename;
            } catch (\Throwable $e) {
                Log::error('Profile picture upload failed: '.$e->getMessage());

                return back()->withInput()->withErrors(['profile_pic' => 'Profile image upload failed']);
            }
        }

        $updated = $this->users->updateUser($userId, $data);

        if ($updated) {
            $this->notifyProfileUpdate($userId);
            $this->logActivity($userId, 'User Profile Updated', 'success', [
                'username' => $username,
                'email' => $email,
            ]);

            return redirect('/profile/edit')->with('status', 'Profile updated successfully');
        }

        $this->logActivity($userId, 'User Profile Update Failed', 'failure', ['username' => $username]);

        return back()->withInput()->withErrors(['error' => 'Failed to update profile']);
    }

    public function showPasswordForm(): View
    {
        return view('user.profile-password', [
            'title' => 'Change Password',
            'header_title' => 'Update Password',
        ]);
    }

    /**
     * Port of legacy POST /profile/password (form variant): verifies current
     * password, stores a new hash + password_changed_at. Complexity rules
     * mirror the /user/change-password endpoint (8+, upper/lower/number/special).
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $userId = (int) Auth::id();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [], [
            'new_password' => 'new password',
            'current_password' => 'current password',
        ]);

        $user = $this->users->getUserById($userId);
        if (! $user || ! Hash::check($validated['current_password'], (string) $user->password)) {
            $this->logActivity($userId, 'Password Change Failed - Invalid Current Password', 'failure');

            return back()->withErrors(['current_password' => 'Current password is incorrect']);
        }

        $new = (string) $validated['new_password'];
        $complexityError = $this->complexityError($new);
        if ($complexityError) {
            return back()->withErrors(['new_password' => $complexityError]);
        }

        $this->users->updateUser($userId, [
            'password' => Hash::make($new),
            'password_changed_at' => now(),
        ]);

        $this->logActivity($userId, 'Password Changed Successfully', 'success');

        return redirect('/profile/password')->with('status', 'Password updated successfully');
    }

    protected function complexityError(string $password): ?string
    {
        if (! preg_match('/[A-Z]/', $password) || ! preg_match('/[a-z]/', $password)
            || ! preg_match('/[0-9]/', $password) || ! preg_match('/[^A-Za-z0-9]/', $password)) {
            return 'Password must contain uppercase, lowercase, number, and special character';
        }

        return null;
    }

    /** Legacy parity: in-app notification + delivery log on profile update. */
    protected function notifyProfileUpdate(int $userId): void
    {
        try {
            $notificationId = DB::table('notifications')->insertGetId([
                'user_id' => $userId,
                'title' => 'Profile Updated',
                'message' => 'Your profile was updated successfully.',
                'type' => 'update',
                'data' => json_encode(['user_id' => $userId, 'channels' => ['push', 'in_app', 'email']], JSON_UNESCAPED_UNICODE),
                'action_url' => '',
                'status' => 'sent',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($notificationId) {
                DB::table('notification_logs')->insert([
                    'notification_id' => $notificationId,
                    'user_id' => $userId,
                    'device_id' => null,
                    'channel' => 'system',
                    'ip_address' => null,
                    'token' => 'profile_update',
                    'status' => 'sent',
                    'response' => 'profile',
                    'message_id' => null,
                    'provider_response' => null,
                    'metadata' => null,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Profile update notification failed: '.$e->getMessage());
        }
    }

    protected function logActivity(int $userId, string $action, string $status, array $details = []): void
    {
        try {
            DB::table('activity_logs')->insert([
                'user_id' => $userId,
                'role' => 'user',
                'action' => $action,
                'resource_type' => 'user',
                'resource_id' => $userId,
                'status' => $status,
                'ip_address' => request()->ip(),
                'user_agent' => mb_substr((string) request()->userAgent(), 0, 500),
                'details' => json_encode($details, JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Profile activity log failed: '.$e->getMessage());
        }
    }
}
