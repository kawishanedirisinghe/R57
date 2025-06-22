php
<?php
/**
 * Main Telegram Bot class
 * Handles API calls, message processing, and command routing
 */

require_once 'config.php';
require_once 'logger.php';
require_once 'ai_training.php';
require_once 'user_analytics.php';
require_once 'multimedia_handler.php';
require_once 'search_commands.php';
require_once 'ai_api.php';

class TelegramBot {

    private $token;
    private $api_url;
    private $aiTraining;
    private $analytics;
    private $multimedia;
    private $searchCommands;
    private $aiAPI;

    public function __construct() {
        $this->token = BOT_TOKEN;
        $this->api_url = TELEGRAM_API_URL;
        $this->aiTraining = new AITraining();
        $this->analytics = new UserAnalytics();
        $this->multimedia = new MultimediaHandler();
        
        // Initialize AI API with error handling
        try {
            $this->aiAPI = new AiAPI();
        } catch (Exception $e) {
            Logger::error("Failed to initialize AiAPI: " . $e->getMessage());
            $this->aiAPI = null;
        }

        // Initialize search commands with error handling
        try {
            $this->searchCommands = new SearchCommands();
        } catch (Exception $e) {
            Logger::error("Failed to initialize SearchCommands: " . $e->getMessage());
            $this->searchCommands = null;
        }
    }

    /**
     * Make API call to Telegram
     */
    public function apiCall($method, $data = []) {
        $url = $this->api_url . $method;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen(json_encode($data))
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            Logger::log("cURL error for $method: $error");
            return ['ok' => false, 'error' => $error];
        }

        $decoded = json_decode($response, true);

        if ($http_code !== 200) {
            Logger::log("API error for $method: HTTP $http_code - " . $response);
            return ['ok' => false, 'error' => "HTTP $http_code", 'response' => $response];
        }

        if (!$decoded['ok']) {
            Logger::log("Telegram API error for $method: " . json_encode($decoded));
        }

