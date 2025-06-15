<?php
/**
 * Simple logging utility for the Telegram bot
 * Provides file-based logging with timestamps
 */

// Define LOG_FILE constant if not already defined
if (!defined('LOG_FILE')) {
    define('LOG_FILE', 'bot.log');
}

// Define DEBUG_MODE constant if not already defined
if (!defined('DEBUG_MODE')) {
    define('DEBUG_MODE', getenv('DEBUG_MODE') === 'true');
}

class Logger {
    
    /**
     * Write log entry to file
     */
    public static function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[$timestamp] [$level] $message" . PHP_EOL;
        
        // Write to file (append mode)
        $log_file = LOG_FILE;
        
        // Ensure log file doesn't grow too large (keep last 1000 lines)
        if (file_exists($log_file) && filesize($log_file) > 1024 * 1024) { // 1MB
            self::rotateLog($log_file);
        }
        
        file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
        
        // Also output to error log if in debug mode
        if (DEBUG_MODE) {
            error_log("TelegramBot: $message");
        }
    }
    
    /**
     * Log error message
     */
    public static function error($message) {
        self::log($message, 'ERROR');
    }
    
    /**
     * Log warning message
     */
    public static function warning($message) {
        self::log($message, 'WARNING');
    }
    
    /**
     * Log debug message (only if debug mode is enabled)
     */
    public static function debug($message) {
        if (DEBUG_MODE) {
            self::log($message, 'DEBUG');
        }
    }
    
    /**
     * Rotate log file to prevent it from growing too large
     */
    private static function rotateLog($log_file) {
        if (!file_exists($log_file)) {
            return;
        }
        
        $lines = file($log_file);
        if (count($lines) > 1000) {
            // Keep only the last 500 lines
            $kept_lines = array_slice($lines, -500);
            file_put_contents($log_file, implode('', $kept_lines));
            self::log("Log file rotated - kept last 500 entries");
        }
    }
    
    /**
     * Get recent log entries
     */
    public static function getRecentLogs($lines = 50) {
        $log_file = LOG_FILE;
        
        if (!file_exists($log_file)) {
            return [];
        }
        
        $all_lines = file($log_file);
        return array_slice($all_lines, -$lines);
    }
    
    /**
     * Clear log file
     */
    public static function clearLog() {
        $log_file = LOG_FILE;
        
        if (file_exists($log_file)) {
            unlink($log_file);
            self::log("Log file cleared");
        }
    }
}

// Log that the logger has been initialized
Logger::log("Logger initialized");
?>
