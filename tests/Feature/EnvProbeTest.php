<?php

namespace Tests\Feature;

use Tests\TestCase;

class EnvProbeTest extends TestCase
{
    public function test_probe(): void
    {
        $cfg = [
            'env' => app()->environment(),
            'cache' => config('cache.default'),
            'session.driver' => config('session.driver'),
            'session.path' => config('session.path'),
            'session.secure' => config('session.secure'),
            'db.connection' => config('database.default'),
            'db.database' => config('database.connections.mysql.database'),
            'queue' => config('queue.default'),
        ];
        fwrite(STDERR, "\nPROBE ".json_encode($cfg)."\n");

        $response = $this->get('/login');
        fwrite(STDERR, "PROBE login status=".(method_exists($response, 'status') ? $response->status() : $response->getStatusCode())."\n");

        $this->assertTrue(true);
    }
}
