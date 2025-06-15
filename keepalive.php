<?php
/**
 * Keep-alive endpoint for maintaining bot activity
 * This helps prevent the Replit from going to sleep
 */

require_once 'config.php';
require_once 'logger.php';

header('Content-Type: application/json');

try {
    // Perform basic health checks
    $status = [
        'timestamp' => date('Y-m-d H:i:s'),
        'status' => 'alive',
        'memory_usage' => memory_get_usage(true),
        'memory_peak' => memory_get_peak_usage(true),
        'php_version' => PHP_VERSION,
        'bot_configured' => BOT_TOKEN !== 'YOUR_BOT_TOKEN_HERE' && !empty(BOT_TOKEN)
    ];
    
    // Check if bot token is configured
    if (!$status['bot_configured']) {
        $status['warning'] = 'Bot token not configured';
        Logger::warning("Keep-alive check: Bot token not configured");
    }
    
    // Check webhook status if bot is configured
    if ($status['bot_configured']) {
        try {
            require_once 'bot.php';
            $bot = new TelegramBot();
            $webhook_info = $bot->getWebhookInfo();
            
            if ($webhook_info['ok']) {
                $status['webhook'] = [
                    'url' => $webhook_info['result']['url'] ?? 'Not set',
                    'pending_updates' => $webhook_info['result']['pending_update_count'] ?? 0,
                    'last_error_date' => $webhook_info['result']['last_error_date'] ?? null
                ];
            } else {
                $status['webhook_error'] = $webhook_info;
            }
        } catch (Exception $e) {
            $status['webhook_error'] = $e->getMessage();
            Logger::error("Keep-alive webhook check failed: " . $e->getMessage());
        }
    }
    
    // Log keep-alive check
    Logger::debug("Keep-alive check performed");
    
    echo json_encode($status, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    Logger::error("Keep-alive error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>
