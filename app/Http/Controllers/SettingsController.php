<?php

namespace App\Http\Controllers;

use App\Support\UserProfileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Ported from legacy app/Controllers/SettingsController.php — GET /user/settings
 * (account settings + password/OAuth state). Write flows live in
 * ProfileController (password) and remain legacy-owned for OAuth linking
 * until their phase.
 */
class SettingsController extends Controller
{
    public function __construct(
        protected UserProfileService $users,
    ) {}

    public function index(Request $request): View
    {
        $userId = (int) Auth::id();
        $user = $this->users->getProfile($userId);
        if (! $user) {
            abort(404, 'User not found');
        }

        return view('user.settings', [
            'title' => 'Account Settings',
            'user_data' => $user,
            'user_has_password' => $this->users->userHasPassword($userId),
            'show_password_setup' => $this->users->needsFirstTimePasswordSetup($userId),
            'current_tab' => (string) $request->query('tab', 'account'),
        ]);
    }
}
