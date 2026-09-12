<?php

namespace App\Http\Controllers;

use App\Support\AppSettings;
use App\Support\MonetizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Port of the legacy app/Controllers/MonetizationController.php public routes:
 *   GET/POST /donate            — donation page + form submit
 *   GET/POST /donate/bkash/callback — bKash webhook (SNS or direct)
 *
 * The admin revenue dashboard stays on AdminRevenueController (Phase 5).
 */
class DonationController extends Controller
{
    public function __construct(
        protected MonetizationService $donations,
        protected AppSettings $appSettings,
    ) {}

    public function show(Request $request): View
    {
        return view('pages.donate', [
            'title' => 'Support Us',
            'donationTotal' => $this->donations->getDonationTotal(),
            'donationCount' => $this->donations->getDonationCount(),
            'recentDonations' => $this->donations->getRecentDonations(6),
            'settings' => $this->appSettings->all(),
            'errors' => session('errors', []),
            'success' => session('success'),
            'serverError' => session('server_error'),
            'old' => session('_old_input', []),
        ]);
    }

    public function submit(Request $request): RedirectResponse
    {
        $name = trim((string) $request->input('donor_name'));
        $email = trim((string) $request->input('donor_email'));
        $phone = trim((string) $request->input('donor_phone'));
        $amount = trim((string) $request->input('donation_amount'));
        $method = strtolower(trim((string) $request->input('donation_method')));
        $note = trim((string) $request->input('donor_note'));
        $trxId = trim((string) $request->input('bkash_trxid'));

        $errors = [];

        if ($name === '') {
            $errors[] = 'Name is required';
        }
        if ($phone === '') {
            $errors[] = 'Phone number is required';
        }
        if ($amount === '' || ! is_numeric($amount) || (float) $amount <= 0) {
            $errors[] = 'Valid donation amount is required';
        }

        $allowedMethods = ['bkash', 'nagad', 'stripe', 'bank', 'handcash'];
        if (! in_array($method, $allowedMethods, true)) {
            $errors[] = 'Please select a valid payment method';
        }

        if (! empty($errors)) {
            return redirect()->back()->withInput()->with('errors', $errors);
        }

        $id = $this->donations->createDonation([
            'name' => $name,
            'email' => $email ?: null,
            'phone' => $phone,
            'amount' => (float) $amount,
            'currency' => 'BDT',
            'method' => $method,
            'bkash_trxid' => $trxId ?: null,
            'nagad_trxid' => trim((string) $request->input('nagad_trxid')),
            'stripe_pi' => trim((string) $request->input('stripe_payment_intent')),
            'note' => $note ?: null,
        ]);

        if ($id === false) {
            return redirect()->back()->withInput()->with('server_error', 'Something went wrong. Please try again.');
        }

        DB::table('activity_logs')->insert([
            'user_id' => 0,
            'role' => 'guest',
            'action' => 'Donation Submitted',
            'resource_type' => 'donation',
            'resource_id' => $id,
            'status' => 'success',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'details' => json_encode(['amount' => $amount, 'method' => $method, 'name' => $name]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Thank you for your generous donation of ৳' . number_format((float) $amount, 2));
    }

    /**
     * GET/POST /donate/bkash/callback — bKash webhook (SNS or direct POST).
     *
     * Port of legacy getBkashCallbackPayload(): parses the raw JSON body (SNS
     * envelope or direct bKash payload), verifies the SNS signature when the
     * request is SNS-shaped, queries the bKash API for the payment status, and
     * confirms the matching pending donation.
     */
    public function bkashCallback(Request $request): JsonResponse
    {
        $rawPost = $request->getContent();
        $isSns = false;
        $input = [];

        if ($rawPost !== '') {
            $dec = json_decode($rawPost, true);
            if (is_array($dec)) {
                $input = $dec;
                if (! empty($dec['Type']) && ! empty($dec['Message']) && ! empty($dec['Signature'])) {
                    $isSns = true;
                }
            }
        }

        $input = array_merge($request->query(), $request->all(), $input);

        if ($isSns) {
            if (! $this->verifySnsSignature($input)) {
                return response()->json(['success' => false, 'message' => 'SNS verification failed'], 403);
            }
            $msg = json_decode((string) ($input['Message'] ?? ''), true);
            if (is_array($msg)) {
                $input = array_merge($input, $msg);
            }
        }

        $trxId = trim((string) ($input['trxID'] ?? $input['trxId'] ?? $input['paymentID'] ?? $input['paymentId'] ?? ''));

        if ($trxId === '') {
            return response()->json(['success' => false, 'message' => 'Missing transaction ID'], 400);
        }

        $gatewayResult = $this->queryBkashPayment($trxId);

        if (! $gatewayResult['success']) {
            return response()->json(['success' => false, 'message' => $gatewayResult['error'] ?? 'Query failed'], 400);
        }

        $pData = $gatewayResult['data'] ?? [];
        $status = strtolower((string) ($pData['status'] ?? $pData['paymentStatus'] ?? ''));
        $trxAmt = (float) ($pData['amount'] ?? 0);

        $invoiceNum = trim((string) ($pData['merchantInvoiceNumber'] ?? $input['merchantInvoiceNumber'] ?? ''));
        $donationId = 0;

        if ($invoiceNum !== '' && preg_match('/^don-(\d+)/', $invoiceNum, $m)) {
            $donationId = (int) $m[1];
        }

        if ($donationId > 0) {
            if (in_array($status, ['completed', 'success', 'authorized'], true)) {
                $completed = $this->donations->confirmDonation($donationId, $trxId, $trxAmt);
                $this->donations->updatePaymentMeta($donationId, 'bkash_payment_id', $trxId);
                $this->donations->updatePaymentMeta($donationId, 'gateway_response', json_encode($pData, JSON_UNESCAPED_UNICODE));
                if ($completed) {
                    DB::table('activity_logs')->insert([
                        'user_id' => 0,
                        'role' => 'guest',
                        'action' => 'Donation Confirmed',
                        'resource_type' => 'donation',
                        'resource_id' => $donationId,
                        'status' => 'success',
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                        'details' => json_encode(['trxId' => $trxId]),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Donation confirmed',
                    'donation_id' => $donationId,
                    'amount' => $trxAmt,
                ]);
            }

            $this->donations->updateDonationStatus($donationId, 'cancelled', "bKash status: {$status}");

            return response()->json([
                'success' => false,
                'message' => "Donation not completed: {$status}",
                'donation_id' => $donationId,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Transaction logged but no matching donation found',
            'trxId' => $trxId,
            'amount' => $trxAmt,
        ]);
    }

    /**
     * Query bKash for payment status. Reads credentials from the security
     * settings table (same keys as legacy BkashGateway) with env fallback.
     */
    protected function queryBkashPayment(string $paymentId): array
    {
        $mode = 'sandbox';
        $appKey = '';
        $authToken = '';

        try {
            $mode = (string) DB::table('app_security_settings')->where('setting_key', 'bkash_mode')->value('setting_value') ?: 'sandbox';
            $appKey = (string) DB::table('app_security_settings')->where('setting_key', 'bkash_app_key')->value('setting_value');
            $authToken = (string) DB::table('app_security_settings')->where('setting_key', 'bkash_auth_token')->value('setting_value');
        } catch (\Throwable $e) {
            Log::warning('DonationController: bkash settings read failed: ' . $e->getMessage());
        }

        if (env('BKASH_MODE') !== null) {
            $mode = (string) env('BKASH_MODE');
        }
        if (env('BKASH_APP_KEY') !== null) {
            $appKey = (string) env('BKASH_APP_KEY');
        }
        if (env('BKASH_AUTH_TOKEN') !== null) {
            $authToken = (string) env('BKASH_AUTH_TOKEN');
        }

        $prefix = in_array($mode, ['production', 'prod'], true) ? 'https://checkout.bka.sh' : 'https://checkout.sandbox.bka.sh';
        $url = rtrim($prefix, '/') . '/v1.2.0-beta/checkout/payment/search';

        if (! function_exists('curl_init')) {
            return ['success' => false, 'error' => 'cURL is required'];
        }

        $headers = ['Accept: application/json', 'Content-Type: application/json'];
        if ($appKey !== '') {
            $headers[] = 'X-APP-Key: ' . $appKey;
        }
        if ($authToken !== '') {
            $headers[] = 'Authorization: ' . $authToken;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['paymentID' => (string) $paymentId]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $err !== '') {
            return ['success' => false, 'error' => $err ?: 'Empty response from bKash'];
        }

        $decoded = json_decode($raw, true);
        if ($status >= 200 && $status < 300) {
            return ['success' => true, 'status' => $status, 'data' => $decoded];
        }

        return ['success' => false, 'status' => $status, 'error' => $decoded['message'] ?? ($decoded['error'] ?? 'Unknown error')];
    }

    /**
     * Port of legacy verifySnsSignature() — bKash SNS callbacks are signed with
     * the AWS SNS string-to-sign scheme (cert hosted on amazonaws.com).
     */
    protected function verifySnsSignature(array $payload): bool
    {
        if (empty($payload['Signature']) || empty($payload['SigningCertURL']) || empty($payload['SignatureVersion'])) {
            return false;
        }

        if ((string) $payload['SignatureVersion'] !== '1') {
            return false;
        }

        $certUrl = (string) $payload['SigningCertURL'];
        $u = parse_url($certUrl);
        if ($u === false || strtolower((string) ($u['scheme'] ?? '')) !== 'https') {
            return false;
        }

        $host = strtolower((string) ($u['host'] ?? ''));
        if (strpos($host, 'amazonaws.com') === false && strpos($host, 'bka.sh') === false && strpos($host, 'bkash') === false) {
            return false;
        }

        $cert = @file_get_contents($certUrl);
        if ($cert === false) {
            return false;
        }

        $type = (string) ($payload['Type'] ?? '');
        $stringToSign = '';
        if ($type === 'Notification') {
            $fields = ['Message', 'MessageId'];
            if (! empty($payload['Subject'])) {
                $fields[] = 'Subject';
            }
            $fields = array_merge($fields, ['Timestamp', 'TopicArn', 'Type']);
        } elseif (in_array($type, ['SubscriptionConfirmation', 'UnsubscribeConfirmation'], true)) {
            $fields = ['Message', 'MessageId', 'SubscribeURL', 'Timestamp', 'Token', 'TopicArn', 'Type'];
        } else {
            return false;
        }

        foreach ($fields as $f) {
            if (isset($payload[$f])) {
                $stringToSign .= $f . "\n" . $payload[$f] . "\n";
            }
        }

        $signature = base64_decode((string) $payload['Signature']);
        if ($signature === false) {
            return false;
        }

        $pubkey = openssl_get_publickey($cert);
        if ($pubkey === false) {
            return false;
        }

        $verified = openssl_verify($stringToSign, $signature, $pubkey, OPENSSL_ALGO_SHA1);
        openssl_free_key($pubkey);

        return $verified === 1;
    }
}