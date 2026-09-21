<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Regression guard for the CSRF hole closed on 2026-09-17.
 *
 * Legacy admin deletes were registered as GET, so a plain link — or an
 * <img src>, crawler, or browser link-prefetch — could destroy a row without
 * any CSRF token. Every admin delete URI must now be:
 *   GET  → a confirmation page (no side effects)
 *   POST → the actual destroy (CSRF-protected by the web middleware group)
 *
 * These tests are route-table assertions (no DB writes): they fail if anyone
 * reintroduces a state-changing GET delete or drops the POST counterpart.
 */
class AdminDeleteRoutesTest extends TestCase
{
    /** URIs that are destructive and must never be reachable via GET alone. */
    protected function destructiveUris(): array
    {
        return [
            'admin/categories/delete/{id}',
            'admin/tags/delete/{id}',
            'admin/pages/delete/{id}',
            'admin/posts/delete/{id}',
            'admin/posts/delete',
            'admin/mobiles/delete/{id}',
            'admin/mobiles/delete',
            'admin/services/delete/{id}',
            'admin/services/delete',
            'admin/users/delete/{id}',
            'admin/users/delete',
            'admin/roles/delete/{id}',
            'admin/roles/delete',
            'admin/permissions/delete/{id}',
            'admin/permissions/delete',
            'admin/notifications/delete/{id}',
            'admin/notifications/delete',
        ];
    }

    public function test_every_destructive_uri_has_a_post_route(): void
    {
        $missing = [];

        foreach ($this->destructiveUris() as $uri) {
            $hasPost = collect(Route::getRoutes())->contains(
                fn ($route) => $route->uri() === $uri && in_array('POST', $route->methods(), true)
            );

            if (! $hasPost) {
                $missing[] = $uri;
            }
        }

        $this->assertSame([], $missing, 'Destructive URIs without a POST handler: '.implode(', ', $missing));
    }

    public function test_no_get_delete_route_points_at_a_destroy_action(): void
    {
        $offenders = [];

        foreach (Route::getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }
            if (! str_contains($route->uri(), 'delete')) {
                continue;
            }

            $action = $route->getActionName();

            // A GET delete route may only render a confirmation page; naming it
            // *Destroy while allowing GET is exactly the old vulnerability.
            if (preg_match('/(Destroy|destroy)$/', $action)) {
                $offenders[] = $route->uri().' → '.$action;
            }
        }

        $this->assertSame([], $offenders, 'GET routes reaching a destroy action: '.implode(', ', $offenders));
    }

    public function test_delete_routes_require_authentication(): void
    {
        // A guest hitting any admin delete URI must be redirected to /login —
        // never silently allowed to reach the confirmation or destroy action.
        $this->get('/admin/posts/delete/1')->assertRedirect('/login');
        $this->get('/admin/categories/delete/1')->assertRedirect('/login');
        $this->get('/admin/tags/delete/1')->assertRedirect('/login');
        $this->get('/admin/pages/delete/1')->assertRedirect('/login');

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->post('/admin/posts/delete/1')->assertRedirect('/login');
    }
}
