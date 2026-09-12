<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Port of the legacy app/Models/AdvertisementModel.php — the public donation
 * page and advertising-inquiry form.
 *
 * Parity: advertisement_inquiries insert, donation_payments insert + the
 * read helpers the /donate page needs (total, count, recent).
 */
class MonetizationService
{
    /**
     * Create an advertisement inquiry (legacy createInquiry()).
     */
    public function createInquiry(string $name, string $email, string $company, string $budget, string $message, ?string $ip = null): int|false
    {
        try {
            return DB::table('advertisement_inquiries')->insertGetId([
                'name' => $name,
                'email' => $email,
                'company' => $company,
                'budget' => $budget,
                'message' => $message,
                'ip_address' => $ip,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('MonetizationService::createInquiry failed: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Create a pending donation (legacy createDonation()).
     */
    public function createDonation(array $data): int|false
    {
        try {
            return DB::table('donation_payments')->insertGetId([
                'donor_name' => $data['name'] ?? '',
                'donor_email' => $data['email'] ?? null,
                'donor_phone' => $data['phone'] ?? '',
                'amount' => (float) ($data['amount'] ?? 0),
                'currency' => $data['currency'] ?? 'BDT',
                'method' => $data['method'] ?? '',
                'bkash_trxid' => $data['bkash_trxid'] ?? null,
                'nagad_trxid' => $data['nagad_trxid'] ?? null,
                'stripe_payment_intent' => $data['stripe_pi'] ?? null,
                'note' => $data['note'] ?? null,
                'status' => 'pending',
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('MonetizationService::createDonation failed: ' . $e->getMessage());

            return false;
        }
    }

    /** Sum of all completed donations. */
    public function getDonationTotal(): float
    {
        try {
            return (float) DB::table('donation_payments')
                ->where('status', 'completed')
                ->sum('amount');
        } catch (\Throwable $e) {
            Log::error('MonetizationService::getDonationTotal failed: ' . $e->getMessage());

            return 0.0;
        }
    }

    /** Count of completed donors. */
    public function getDonationCount(): int
    {
        try {
            return (int) DB::table('donation_payments')
                ->where('status', 'completed')
                ->count();
        } catch (\Throwable $e) {
            Log::error('MonetizationService::getDonationCount failed: ' . $e->getMessage());

            return 0;
        }
    }

    /** N most recent completed donations. */
    public function getRecentDonations(int $limit = 6, int $offset = 0): array
    {
        try {
            return DB::table('donation_payments')
                ->select('id', 'donor_name', 'amount', 'method', 'status', 'created_at')
                ->where('status', 'completed')
                ->orderByDesc('created_at')
                ->offset($offset)
                ->limit($limit)
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all();
        } catch (\Throwable $e) {
            Log::error('MonetizationService::getRecentDonations failed: ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Mark a pending donation completed (legacy confirmDonation()).
     */
    public function confirmDonation(int $id, string $trxId, float $amount): bool
    {
        try {
            return (bool) DB::table('donation_payments')
                ->where('id', $id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'completed',
                    'bkash_trxid' => $trxId,
                    'updated_at' => now(),
                ]);
        } catch (\Throwable $e) {
            Log::error('MonetizationService::confirmDonation failed: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Update a donation's status (legacy updateDonationStatus()).
     */
    public function updateDonationStatus(int $id, string $status, ?string $note = null): bool
    {
        try {
            $data = ['status' => $status, 'updated_at' => now()];
            if ($note !== null) {
                $data['note'] = $note;
            }

            return (bool) DB::table('donation_payments')->where('id', $id)->update($data);
        } catch (\Throwable $e) {
            Log::error('MonetizationService::updateDonationStatus failed: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Append a key-value line to the donation note (legacy updatePaymentMeta()).
     */
    public function updatePaymentMeta(int $id, string $key, mixed $value): bool
    {
        try {
            $current = DB::table('donation_payments')->where('id', $id)->value('note');
            $line = "\n[{$key}] " . (string) $value;
            $merged = ($current === null || $current === '') ? trim($line) : $current . $line;

            return (bool) DB::table('donation_payments')
                ->where('id', $id)
                ->update(['note' => $merged, 'updated_at' => now()]);
        } catch (\Throwable $e) {
            Log::error('MonetizationService::updatePaymentMeta failed: ' . $e->getMessage());

            return false;
        }
    }
}