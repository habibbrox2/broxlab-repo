<?php

use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\Admin\AdminAccountController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminPostController;
use App\Http\Controllers\Admin\AdminPageController;
use App\Http\Controllers\Admin\AdminServiceController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminRbacController;
use App\Http\Controllers\Admin\AdminRevenueController;
use App\Http\Controllers\Admin\AdminWalletController;
use App\Http\Controllers\Admin\AdminLogsController;
use App\Http\Controllers\Admin\AdminSecurityController;
use App\Http\Controllers\Admin\AdminSetupController;
use App\Http\Controllers\Admin\AdminScraperController;
use App\Http\Controllers\Admin\AdminCvController;
use App\Http\Controllers\Admin\AdminNotificationController;
use App\Http\Controllers\Admin\TagCategoryController;
use App\Http\Controllers\Admin\AdminKharijController;
use App\Http\Controllers\KharijVerifyController;
use App\Http\Controllers\UserSecurityController;
use App\Http\Controllers\Admin\AdminSitemapController;
use App\Http\Controllers\Admin\AdminWeatherController;
use App\Http\Controllers\Admin\AdminLiveTvController;
use App\Http\Controllers\Admin\AdminCalculatorController;
use App\Http\Controllers\Admin\AdminOcrController;
use App\Http\Controllers\Admin\AdminPhotoStudioController;
use App\Http\Controllers\Admin\AdminAiSystemController;
use App\Http\Controllers\Admin\AdminApiProxyController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\DonationController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\TranslateController;
use App\Http\Controllers\FirebaseConfigController;
use App\Http\Controllers\MobileController;
use App\Http\Controllers\Admin\AdminMobileController;
use App\Http\Controllers\NotificationsController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicPageController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\Internal\ScrapControlCenterController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CvExportController;
use App\Http\Controllers\ServiceApplicationController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\MedicinesController;
use App\Http\Controllers\WeatherApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Migrated routes (strangler fig)
|--------------------------------------------------------------------------
| Each route below is delegated to Laravel by the bridge in
| public/index.php (allowlist in laravel/bridge.php).
| Legacy controllers keep serving everything that is not listed here.
|
| Route order matters: /posts/view must be registered before /posts/{id}.
*/

Route::get('/', [HomeController::class, 'home'])->name('home');
Route::get('/api/feed/load-more', [HomeController::class, 'loadMore'])->name('home.feed.load-more');
Route::get('/weather/details', [WeatherApiController::class, 'details'])->name('weather.details');

// Auth — Phase 2 (custom guard over the shared legacy session)
Route::middleware('guest')->group(function () {
    // Brute-force protection (Phase 7 cache/rate-limiting review).
    // Login: 5 attempts/min per IP.
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:login')
        ->name('login.submit');

    // Registration: 3 attempts/min per IP.
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('throttle:register')
        ->name('register.submit');

    // Forgot password: 3 requests/min per email (enumeration guard).
    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('forgot-password');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
        ->middleware('throttle:forgot-password')
        ->name('forgot-password.submit');

    Route::get('/reset-password', [AuthController::class, 'showResetPassword'])->name('reset-password');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('reset-password.submit');

    // 2FA verify (pending_2fa session — guest by definition).
    // No throttle here: the login throttle already gates the path that leads here,
    // and 2FA codes are short-lived + tied to a session the attacker doesn't have.
    Route::get('/verify-2fa', [AuthController::class, 'showVerify2FA'])->name('verify-2fa');
    Route::post('/verify-2fa', [AuthController::class, 'verify2FA'])->name('verify-2fa.submit');

    // Email verification (token links + manual entry + resend).
    // Resend: 3 requests/min per email (enumeration guard).
    Route::get('/verify-email', [AuthController::class, 'showVerifyEmail'])->name('verify-email');
    Route::post('/verify-email', [AuthController::class, 'verifyEmail'])->name('verify-email.submit');
    Route::get('/send-verification-email', [AuthController::class, 'showSendVerificationEmail'])->name('send-verification-email');
    Route::post('/resend-verification-email', [AuthController::class, 'resendVerificationEmail'])
        ->middleware('throttle:resend-verification')
        ->name('resend-verification-email');
});
Route::match(['get', 'post'], '/logout', [AuthController::class, 'logout'])->name('logout');

// Static/public pages (Phase 1 + backlog follow-ups)
Route::get('/about-us', [PageController::class, 'about'])->name('pages.about');
Route::get('/about', fn () => redirect('/about-us', 301))->name('pages.about.alias');
Route::get('/contact', [PageController::class, 'contact'])->name('pages.contact');
Route::post('/contact', [PageController::class, 'contactSubmit'])->name('pages.contact.submit');
Route::get('/advertise', [PageController::class, 'advertise'])->name('pages.advertise');
Route::post('/advertise', [PageController::class, 'advertiseSubmit'])->name('pages.advertise.submit');
Route::view('/digital-sheba', 'pages.digital-sheba')->name('digital-sheba');
Route::get('/faq', [PageController::class, 'faq'])->name('pages.faq');
Route::get('/terms', [PageController::class, 'terms'])->name('pages.terms');
Route::get('/privacy', [PageController::class, 'privacy'])->name('pages.privacy');
Route::get('/newsletter', [PageController::class, 'newsletter'])->name('pages.newsletter');
Route::post('/newsletter/subscribe', [PageController::class, 'subscribe'])->name('newsletter.subscribe');

// Donations — public page + bKash callback (Phase 8 backlog: MonetizationController)
Route::get('/donate', [DonationController::class, 'show'])->name('donate.show');
Route::post('/donate', [DonationController::class, 'submit'])->name('donate.submit');
Route::match(['get', 'post'], '/donate/bkash/callback', [DonationController::class, 'bkashCallback'])->name('donate.bkash.callback');

