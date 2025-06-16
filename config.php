<?php
/**
 * Configuration file for the Telegram bot
 * Contains bot token, webhook URL, and other settings
 */

// Include logger first
require_once 'logger.php';

// Bot configuration
define('BOT_TOKEN', getenv('BOT_TOKEN') ?: '7806200256:AAG_ODhxIaJ25x70PlWLrisdgGV9wf-8ZAM');
define('BOT_USERNAME', getenv('BOT_USERNAME') ?: 'YourBotUsername');

// Replit URL configuration
$replit_url = getenv('REPL_URL') ?: 'https://r57.onrender.com';
define('WEBHOOK_URL', $replit_url . '/webhook');
define('BASE_URL', $replit_url);

// Telegram API configuration
define('TELEGRAM_API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');

// Bot settings
define('MAX_MESSAGE_LENGTH', 4096);
if (!defined('LOG_FILE')) {
    define('LOG_FILE', 'bot.log');
}
if (!defined('DEBUG_MODE')) {
    define('DEBUG_MODE', getenv('DEBUG_MODE') === 'true');
}

// Validate bot token
if (BOT_TOKEN === 'YOUR_BOT_TOKEN_HERE' || empty(BOT_TOKEN)) {
    if (class_exists('Logger')) {
        Logger::log("ERROR: Bot token not configured. Please set BOT_TOKEN environment variable.");
    }
    if (DEBUG_MODE) {
        die("Bot token not configured. Please set BOT_TOKEN environment variable in Replit secrets.");
    }
}

// Bot commands configuration
$BOT_COMMANDS = [
    '/start' => 'Welcome message and bot introduction',
    '/help' => 'Show available commands and help information',
    '/status' => 'Show bot status and uptime',
    '/ping' => 'Simple ping-pong response'
];

// Admin user IDs (optional)
$ADMIN_USERS = [];
$admin_ids = getenv('ADMIN_USERS');
if ($admin_ids) {
    $ADMIN_USERS = array_map('intval', explode(',', $admin_ids));
}

if (class_exists('Logger')) {
    Logger::log("Configuration loaded successfully");
}
?>
