<?php
/**
 * Webhook Setup Script for Telegram Bot
 * Automatically configures the webhook URL for the bot
 */

// Clean any output buffer to prevent header issues
while (ob_get_level()) {
    ob_end_clean();
}

// Start output buffering
ob_start();

// Prevent any output before headers
error_reporting(0);
ini_set('display_errors', 0);

require_once 'logger.php';
require_once 'config.php';
require_once 'ai_training.php';
require_once 'bot.php';

// Set headers after all includes
if (!headers_sent()) {
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');
}

try {
    $bot = new TelegramBot();
    
    // Get the current webhook info
    $webhook_info = $bot->getWebhookInfo();
    
    if ($webhook_info['ok']) {
        $current_url = $webhook_info['result']['url'] ?? '';
        $expected_url = WEBHOOK_URL;
        
        echo json_encode([
            'status' => 'checking',
            'current_webhook' => $current_url,
            'expected_webhook' => $expected_url,
            'webhook_info' => $webhook_info['result']
        ]);
        
        // If webhook is not set or different, update it
        if ($current_url !== $expected_url) {
            $setup_result = $bot->setWebhook();
            
            if ($setup_result['ok']) {
                Logger::log("Webhook successfully configured: " . $expected_url);
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Webhook configured successfully',
                    'webhook_url' => $expected_url,
                    'setup_result' => $setup_result
                ]);
            } else {
                Logger::error("Failed to set webhook: " . json_encode($setup_result));
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Failed to configure webhook',
                    'error' => $setup_result,
                    'webhook_url' => $expected_url
                ]);
            }
        } else {
            echo json_encode([
                'status' => 'already_configured',
                'message' => 'Webhook is already properly configured',
                'webhook_url' => $current_url
            ]);
        }
    } else {
        Logger::error("Failed to get webhook info: " . json_encode($webhook_info));
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to get webhook information',
            'error' => $webhook_info
        ]);
    }
    
} catch (Exception $e) {
    Logger::error("Webhook setup exception: " . $e->getMessage());
    echo json_encode([
        'status' => 'exception',
        'message' => 'Exception occurred during webhook setup',
        'error' => $e->getMessage()
    ]);
}
?>