// Language switch — legacy /lang/{code} (GET redirect + POST JSON)
Route::get('/lang/{code}', [LanguageController::class, 'switch'])->name('lang.switch');
Route::post('/lang/{code}', [LanguageController::class, 'switchJson'])->name('lang.switch.json');

// Dynamic translation — legacy POST /api/translate (brox-i18n.js batch fallback)
Route::post('/api/translate', [TranslateController::class, 'translate'])->name('api.translate');

// Kharij QR verification (public, no auth — scanner apps hit these directly)
// Legacy paths preserved: /mutation-land-gov-bd/qr-vk/{hash}
Route::get('/mutation-land-gov-bd/qr-vk/{hash}', [KharijVerifyController::class, 'verify'])->name('kharij.verify');
Route::get('/api/kharij/verify/{hash}', [KharijVerifyController::class, 'verifyJson'])->name('kharij.verify.json');

// Firebase Web SDK config — legacy GET /api/firebase-config (public-by-design values)
Route::get('/api/firebase-config', [FirebaseConfigController::class, 'show'])->name('api.firebase-config');
Route::match(['post', 'put', 'patch', 'delete'], '/api/firebase-config', [FirebaseConfigController::class, 'rejectWrite'])->name('api.firebase-config.reject');

// robots.txt + split XML sitemaps (legacy SitemapController port)
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('sitemap.robots');
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap.index');
Route::get('/sitemap-posts.xml', [SitemapController::class, 'posts'])->name('sitemap.posts');
Route::get('/sitemap-pages.xml', [SitemapController::class, 'pages'])->name('sitemap.pages');
Route::get('/sitemap-categories.xml', [SitemapController::class, 'categories'])->name('sitemap.categories');
Route::get('/sitemap-tags.xml', [SitemapController::class, 'tags'])->name('sitemap.tags');
Route::get('/sitemap-services.xml', [SitemapController::class, 'services'])->name('sitemap.services');
Route::get('/sitemap-mobiles.xml', [SitemapController::class, 'mobiles'])->name('sitemap.mobiles');
Route::get('/sitemap-static.xml', [SitemapController::class, 'staticPages'])->name('sitemap.static');
Route::get('/sitemap-medex-brands.xml', [SitemapController::class, 'medexBrands'])->name('sitemap.medex-brands');
Route::get('/sitemap-products.xml', [SitemapController::class, 'productsRedirect'])->name('sitemap.products');

// Public CMS page viewer — legacy /pages/view/{slug}
Route::get('/pages/view/{slug}', [PublicPageController::class, 'view'])->name('pages.view');

// Category / tag index aliases — legacy 301s to /categories and /tags
Route::get('/category', fn () => redirect('/categories', 301))->name('category.index.alias');
Route::get('/tag', fn () => redirect('/tags', 301))->name('tag.index.alias');

// Posts — public read side (Phase 4)
Route::get('/posts', [PostController::class, 'index'])->name('posts.index');
Route::get('/posts/view', [PostController::class, 'view'])->name('posts.view.query');
Route::get('/posts/view/{slug}', [PostController::class, 'view'])->name('posts.view');
Route::get('/posts/{id}/{slug}', [PostController::class, 'viewById'])->name('posts.show.id.slug');
Route::get('/posts/{id}', [PostController::class, 'viewById'])->whereNumber('id')->name('posts.show.id');

// Comments — write endpoints for the Blade comment UI (read tree is server-rendered)
Route::post('/comment/add', [CommentController::class, 'add'])->name('comments.add');
Route::post('/comment/edit', [CommentController::class, 'edit'])->name('comments.edit');
Route::post('/comment/delete', [CommentController::class, 'delete'])->name('comments.delete');
Route::post('/comment/react', [CommentController::class, 'react'])->name('comments.react');
Route::post('/comment/like', [CommentController::class, 'like'])->name('comments.like');

// Mobiles catalog — public read side
Route::get('/mobiles', [MobileController::class, 'index'])->name('mobiles.index');
Route::get('/mobiles/prices', [MobileController::class, 'prices'])->name('mobiles.prices');
Route::get('/mobiles/new', [MobileController::class, 'newArrivals'])->name('mobiles.new');
Route::get('/mobiles/brands', [MobileController::class, 'brands'])->name('mobiles.brands');
Route::get('/mobiles/compare', [MobileController::class, 'compare'])->name('mobiles.compare');
Route::get('/mobiles/view/{id}', [MobileController::class, 'view'])->whereNumber('id')->name('mobiles.view');

// Categories / tags — archives
Route::get('/categories', [ArchiveController::class, 'categories'])->name('categories.index');
Route::get('/category/{slug}', [ArchiveController::class, 'category'])->name('category.archive');
Route::get('/tags', [ArchiveController::class, 'tags'])->name('tags.index');
Route::get('/tag/{slug}', [ArchiveController::class, 'tag'])->name('tag.archive');

// Services — user area (auth-gated). Registered BEFORE /services/{slug}
// so the /services/applications path is not swallowed by the {slug} catch-all.
Route::middleware('auth')->group(function () {
    Route::get('/services/applications', [ServiceApplicationController::class, 'index'])->name('services.applications');
    Route::get('/services/applications/{id}', [ServiceApplicationController::class, 'show'])->name('services.application.show');
    Route::post('/services/applications/{id}/cancel', [ServiceApplicationController::class, 'cancel'])->name('services.application.cancel');
    Route::get('/services/{slug}/apply', [ServiceApplicationController::class, 'applyForm'])->name('services.apply');
    Route::post('/services/{slug}/apply', [ServiceApplicationController::class, 'apply'])->name('services.apply.submit');
});

