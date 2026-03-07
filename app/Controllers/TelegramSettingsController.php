<?php
/**
 * TelegramSettingsController.php
 * Handles Telegram bot configuration in the Admin Panel using database settings.
 */
global $mysqli, $router, $twig;
class TelegramSettingsController
{
    private mysqli $mysqli;
    private \AppSettings $settings;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
        $this->settings = new \AppSettings($mysqli);
    }

    public function index($router, $twig): void
    {
        $allSettings = $this->settings->getAll();
        $publicHttpsUrl = $this->normalizePublicHttpsUrl($_GET['public_https_url'] ?? '') ?? $this->defaultPublicHttpsUrl();
        $webhookUrl = $this->buildWebhookUrl($publicHttpsUrl);
        $botToken = (string)($allSettings['telegram_bot_token'] ?? '');
        $webhookSecret = (string)($allSettings['telegram_webhook_secret'] ?? '');
        $adminChatId = (string)($allSettings['telegram_admin_chat_id'] ?? '');
        $legacyFlash = null;

        if (isset($_GET['success']) && (string)$_GET['success'] === '1') {
            $legacyFlash = [
                'message' => 'Telegram settings updated successfully.',
                'type' => 'success',
            ];
        } elseif (isset($_GET['error'])) {
            $legacyFlash = [
                'message' => ((string)$_GET['error'] === 'csrf')
                    ? 'Invalid request token. Please try again.'
                    : 'Failed to update Telegram settings.',
                'type' => 'danger',
            ];
        }

        echo $twig->render('admin/telegram_settings.twig', [
            'title' => 'Telegram Bot Settings',
            'settings' => [
                'bot_token' => $botToken,
                'public_https_url' => $publicHttpsUrl,
                'webhook_url' => $webhookUrl,
                'webhook_secret' => $webhookSecret,
                'admin_chat_id' => $adminChatId,
                'set_webhook_api_url' => ($botToken !== '')
                    ? $this->buildSetWebhookApiUrl($botToken, $webhookUrl, $webhookSecret)
                    : ''
            ],
            'csrf_token' => generateCsrfToken(),
            'flash' => $legacyFlash,
        ]);
    }

    public function update(): void
    {
        // Verify CSRF
        if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
            showMessage('Invalid request token. Please try again.', 'danger');
            redirect('/admin/telegram-settings');
        }

        $botToken = trim((string)($_POST['bot_token'] ?? ''));
        $webhookSecret = trim((string)($_POST['webhook_secret'] ?? ''));
        $adminChatId = trim((string)($_POST['admin_chat_id'] ?? ''));

        $success = $this->settings->update([
            'telegram_bot_token' => $botToken,
            'telegram_webhook_secret' => $webhookSecret,
            'telegram_admin_chat_id' => $adminChatId
        ]);

        if ($success) {
            showMessage('Telegram settings updated successfully.', 'success');
            redirect('/admin/telegram-settings');
        }

        showMessage('Failed to update Telegram settings.', 'danger');
        redirect('/admin/telegram-settings');
    }

    public function setWebhook(): void
    {
        if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
            showMessage('Invalid request token. Please try again.', 'danger');
            redirect('/admin/telegram-settings');
        }

        $botToken = trim((string)($_POST['bot_token'] ?? $this->settings->get('telegram_bot_token', '')));
        $botToken = preg_replace('/\s+/', '', $botToken);
        $webhookSecret = trim((string)($_POST['webhook_secret'] ?? $this->settings->get('telegram_webhook_secret', '')));
        $publicHttpsUrl = $this->normalizePublicHttpsUrl($_POST['public_https_url'] ?? '');

        if ($botToken === '' || !preg_match('/^[0-9]{6,}:[A-Za-z0-9_-]{20,}$/', $botToken)) {
            showMessage('Invalid Telegram bot token. Please provide a valid bot token first.', 'danger');
            redirect('/admin/telegram-settings');
        }

        if ($webhookSecret !== '' && !preg_match('/^[A-Za-z0-9_-]{1,256}$/', $webhookSecret)) {
            showMessage('Webhook secret token can contain only letters, numbers, underscore, or hyphen.', 'danger');
            redirect('/admin/telegram-settings');
        }

        if ($publicHttpsUrl === null) {
            showMessage('Public HTTPS URL is invalid. Example: https://your-domain.com', 'danger');
            redirect('/admin/telegram-settings');
        }

        $webhookUrl = $this->buildWebhookUrl($publicHttpsUrl);

        $this->settings->update([
            'telegram_bot_token' => $botToken,
            'telegram_webhook_secret' => $webhookSecret
        ]);

        $setWebhookApiUrl = $this->buildSetWebhookApiUrl($botToken, $webhookUrl, $webhookSecret);
        $response = $this->requestJsonGet($setWebhookApiUrl);

        if (($response['ok'] ?? false) === true) {
            $description = (string)($response['description'] ?? 'Webhook set successfully.');
            showMessage($description, 'success');
            redirect('/admin/telegram-settings');
        }

        $description = (string)($response['description'] ?? 'Failed to set webhook on Telegram API.');
        showMessage($description, 'danger');
        redirect('/admin/telegram-settings');
    }

    private function defaultPublicHttpsUrl(): string
    {
        $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
        return 'https://' . $host;
    }

    private function normalizePublicHttpsUrl($rawUrl): ?string
    {
        $url = trim((string)$rawUrl);
        if ($url === '') {
            $url = $this->defaultPublicHttpsUrl();
        }

        $url = rtrim($url, '/');
        if (!preg_match('#^https://#i', $url)) {
            return null;
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return $url;
    }

    private function buildWebhookUrl(string $publicHttpsUrl): string
    {
        return rtrim($publicHttpsUrl, '/') . '/api/telegram/webhook';
    }

    private function buildSetWebhookApiUrl(string $botToken, string $webhookUrl, string $webhookSecret): string
    {
        $params = ['url' => $webhookUrl];
        if ($webhookSecret !== '') {
            $params['secret_token'] = $webhookSecret;
        }

        return 'https://api.telegram.org/bot' . $botToken . '/setWebhook?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    private function requestJsonGet(string $url): array
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            $body = curl_exec($ch);
            $statusCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($body === false) {
                return ['ok' => false, 'description' => 'Telegram API request failed: ' . $curlError];
            }

            $decoded = json_decode((string)$body, true);
            if (!is_array($decoded)) {
                return ['ok' => false, 'description' => 'Invalid response from Telegram API (HTTP ' . $statusCode . ').'];
            }

            return $decoded;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 20,
                'ignore_errors' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        if ($body === false) {
            return ['ok' => false, 'description' => 'Telegram API request failed.'];
        }

        $decoded = json_decode((string)$body, true);
        if (!is_array($decoded)) {
            return ['ok' => false, 'description' => 'Invalid response from Telegram API.'];
        }

        return $decoded;
    }
}

// Initialize and register routes
$telegramSettingsController = new TelegramSettingsController($mysqli);

$router->group('/admin/telegram-settings', ['middleware' => ['auth', 'super_admin_only']], function ($router) use ($telegramSettingsController, $twig) {
    $router->get('', function () use ($telegramSettingsController, $router, $twig) {
            $telegramSettingsController->index($router, $twig);
        }
        );
        $router->post('/update', function () use ($telegramSettingsController) {
            $telegramSettingsController->update();
        }
        );
        $router->post('/set-webhook', function () use ($telegramSettingsController) {
            $telegramSettingsController->setWebhook();
        }
        );
    });
