<?php
/**
 * Webhook endpoint for receiving Telegram updates
 * This file processes incoming messages from Telegram
 */

require_once 'config.php';
require_once 'logger.php';
require_once 'bot.php';

// Set proper headers
header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Get the raw POST data
    $input = file_get_contents('php://input');
    
    if (empty($input)) {
        Logger::log("Empty webhook request received");
        http_response_code(400);
        echo json_encode(['error' => 'Empty request']);
        exit;
    }
    
    // Decode JSON
    $update = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        Logger::log("Invalid JSON in webhook: " . json_last_error_msg());
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        exit;
    }
    
    Logger::log("Webhook received: " . $input);
    
    // Validate update structure
    if (!is_array($update) || !isset($update['update_id'])) {
        Logger::log("Invalid update structure");
        http_response_code(400);
        echo json_encode(['error' => 'Invalid update structure']);
        exit;
    }
    
    // Create bot instance and process update
    $bot = new TelegramBot();
    $bot->processUpdate($update);
    
    // Respond with success
    echo json_encode(['status' => 'ok']);
    
} catch (Exception $e) {
    Logger::log("Webhook error: " . $e->getMessage());
    Logger::log("Stack trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
} catch (Error $e) {
    Logger::log("Webhook fatal error: " . $e->getMessage());
    Logger::log("Stack trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>