// Services — public read side (canonical /services/view/{slug} + legacy /services/{slug})
Route::get('/services', [ServiceController::class, 'index'])->name('services.index');
Route::get('/services/view/{slugOrId}', [ServiceController::class, 'view'])->name('services.view');
Route::get('/services/{slug}', [ServiceController::class, 'view'])->name('services.show');

// Medicines catalog — Phase 6 (herbal pharma companies/brands; JSON-file driven)
Route::get('/medicines', [MedicinesController::class, 'companies'])->name('medicines.index');
Route::get('/medicines/details', [MedicinesController::class, 'details'])->name('medicines.details');
Route::get('/medicines/companies', [MedicinesController::class, 'companiesRedirect'])->name('medicines.companies');
Route::get('/medicines/company/{id}', [MedicinesController::class, 'company'])->whereNumber('id')->name('medicines.company');
Route::get('/medicines/brand/{id}', [MedicinesController::class, 'brand'])->whereNumber('id')->name('medicines.brand');

// Medicines JSON API + JS scraper support (dual-auth: MEDEX_REFRESH_TOKEN OR CSRF)
Route::get('/api/medicines/companies', [MedicinesController::class, 'apiCompanies'])->name('medicines.api.companies');
Route::get('/api/medicines/company/{id}', [MedicinesController::class, 'apiCompany'])->whereNumber('id')->name('medicines.api.company');
Route::get('/api/medicines/brand/{id}', [MedicinesController::class, 'apiBrand'])->whereNumber('id')->name('medicines.api.brand');
Route::match(['get', 'post'], '/api/medicines/refresh', [MedicinesController::class, 'apiRefresh'])->name('medicines.api.refresh');
Route::post('/api/medicines/proxy', [MedicinesController::class, 'apiProxy'])->name('medicines.api.proxy');
Route::post('/api/medicines/fetch-page', [MedicinesController::class, 'apiFetchPage'])->name('medicines.api.fetch-page');
Route::post('/api/medicines/save-data', [MedicinesController::class, 'apiSaveData'])->name('medicines.api.save-data');

