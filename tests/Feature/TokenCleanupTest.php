<?php

namespace Tests\Feature;

use App\Support\TokenCleanupService;
use Tests\TestCase;

/**
 * Classification parity for the legacy classify_fcm_send_error() helper
 * (FirebaseHelper). Pure logic — no FCM or DB calls.
 */
class TokenCleanupTest extends TestCase
{
    protected TokenCleanupService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TokenCleanupService();
    }

    public function test_unregistered_error_code_classifies_not_registered(): void
    {
        $info = $this->service->classify([
            'success' => false,
            'error' => 'Requested entity was not found.',
            'error_code' => 'UNREGISTERED',
            'error_status' => 'NOT_FOUND',
        ]);

        $this->assertTrue($info['not_registered']);
        $this->assertFalse($info['invalid_registration']);
    }

    public function test_not_found_status_classifies_not_registered(): void
    {
        $info = $this->service->classify([
            'success' => false,
            'error' => 'Requested entity was not found.',
            'error_code' => null,
            'error_status' => 'NOT_FOUND',
        ]);

        $this->assertTrue($info['not_registered']);
    }

    public function test_message_text_classifies_not_registered(): void
    {
        $this->assertTrue($this->service->classify([
            'error' => 'The registration token is not registered.',
        ])['not_registered']);
        $this->assertTrue($this->service->classify([
            'error' => 'registration-token-not-registered',
        ])['not_registered']);
    }

    public function test_invalid_argument_classifies_invalid_registration(): void
    {
        $info = $this->service->classify([
            'success' => false,
            'error' => 'The registration token is not a valid FCM registration token',
            'error_code' => 'INVALID_ARGUMENT',
            'error_status' => null,
        ]);

        $this->assertTrue($info['invalid_registration']);
        $this->assertFalse($info['not_registered']);
    }

    public function test_sender_mismatch_classifies_sender_mismatch(): void
    {
        $info = $this->service->classify([
            'error' => 'Sender ID mismatch',
            'error_code' => 'SENDER_ID_MISMATCH',
            'error_status' => null,
        ]);

        $this->assertTrue($info['sender_mismatch']);
        $this->assertFalse($info['not_registered']);
    }

    public function test_unrelated_error_classifies_none(): void
    {
        $info = $this->service->classify([
            'error' => 'Internal server error',
            'error_code' => 'INTERNAL',
            'error_status' => 'INTERNAL',
        ]);

        $this->assertFalse($info['not_registered']);
        $this->assertFalse($info['invalid_registration']);
        $this->assertFalse($info['sender_mismatch']);
    }

    public function test_handle_failed_send_returns_cleanup_action(): void
    {
        $result = $this->service->handleFailedSend(
            [
                'success' => false,
                'error' => 'UNREGISTERED',
                'error_code' => 'UNREGISTERED',
                'error_status' => 'NOT_FOUND',
            ],
            'nonexistent-token-for-unit-test',
            null
        );

        // No real token matched, so no DB row was modified — but the action
        // classification and failure recording still ran without error.
        $this->assertSame('revoked', $result['cleanup_action']);
    }
}