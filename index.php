<?php
/**
 * Main entry point for the Telegram bot on Replit
 * This file handles the initial setup and routing
 */

require_once 'logger.php';
require_once 'config.php';
require_once 'ai_training.php';
require_once 'bot.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set timezone
date_default_timezone_set('UTC');

// Log the start of the application
Logger::log("Bot application started");

// Get the request method and path
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$request_uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($request_uri, PHP_URL_PATH);

// Handle different routes
if ($method === 'POST' && $path === '/webhook') {
    // Webhook endpoint for Telegram
    try {
        $input = file_get_contents('php://input');
        $update = json_decode($input, true);
        
        if ($update) {
            $bot = new TelegramBot();
            $bot->processUpdate($update);
            
            // Send immediate response to Telegram
            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode(['ok' => true]);
            exit;
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON']);
            exit;
        }
    } catch (Exception $e) {
        Logger::error("Webhook error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Internal server error']);
        exit;
    }
} elseif ($method === 'GET' && $path === '/setup') {
    // Setup page
    require_once 'setup_webhook.php';
    exit;
} elseif ($method === 'GET' && ($path === '/' || $path === '/index.php')) {
    // Main web interface
    require_once 'web_interface.php';
    exit;
} else {
    // 404 for other routes
    http_response_code(404);
    echo "404 - Page not found";
    exit;
}
switch ($path) {
    case '/':
        // Root endpoint - show web interface
        require_once 'web_interface.php';
        break;
        
    case '/webhook':
        // Webhook endpoint for Telegram
        require_once 'webhook.php';
        break;
        
    case '/setup':
        // Setup webhook endpoint
        require_once 'setup_webhook.php';
        break;
        
    case '/info':
        // Get webhook info
        $bot = new TelegramBot();
        $info = $bot->getWebhookInfo();
        header('Content-Type: application/json');
        echo json_encode($info);
        break;
        
    case '/keepalive':
        // Keep-alive endpoint
        require_once 'keepalive.php';
        break;
        
    case '/status':
        // Status endpoint with complete system information
        header('Content-Type: application/json');
        $aiTraining = new AITraining();
        $bot = new TelegramBot();
        
        $status = [
            'system' => [
                'status' => 'running',
                'timestamp' => date('Y-m-d H:i:s'),
                'php_version' => PHP_VERSION,
                'memory_usage' => memory_get_usage(true),
                'memory_peak' => memory_get_peak_usage(true)
            ],
            'bot' => [
                'configured' => BOT_TOKEN !== 'YOUR_BOT_TOKEN_HERE' && !empty(BOT_TOKEN),
                'webhook_url' => WEBHOOK_URL,
                'base_url' => BASE_URL
            ],
            'training' => [
                'total_entries' => $aiTraining->getTrainingCount(),
                'data_file_exists' => file_exists('train.json'),
                'data_file_size' => file_exists('train.json') ? filesize('train.json') : 0
            ],
            'files' => [
                'log_file' => file_exists(LOG_FILE) ? filesize(LOG_FILE) : 0,
                'bug_file' => file_exists('bug.txt') ? filesize('bug.txt') : 0,
                'counter_file' => file_exists('counter.txt') ? file_get_contents('counter.txt') : '0'
            ]
        ];
        
        echo json_encode($status, JSON_PRETTY_PRINT);
        break;
        
    case '/test':
        // Test interface for debugging
        require_once 'test_interface.php';
        break;
        
    case '/admin':
        // Admin panel for bot management
        require_once 'admin_panel.php';
        break;
        
    case '/analytics':
        // Analytics endpoint
        header('Content-Type: application/json');
        require_once 'user_analytics.php';
        $analytics = new UserAnalytics();
        echo json_encode($analytics->generateReport(), JSON_PRETTY_PRINT);
        break;
        
    default:
        // 404 for unknown routes
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found']);
        break;
}

Logger::log("Request processed: $method $path");
?>
