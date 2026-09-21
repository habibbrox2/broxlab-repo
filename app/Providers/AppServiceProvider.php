<?php

namespace App\Providers;

use App\Auth\LegacySessionGuard;
use App\Support\AppSettings;
use App\Support\I18n\LanguageService;
use App\Support\I18n\Translator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\View\View as IlluminateView;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AppSettings::class);

        // i18n: singletons so the active language is detected once per request
        // (t() is called 800+ times per page) and dictionaries are read once.
        $this->app->singleton(LanguageService::class);
        $this->app->singleton(Translator::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Custom guard reading the shared legacy native PHP session.
        Auth::extend('legacy-session', function ($app, $name, array $config) {
            $provider = $app['auth']->createUserProvider($config['provider'] ?? null);

            return new LegacySessionGuard($provider, $app['request']);
        });
        // @assetVersion('/assets/css/dist/tailwind-public.css') — mirrors
        // the legacy withAssetVersion() Twig helper.
        Blade::directive('assetVersion', function (string $expression) {
            return "<?php echo assetVersion($expression); ?>";
        });


        // ── Rate limiting (Phase 7 cache/rate-limiting review) ────────────────────
        // Conservative per-IP limits on the auth endpoints that are exposed to
        // anonymous traffic. Password-reset token generation already has a built-in
        // 60 s broker throttle (config/auth.php passwords.users.throttle).
        $this->configureRateLimiting();

        // Legacy Twig exposed `app_settings` to every template — same here.
        View::share('appSettings', $this->app->make(AppSettings::class)->all());

        // Share legacy-equivalent UI globals across all views (header/footer/breadcrumb parity).
        // $sharedTranslations is resolved once per request — the composer runs for
        // every view AND partial, so recomputing two dictionaries each time would
        // be wasteful.
        $sharedTranslations = null;

        View::composer('*', function (IlluminateView $view) use (&$sharedTranslations) {
            $appSettings = $this->app->make(AppSettings::class)->all();

            $view->with('authUser', auth()->user());
            $view->with('isAuthenticated', auth()->check());
            $view->with('isSuperAdmin', auth()->check() && (auth()->user()?->is_super_admin ?? false));
            $view->with('isAdmin', auth()->check()
                && (($authUser = auth()->user()) && ($authUser->is_super_admin ?? false)
                    || ($authUser->role === 'admin')));
            $view->with('canonicalUrl', request()->url());

            if ($sharedTranslations === null) {
                $translator = $this->app->make(Translator::class);
                $sharedTranslations = [
                    'en' => $translator->dictionary('en'),
                    'bn' => $translator->dictionary('bn'),
                ];
            }

            $view->with('siteTranslations', $sharedTranslations);
            $view->with('availableLanguages', $this->app->make(LanguageService::class)->available());

            // Public nav items (mirrors legacy header-v2.twig default nav_items).
            $view->with('publicNavItems', []);
        });
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        // Login: 5 attempts per minute per IP. Conservative — keeps the legacy
        // login path (shared session, no legacy-side throttle) protected on the
        // Laravel side without locking out legitimate admins behind a single NAT.
        RateLimiter::for('login', function ($request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // Registration: 3 attempts per minute per IP. Low because registration
        // is write-heavy (users row + user_roles + verification token + activity log).
        RateLimiter::for('register', function ($request) {
            return Limit::perMinute(3)->by($request->ip());
        });

        // Forgot password: 3 requests per minute per email address. Prevents
        // enumeration spam while still allowing a legitimate user to retry.
        RateLimiter::for('forgot-password', function ($request) {
            return Limit::perMinute(3)->by($request->input('email', $request->ip()));
        });

        // Resend verification email: 3 requests per minute per email address.
        // Same enumeration concern as forgot-password.
        RateLimiter::for('resend-verification', function ($request) {
            return Limit::perMinute(3)->by($request->input('email', $request->ip()));
        });
    }
}