// Admin — Phase 5 (admin area: layout + dashboard + taxonomy CRUD)
Route::get('/admin', fn () => redirect('/admin/dashboard'));
Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/admin/dashboard', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
    Route::get('/api/admin/sidebar-counts', [AdminDashboardController::class, 'sidebarCounts'])->name('admin.sidebar-counts');

    // Categories + tags — port of TagsCategoriesController admin routes
    Route::get('/admin/categories', [TagCategoryController::class, 'categoryIndex'])->name('admin.categories.index');
    Route::get('/admin/categories/create', [TagCategoryController::class, 'categoryCreate'])->name('admin.categories.create');
    Route::post('/admin/categories/create', [TagCategoryController::class, 'categoryStore'])->name('admin.categories.store');
    Route::get('/admin/categories/view/{id}', [TagCategoryController::class, 'categoryShow'])->name('admin.categories.show');
    Route::get('/admin/categories/edit/{id}', [TagCategoryController::class, 'categoryEdit'])->name('admin.categories.edit');
    Route::post('/admin/categories/edit/{id}', [TagCategoryController::class, 'categoryUpdate'])->name('admin.categories.update');
    // Delete is confirm-on-GET, destroy-on-POST (CSRF-protected).
    Route::get('/admin/categories/delete/{id}', [TagCategoryController::class, 'categoryDeleteConfirm'])->whereNumber('id')->name('admin.categories.delete.confirm');
    Route::post('/admin/categories/delete/{id}', [TagCategoryController::class, 'categoryDestroy'])->whereNumber('id')->name('admin.categories.destroy');

    Route::get('/admin/tags', [TagCategoryController::class, 'tagIndex'])->name('admin.tags.index');
    Route::get('/admin/tags/create', [TagCategoryController::class, 'tagCreate'])->name('admin.tags.create');
    Route::post('/admin/tags/create', [TagCategoryController::class, 'tagStore'])->name('admin.tags.store');
    Route::get('/admin/tags/view/{id}', [TagCategoryController::class, 'tagShow'])->name('admin.tags.show');
    Route::get('/admin/tags/edit/{id}', [TagCategoryController::class, 'tagEdit'])->name('admin.tags.edit');
    Route::post('/admin/tags/edit/{id}', [TagCategoryController::class, 'tagUpdate'])->name('admin.tags.update');
    Route::get('/admin/tags/delete/{id}', [TagCategoryController::class, 'tagDeleteConfirm'])->whereNumber('id')->name('admin.tags.delete.confirm');
    Route::post('/admin/tags/delete/{id}', [TagCategoryController::class, 'tagDestroy'])->whereNumber('id')->name('admin.tags.destroy');

    // Pages CMS — port of PagesController admin routes
    Route::get('/admin/pages', [AdminPageController::class, 'index'])->name('admin.pages.index');
    Route::get('/admin/pages/create', [AdminPageController::class, 'create'])->name('admin.pages.create');
    Route::post('/admin/pages/create', [AdminPageController::class, 'store'])->name('admin.pages.store');
    Route::get('/admin/pages/view/{slug}', [AdminPageController::class, 'show'])->name('admin.pages.show');
    Route::get('/admin/pages/edit/{id}', [AdminPageController::class, 'edit'])->whereNumber('id')->name('admin.pages.edit');
    Route::post('/admin/pages/edit/{id}', [AdminPageController::class, 'update'])->whereNumber('id')->name('admin.pages.update');
    Route::get('/admin/pages/delete/{id}', [AdminPageController::class, 'deleteConfirm'])->whereNumber('id')->name('admin.pages.delete.confirm');
    Route::post('/admin/pages/delete/{id}', [AdminPageController::class, 'destroy'])->whereNumber('id')->name('admin.pages.destroy');
    Route::get('/api/pages/check_url', [AdminPageController::class, 'checkUrl'])->name('admin.pages.check-url');
    // Posts — port of PostsController admin routes (+ legacy ?id= query form)
    Route::get('/admin/posts', [AdminPostController::class, 'index'])->name('admin.posts.index');
    Route::get('/admin/posts/create', [AdminPostController::class, 'create'])->name('admin.posts.create');
    Route::post('/admin/posts/create', [AdminPostController::class, 'store'])->name('admin.posts.store');
    Route::get('/admin/posts/view/{id}', [AdminPostController::class, 'show'])->whereNumber('id')->name('admin.posts.show');
    Route::get('/admin/posts/edit/{id}', [AdminPostController::class, 'edit'])->whereNumber('id')->name('admin.posts.edit');
    Route::post('/admin/posts/edit/{id}', [AdminPostController::class, 'update'])->whereNumber('id')->name('admin.posts.update');
    Route::get('/admin/posts/delete/{id}', [AdminPostController::class, 'deleteConfirm'])->whereNumber('id')->name('admin.posts.delete.confirm');
    Route::post('/admin/posts/delete/{id}', [AdminPostController::class, 'destroy'])->whereNumber('id')->name('admin.posts.destroy.path');

    // Legacy query-string forms (?id=) — keep working alongside path forms
    Route::get('/admin/posts/view', [AdminPostController::class, 'show'])->name('admin.posts.show.query');
    Route::get('/admin/posts/edit', [AdminPostController::class, 'edit'])->name('admin.posts.edit.query');
    Route::post('/admin/posts/edit', [AdminPostController::class, 'update'])->name('admin.posts.update.query');
    Route::get('/admin/posts/delete', [AdminPostController::class, 'deleteConfirm'])->name('admin.posts.delete.query.confirm');
    Route::post('/admin/posts/delete', [AdminPostController::class, 'destroy'])->name('admin.posts.destroy.query');

    // AJAX — port of check_permalink + autosave
    Route::get('/api/posts/check_permalink', [AdminPostController::class, 'checkPermalink'])->name('admin.posts.check-permalink');
    Route::post('/api/posts/autosave', [AdminPostController::class, 'autosave'])->name('admin.posts.autosave');

    // Mobiles CRUD — port of MobilesController admin routes
    Route::get('/admin/mobiles', [AdminMobileController::class, 'index'])->name('admin.mobiles.index');
    Route::get('/admin/mobiles/create', [AdminMobileController::class, 'create'])->name('admin.mobiles.create');
    Route::post('/admin/mobiles/create', [AdminMobileController::class, 'store'])->name('admin.mobiles.store');
    Route::get('/admin/mobiles/view/{id}', [AdminMobileController::class, 'show'])->whereNumber('id')->name('admin.mobiles.show');
    Route::get('/admin/mobiles/edit/{id}', [AdminMobileController::class, 'edit'])->whereNumber('id')->name('admin.mobiles.edit');
    Route::post('/admin/mobiles/edit/{id}', [AdminMobileController::class, 'update'])->whereNumber('id')->name('admin.mobiles.update');
    // Legacy query-string forms (?id=) — keep working alongside path forms
    Route::get('/admin/mobiles/view', [AdminMobileController::class, 'show'])->name('admin.mobiles.show.query');
    Route::get('/admin/mobiles/edit', [AdminMobileController::class, 'edit'])->name('admin.mobiles.edit.query');
    Route::post('/admin/mobiles/edit', [AdminMobileController::class, 'update'])->name('admin.mobiles.update.query');
    Route::get('/admin/mobiles/delete', [AdminMobileController::class, 'deleteConfirm'])->name('admin.mobiles.delete.query');
    Route::post('/admin/mobiles/delete', [AdminMobileController::class, 'destroy'])->name('admin.mobiles.destroy.query');

    // DELETE via path (GET for confirmation, POST for actual delete).
    // NOTE: only ONE GET registration — a second for the same URI would shadow
    // this confirmation page and delete on GET (that was the old behaviour).
    Route::get('/admin/mobiles/delete/{id}', [AdminMobileController::class, 'deleteConfirm'])->whereNumber('id')->name('admin.mobiles.delete');
    Route::post('/admin/mobiles/delete/{id}', [AdminMobileController::class, 'destroy'])->whereNumber('id')->name('admin.mobiles.destroy.path');

    // Services CRUD
    Route::get('/admin/services', [AdminServiceController::class, 'index'])->name('admin.services.index');
    Route::get('/admin/services/create', [AdminServiceController::class, 'create'])->name('admin.services.create');
    Route::post('/admin/services/create', [AdminServiceController::class, 'store'])->name('admin.services.store');
    Route::get('/admin/services/view/{id}', [AdminServiceController::class, 'view'])->whereNumber('id')->name('admin.services.show');
    Route::get('/admin/services/edit/{id}', [AdminServiceController::class, 'edit'])->whereNumber('id')->name('admin.services.edit');
    Route::post('/admin/services/edit/{id}', [AdminServiceController::class, 'update'])->whereNumber('id')->name('admin.services.update');
    // Services: GET delete path shows confirmation (Path-based)
    Route::get('/admin/services/delete/{id}', [AdminServiceController::class, 'deleteConfirm'])->whereNumber('id')->name('admin.services.delete.confirm');
    // Services: POST delete path executes delete (Path-based)
    Route::post('/admin/services/delete/{id}', [AdminServiceController::class, 'destroy'])->whereNumber('id')->name('admin.services.destroy.path');

    // Legacy query-string forms (?id=) for services
    Route::get('/admin/services/view', [AdminServiceController::class, 'view'])->name('admin.services.show.query');
    Route::get('/admin/services/edit', [AdminServiceController::class, 'edit'])->name('admin.services.edit.query');
    Route::post('/admin/services/edit', [AdminServiceController::class, 'update'])->name('admin.services.update.query');
    Route::get('/admin/services/delete', [AdminServiceController::class, 'deleteConfirm'])->name('admin.services.delete.query.confirm');
    Route::post('/admin/services/delete', [AdminServiceController::class, 'destroy'])->name('admin.services.destroy.query');

    // Users admin — port of AdminUserController routes
    Route::get('/admin/users', [AdminUserController::class, 'index'])->name('admin.users.index');
    Route::get('/admin/users/view/{id}', [AdminUserController::class, 'view'])->whereNumber('id')->name('admin.users.show');
    Route::get('/admin/users/edit/{id}', [AdminUserController::class, 'edit'])->whereNumber('id')->name('admin.users.edit');
    Route::post('/admin/users/edit/{id}', [AdminUserController::class, 'update'])->whereNumber('id')->name('admin.users.update');
    Route::get('/admin/users/delete/{id}', [AdminUserController::class, 'deleteConfirm'])->whereNumber('id')->name('admin.users.delete.confirm');
    Route::post('/admin/users/delete/{id}', [AdminUserController::class, 'destroy'])->whereNumber('id')->name('admin.users.destroy.path');

    // Legacy query-string forms (?id=) for users
    Route::get('/admin/users/view', [AdminUserController::class, 'view'])->name('admin.users.show.query');
    Route::get('/admin/users/edit', [AdminUserController::class, 'edit'])->name('admin.users.edit.query');
    Route::post('/admin/users/edit', [AdminUserController::class, 'update'])->name('admin.users.update.query');
    Route::get('/admin/users/delete', [AdminUserController::class, 'deleteConfirm'])->name('admin.users.delete.query.confirm');
    Route::post('/admin/users/delete', [AdminUserController::class, 'destroy'])->name('admin.users.destroy.query');

    // RBAC — Roles and Permissions admin
    // Roles
    Route::get('/admin/roles', [AdminRbacController::class, 'rolesIndex'])->name('admin.roles.index');
    Route::get('/admin/roles/create', [AdminRbacController::class, 'roleCreate'])->name('admin.roles.create');
    Route::post('/admin/roles/create', [AdminRbacController::class, 'roleStore'])->name('admin.roles.store');
    Route::get('/admin/roles/view/{id}', [AdminRbacController::class, 'roleView'])->whereNumber('id')->name('admin.roles.show');
    Route::get('/admin/roles/edit/{id}', [AdminRbacController::class, 'roleEdit'])->whereNumber('id')->name('admin.roles.edit');
    Route::post('/admin/roles/edit/{id}', [AdminRbacController::class, 'roleUpdate'])->whereNumber('id')->name('admin.roles.update');
    Route::get('/admin/roles/delete/{id}', [AdminRbacController::class, 'roleDeleteConfirm'])->whereNumber('id')->name('admin.roles.delete.confirm');
    Route::post('/admin/roles/delete/{id}', [AdminRbacController::class, 'roleDestroy'])->whereNumber('id')->name('admin.roles.destroy.path');
    Route::post('/admin/roles/assign-permission/{id}', [AdminRbacController::class, 'roleAssignPermission'])->whereNumber('id')->name('admin.roles.assign-permission');
    Route::post('/admin/roles/remove-permission/{id}', [AdminRbacController::class, 'roleRemovePermission'])->whereNumber('id')->name('admin.roles.remove-permission');

    // Legacy query-string forms for roles
    Route::get('/admin/roles/view', [AdminRbacController::class, 'roleView'])->name('admin.roles.show.query');
    Route::get('/admin/roles/edit', [AdminRbacController::class, 'roleEdit'])->name('admin.roles.edit.query');
    Route::post('/admin/roles/edit', [AdminRbacController::class, 'roleUpdate'])->name('admin.roles.update.query');
    Route::get('/admin/roles/delete', [AdminRbacController::class, 'roleDeleteConfirm'])->name('admin.roles.delete.query.confirm');
    Route::post('/admin/roles/delete', [AdminRbacController::class, 'roleDestroy'])->name('admin.roles.destroy.query');

    // Permissions
    Route::get('/admin/permissions', [AdminRbacController::class, 'permissionsIndex'])->name('admin.permissions.index');
    Route::get('/admin/permissions/create', [AdminRbacController::class, 'permissionCreate'])->name('admin.permissions.create');
    Route::post('/admin/permissions/create', [AdminRbacController::class, 'permissionStore'])->name('admin.permissions.store');
    Route::get('/admin/permissions/view/{id}', [AdminRbacController::class, 'permissionView'])->whereNumber('id')->name('admin.permissions.show');
    Route::get('/admin/permissions/edit/{id}', [AdminRbacController::class, 'permissionEdit'])->whereNumber('id')->name('admin.permissions.edit');
    Route::post('/admin/permissions/edit/{id}', [AdminRbacController::class, 'permissionUpdate'])->whereNumber('id')->name('admin.permissions.update');
    Route::get('/admin/permissions/delete/{id}', [AdminRbacController::class, 'permissionDeleteConfirm'])->whereNumber('id')->name('admin.permissions.delete.confirm');
    Route::post('/admin/permissions/delete/{id}', [AdminRbacController::class, 'permissionDestroy'])->whereNumber('id')->name('admin.permissions.destroy.path');
    Route::post('/admin/permissions/assign-role/{id}', [AdminRbacController::class, 'permissionAssignRole'])->whereNumber('id')->name('admin.permissions.assign-role');
    Route::post('/admin/permissions/remove-role/{id}', [AdminRbacController::class, 'permissionRemoveRole'])->whereNumber('id')->name('admin.permissions.remove-role');

    // Legacy query-string forms for permissions
    Route::get('/admin/permissions/view', [AdminRbacController::class, 'permissionView'])->name('admin.permissions.show.query');
    Route::get('/admin/permissions/edit', [AdminRbacController::class, 'permissionEdit'])->name('admin.permissions.edit.query');
    Route::post('/admin/permissions/edit', [AdminRbacController::class, 'permissionUpdate'])->name('admin.permissions.update.query');
    Route::get('/admin/permissions/delete', [AdminRbacController::class, 'permissionDeleteConfirm'])->name('admin.permissions.delete.query.confirm');
    Route::post('/admin/permissions/delete', [AdminRbacController::class, 'permissionDestroy'])->name('admin.permissions.destroy.query');

    // Revenue — Ads, Sponsored, Donations
    Route::get('/admin/revenue', [AdminRevenueController::class, 'index'])->name('admin.revenue.index');
    Route::get('/admin/revenue/ads', [AdminRevenueController::class, 'ads'])->name('admin.revenue.ads');
    Route::get('/admin/revenue/ads/analytics', [AdminRevenueController::class, 'adsAnalytics'])->name('admin.revenue.ads.analytics');
    Route::get('/admin/revenue/ads/campaigns', [AdminRevenueController::class, 'adsCampaigns'])->name('admin.revenue.ads.campaigns');
    Route::get('/admin/revenue/ads/placements', [AdminRevenueController::class, 'adsPlacements'])->name('admin.revenue.ads.placements');
    Route::get('/admin/revenue/ads/settings', [AdminRevenueController::class, 'adsSettings'])->name('admin.revenue.ads.settings');
    Route::get('/admin/revenue/sponsored', [AdminRevenueController::class, 'sponsored'])->name('admin.revenue.sponsored');
    Route::get('/admin/revenue/sponsored/create', [AdminRevenueController::class, 'sponsoredCreate'])->name('admin.revenue.sponsored.create');
    Route::get('/admin/revenue/sponsored/edit', [AdminRevenueController::class, 'sponsoredEdit'])->name('admin.revenue.sponsored.edit');
    Route::get('/admin/revenue/donations', [AdminRevenueController::class, 'donations'])->name('admin.revenue.donations');
    Route::get('/admin/revenue/donations/bkash', [AdminRevenueController::class, 'donationsBkash'])->name('admin.revenue.donations.bkash');
    Route::get('/admin/revenue/donations/nagad', [AdminRevenueController::class, 'donationsNagad'])->name('admin.revenue.donations.nagad');
    Route::get('/admin/revenue/donations/rocket', [AdminRevenueController::class, 'donationsRocket'])->name('admin.revenue.donations.rocket');

    // Wallet — recharges, ledger, user balances
    Route::get('/admin/wallet/recharges', [AdminWalletController::class, 'recharges'])->name('admin.wallet.recharges');
    Route::post('/admin/wallet/recharges/{id}/approve', [AdminWalletController::class, 'approveRecharge'])->name('admin.wallet.recharge.approve');
    Route::post('/admin/wallet/recharges/{id}/reject', [AdminWalletController::class, 'rejectRecharge'])->name('admin.wallet.recharge.reject');
    Route::get('/admin/wallet/transactions', [AdminWalletController::class, 'transactions'])->name('admin.wallet.transactions');
    Route::get('/admin/wallet/users', [AdminWalletController::class, 'users'])->name('admin.wallet.users');
    Route::post('/admin/wallet/users/{id}/adjust', [AdminWalletController::class, 'adjustBalance'])->name('admin.wallet.users.adjust');

    // Logs
    Route::get('/admin/logs', [AdminLogsController::class, 'index'])->name('admin.logs.index');

    // Security settings
    Route::get('/admin/security', [AdminSecurityController::class, 'index'])->name('admin.security.index');
    Route::get('/admin/security/auth', [AdminSecurityController::class, 'auth'])->name('admin.security.auth');
    Route::get('/admin/security/recaptcha', [AdminSecurityController::class, 'recaptcha'])->name('admin.security.recaptcha');
    Route::get('/admin/security/smtp', [AdminSecurityController::class, 'smtp'])->name('admin.security.smtp');
    Route::post('/admin/security/auth', [AdminSecurityController::class, 'update'])->name('admin.security.auth.update');
    Route::post('/admin/security/recaptcha', [AdminSecurityController::class, 'update'])->name('admin.security.recaptcha.update');
    Route::post('/admin/security/smtp', [AdminSecurityController::class, 'update'])->name('admin.security.smtp.update');
    Route::post('/admin/security/smtp/test', [AdminSecurityController::class, 'testMail'])->name('admin.security.smtp.test');

    // Setup wizard
    Route::get('/admin/setup', [AdminSetupController::class, 'index'])->name('admin.setup.index');

    // Scraper pipeline
    Route::get('/admin/scraper', [AdminScraperController::class, 'index'])->name('admin.scraper.index');
    Route::get('/admin/scraper/jobs', [AdminScraperController::class, 'jobs'])->name('admin.scraper.jobs');
    Route::get('/admin/scraper/sources', [AdminScraperController::class, 'sources'])->name('admin.scraper.sources');
    Route::get('/admin/scraper/sources/create', [AdminScraperController::class, 'sourcesCreate'])->name('admin.scraper.sources.create');
    Route::get('/admin/scraper/settings', [AdminScraperController::class, 'settings'])->name('admin.scraper.settings');
    Route::get('/admin/scraper/logs', [AdminScraperController::class, 'logs'])->name('admin.scraper.logs');
    Route::get('/admin/scraper/settings/automation', [AdminScraperController::class, 'settingsAutomation'])->name('admin.scraper.settings.automation');
    Route::get('/admin/scraper/settings/limits', [AdminScraperController::class, 'settingsLimits'])->name('admin.scraper.settings.limits');
    Route::get('/admin/scraper/settings/storage', [AdminScraperController::class, 'settingsStorage'])->name('admin.scraper.settings.storage');
    Route::post('/admin/scraper/settings/autopublish', [AdminScraperController::class, 'updateAutopublish'])->name('admin.scraper.settings.autopublish');
    Route::get('/admin/scraper/source/{key}', [AdminScraperController::class, 'showSource'])->name('admin.scraper.source.show');
    Route::post('/admin/scraper/run', [AdminScraperController::class, 'run'])->name('admin.scraper.run');
    Route::post('/admin/scraper/source/{key}/clear', [AdminScraperController::class, 'clearSource'])->name('admin.scraper.source.clear');

    // CV Builder — admin
    Route::get('/admin/cv', [AdminCvController::class, 'index'])->name('admin.cv.index');
    Route::get('/admin/cv/view/{id}', [AdminCvController::class, 'view'])->whereNumber('id')->name('admin.cv.show');
    Route::get('/admin/cv/view', [AdminCvController::class, 'view'])->name('admin.cv.show.query');

    // Kharij
    Route::get('/admin/kharij', [AdminKharijController::class, 'index'])->name('admin.kharij.index');

    // Sitemap
    Route::get('/admin/sitemap', [AdminSitemapController::class, 'index'])->name('admin.sitemap.index');
    Route::get('/admin/sitemap/history', [AdminSitemapController::class, 'history'])->name('admin.sitemap.history');

    // Weather
    Route::get('/admin/weather', [AdminWeatherController::class, 'index'])->name('admin.weather.index');
    Route::get('/admin/weather/api', [AdminWeatherController::class, 'api'])->name('admin.weather.api');
    Route::get('/admin/weather/locations', [AdminWeatherController::class, 'locations'])->name('admin.weather.locations');
    Route::post('/admin/weather/api', [AdminWeatherController::class, 'update'])->name('admin.weather.api.update');
    Route::post('/admin/weather/locations', [AdminWeatherController::class, 'update'])->name('admin.weather.locations.update');
    Route::post('/admin/weather/api/test', [AdminWeatherController::class, 'test'])->name('admin.weather.api.test');

    // Live TV
    Route::get('/admin/livetv', [AdminLiveTvController::class, 'index'])->name('admin.livetv.index');
    Route::get('/admin/livetv/channels', [AdminLiveTvController::class, 'channels'])->name('admin.livetv.channels');
    Route::get('/admin/livetv/proxy', [AdminLiveTvController::class, 'proxy'])->name('admin.livetv.proxy');

    // Calculator
    Route::get('/admin/calculator', [AdminCalculatorController::class, 'index'])->name('admin.calculator.index');
    Route::get('/admin/calculator/gpa', [AdminCalculatorController::class, 'gpa'])->name('admin.calculator.gpa');
    Route::get('/admin/calculator/loan', [AdminCalculatorController::class, 'loan'])->name('admin.calculator.loan');
    Route::get('/admin/calculator/widgets', [AdminCalculatorController::class, 'widgets'])->name('admin.calculator.widgets');

    // OCR
    Route::get('/admin/ocr', [AdminOcrController::class, 'index'])->name('admin.ocr.index');
    Route::get('/admin/ocr/history', [AdminOcrController::class, 'history'])->name('admin.ocr.history');
    Route::get('/admin/ocr/settings', [AdminOcrController::class, 'ocrSettings'])->name('admin.ocr.settings');
    Route::get('/admin/ocr/test', [AdminOcrController::class, 'test'])->name('admin.ocr.test');

    // Photo Studio
    Route::get('/admin/photo-studio', [AdminPhotoStudioController::class, 'index'])->name('admin.photo-studio.index');
    Route::get('/admin/photo-studio/cutout', [AdminPhotoStudioController::class, 'cutout'])->name('admin.photo-studio.cutout');
    Route::get('/admin/photo-studio/editor', [AdminPhotoStudioController::class, 'editor'])->name('admin.photo-studio.editor');
    Route::get('/admin/photo-studio/history', [AdminPhotoStudioController::class, 'history'])->name('admin.photo-studio.history');

    // AI System
    Route::get('/admin/aisystem', [AdminAiSystemController::class, 'index'])->name('admin.aisystem.index');
    Route::get('/admin/aisystem/analytics', [AdminAiSystemController::class, 'analytics'])->name('admin.aisystem.analytics');
    Route::get('/admin/aisystem/chat', [AdminAiSystemController::class, 'chat'])->name('admin.aisystem.chat');
    Route::get('/admin/aisystem/knowledge', [AdminAiSystemController::class, 'knowledge'])->name('admin.aisystem.knowledge');
    Route::get('/admin/aisystem/providers', [AdminAiSystemController::class, 'providers'])->name('admin.aisystem.providers');
    Route::post('/admin/aisystem/providers', [AdminAiSystemController::class, 'providerStore'])->name('admin.aisystem.providers.store');
    Route::post('/admin/aisystem/providers/{id}', [AdminAiSystemController::class, 'providerUpdate'])->where('id', '[A-Za-z0-9]+')->name('admin.aisystem.providers.update');
    Route::post('/admin/aisystem/providers/{id}/delete', [AdminAiSystemController::class, 'providerDelete'])->where('id', '[A-Za-z0-9]+')->name('admin.aisystem.providers.delete');
    Route::post('/admin/aisystem/providers/{id}/default', [AdminAiSystemController::class, 'providerDefault'])->where('id', '[A-Za-z0-9]+')->name('admin.aisystem.providers.default');
    Route::post('/admin/aisystem/providers/{id}/test', [AdminAiSystemController::class, 'providerTest'])->where('id', '[A-Za-z0-9]+')->name('admin.aisystem.providers.test');
    Route::get('/admin/aisystem/writer', [AdminAiSystemController::class, 'writer'])->name('admin.aisystem.writer');

    // API Proxies
    Route::get('/admin/api-proxy', [AdminApiProxyController::class, 'index'])->name('admin.api-proxy.index');
    Route::get('/admin/api-proxy/firebase', [AdminApiProxyController::class, 'firebase'])->name('admin.api-proxy.firebase');
    Route::get('/admin/api-proxy/pexels', [AdminApiProxyController::class, 'pexels'])->name('admin.api-proxy.pexels');
    Route::get('/admin/api-proxy/pixabay', [AdminApiProxyController::class, 'pixabay'])->name('admin.api-proxy.pixabay');
    Route::get('/admin/api-proxy/puter', [AdminApiProxyController::class, 'puter'])->name('admin.api-proxy.puter');

    // Notifications — admin send/schedule/manage
    Route::get('/admin/notifications', [AdminNotificationController::class, 'index'])->name('admin.notifications.index');
    Route::get('/admin/notifications/create', [AdminNotificationController::class, 'create'])->name('admin.notifications.create');
    Route::post('/admin/notifications/create', [AdminNotificationController::class, 'store'])->name('admin.notifications.store');
    Route::get('/admin/notifications/schedule', [AdminNotificationController::class, 'scheduleForm'])->name('admin.notifications.schedule.form');
    Route::post('/admin/notifications/schedule', [AdminNotificationController::class, 'scheduleStore'])->name('admin.notifications.schedule.store');
    Route::get('/admin/notifications/view/{id}', [AdminNotificationController::class, 'view'])->whereNumber('id')->name('admin.notifications.show');
    Route::get('/admin/notifications/delete/{id}', [AdminNotificationController::class, 'deleteConfirm'])->whereNumber('id')->name('admin.notifications.delete.confirm');
    Route::post('/admin/notifications/delete/{id}', [AdminNotificationController::class, 'destroy'])->whereNumber('id')->name('admin.notifications.destroy.path');
    Route::get('/admin/notifications/view', [AdminNotificationController::class, 'view'])->name('admin.notifications.show.query');
    Route::get('/admin/notifications/delete', [AdminNotificationController::class, 'deleteConfirm'])->name('admin.notifications.delete.query.confirm');
    Route::post('/admin/notifications/delete', [AdminNotificationController::class, 'destroy'])->name('admin.notifications.destroy.query');

    // The logged-in admin's own account — the admin-chrome equivalents of the
    // user-area /profile, /user/settings and /user/notifications pages that the
    // public header and the admin dropdown link to for admins.
    Route::get('/admin/profile', [AdminAccountController::class, 'profile'])->name('admin.profile');
    Route::get('/admin/account-settings', [AdminAccountController::class, 'settings'])->name('admin.account-settings');
    Route::get('/admin/my/notifications', [AdminAccountController::class, 'notifications'])->name('admin.my-notifications');
});


