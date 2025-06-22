<?php
/**
 * Search Commands Handler
 * Handles Google search and data collection commands
 */

require_once 'logger.php';

class SearchCommands {

    private $googleAPI;

    public function __construct() {
        // Initialize with error handling
        try {
            if (file_exists('google_search_api.php')) {
                require_once 'google_search_api.php';
                if (class_exists('GoogleSearchAPI')) {
                    $this->googleAPI = new GoogleSearchAPI();
                } else {
                    $this->googleAPI = null;
                    Logger::log("GoogleSearchAPI class not found after including file");
                }
            } else {
                $this->googleAPI = null;
                Logger::log("google_search_api.php file not found, search functionality limited");
            }
        } catch (Exception $e) {
            $this->googleAPI = null;
            Logger::error("Failed to initialize GoogleSearchAPI: " . $e->getMessage());
        }
    }

    /**
     * Handle search command with real-time status updates
     */
    public function handleSearchCommand($chat_id, $command, $bot) {
        // Parse command: /search query num_results
        $command_parts = explode(' ', trim($command));

        if (count($command_parts) < 3) {
            $bot->sendMessage($chat_id, 
                "❌ <b>Invalid search command</b>\n\n" .
                "<b>Usage:</b>\n" .
                "<code>/search [query] [number of results]</code>\n\n" .
                "<b>Example:</b>\n" .
                "<code>/search Sri Lanka tourism 10</code>"
            );
            return;
        }

        // Get the number of results (last parameter)
        $last_part = end($command_parts);
        $num_results = intval($last_part);

        // Validate that last part is actually a number
        if ($num_results == 0 && $last_part !== '0') {
            $bot->sendMessage($chat_id, 
                "❌ <b>Invalid number of results</b>\n\n" .
                "The last parameter must be a number between 1 and 50."
            );
            return;
        }

        // Get the query (everything between /search and the last number)
        array_shift($command_parts); // Remove /search
        array_pop($command_parts);   // Remove num_results
        $query = implode(' ', $command_parts);

        // Validate parameters
        if (empty(trim($query))) {
            $bot->sendMessage($chat_id, "❌ Please provide a search query");
            return;
        }

        if ($num_results < 1 || $num_results > 50) {
            $bot->sendMessage($chat_id, "❌ Number of results must be between 1 and 50");
            return;
        }

        // Send initial processing message
        $processing_msg = $bot->sendMessage($chat_id, 
            "🔍 <b>Complete Search Workflow Starting...</b>\n\n" .
            "Query: <code>$query</code>\n" .
            "Results to process: $num_results\n\n" .
            "Progress: ░░░░░░░░░░ 0%\n" .
            "Step 0/8: Initializing workflow...\n\n" .
            "<b>8-Step Complete Workflow:</b>\n" .
            "⏸️ Step 1: Search Google and get URLs with titles\n" .
            "⏸️ Step 2: AI comparison to check title relevance\n" .
            "⏸️ Step 3: Fetch actual content from URLs\n" .
            "⏸️ Step 4: Extract text from HTML (remove HTML code)\n" .
            "⏸️ Step 5: Split content into 100+ word chunks\n" .
            "⏸️ Step 6: Send chunks to AI training\n" .
            "⏸️ Step 7: Save processed data to train.json\n" .
            "⏸️ Step 8: Real-time status updates via Telegram"
        );

        $processing_msg_id = $processing_msg['result']['message_id'] ?? null;

        // Perform search and collection with real-time updates
        if ($this->googleAPI) {
            $result = $this->googleAPI->searchAndCollect($query, $num_results, $bot, $chat_id, $processing_msg_id);
        } else {
            $result = ['success' => false, 'error' => 'Search functionality not available'];
        }

        if ($result['success']) {
            $response = "🎉 <b>Complete Search Workflow Finished Successfully!</b>\n\n";
            $response .= "🔍 <b>Query:</b> <code>$query</code>\n";
            $response .= "📊 <b>Search Results Found:</b> {$result['results_found']}\n";
            $response .= "✅ <b>URLs Successfully Processed:</b> {$result['processed']}\n";

            if ($result['failed'] > 0) {
                $response .= "❌ <b>URLs Failed:</b> {$result['failed']}\n";
            }

            if (isset($result['training_entries_added'])) {
                $response .= "🎓 <b>Training Entries Added:</b> {$result['training_entries_added']}\n";
            }

            $response .= "📈 <b>Total Training Entries:</b> {$result['total_training_entries']}\n\n";
            
            $response .= "✅ <b>All 8 Steps Completed:</b>\n";
            $response .= "1️⃣ Google search performed\n";
            $response .= "2️⃣ AI title comparison completed\n";
            $response .= "3️⃣ URL content fetched\n";
            $response .= "4️⃣ Text extracted from HTML (HTML code removed)\n";
            $response .= "5️⃣ Content split into 100+ word chunks\n";
            $response .= "6️⃣ AI training data generated\n";
            $response .= "7️⃣ Data saved to train.json\n";
            $response .= "8️⃣ Real-time status updates provided\n\n";
            $response .= "🎯 The Legendary King has successfully processed all data and enriched the AI training dataset!";
        } else {
            $response = "❌ <b>Search Workflow Failed</b>\n\n";
            $response .= "Error: " . ($result['error'] ?? 'Unknown error') . "\n";
            if (isset($result['step'])) {
                $response .= "Failed at step: " . $result['step'] . "\n";
            }
            $response .= "\nPlease try again with a different query.";
        }

        // Edit the processing message with final results
        if ($processing_msg_id) {
            $bot->editMessage($chat_id, $processing_msg_id, $response);
        } else {
            $bot->sendMessage($chat_id, $response);
        }
    }