        return $decoded;
    }

    /**
     * Set webhook URL
     */
    public function setWebhook() {
        $data = [
            'url' => WEBHOOK_URL,
            'allowed_updates' => ['message', 'callback_query']
        ];

        $result = $this->apiCall('setWebhook', $data);
        Logger::log("Webhook setup result: " . json_encode($result));

        return $result;
    }

    /**
     * Get webhook info
     */
    public function getWebhookInfo() {
        return $this->apiCall('getWebhookInfo');
    }

    /**
     * Send message to chat
     */
    public function sendMessage($chat_id, $text, $reply_markup = null, $parse_mode = 'HTML') {
        // Ensure message length doesn't exceed Telegram limits
        if (strlen($text) > MAX_MESSAGE_LENGTH) {
            $text = substr($text, 0, MAX_MESSAGE_LENGTH - 3) . '...';
        }

        $data = [
            'chat_id' => $chat_id,
            'text' => $text,
            'parse_mode' => $parse_mode
        ];

        if ($reply_markup) {
            $data['reply_markup'] = $reply_markup;
        }

        return $this->apiCall('sendMessage', $data);
    }

    /**
     * Edit message text
     */
    public function editMessage($chat_id, $message_id, $text, $reply_markup = null, $parse_mode = 'HTML') {
        // Ensure message length doesn't exceed Telegram limits
        if (strlen($text) > MAX_MESSAGE_LENGTH) {
            $text = substr($text, 0, MAX_MESSAGE_LENGTH - 3) . '...';
        }

        $data = [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'text' => $text,
            'parse_mode' => $parse_mode
        ];

        if ($reply_markup) {
            $data['reply_markup'] = $reply_markup;
        }

        return $this->apiCall('editMessageText', $data);
    }

    /**
     * Delete message
     */
    public function deleteMessage($chat_id, $message_id) {
        $data = [
            'chat_id' => $chat_id,
            'message_id' => $message_id
        ];

        return $this->apiCall('deleteMessage', $data);
    }

    /**
     * Process incoming update from webhook
     */
    public function processUpdate($update) {
        Logger::log("Processing update: " . json_encode($update));

        if (!isset($update['message'])) {
            Logger::log("No message in update");
            return;
        }

        $message = $update['message'];
        $chat_id = $message['chat']['id'];
        $user_id = $message['from']['id'];
        $username = $message['from']['username'] ?? 'Unknown';
        $text = $message['text'] ?? '';

        Logger::log("Message from @$username (ID: $user_id): $text");

        // Handle document uploads
        if (isset($message['document'])) {
            $this->handleDocument($chat_id, $message['document'], $user_id, $username);
            return;
        }

        // Handle photos
        if (isset($message['photo'])) {
            $this->handlePhoto($chat_id, $message['photo'], $user_id, $username);
            return;
        }

        // Handle commands
        if (strpos($text, '/') === 0) {
            $this->analytics->trackInteraction($user_id, $username, 'command', $text);
            $this->handleCommand($chat_id, $text, $user_id, $username);
        } else {
            // Determine message type based on user mode
            $mode = $this->getUserMode($chat_id);
            $this->analytics->trackInteraction($user_id, $username, $mode, $text);
            $this->handleTextMessage($chat_id, $text, $user_id, $username);
        }
    }

    /**
     * Handle bot commands
     */
    private function handleCommand($chat_id, $command, $user_id, $username) {
        global $BOT_COMMANDS, $ADMIN_USERS;

        // Extract command (remove parameters)
        $cmd = strtolower(trim(explode(' ', $command)[0]));

        switch ($cmd) {
            case '/start':
                $response = "🏛️ <b>Greetings, traveler!</b>\n\n";
                $response .= "I am <b>The Legendary King of Ancient Sri Lanka</b>, here to serve as your guide through my magnificent Lanka and assist in collecting training data for AI.\n\n";
                $response .= "<b>Available commands:</b>\n";
                $response .= "<code>/d</code> - Switch to data collection mode\n";
                $response .= "<code>/q</code> - Switch to question mode\n";
                $response .= "<code>/search [query] [num]</code> - Search Google and collect data\n";
                $response .= "<code>/enhancedsearch [query] | [reference]</code> - Enhanced search with AI comparison\n";
                $response .= "<code>/query [question]</code> - Query collected search data\n";
                $response .= "<code>/searchstats</code> - View search statistics\n";
                $response .= "<code>/status</code> - View bot status and training progress\n";
                $response .= "<code>/data</code> - Download training data file\n";
                $response .= "<code>/bug</code> - Download error log\n";
                $response .= "<code>/url</code> - Get web interface URL\n";
                $response .= "<code>/menu</code> - Show all commands\n\n";
                $response .= "Send me data or questions to help train the AI about my Lanka!";
                break;

            case '/help':
            case '/menu':
                $response = "📋 <b>Commands Available:</b>\n\n";
                $response .= "<code>/d</code> - Data collection mode\n";
                $response .= "<code>/q</code> - Question mode\n";
                $response .= "<code>/search [query] [num]</code> - Search & collect\n";
                $response .= "<code>/enhancedsearch [query] | [reference]</code> - Enhanced search with AI comparison\n";
                $response .= "<code>/query [question]</code> - Query collected data\n";
                $response .= "<code>/searchstats</code> - Search statistics\n";
                $response .= "<code>/status</code> - Bot status\n";
                $response .= "<code>/data</code> - Get training data\n";
                $response .= "<code>/bug</code> - Get error logs\n";
                $response .= "<code>/url</code> - Web interface\n\n";
                $response .= "💡 <b>How to use:</b>\n";
                $response .= "1. Use /d for data collection\n";
                $response .= "2. Use /q for asking questions\n";
                $response .= "3. Send your message and I'll process it!";
                break;

            case '/status':
                $uptime = $this->getUptime();
                $training_count = $this->aiTraining->getTrainingCount();
                $response = "👑 <b>The Legendary King's Status:</b>\n\n";
                $response .= "🟢 Status: Online and Ready\n";
                $response .= "⏰ Uptime: $uptime\n";
                $response .= "📚 Training Data Collected: $training_count entries\n";
                $response .= "🌐 Webhook: " . WEBHOOK_URL . "\n";
                $response .= "👤 Your ID: <code>$user_id</code>\n";
                $response .= "📅 Time: " . date('Y-m-d H:i:s') . " UTC\n\n";
                $response .= "My Lanka's wisdom grows with each contribution!";
                break;

            case '/ping':
                $response = "⚡ The King responds swiftly!\n";
                $response .= "Lanka's power flows strong at " . date('H:i:s');
                break;

            case '/d':
                $this->setUserMode($chat_id, 'data');
                $response = "📊 <b>Data Collection Mode Activated</b>\n\n";
                $response .= "Now send me training data about Sri Lankan tourism, history, or culture.\n";
                $response .= "I will process it as The Legendary King and create structured training data!";
                break;

            case '/q':
                $this->setUserMode($chat_id, 'question');
                $response = "❓ <b>Question Mode Activated</b>\n\n";
                $response .= "Ask me anything about my Lanka's attractions, history, or culture.\n";
                $response .= "I shall respond as The Legendary King and generate training data!";
                break;

            case '/data':
                $this->sendTrainingDataFile($chat_id);
                return;

            case '/bug':
                $this->sendBugReport($chat_id);
                return;

            case '/url':
                $base_url = BASE_URL;
                $response = "🌐 <b>Web Interface URL:</b>\n\n";
                $response .= "<a href='$base_url'>$base_url</a>\n\n";
                $response .= "Access the web interface to interact with the training system!";
                break;

            case '/search':
                if ($this->searchCommands) {
                    $this->searchCommands->handleSearchCommand($chat_id, $command, $this);
                } else {
                    $this->sendMessage($chat_id, "❌ Search functionality is currently unavailable. Please try again later.");
                }
                return;

            case '/query':
                if ($this->searchCommands) {
                    $this->searchCommands->handleQueryCommand($chat_id, $command, $this);
                } else {
                    $this->sendMessage($chat_id, "❌ Query functionality is currently unavailable. Please try again later.");
                }
                return;
            case '/enhancedsearch':
                if ($this->searchCommands) {
                    $this->searchCommands->handleEnhancedSearchCommand($chat_id, $command, $this);
                } else {
                    $this->sendMessage($chat_id, "❌ Enhanced Search functionality is currently unavailable. Please try again later.");
                }
                return;

            case '/searchstats':
                if ($this->searchCommands) {
                    $this->searchCommands->handleStatsCommand($chat_id, $this);
                } else {
                    $this->sendMessage($chat_id, "❌ Statistics functionality is currently unavailable. Please try again later.");
                }
                return;

            default:
                $response = "❓ Unknown command: <code>$command</code>\n\n";
                $response .= "Use /help to see available commands, traveler.";
                break;
        }

        $this->sendMessage($chat_id, $response);
    }

    /**
     * Handle regular text messages
     */
    private function handleTextMessage($chat_id, $text, $user_id, $username) {
        Logger::log("Processing text message from @$username: $text");

        // Get user mode (data or question)
        $mode = $this->getUserMode($chat_id);

        // Send processing message and store its ID
        $processing_msg = $this->sendMessage($chat_id, "⏳ <b>Processing your " . ($mode == 'data' ? 'data' : 'question') . "...</b>\n\nThe Legendary King is analyzing your input...");
        $processing_msg_id = $processing_msg['result']['message_id'] ?? null;

        // Process with AI training
        $result = $this->aiTraining->processTrainingData($text, $mode);

        if ($result) {
            // Save the training data
            if ($this->aiTraining->saveTrainingData($result)) {
                // Parse the result to show user
                $jsonObject = json_decode($result, true);

                $response = "✅ <b>Training data processed successfully!</b>\n\n";

                if ($jsonObject && isset($jsonObject['prompt']) && isset($jsonObject['response'])) {
                    $response .= "📝 <b>Generated Prompt:</b>\n";
                    $response .= htmlspecialchars(substr($jsonObject['prompt'], 0, 200)) . "...\n\n";

                    $response .= "🏛️ <b>Ravana's Response:</b>\n";
                    $response .= htmlspecialchars(substr($jsonObject['response'], 0, 300)) . "...\n\n";
                }

                $training_count = $this->aiTraining->getTrainingCount();
                $response .= "📊 <b>Total training entries:</b> $training_count\n\n";
                $response .= "Send more quality data to help train the AI about my Lanka!";

                // Send detailed results in separate messages
                if ($jsonObject && isset($jsonObject['prompt']) && isset($jsonObject['response'])) {
                    $this->sendMessage($chat_id, "📝 <b>Full Generated Prompt:</b>\n<code>" . htmlspecialchars($jsonObject['prompt']) . "</code>");
                    $this->sendMessage($chat_id, "🏛️ <b>Full Ravana's Response:</b>\n<code>" . htmlspecialchars($jsonObject['response']) . "</code>");
                }
            } else {
                $response = "❌ <b>Failed to save training data</b>\n\n";
                $response .= "Please try again or contact support.";
            }
        } else {
            $error_msg = $this->aiTraining->logError($text);
            $response = "⚠️ <b>Processing Error</b>\n\n";
            $response .= $error_msg;
        }

        // Edit the processing message with final result
        if ($processing_msg_id) {
            $this->editMessage($chat_id, $processing_msg_id, $response);
        } else {
            $this->sendMessage($chat_id, $response);
        }
    }

    /**
     * Get bot uptime (simplified)
     */
    private function getUptime() {
        // Since we can't track actual uptime in stateless environment,
        // we'll show session uptime or a generic message
        $start_time = $_SERVER['REQUEST_TIME'] ?? time();
        $uptime_seconds = time() - $start_time;

        if ($uptime_seconds < 60) {
            return "Less than a minute";
        } elseif ($uptime_seconds < 3600) {
            return floor($uptime_seconds / 60) . " minutes";
        } else {
            return floor($uptime_seconds / 3600) . " hours";
        }
    }

    /**
     * Get bot information
     */
    public function getMe() {
        return $this->apiCall('getMe');
    }

    /**
     * Set user mode (data or question)
     */
    private function setUserMode($chat_id, $mode) {
        $file = $chat_id . '_mode.txt';
        file_put_contents($file, $mode);
        Logger::log("User mode set to '$mode' for chat $chat_id");
    }

    /**
     * Get user mode
     */
    private function getUserMode($chat_id) {
        $file = $chat_id . '_mode.txt';
        if (file_exists($file)) {
            return file_get_contents($file);
        }
        return 'data'; // Default mode
    }

    /**
     * Send training data file
     */
    private function sendTrainingDataFile($chat_id) {
        $file_path = 'train.json';

        if (!file_exists($file_path)) {
            $this->sendMessage($chat_id, "❌ <b>No training data available yet</b>\n\nStart sending data to create the training file!");
            return;
        }

        $this->sendMessage($chat_id, "📤 <b>Preparing training data file...</b>");

        // Send document
        $result = $this->sendDocument($chat_id, $file_path);

        if ($result['ok']) {
            $this->sendMessage($chat_id, "✅ <b>Training data file sent successfully!</b>");
        } else {
            $this->sendMessage($chat_id, "❌ <b>Failed to send training data file</b>\n\nPlease try again later.");
        }
    }

    /**
     * Send bug report file
     */
    private function sendBugReport($chat_id) {
        $file_path = 'bug.txt';

        if (!file_exists($file_path)) {
            $this->sendMessage($chat_id, "✅ <b>No bugs reported yet!</b>\n\nThe system is running smoothly.");
            return;
        }

        $this->sendMessage($chat_id, "📤 <b>Preparing bug report...</b>");

        // Send document
        $result = $this->sendDocument($chat_id, $file_path);

        if ($result['ok']) {
            $this->sendMessage($chat_id, "✅ <b>Bug report sent successfully!</b>");
        } else {
            $this->sendMessage($chat_id, "❌ <b>Failed to send bug report</b>\n\nPlease try again later.");
        }
    }

    /**
     * Send document to chat
     */
    public function sendDocument($chat_id, $file_path) {
        if (!file_exists($file_path)) {
            return ['ok' => false, 'error' => 'File not found'];
        }

        $url = $this->api_url . 'sendDocument';

        $mime_type = 'application/octet-stream';
        if (function_exists('mime_content_type')) {
            $mime_type = mime_content_type($file_path);
        }

        $data = [
            'chat_id' => $chat_id,
            'document' => new CURLFile(realpath($file_path), $mime_type, basename($file_path))
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            Logger::log("cURL error for sendDocument: $error");
            return ['ok' => false, 'error' => $error];
        }

        $decoded = json_decode($response, true);

        if ($http_code !== 200) {
            Logger::log("API error for sendDocument: HTTP $http_code - " . $response);
            return ['ok' => false, 'error' => "HTTP $http_code", 'response' => $response];
        }

        return $decoded;
    }

    /**
     * Handle document uploads
     */
    private function handleDocument($chat_id, $document, $user_id, $username) {
        $file_id = $document['file_id'];

        // Check if this document is already being processed
        $processing_lock = "processing_" . $file_id . ".lock";
        if (file_exists($processing_lock)) {
            Logger::log("Document $file_id already being processed, skipping");
            return;
        }

        // Create processing lock
        file_put_contents($processing_lock, time());

        // Send initial processing message
        $processing_msg = $this->sendMessage($chat_id, "📄 <b>Processing document...</b>\n\nThe Legendary King is analyzing your file...");
        $processing_msg_id = $processing_msg['result']['message_id'] ?? null;

        // Process the document
        $result = $this->multimedia->handleDocument($this, $chat_id, $document);

        if ($result['success']) {
            $response = "✅ <b>Document processed successfully!</b>\n\n";
            $response .= $result['message'];

            // Log the successful processing
            Logger::log("Document processed successfully for user @$username: " . $document['file_name']);
        } else {
            $response = "❌ <b>Document processing failed</b>\n\n";
            $response .= $result['message'];

            // Log the error
            Logger::error("Document processing failed for user @$username: " . $result['message']);
        }

        // Edit the processing message with the final result
        if ($processing_msg_id) {
            $this->editMessage($chat_id, $processing_msg_id, $response);
        } else {
            $this->sendMessage($chat_id, $response);
        }

        // Clean up processing lock
        if (file_exists($processing_lock)) {
            unlink($processing_lock);
        }
    }

    /**
     * Handle photo uploads
     */
    private function handlePhoto($chat_id, $photos, $user_id, $username) {
        $this->sendMessage($chat_id, "🖼️ <b>Image received!</b>\n\nThe Legendary King acknowledges your visual offering. Image processing capabilities will be enhanced in future updates to extract tourism information from photos.");
    }

    /**
     * Get analytics data
     */
    public function getAnalytics() {
        return $this->analytics->getUserStats();
    }

    /**
     * Get multimedia statistics
     */
    public function getMultimediaStats() {
        return $this->multimedia->getUploadStats();
    }
}
?>