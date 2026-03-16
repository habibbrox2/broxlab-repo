<?php
declare(strict_types=1);

namespace App\Telegram;

use mysqli;
use App\FeatureFlags\FeatureManager;
use App\Telegram\Commands\StartCommand;
use App\Telegram\Callbacks\SmsMenuCallback;
use App\Telegram\Callbacks\SmsLogsCallback;
use App\Telegram\Callbacks\IncomingSmsCallback;
use App\Telegram\Callbacks\ScraperMenuCallback;
use App\Telegram\Callbacks\DeviceControlMenuCallback;
use App\Telegram\Callbacks\PdfMenuCallback;
use App\Modules\Scraper\ScraperService;
use App\Modules\DeviceControl\DeviceControlService;
use App\Modules\PdfTools\PdfService;
use Exception;

/**
 * BotKernel.php
 * Central hub for handling Telegram updates.
 */
class BotKernel
{
    private mysqli $mysqli;
    private FeatureManager $featureManager;
    private CommandRegistry $commandRegistry;
    private CallbackRegistry $callbackRegistry;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
        $this->featureManager = FeatureManager::getInstance($mysqli);
        $this->commandRegistry = new CommandRegistry($mysqli);
        $this->callbackRegistry = new CallbackRegistry($mysqli);
        $this->boot();
    }

    private function boot(): void
    {
        // Register Commands
        $this->commandRegistry->register('/start', StartCommand::class);
        $this->commandRegistry->register('/menu', StartCommand::class);

        // Register Callbacks
        $this->callbackRegistry->register('menu_sms', SmsMenuCallback::class);
        $this->callbackRegistry->register('menu_incoming', IncomingSmsCallback::class);
        $this->callbackRegistry->register('menu_scraper', ScraperMenuCallback::class);
        $this->callbackRegistry->register('menu_device', DeviceControlMenuCallback::class);
        $this->callbackRegistry->register('menu_pdf', PdfMenuCallback::class);
        $this->callbackRegistry->register('sms_send', \App\Telegram\Callbacks\SmsSendCallback::class);
        $this->callbackRegistry->register('sms_logs', SmsLogsCallback::class);
    }

    /**
     * Handle incoming webhook updates.
     */
    public function handleUpdates(array $update): void
    {
        if (!$this->featureManager->isEnabled('telegram_panel')) {
            error_log("Telegram Panel is disabled.");
            return;
        }

        if (isset($update['message'])) {
            $this->handleMessage($update['message'], $update);
        }
        elseif (isset($update['callback_query'])) {
            $this->handleCallbackQuery($update['callback_query'], $update);
        }
    }

    private function handleMessage(array $message, array $update): void
    {
        $chatId = (string)($message['chat']['id'] ?? '');
        if (!$chatId)
            return;

        // Check for active session state
        $session = new TelegramSessionManager($this->mysqli);
        $state = $session->getState($chatId);

        // Handle Documents (PDFs)
        if (isset($message['document']) && $state === 'pdf_waiting_files') {
            $this->handlePdfUpload($message['document'], $chatId, $session);
            return;
        }

        $text = $message['text'] ?? '';
        if ($state && strpos($text, '/') !== 0) {
            $this->handleSessionState($state, $text, $chatId, $update, $session);
            return;
        }

        if (strpos($text, '/') === 0) {
            $this->commandRegistry->execute($text, $update);
        }
    }

    private function handlePdfUpload(array $document, string $chatId, TelegramSessionManager $session): void
    {
        if ($document['mime_type'] !== 'application/pdf') {
            $telegram = new TelegramService((new \AppSettings($this->mysqli))->get('telegram_bot_token', ''));
            $telegram->sendMessage($chatId, "⚠️ Please upload only PDF files.");
            return;
        }

        $data = $session->getData($chatId);
        $files = $data['files'] ?? [];
        $files[] = $document['file_id'];
        $data['files'] = $files;
        $session->setState($chatId, 'pdf_waiting_files', $data);

        $count = count($files);
        $telegram = new TelegramService((new \AppSettings($this->mysqli))->get('telegram_bot_token', ''));

        $builder = new InlineKeyboardBuilder();
        if ($count >= 2) {
            $builder->addButton("🔗 Merge ($count Files)", 'pdf_action_merge')->nextRow();
        }
        if ($count == 1) {
            $builder->addButton("📑 Split", 'pdf_action_split')->nextRow();
        }
        $builder->addButton("❌ Cancel", 'menu_main');

        $telegram->sendMessage($chatId, "✅ File #$count received: {$document['file_name']}\n\nYou can upload more or choose an action:", $builder->build());
    }

    private function handleSessionState(string $state, string $text, string $chatId, array $update, TelegramSessionManager $session): void
    {
        $telegram = new TelegramService((new \AppSettings($this->mysqli))->get('telegram_bot_token', ''));

        switch ($state) {
            case 'sms_waiting_recipient':
                // Validate recipient (basic)
                if (!preg_match('/^\+?[0-9]{10,15}$/', $text)) {
                    $telegram->sendMessage($chatId, "⚠️ Invalid phone number format. Please try again (e.g., +8801700000000):");
                    return;
                }

                $session->setState($chatId, 'sms_waiting_device', ['recipient' => $text]);

                // Fetch online devices
                $devices = $this->mysqli->query("SELECT id, device_name FROM devices WHERE status = 'online'");
                $builder = new InlineKeyboardBuilder();

                if ($devices->num_rows > 0) {
                    while ($device = $devices->fetch_assoc()) {
                        $builder->addButton($device['device_name'], "sms_device_" . $device['id'])->nextRow();
                    }
                    $telegram->sendMessage($chatId, "📤 *Step 2/3: Select Device*\nChoose an online device to send from:", $builder->build());
                }
                else {
                    $telegram->sendMessage($chatId, "❌ No online devices available. Use /start to try again later.");
                    $session->clear($chatId);
                }
                break;

            case 'sms_waiting_message':
                $data = $session->getData($chatId);
                $recipient = $data['recipient'] ?? '';
                $deviceId = (int)($data['device_id'] ?? 0);

                try {
                    $smsService = new \App\Modules\SmsGateway\SmsService($this->mysqli);
                    $success = $smsService->sendSms($recipient, $text, $deviceId);

                    if ($success) {
                        $telegram->sendMessage($chatId, "✅ *SMS Sent!*\nTo: $recipient\nDevice: $deviceId\nMessage: $text");
                    }
                    else {
                        $telegram->sendMessage($chatId, "❌ *Failed to send SMS.* Check device status.");
                    }
                }
                catch (Exception $e) {
                    $telegram->sendMessage($chatId, "❌ *Error:* " . $e->getMessage());
                }

                $session->clear($chatId);
                break;

            case 'scraper_waiting_url':
                $telegram->sendMessage($chatId, "🕷 *Scraping...* Please wait.");

                $scraper = new ScraperService();
                $result  = $scraper->scrape($text);

                if (isset($result['error'])) {
                    $telegram->sendMessage($chatId, "❌ *Scraping Failed:* " . $result['error']);
                } else {
                    $response  = "🕷 *Scraper Results*\n\n";
                    $response .= "🔗 *URL:* " . $result['url'] . "\n";
                    $response .= "📝 *Title:* " . $result['title'] . "\n";
                    $response .= "📄 *Description:* " . mb_substr($result['description'], 0, 300) . "\n";
                    if (!empty($result['image'])) {
                        $response .= "🖼 *Image:* " . $result['image'] . "\n";
                    }
                    if (!empty($result['links'])) {
                        $response .= "\n🔗 *Top Links:*\n";
                        foreach ($result['links'] as $i => $link) {
                            $response .= ($i + 1) . ". " . $link . "\n";
                        }
                    }
                    $response .= "\n📅 _" . $result['timestamp'] . "_";
                    $telegram->sendMessage($chatId, $response);
                }

                $session->clear($chatId);
                break;
        }
    }
    private function handleCallbackQuery(array $callbackQuery, array $update): void
    {
        $data = $callbackQuery['data'] ?? '';
        $chatId = (string)($callbackQuery['message']['chat']['id'] ?? '');
        $callbackId = (string)($callbackQuery['id'] ?? '');

        if (!$data || !$chatId) {
            return;
        }

        if ($data === 'menu_main') {
            $this->sendMainMenu($chatId);
            $this->answerCallback($callbackId);
            return;
        }

        if ($data === 'menu_sim') {
            $this->showSimRoutingMenu($chatId);
            $this->answerCallback($callbackId);
            return;
        }

        if ($data === 'menu_settings') {
            $this->showFeatureSettings($chatId);
            $this->answerCallback($callbackId);
            return;
        }

        if ($data === 'sms_devices') {
            $this->showSmsDevices($chatId);
            $this->answerCallback($callbackId);
            return;
        }

        // SMS Device Flow
        if (strpos($data, 'sms_device_') === 0) {
            $deviceId = str_replace('sms_device_', '', $data);
            $session = new TelegramSessionManager($this->mysqli);
            $currentData = $session->getData($chatId);
            $currentData['device_id'] = $deviceId;

            $session->setState($chatId, 'sms_waiting_message', $currentData);

            $telegram = new TelegramService((new \AppSettings($this->mysqli))->get('telegram_bot_token', ''));
            $telegram->sendMessage($chatId, "Step 3/3: Enter Message\nPlease type the message you want to send:");
            $this->answerCallback($callbackId);
            return;
        }

        // Device Control Select
        if (strpos($data, 'device_select_') === 0) {
            if (!$this->featureManager->isEnabled('remote_device')) {
                $telegram = new TelegramService((new \AppSettings($this->mysqli))->get('telegram_bot_token', ''));
                $telegram->sendMessage($chatId, 'Device control feature is disabled.');
                $this->answerCallback($callbackId, 'Feature disabled');
                return;
            }

            $deviceId = (int)str_replace('device_select_', '', $data);
            if ($deviceId <= 0) {
                $this->answerCallback($callbackId, 'Invalid device');
                return;
            }

            $builder = new InlineKeyboardBuilder();
            $builder->addButton('Ping', "device_cmd_{$deviceId}_ping")
                ->addButton('Battery', "device_cmd_{$deviceId}_battery")
                ->nextRow()
                ->addButton('Status', "device_cmd_{$deviceId}_status")
                ->addButton('Network', "device_cmd_{$deviceId}_network")
                ->nextRow()
                ->addButton('Reboot', "device_cmd_{$deviceId}_reboot")
                ->nextRow()
                ->addButton('Refresh', "device_select_{$deviceId}")
                ->nextRow()
                ->addButton('Back to Devices', 'menu_device');

            $telegram = new TelegramService((new \AppSettings($this->mysqli))->get('telegram_bot_token', ''));
            $telegram->sendMessage($chatId, "Device Control: ID {$deviceId}\nChoose a command:", $builder->build());
            $this->answerCallback($callbackId);
            return;
        }

        // Device Control Execute
        if (strpos($data, 'device_cmd_') === 0) {
            if (!$this->featureManager->isEnabled('remote_device')) {
                $telegram = new TelegramService((new \AppSettings($this->mysqli))->get('telegram_bot_token', ''));
                $telegram->sendMessage($chatId, 'Device control feature is disabled.');
                $this->answerCallback($callbackId, 'Feature disabled');
                return;
            }

            if (!preg_match('/^device_cmd_(\d+)_(ping|battery|reboot|status|network)$/', $data, $matches)) {
                $this->answerCallback($callbackId, 'Invalid command');
                return;
            }

            $deviceId = (int)$matches[1];
            $command = (string)$matches[2];
            $requestedBy = isset($callbackQuery['from']['id']) ? (int)$callbackQuery['from']['id'] : null;

            $service = new DeviceControlService($this->mysqli);
            $result = $service->executeCommand($deviceId, $command, [], $requestedBy);

            $telegram = new TelegramService((new \AppSettings($this->mysqli))->get('telegram_bot_token', ''));
            $status = !empty($result['success']) ? 'SUCCESS' : 'FAILED';
            $deviceName = (string)($result['device_name'] ?? ('Device #' . $deviceId));
            $commandId = (int)($result['command_id'] ?? 0);
            $message = (string)($result['message'] ?? 'Unknown response');
            $meta = $commandId > 0 ? "\nCommand ID: #{$commandId}" : '';
            $telegram->sendMessage($chatId, "[{$status}] *{$deviceName}*\n{$message}{$meta}");
            $this->answerCallback($callbackId, !empty($result['success']) ? 'Command accepted' : 'Command failed');
            return;
        }

        // PDF Actions
        if ($data === 'pdf_action_merge' || $data === 'pdf_action_split') {
            $botToken = (new \AppSettings($this->mysqli))->get('telegram_bot_token', '');
            $telegram = new TelegramService($botToken);
            $session = new TelegramSessionManager($this->mysqli);
            $sessionData = $session->getData($chatId);
            $fileIds = $sessionData['files'] ?? [];

            if (empty($fileIds)) {
                $telegram->sendMessage($chatId, 'No PDF files found in session. Please start again.');
                $session->clear($chatId);
                $this->answerCallback($callbackId);
                return;
            }

            $telegram->sendMessage($chatId, 'Downloading and processing PDF files...');

            $pdfService = new PdfService();
            $localPaths = [];
            foreach ($fileIds as $fileId) {
                $path = $pdfService->downloadTelegramFile($fileId, $botToken);
                if ($path !== null) {
                    $localPaths[] = $path;
                }
            }

            if (empty($localPaths)) {
                $telegram->sendMessage($chatId, 'Failed to download PDF files. Please try again.');
                $session->clear($chatId);
                $this->answerCallback($callbackId);
                return;
            }

            if ($data === 'pdf_action_merge') {
                $outputPath = $pdfService->merge($localPaths);
                if ($outputPath && file_exists($outputPath)) {
                    $telegram->sendDocument($chatId, $outputPath, 'Merged PDF');
                    $pdfService->cleanup(array_merge($localPaths, [$outputPath]));
                } else {
                    $pdfService->cleanup($localPaths);
                    $telegram->sendMessage($chatId, 'PDF merge failed. Ensure all files are valid PDFs.');
                }
            } else {
                $outputFiles = $pdfService->split($localPaths[0]);
                if (!empty($outputFiles)) {
                    foreach ($outputFiles as $idx => $splitPath) {
                        $telegram->sendDocument($chatId, $splitPath, 'Page ' . ($idx + 1));
                    }
                    $pdfService->cleanup(array_merge($localPaths, $outputFiles));
                } else {
                    $pdfService->cleanup($localPaths);
                    $telegram->sendMessage($chatId, 'PDF split failed. Ensure the file is a valid PDF.');
                }
            }

            $session->clear($chatId);
            $this->answerCallback($callbackId);
            return;
        }

        $this->callbackRegistry->execute($data, $update);
        $this->answerCallback($callbackId);
    }

    private function answerCallback(string $callbackId, string $text = ''): void
    {
        if ($callbackId === '') {
            return;
        }

        $telegram = new TelegramService((new \AppSettings($this->mysqli))->get('telegram_bot_token', ''));
        $telegram->answerCallbackQuery($callbackId, $text);
    }
    private function sendMainMenu(string $chatId): void
    {
        $session = new TelegramSessionManager($this->mysqli);
        $session->clear($chatId);

        $telegram = new TelegramService((new \AppSettings($this->mysqli))->get('telegram_bot_token', ''));
        $telegram->sendMessage($chatId, 'Main menu:', MainMenu::get());
    }

    private function showSimRoutingMenu(string $chatId): void
    {
        $telegram = new TelegramService((new \AppSettings($this->mysqli))->get('telegram_bot_token', ''));

        if (!$this->featureManager->isEnabled('sim_routing')) {
            $keyboard = (new InlineKeyboardBuilder())->addButton('⬅️ Main Menu', 'menu_main')->build();
            $telegram->sendMessage($chatId, '⚠️ SIM Routing is currently *disabled*.\nEnable it from Admin › Feature Flags.', $keyboard);
            return;
        }

        $router = new \App\Modules\SmsGateway\SimRoutingService($this->mysqli);
        $routes = $router->getAllRoutes();

        $text = "📡 *SIM Routing Rules*\n\n";
        if (empty($routes)) {
            $text .= "No rules configured yet.\n";
        } else {
            foreach ($routes as $i => $route) {
                $status = (int)$route['enabled'] === 1 ? '✅' : '⏸';
                $match  = $route['match_type'] === 'any' ? 'Any' : ucfirst($route['match_type']) . ': ' . ($route['match_value'] ?? '');
                $action = str_replace('_', ' ', $route['action']);
                $text  .= ($i + 1) . ". {$status} *{$route['label']}*\n";
                $text  .= "   Match: {$match} | Action: {$action}\n";
            }
        }
        $text .= "\n_Manage rules from Admin › SIM Routing._";

        $keyboard = (new InlineKeyboardBuilder())->addButton('⬅️ Main Menu', 'menu_main')->build();
        $telegram->sendMessage($chatId, $text, $keyboard);
    }

    private function showFeatureSettings(string $chatId): void
    {
        $telegram = new TelegramService((new \AppSettings($this->mysqli))->get('telegram_bot_token', ''));
        $keyboard = (new InlineKeyboardBuilder())
            ->addButton('Back to Main Menu', 'menu_main')
            ->build();

        $result = $this->mysqli->query("SELECT feature_key, enabled FROM feature_flags ORDER BY feature_key ASC");
        if (!$result) {
            $telegram->sendMessage($chatId, 'Could not load feature settings. Please check database setup.', $keyboard);
            return;
        }

        $text = "Feature settings:\n";
        while ($row = $result->fetch_assoc()) {
            $status = ((int)$row['enabled'] === 1) ? 'ON' : 'OFF';
            $text .= "- {$row['feature_key']}: {$status}\n";
        }
        $text .= "\nManage these flags from Admin > Feature Flags.";

        $telegram->sendMessage($chatId, $text, $keyboard);
    }

    private function showSmsDevices(string $chatId): void
    {
        $telegram = new TelegramService((new \AppSettings($this->mysqli))->get('telegram_bot_token', ''));

        if (!$this->featureManager->isEnabled('sms_gateway')) {
            $telegram->sendMessage($chatId, 'This feature is currently disabled.');
            return;
        }

        $keyboard = (new InlineKeyboardBuilder())
            ->addButton('Back to SMS Menu', 'menu_sms')
            ->build();

        $result = $this->mysqli->query("SELECT id, device_name, status, last_sync FROM devices ORDER BY (status = 'online') DESC, device_name ASC LIMIT 20");
        if (!$result || $result->num_rows === 0) {
            $telegram->sendMessage($chatId, 'No devices found.', $keyboard);
            return;
        }

        $text = "Registered devices:\n";
        while ($row = $result->fetch_assoc()) {
            $name = $row['device_name'] ?? ('Device #' . ($row['id'] ?? ''));
            $status = strtoupper((string)($row['status'] ?? 'unknown'));
            $lastSync = (string)($row['last_sync'] ?? 'n/a');
            $text .= "- #{$row['id']} {$name} [{$status}] last_sync={$lastSync}\n";
        }

        $telegram->sendMessage($chatId, $text, $keyboard);
    }
}
