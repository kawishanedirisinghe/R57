
<?php
set_time_limit(999999999);

/**
 * Main entry point for the Telegram bot on Replit
 * This file handles the initial setup and routing
 */

// Clean output buffer first
while (ob_get_level()) {
    ob_end_clean();
}

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

// Clean output buffer
while (ob_get_level()) {
    ob_end_clean();
}

if ($method === 'POST' && $path === '/webhook') {
    // Webhook endpoint for Telegram
    try {
        // Clean any output buffer first
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Send immediate response headers
        if (!headers_sent()) {
            http_response_code(200);
            header('Content-Type: application/json');
            header('Cache-Control: no-cache');
        }

        $input = file_get_contents('php://input');
        $update = json_decode($input, true);
        $idd = $update["message"]["message_id"];
        if(file_get_contents($idd)){
            exit;
        }
        $myfile = fopen("$idd", "w") or die("Unable to open file!");
        $txt = "done";
        fwrite($myfile, $txt);
        fclose($myfile);
        
        if ($update && is_array($update)) {
            // Send immediate response to prevent retries
            echo json_encode(['ok' => true]);
            
            // Flush output immediately
            if (ob_get_level()) {
                ob_end_flush();
            }
            flush();
            
            // Use fastcgi_finish_request if available
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }

            // Process update in background with error handling
            try {
                $bot = new TelegramBot();
                $bot->processUpdate($update);
            } catch (Exception $e) {
                Logger::error("Update processing error: " . $e->getMessage());
                // Don't throw error to user, already sent 200 response
            }
        } else {
            echo json_encode(['error' => 'Invalid JSON']);
        }
        exit;
    } catch (Exception $e) {
        Logger::error("Webhook error: " . $e->getMessage());
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json');
        }
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
} elseif ($method === 'GET' && $path === '/info') {
    // Get webhook info
    $bot = new TelegramBot();
    $info = $bot->getWebhookInfo();
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    echo json_encode($info);
    exit;
} elseif ($method === 'GET' && $path === '/status') {
    // Status endpoint
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
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
        ]
    ];
    
    echo json_encode($status, JSON_PRETTY_PRINT);
    exit;
} elseif ($method === 'GET' && $path === '/admin') {
    require_once 'admin_panel.php';
    exit;
} else {
    // 404 for other routes
    if (!headers_sent()) {
        http_response_code(404);
    }
    echo "404 - Page not found";
    exit;
}

Logger::log("Request processed: $method $path");
?>
</replit_final_file>