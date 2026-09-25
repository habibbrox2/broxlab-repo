<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

/**
 * Authenticated admin route scan. Non-destructive: no RefreshDatabase,
 * read-only GET requests, results written to storage/tmp/.
 */
class AdminRouteScanTest extends TestCase
{
    public function test_scan_all_admin_routes_as_admin(): void
    {
        $file = storage_path('tmp/admin_urls.txt');
        if (! is_file($file)) {
            $this->addWarning('admin url list missing — regenerate storage/tmp/admin_urls.txt to enable this scan');

            return;
        }

        $urls = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $this->assertNotEmpty($urls);

        $user = User::query()->find(1);
        $this->assertNotNull($user, 'admin user #1 not found');
        $this->actingAs($user);

        $out = '';
        foreach ($urls as $u) {
            $u = '/' . ltrim(trim($u), '/');
            try {
                $resp = $this->get($u);
                $status = $resp->getStatusCode();
                $loc = $resp->isRedirect() ? ' -> ' . $resp->headers->get('Location') : '';
                $out .= $status . ' ' . $u . $loc . "\n";
            } catch (\Throwable $e) {
                $out .= 'EXC ' . $u . ' :: ' . class_basename($e) . ' :: ' . substr($e->getMessage(), 0, 120) . "\n";
            }
        }

        file_put_contents(storage_path('tmp/auth_scan_results.txt'), $out);
        $this->assertStringContainsString("\n", $out);
    }
}