    /**
     * Handle query command
     */
    public function handleQueryCommand($chat_id, $command, $bot) {
        $parts = explode(' ', $command, 2);

        if (count($parts) < 2) {
            $bot->sendMessage($chat_id, 
                "❌ <b>Invalid query command</b>\n\n" .
                "<b>Usage:</b>\n" .
                "<code>/query [your question]</code>\n\n" .
                "<b>Example:</b>\n" .
                "<code>/query What are the best beaches in Sri Lanka?</code>"
            );
            return;
        }

        $query = $parts[1];

        // Send processing message
        $processing_msg = $bot->sendMessage($chat_id, 
            "🔍 <b>Searching collected data...</b>\n\n" .
            "Query: <code>$query</code>\n\n" .
            "⏳ The Legendary King is consulting his vast knowledge..."
        );

        $processing_msg_id = $processing_msg['result']['message_id'] ?? null;

        // Query the processed data
        if ($this->googleAPI) {
            $result = $this->googleAPI->queryData($query);
        } else {
            $result = ['success' => false, 'error' => 'Query functionality not available'];
        }

        if ($result['success']) {
            $response = "🎯 <b>Query Results</b>\n\n";
            $response .= "❓ <b>Your Query:</b> <code>$query</code>\n";
            $response .= "📊 <b>Entries Searched:</b> {$result['total_entries_searched']}\n";
            $response .= "✅ <b>Relevant Results:</b> " . count($result['relevant_results']) . "\n\n";

            if (count($result['relevant_results']) > 0) {
                $response .= "<b>🔍 Top Results:</b>\n\n";

                foreach (array_slice($result['relevant_results'], 0, 5) as $i => $entry) {
                    $num = $i + 1;
                    $score = round($entry['relevance_score'] * 100, 1);
                    $response .= "<b>$num.</b> ";
                    $response .= "<b>Relevance:</b> {$score}%\n";
                    $response .= "<b>Source:</b> " . htmlspecialchars($entry['source_title']) . "\n";

                    if (isset($entry['processed_data']['response'])) {
                        $answer = $entry['processed_data']['response'];
                        if (is_array($answer)) {
                            $answer = json_encode($answer);
                        } elseif (!is_string($answer)) {
                            $answer = (string)$answer;
                        }

                        // Ensure $answer is a string before using substr
                        if (is_string($answer) && !empty($answer)) {
                            $truncated_answer = strlen($answer) > 200 ? substr($answer, 0, 200) . "..." : $answer;
                            $response .= "<b>Answer:</b> " . htmlspecialchars($truncated_answer) . "\n";
                        } else {
                            $response .= "<b>Answer:</b> Data available but not in text format\n";
                        }
                    }

                    $response .= "\n";
                }
            } else {
                $response .= "❌ No relevant results found for your query.\n\n";
                $response .= "Try using different keywords or collect more data first.";
            }
        } else {
            $response = "❌ <b>Query Failed</b>\n\n";
            $response .= "Error: " . ($result['error'] ?? 'Unknown error') . "\n\n";
            $response .= "Make sure you have collected some data first using the /search command.";
        }

        // Edit the processing message with results
        if ($processing_msg_id) {
            $bot->editMessage($chat_id, $processing_msg_id, $response);
        } else {
            $bot->sendMessage($chat_id, $response);
        }
    }