// User area — Phase 3 (dashboard, profile, settings, notifications)
Route::middleware('auth')->group(function () {
    Route::get('/user/dashboard', [DashboardController::class, 'index'])->name('user.dashboard');

    // CV export (PDF via mPDF — port of the legacy CvController stream path)
    Route::get('/cv/{id}/pdf', [CvExportController::class, 'pdf'])
        ->whereNumber('id')->name('cv.pdf');

    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('/profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::post('/profile/edit', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/profile/password', [ProfileController::class, 'showPasswordForm'])->name('profile.password');
    Route::post('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');
    Route::get('/profile/2fa', fn () => redirect('/user/security/2fa', 302)); // legacy-compat redirect

    // 2FA enrollment (port of legacy /user/security/2fa/* flow)
    Route::get('/user/security/2fa', [UserSecurityController::class, 'show2FA'])->name('user.security.2fa');
    Route::get('/user/security/2fa/setup', [UserSecurityController::class, 'setup'])->name('user.security.2fa.setup');
    Route::post('/user/security/2fa/verify', [UserSecurityController::class, 'verify'])->name('user.security.2fa.verify');
    Route::get('/user/security/2fa/backup', [UserSecurityController::class, 'backup'])->name('user.security.2fa.backup');
    Route::post('/user/security/2fa/disable', [UserSecurityController::class, 'disable'])->name('user.security.2fa.disable');

    Route::get('/user/settings', [SettingsController::class, 'index'])->name('user.settings');

    Route::get('/user/notifications', [NotificationsController::class, 'index'])->name('user.notifications');
    Route::post('/api/notification/mark-read', [NotificationsController::class, 'markRead'])->name('notifications.mark-read');
    Route::post('/api/notification/mark-all-read', [NotificationsController::class, 'markAllRead'])->name('notifications.mark-all-read');

    // Wallet — balance, recharge, transactions
    Route::get('/wallet', [WalletController::class, 'dashboard'])->name('wallet.dashboard');
    Route::get('/wallet/recharge', [WalletController::class, 'rechargeForm'])->name('wallet.recharge');
    Route::post('/wallet/recharge', [WalletController::class, 'recharge'])->name('wallet.recharge.submit');
    Route::get('/wallet/transactions', [WalletController::class, 'transactions'])->name('wallet.transactions');
    Route::get('/wallet/recharges', [WalletController::class, 'recharges'])->name('wallet.recharges');
    Route::get('/wallet/recharges/{id}', [WalletController::class, 'showRecharge'])->name('wallet.recharge.view');
});

Route::prefix('internal/api')->group(function () {
    Route::post('/scrap-control-center/cron-run-pipeline', [ScrapControlCenterController::class, 'cronRunPipeline']);
});

Route::fallback(function () {
    return response('Not found', 404);
});