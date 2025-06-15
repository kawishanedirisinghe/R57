<?php
/**
 * Test Interface for System Verification
 * Provides testing capabilities for all bot and AI functionality
 */

require_once 'logger.php';
require_once 'config.php';
require_once 'ai_training.php';
require_once 'bot.php';

$aiTraining = new AITraining();
$bot = new TelegramBot();
$test_results = [];

// Run comprehensive tests if requested
if (isset($_GET['run_tests'])) {
    // Test 1: Bot Configuration
    $test_results['bot_config'] = [
        'bot_token_configured' => !empty(BOT_TOKEN) && BOT_TOKEN !== 'YOUR_BOT_TOKEN_HERE',
        'webhook_url' => WEBHOOK_URL,
        'api_url' => TELEGRAM_API_URL
    ];
    
    // Test 2: AI Training System
    $test_results['ai_training'] = [
        'class_loaded' => class_exists('AITraining'),
        'training_count' => $aiTraining->getTrainingCount(),
        'counter_file_exists' => file_exists('counter.txt')
    ];
    
    // Test 3: File System
    $test_results['file_system'] = [
        'train_json_exists' => file_exists('train.json'),
        'train_json_size' => file_exists('train.json') ? filesize('train.json') : 0,
        'log_file_exists' => file_exists(LOG_FILE),
        'config_loaded' => defined('BOT_TOKEN')
    ];
    
    // Test 4: Webhook Status
    if ($test_results['bot_config']['bot_token_configured']) {
        try {
            $webhook_info = $bot->getWebhookInfo();
            $test_results['webhook'] = $webhook_info;
        } catch (Exception $e) {
            $test_results['webhook'] = ['error' => $e->getMessage()];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Test Interface</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            background-color: #1a1a1a;
            color: #00ff00;
            padding: 20px;
            line-height: 1.6;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #00ff00;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .test-section {
            background-color: #2a2a2a;
            border: 1px solid #00ff00;
            margin-bottom: 20px;
            padding: 15px;
            border-radius: 5px;
        }
        .test-title {
            color: #00ffff;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .status-ok {
            color: #00ff00;
        }
        .status-error {
            color: #ff0000;
        }
        .status-warning {
            color: #ffff00;
        }
        .btn {
            background-color: #00ff00;
            color: #000;
            padding: 10px 20px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            margin: 5px;
            text-decoration: none;
            display: inline-block;
        }
        .btn:hover {
            background-color: #00cc00;
        }
        pre {
            background-color: #000;
            padding: 10px;
            border-radius: 3px;
            overflow-x: auto;
            white-space: pre-wrap;
        }
        .endpoints {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
            margin-top: 20px;
        }
        .endpoint {
            background-color: #333;
            padding: 10px;
            border-radius: 3px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔧 SYSTEM TEST INTERFACE 🔧</h1>
            <p>Telegram Bot & AI Training System Diagnostics</p>
        </div>

        <div class="test-section">
            <div class="test-title">🚀 QUICK ACTIONS</div>
            <a href="?run_tests=1" class="btn">Run All Tests</a>
            <a href="/setup" class="btn">Setup Webhook</a>
            <a href="/info" class="btn">Webhook Info</a>
            <a href="/status" class="btn">System Status</a>
            <a href="/" class="btn">Chat Interface</a>
        </div>

        <?php if (!empty($test_results)): ?>
        <div class="test-section">
            <div class="test-title">🤖 BOT CONFIGURATION</div>
            <p>Token Configured: <span class="<?php echo $test_results['bot_config']['bot_token_configured'] ? 'status-ok' : 'status-error'; ?>">
                <?php echo $test_results['bot_config']['bot_token_configured'] ? 'YES' : 'NO'; ?>
            </span></p>
            <p>Webhook URL: <span class="status-ok"><?php echo htmlspecialchars($test_results['bot_config']['webhook_url']); ?></span></p>
            <p>API URL: <span class="status-ok"><?php echo htmlspecialchars($test_results['bot_config']['api_url']); ?></span></p>
        </div>

        <div class="test-section">
            <div class="test-title">🧠 AI TRAINING SYSTEM</div>
            <p>Class Loaded: <span class="<?php echo $test_results['ai_training']['class_loaded'] ? 'status-ok' : 'status-error'; ?>">
                <?php echo $test_results['ai_training']['class_loaded'] ? 'YES' : 'NO'; ?>
            </span></p>
            <p>Training Entries: <span class="status-ok"><?php echo $test_results['ai_training']['training_count']; ?></span></p>
            <p>Counter File: <span class="<?php echo $test_results['ai_training']['counter_file_exists'] ? 'status-ok' : 'status-warning'; ?>">
                <?php echo $test_results['ai_training']['counter_file_exists'] ? 'EXISTS' : 'MISSING'; ?>
            </span></p>
        </div>

        <div class="test-section">
            <div class="test-title">📁 FILE SYSTEM</div>
            <p>Training Data: <span class="<?php echo $test_results['file_system']['train_json_exists'] ? 'status-ok' : 'status-warning'; ?>">
                <?php echo $test_results['file_system']['train_json_exists'] ? 'EXISTS' : 'MISSING'; ?>
            </span> (<?php echo $test_results['file_system']['train_json_size']; ?> bytes)</p>
            <p>Log File: <span class="<?php echo $test_results['file_system']['log_file_exists'] ? 'status-ok' : 'status-warning'; ?>">
                <?php echo $test_results['file_system']['log_file_exists'] ? 'EXISTS' : 'MISSING'; ?>
            </span></p>
            <p>Config Loaded: <span class="<?php echo $test_results['file_system']['config_loaded'] ? 'status-ok' : 'status-error'; ?>">
                <?php echo $test_results['file_system']['config_loaded'] ? 'YES' : 'NO'; ?>
            </span></p>
        </div>

        <?php if (isset($test_results['webhook'])): ?>
        <div class="test-section">
            <div class="test-title">🌐 WEBHOOK STATUS</div>
            <?php if (isset($test_results['webhook']['error'])): ?>
                <p class="status-error">ERROR: <?php echo htmlspecialchars($test_results['webhook']['error']); ?></p>
            <?php else: ?>
                <pre><?php echo json_encode($test_results['webhook'], JSON_PRETTY_PRINT); ?></pre>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <div class="test-section">
            <div class="test-title">🔗 AVAILABLE ENDPOINTS</div>
            <div class="endpoints">
                <div class="endpoint">
                    <strong>/</strong><br>
                    Chat Interface
                </div>
                <div class="endpoint">
                    <strong>/webhook</strong><br>
                    Telegram Webhook
                </div>
                <div class="endpoint">
                    <strong>/setup</strong><br>
                    Setup Webhook
                </div>
                <div class="endpoint">
                    <strong>/info</strong><br>
                    Webhook Info
                </div>
                <div class="endpoint">
                    <strong>/status</strong><br>
                    System Status
                </div>
                <div class="endpoint">
                    <strong>/keepalive</strong><br>
                    Keep Alive
                </div>
                <div class="endpoint">
                    <strong>/test</strong><br>
                    This Interface
                </div>
            </div>
        </div>

        <div class="test-section">
            <div class="test-title">📊 SYSTEM INFO</div>
            <p>PHP Version: <span class="status-ok"><?php echo PHP_VERSION; ?></span></p>
            <p>Memory Usage: <span class="status-ok"><?php echo number_format(memory_get_usage(true) / 1024 / 1024, 2); ?> MB</span></p>
            <p>Current Time: <span class="status-ok"><?php echo date('Y-m-d H:i:s'); ?> UTC</span></p>
            <p>Server: <span class="status-ok"><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></span></p>
        </div>

        <div class="test-section">
            <div class="test-title">🔧 MANUAL TESTS</div>
            <p>1. Visit the chat interface at <a href="/" class="status-ok">/</a></p>
            <p>2. Test webhook setup at <a href="/setup" class="status-ok">/setup</a></p>
            <p>3. Check system status at <a href="/status" class="status-ok">/status</a></p>
            <p>4. Send a test message to your Telegram bot</p>
            <p>5. Verify training data is being generated</p>
        </div>
    </div>
</body>
</html>