    /**
     * Handle enhanced search command
     */
    public function handleEnhancedSearchCommand($chat_id, $command, $bot) {
        try {
            // Parse command: /enhancedsearch [query] | [reference_data]
            $parts = explode('|', str_replace('/enhancedsearch ', '', $command), 2);

            if (count($parts) < 2) {
                $bot->sendMessage($chat_id, 
                    "❌ <b>Invalid enhanced search command</b>\n\n" .
                    "<b>Usage:</b>\n" .
                    "<code>/enhancedsearch [query] | [reference data]</code>\n\n" .
                    "<b>Example:</b>\n" .
                    "<code>/enhancedsearch ravana sri lanka | Legendary King of Ancient Sri Lanka</code>"
                );
                return;
            }

            $query = trim($parts[0]);
            $referenceData = trim($parts[1]);

            if (empty($query) || empty($referenceData)) {
                $bot->sendMessage($chat_id, "❌ Both query and reference data are required");
                return;
            }

            // Send processing message
            $processing_msg = $bot->sendMessage($chat_id, 
                "🔍 <b>Enhanced Search Starting...</b>\n\n" .
                "🔎 <b>Query:</b> <code>$query</code>\n" .
                "📊 <b>Reference:</b> <code>" . substr($referenceData, 0, 100) . "...</code>\n\n" .
                "⏳ The Legendary King is searching the web and collecting data...\n" .
                "⚡ This process includes:\n" .
                "• Web search across multiple pages\n" .
                "• Content analysis and processing\n" .
                "• Training data generation\n\n" .
                "⏰ Please wait, this may take a few minutes..."
            );

            $processing_msg_id = $processing_msg['result']['message_id'] ?? null;

            // Use regular Google API for enhanced search
            if ($this->googleAPI) {
                $result = $this->googleAPI->searchAndCollect($query, 10);
            } else {
                $result = ['success' => false, 'error' => 'Search functionality not available'];
            }

            if ($result['success']) {
                $response = "✅ <b>Enhanced Search Complete!</b>\n\n";
                $response .= "🔍 <b>Query:</b> <code>$query</code>\n";
                $response .= "📊 <b>Results Found:</b> {$result['results_found']}\n";
                $response .= "✅ <b>Successfully Processed:</b> {$result['processed']}\n";
                
                if ($result['failed'] > 0) {
                    $response .= "❌ <b>Failed to Process:</b> {$result['failed']}\n";
                }
                
                $response .= "📈 <b>Total Training Entries:</b> {$result['total_training_entries']}\n\n";
                $response .= "🎉 The Legendary King has successfully gathered wisdom from the web!\n\n";
                $response .= "📝 <b>Reference Context:</b> " . substr($referenceData, 0, 200) . "...";
            } else {
                $response = "❌ <b>Enhanced Search Failed</b>\n\n";
                $response .= "Error: " . ($result['error'] ?? 'Unknown error') . "\n\n";
                $response .= "Please try again with a different query.";
            }

            // Edit the processing message with results
            if ($processing_msg_id) {
                $bot->editMessage($chat_id, $processing_msg_id, $response);
            } else {
                $bot->sendMessage($chat_id, $response);
            }

        } catch (Exception $e) {
            Logger::error("Enhanced search command error: " . $e->getMessage());
            $bot->sendMessage($chat_id, "❌ <b>Enhanced Search Error</b>\n\nThere was an error processing your request. Please try again.");
        }
    }

    /**
     * Handle stats command
     */
    public function handleStatsCommand($chat_id, $bot) {
        if ($this->googleAPI) {
            $stats = $this->googleAPI->getStats();
        } else {
            $stats = [
                'processed_search_entries' => 0,
                'training_data_entries' => 0,
                'data_file_size' => 0
            ];
        }

        $response = "📊 <b>Search & Data Collection Statistics</b>\n\n";
        $response .= "🔍 <b>Processed Search Entries:</b> {$stats['processed_search_entries']}\n";
        $response .= "🎓 <b>Training Data Entries:</b> {$stats['training_data_entries']}\n";
        $response .= "💾 <b>Data File Size:</b> " . $this->formatBytes($stats['data_file_size']) . "\n\n";
        $response .= "📈 <b>Available Commands:</b>\n";
        $response .= "<code>/search [query] [num]</code> - Search and collect data\n";
        $response .= "<code>/query [question]</code> - Query collected data\n";
        $response .= "<code>/searchstats</code> - Show these statistics";

        $bot->sendMessage($chat_id, $response);
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB');

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
?>