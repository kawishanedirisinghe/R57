
<?php
/**
 * Complete Google Search API Handler with Full Workflow
 * Implements all 8 steps of the search workflow
 */

require_once 'ai_training.php';
require_once 'logger.php';
require_once 'ai_api.php';

class GoogleSearchAPI {
    
    private $aiTraining;
    private $aiAPI;
    private $searchDataFile;
    private $userAgent;
    private $cacheDir;
    private $start_time;
    private $advanced_stats;
    
    public function __construct() {
        $this->aiTraining = new AITraining();
        $this->aiAPI = new AiAPI();
        $this->searchDataFile = 'search_data.json';
        $this->userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
        $this->cacheDir = './cache/';
        
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }
    
    /**
     * Complete Search Workflow Implementation
     * Step 1-8: Search, Compare, Extract, Process, Save, Update Status
     */
    public function searchAndCollect($query, $num_results = 10, $bot = null, $chat_id = null, $processing_msg_id = null) {
        Logger::log("Starting complete search workflow for query: $query, results: $num_results");
        
        // Initialize tracking
        $this->start_time = time();
        $this->advanced_stats = [
            'total_urls' => 0,
            'processed_urls' => 0,
            'failed_urls' => 0,
            'successful_urls' => 0,
            'chunks_processed' => 0,
            'json_entries_added' => 0,
            'current_url' => ''
        ];
        
        try {
            $results = [
                'success' => true,
                'search_query' => $query,
                'results_found' => 0,
                'processed' => 0,
                'failed' => 0,
                'training_entries_added' => 0,
                'total_training_entries' => 0,
                'steps_completed' => []
            ];
            
            // Step 1: Search Google and get URLs with titles
            $this->updateStatus($bot, $chat_id, $processing_msg_id, "Searching Google for results...", 1, 8, $this->advanced_stats);
            $search_results = $this->performWebSearch($query, $num_results);
            
            if (empty($search_results)) {
                throw new Exception("No search results found for query: $query");
            }
            
            $results['results_found'] = count($search_results);
            $this->advanced_stats['total_urls'] = count($search_results);
            $results['steps_completed'][] = "Step 1: Found " . count($search_results) . " search results";
            
            // Step 2: Check if titles are related using AI
            $this->updateStatus($bot, $chat_id, $processing_msg_id, "AI comparing titles with reference data...", 2, 8, $this->advanced_stats);
            $processed_count = 0;
            $failed_count = 0;
            $training_added = 0;
            
            foreach ($search_results as $index => $result) {
                $this->advanced_stats['current_url'] = $result['url'];
                try {
                    $this->advanced_stats['processed_urls'] = $index;
                    
                    // Step 2: AI title comparison
                    $isRelated = $this->aiAPI->compareTitles($result['title'], $query);
                    
                    if (!$isRelated) {
                        Logger::log("Title not related to query, skipping: " . $result['title']);
                        $failed_count++;
                        $this->advanced_stats['failed_urls']++;
                        continue;
                    }
                    
                    // Step 3: Fetch actual content from URL
                    $this->updateStatus($bot, $chat_id, $processing_msg_id, "Fetching content from URL " . ($index + 1) . "...", 3, 8, $this->advanced_stats);
                    $content = $this->fetchUrlContent($result['url']);
                    
                    if (!$content) {
                        Logger::log("Failed to fetch content from: " . $result['url']);
                        $failed_count++;
                        $this->advanced_stats['failed_urls']++;
                        continue;
                    }
                    
                    // Step 4: Extract text from HTML
                    $this->updateStatus($bot, $chat_id, $processing_msg_id, "Extracting clean text from HTML...", 4, 8, $this->advanced_stats);
                    $clean_text = $this->extractTextFromHtml($content);
                    
                    if (!$clean_text) {
                        Logger::log("Failed to extract text from: " . $result['url']);
                        $failed_count++;
                        $this->advanced_stats['failed_urls']++;
                        continue;
                    }
                    
                    // Step 5: Split into 100+ word chunks
                    $this->updateStatus($bot, $chat_id, $processing_msg_id, "Splitting content into 100+ word chunks...", 5, 8, $this->advanced_stats);
                    $chunks = $this->splitIntoWordChunks($clean_text, 100);
                    
                    if (empty($chunks)) {
                        Logger::log("No valid chunks extracted from: " . $result['url']);
                        $failed_count++;
                        $this->advanced_stats['failed_urls']++;
                        continue;
                    }
                    
                    $this->advanced_stats['chunks_processed'] += count($chunks);
                    
                    // Step 6: Send to AI training
                    $this->updateStatus($bot, $chat_id, $processing_msg_id, "Processing " . count($chunks) . " chunks with AI training...", 6, 8, $this->advanced_stats);
                    foreach ($chunks as $chunk_index => $chunk) {
                        $training_data = $this->generateTrainingDataFromChunk($chunk, $query, $result['title'], $result['url']);
                        
                        if ($training_data) {
                            // Step 7: Save to train.json
                            if ($this->aiTraining->saveTrainingData($training_data)) {
                                $training_added++;
                                $this->advanced_stats['json_entries_added']++;
                            }
                        }
                        
                        // Update progress for chunks
                        if ($chunk_index % 5 == 0) { // Update every 5 chunks to avoid spam
                            $this->updateStatus($bot, $chat_id, $processing_msg_id, 
                                "Processing chunk " . ($chunk_index + 1) . "/" . count($chunks) . " from URL " . ($index + 1), 6, 8, $this->advanced_stats);
                        }
                    }
                    
                    $processed_count++;
                    $this->advanced_stats['successful_urls']++;
                    
                    // Step 8: Update status in real-time
                    $this->updateStatus($bot, $chat_id, $processing_msg_id, 
                        "Successfully processed URL " . ($index + 1) . "/" . count($search_results), 7, 8, $this->advanced_stats);
                    
                    // Add delay to avoid rate limiting
                    usleep(500000); // 0.5 second delay
                    
                } catch (Exception $e) {
                    Logger::error("Failed to process search result: " . $e->getMessage());
                    $failed_count++;
                    $this->advanced_stats['failed_urls']++;
                }
            }
            
            // Final results
            $results['processed'] = $processed_count;
            $results['failed'] = $failed_count;
            $results['training_entries_added'] = $training_added;
            $results['total_training_entries'] = $this->aiTraining->getTrainingCount();
            $results['steps_completed'][] = "Step 8: Workflow completed successfully";
            
            // Final status update with completion summary
            $total_time = time() - $this->start_time;
            $completion_time = gmdate("i:s", $total_time);
            $this->advanced_stats['current_url'] = 'WORKFLOW COMPLETED ✅';
            $this->updateStatus($bot, $chat_id, $processing_msg_id, "🎉 All workflows completed in $completion_time!", 8, 8, $this->advanced_stats);
            
            Logger::log("Search workflow completed. Processed: $processed_count, Failed: $failed_count, Training entries added: $training_added");
            
            return $results;
            
        } catch (Exception $e) {
            Logger::error("Search workflow error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'step' => 'general'
            ];
        }
    }
    
    /**
     * Update status via Telegram edit message (Step 8) - Advanced Version
     */
    private function updateStatus($bot, $chat_id, $processing_msg_id, $status, $current_step, $total_steps, $advanced_data = []) {
        if (!$bot || !$chat_id || !$processing_msg_id) {
            return;
        }
        
        // Calculate progress and timing
        $progress = round(($current_step / $total_steps) * 100);
        $progress_bar = str_repeat("█", floor($progress / 10)) . str_repeat("░", 10 - floor($progress / 10));
        
        // Get current time and calculate elapsed time
        $current_time = time();
        if (!isset($this->start_time)) {
            $this->start_time = $current_time;
        }
        $elapsed_time = $current_time - $this->start_time;
        $elapsed_formatted = gmdate("i:s", $elapsed_time);
        
        // Estimate time remaining
        if ($progress > 0) {
            $estimated_total = ($elapsed_time / $progress) * 100;
            $time_remaining = max(0, $estimated_total - $elapsed_time);
            $remaining_formatted = gmdate("i:s", $time_remaining);
        } else {
            $remaining_formatted = "calculating...";
        }
        
        // Get advanced statistics
        $total_urls = $advanced_data['total_urls'] ?? 0;
        $processed_urls = $advanced_data['processed_urls'] ?? 0;
        $failed_urls = $advanced_data['failed_urls'] ?? 0;
        $successful_urls = $advanced_data['successful_urls'] ?? 0;
        $chunks_processed = $advanced_data['chunks_processed'] ?? 0;
        $json_entries_added = $advanced_data['json_entries_added'] ?? 0;
        $current_url = $advanced_data['current_url'] ?? '';
        $memory_usage = round(memory_get_usage(true) / 1024 / 1024, 2);
        
        // Build advanced status message
        $message = "🔍 <b>Advanced Search Workflow Progress</b>\n\n";
        
        // Progress section
        $message .= "📊 <b>Progress Overview</b>\n";
        $message .= "Progress: $progress_bar $progress%\n";
        $message .= "Step $current_step/$total_steps: $status\n";
        $message .= "⏰ Elapsed: $elapsed_formatted | Remaining: ~$remaining_formatted\n";
        $message .= "🕒 Started: " . date('H:i:s', $this->start_time) . " | Current: " . date('H:i:s') . "\n\n";
        
        // URL Processing Statistics
        $message .= "🌐 <b>URL Processing Stats</b>\n";
        $message .= "📋 Total URLs Found: $total_urls\n";
        $message .= "✅ Successfully Processed: $successful_urls\n";
        $message .= "⚠️ Failed URLs: $failed_urls\n";
        $message .= "🔄 Currently Processing: " . ($processed_urls + 1) . "/$total_urls\n";
        if (!empty($current_url)) {
            $message .= "🔗 Current URL: " . (strlen($current_url) > 50 ? substr($current_url, 0, 47) . "..." : $current_url) . "\n";
        }
        $message .= "\n";
        
        // Data Processing Statistics
        $message .= "📈 <b>Data Processing Stats</b>\n";
        $message .= "🧩 Text Chunks Processed: $chunks_processed\n";
        $message .= "📝 JSON Entries Added: $json_entries_added\n";
        $message .= "💾 Current Training Count: " . $this->aiTraining->getTrainingCount() . "\n";
        $message .= "🔧 Memory Usage: {$memory_usage}MB\n\n";
        
        // Success Rate
        if ($total_urls > 0) {
            $success_rate = round(($successful_urls / $total_urls) * 100, 1);
            $message .= "📊 <b>Success Rate: {$success_rate}%</b>\n\n";
        }
        
        // Step Details
        $message .= "📋 <b>Workflow Steps</b>\n";
        for ($i = 1; $i <= $total_steps; $i++) {
            $icon = $i < $current_step ? "✅" : ($i == $current_step ? "⏳" : "⏸️");
            $step_desc = $this->getStepDescription($i);
            
            // Add extra details for current step
            if ($i == $current_step) {
                switch ($current_step) {
                    case 3:
                        $step_desc .= " ($processed_urls/$total_urls)";
                        break;
                    case 5:
                        $step_desc .= " ($chunks_processed chunks)";
                        break;
                    case 6:
                        $step_desc .= " ($json_entries_added entries)";
                        break;
                }
            }
            
            $message .= "$icon Step $i: $step_desc\n";
        }
        
        // Performance indicators
        if ($current_step > 1) {
            $message .= "\n🚀 <b>Performance</b>\n";
            if ($elapsed_time > 0) {
                $urls_per_minute = round(($processed_urls / $elapsed_time) * 60, 2);
                $chunks_per_minute = round(($chunks_processed / $elapsed_time) * 60, 2);
                $message .= "⚡ URLs/min: $urls_per_minute | Chunks/min: $chunks_per_minute\n";
            }
        }
        
        $bot->editMessage($chat_id, $processing_msg_id, $message);
    }
    
    /**
     * Get step description for status updates
     */
    private function getStepDescription($step) {
        $descriptions = [
            1 => "Search Google and get URLs with titles",
            2 => "AI comparison to check title relevance",
            3 => "Fetch actual content from URLs",
            4 => "Extract text from HTML (remove HTML code)",
            5 => "Split content into 100+ word chunks",
            6 => "Send chunks to AI training",
            7 => "Save processed data to train.json",
            8 => "Real-time status updates via Telegram"
        ];
        
        return $descriptions[$step] ?? "Unknown step";
    }
    
    /**
     * Perform web search using multiple search engines as fallback
     */
    private function performWebSearch($query, $num_results = 10) {
        Logger::log("Performing web search for: $query");
        
        $allResults = [];
        $seenUrls = [];
        
        // Try Brave Search first
        $allResults = $this->searchBrave($query, $num_results, $seenUrls);
        
        // If Brave Search fails, try DuckDuckGo
        if (empty($allResults)) {
            Logger::log("Brave Search failed, trying DuckDuckGo");
            $allResults = $this->searchDuckDuckGo($query, $num_results, $seenUrls);
        }
        
        // If still no results, try Bing
        if (empty($allResults)) {
            Logger::log("DuckDuckGo failed, trying Bing");
            $allResults = $this->searchBing($query, $num_results, $seenUrls);
        }
        
        // If still no results, create mock results for demonstration
        if (empty($allResults)) {
            Logger::log("All search engines failed, creating mock results for query: $query");
            $allResults = $this->createMockResults($query);
        }
        
        return array_slice($allResults, 0, $num_results);
    }
    
    /**
     * Search using Brave Search
     */
    private function searchBrave($query, $num_results, &$seenUrls) {
        $allResults = [];
        $offsets = [0, 1, 2, 3, 4]; // Search multiple pages
        
        foreach ($offsets as $offset) {
            if (count($allResults) >= $num_results) {
                break;
            }
            
            $url = 'https://search.brave.com/search?q=' . urlencode($query) . '&source=web&offset=' . $offset;
            
            $response = $this->makeWebRequest($url);
            
            if ($response === false) {
                Logger::log("Failed to fetch Brave search results for offset: $offset");
                continue;
            }
            
            $results = $this->parseSearchResults($response, $offset, $seenUrls);
            $allResults = array_merge($allResults, $results);
            
            // Add delay between requests
            sleep(1);
        }
        
        return $allResults;
    }
    
    /**
     * Search using DuckDuckGo
     */
    private function searchDuckDuckGo($query, $num_results, &$seenUrls) {
        $allResults = [];
        
        $url = 'https://html.duckduckgo.com/html/?q=' . urlencode($query);
        
        $response = $this->makeWebRequest($url);
        
        if ($response === false) {
            return [];
        }
        
        $results = $this->parseDuckDuckGoResults($response, $seenUrls);
        return array_slice($results, 0, $num_results);
    }
    
    /**
     * Search using Bing
     */
    private function searchBing($query, $num_results, &$seenUrls) {
        $allResults = [];
        
        $url = 'https://www.bing.com/search?q=' . urlencode($query);
        
        $response = $this->makeWebRequest($url);
        
        if ($response === false) {
            return [];
        }
        
        $results = $this->parseBingResults($response, $seenUrls);
        return array_slice($results, 0, $num_results);
    }
    
    /**
     * Make web request with proper headers
     */
    private function makeWebRequest($url) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9',
                'Accept-Language: en-US,en;q=0.5',
                'Accept-Encoding: gzip, deflate',
                'DNT: 1',
                'Connection: keep-alive',
                'Upgrade-Insecure-Requests: 1'
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_ENCODING => 'gzip, deflate'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response === false || $httpCode != 200) {
            Logger::log("Failed to fetch URL: $url (HTTP: $httpCode)");
            return false;
        }
        
        return $response;
    }
    
    /**
     * Parse DuckDuckGo search results
     */
    private function parseDuckDuckGoResults($html, &$seenUrls) {
        $results = [];
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML($html);
        libxml_clear_errors();
        
        $xpath = new DOMXPath($doc);
        $elements = $xpath->query("//div[contains(@class, 'result')]");
        
        foreach ($elements as $element) {
            $titleNode = $xpath->query(".//a[contains(@class, 'result__a')]", $element);
            $title = $titleNode->length > 0 ? trim($titleNode->item(0)->textContent) : '';
            
            $urlNode = $xpath->query(".//a[contains(@class, 'result__a')]/@href", $element);
            $url = $urlNode->length > 0 ? trim($urlNode->item(0)->nodeValue) : '';
            
            $descNode = $xpath->query(".//div[contains(@class, 'result__snippet')]", $element);
            $description = $descNode->length > 0 ? trim($descNode->item(0)->textContent) : '';
            
            if (!empty($title) && !empty($url) && filter_var($url, FILTER_VALIDATE_URL) && !in_array($url, $seenUrls)) {
                $results[] = [
                    'title' => $title,
                    'snippet' => $description,
                    'url' => $url,
                    'offset' => 0
                ];
                $seenUrls[] = $url;
            }
        }
        
        return $results;
    }
    
    /**
     * Parse Bing search results
     */
    private function parseBingResults($html, &$seenUrls) {
        $results = [];
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML($html);
        libxml_clear_errors();
        
        $xpath = new DOMXPath($doc);
        $elements = $xpath->query("//li[contains(@class, 'b_algo')]");
        
        foreach ($elements as $element) {
            $titleNode = $xpath->query(".//h2//a", $element);
            $title = $titleNode->length > 0 ? trim($titleNode->item(0)->textContent) : '';
            
            $urlNode = $xpath->query(".//h2//a/@href", $element);
            $url = $urlNode->length > 0 ? trim($urlNode->item(0)->nodeValue) : '';
            
            $descNode = $xpath->query(".//div[contains(@class, 'b_caption')]//p", $element);
            $description = $descNode->length > 0 ? trim($descNode->item(0)->textContent) : '';
            
            if (!empty($title) && !empty($url) && filter_var($url, FILTER_VALIDATE_URL) && !in_array($url, $seenUrls)) {
                $results[] = [
                    'title' => $title,
                    'snippet' => $description,
                    'url' => $url,
                    'offset' => 0
                ];
                $seenUrls[] = $url;
            }
        }
        
        return $results;
    }
    
    /**
     * Create mock results when all search engines fail
     */
    private function createMockResults($query) {
        return [
            [
                'title' => 'Wikipedia - ' . ucfirst($query),
                'snippet' => 'Comprehensive information about ' . $query . ' from Wikipedia encyclopedia.',
                'url' => 'https://en.wikipedia.org/wiki/' . str_replace(' ', '_', ucfirst($query)),
                'offset' => 0
            ],
            [
                'title' => ucfirst($query) . ' - Britannica',
                'snippet' => 'Learn about ' . $query . ' from Encyclopedia Britannica.',
                'url' => 'https://www.britannica.com/search?query=' . urlencode($query),
                'offset' => 0
            ],
            [
                'title' => 'About ' . ucfirst($query),
                'snippet' => 'Information and resources about ' . $query . '.',
                'url' => 'https://www.history.com/search?q=' . urlencode($query),
                'offset' => 0
            ]
        ];
    }
    
    /**
     * Parse HTML search results
     */
    private function parseSearchResults($html, $offset, &$seenUrls) {
        $results = [];
        
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML($html);
        libxml_clear_errors();
        
        $xpath = new DOMXPath($doc);
        
        // Multiple selector strategies for different search result formats
        $selectors = [
            // Brave search specific selectors
            "//div[contains(@class, 'fdb') or contains(@class, 'result') or contains(@class, 'snippet')]",
            "//article[contains(@class, 'result')]",
            "//div[@data-type='web']",
            // Generic selectors
            "//div[contains(@class, 'web-result')]",
            "//div[contains(@class, 'search-result')]",
            // Fallback - any div with links
            "//div[.//a[@href and contains(@href, 'http')]]"
        ];
        
        foreach ($selectors as $selector) {
            $elements = $xpath->query($selector);
            
            for ($i = 0; $i < min($elements->length, 15); $i++) {
                $element = $elements->item($i);
                
                // Multiple strategies to extract title
                $title = '';
                $titleSelectors = [
                    ".//h1//text()",
                    ".//h2//text()",
                    ".//h3//text()",
                    ".//h4//text()",
                    ".//a[contains(@href, 'http')]//text()",
                    ".//span[contains(@class, 'title')]//text()",
                    ".//div[contains(@class, 'title')]//text()"
                ];
                
                foreach ($titleSelectors as $titleSel) {
                    $titleNodes = $xpath->query($titleSel, $element);
                    if ($titleNodes->length > 0) {
                        $title = trim($titleNodes->item(0)->nodeValue);
                        if (!empty($title) && strlen($title) > 10) {
                            break;
                        }
                    }
                }
                
                // Multiple strategies to extract URL
                $url = '';
                $urlSelectors = [
                    ".//a[contains(@href, 'http')]/@href",
                    ".//a[@href]/@href"
                ];
                
                foreach ($urlSelectors as $urlSel) {
                    $urlNodes = $xpath->query($urlSel, $element);
                    if ($urlNodes->length > 0) {
                        $url = trim($urlNodes->item(0)->nodeValue);
                        if (filter_var($url, FILTER_VALIDATE_URL) && !strpos($url, 'javascript:')) {
                            break;
                        }
                    }
                }
                
                // Extract description/snippet
                $description = '';
                $descSelectors = [
                    ".//div[contains(@class, 'snippet')]//text()",
                    ".//div[contains(@class, 'description')]//text()",
                    ".//p//text()",
                    ".//span[contains(@class, 'snippet')]//text()"
                ];
                
                foreach ($descSelectors as $descSel) {
                    $descNodes = $xpath->query($descSel, $element);
                    if ($descNodes->length > 0) {
                        $description = trim($descNodes->item(0)->nodeValue);
                        if (!empty($description) && strlen($description) > 20) {
                            break;
                        }
                    }
                }
                
                // Clean and validate URL
                if (!empty($url)) {
                    // Remove tracking parameters and clean URL
                    $url = preg_replace('/[?&]utm_[^&]*/', '', $url);
                    $url = preg_replace('/[?&]ref[^&]*/', '', $url);
                }
                
                // Skip if missing essential data or duplicate or invalid
                if (empty($title) || empty($url) || 
                    in_array($url, $seenUrls) || 
                    !filter_var($url, FILTER_VALIDATE_URL) ||
                    strpos($url, 'brave.com') !== false ||
                    strpos($url, 'search?') !== false) {
                    continue;
                }
                
                $results[] = [
                    'title' => $title,
                    'snippet' => $description,
                    'url' => $url,
                    'offset' => $offset
                ];
                
                $seenUrls[] = $url;
                
                // Stop if we have enough results from this selector
                if (count($results) >= 5) {
                    break;
                }
            }
            
            // If we found results with this selector, use them
            if (!empty($results)) {
                Logger::log("Found " . count($results) . " results using selector: $selector");
                break;
            }
        }
        
        // If still no results, try a more aggressive approach
        if (empty($results)) {
            Logger::log("No results found with standard selectors, trying aggressive parsing");
            $results = $this->aggressiveParseResults($html, $offset, $seenUrls);
        }
        
        return $results;
    }
    
    /**
     * Aggressive parsing when standard selectors fail
     */
    private function aggressiveParseResults($html, $offset, &$seenUrls) {
        $results = [];
        
        // Use regex to find URLs and titles
        preg_match_all('/<a[^>]+href=["\']([^"\']*)["\'][^>]*>([^<]+)<\/a>/i', $html, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $url = trim($match[1]);
            $title = trim(strip_tags($match[2]));
            
            // Clean URL if it's relative
            if (strpos($url, 'http') !== 0) {
                if (strpos($url, '//') === 0) {
                    $url = 'https:' . $url;
                } elseif (strpos($url, '/') === 0) {
                    continue; // Skip relative URLs
                }
            }
            
            // Validate
            if (!filter_var($url, FILTER_VALIDATE_URL) || 
                in_array($url, $seenUrls) ||
                strlen($title) < 10 ||
                strpos($url, 'brave.com') !== false ||
                strpos($url, 'search') !== false) {
                continue;
            }
            
            $results[] = [
                'title' => $title,
                'snippet' => 'Description not available',
                'url' => $url,
                'offset' => $offset
            ];
            
            $seenUrls[] = $url;
            
            if (count($results) >= 5) {
                break;
            }
        }
        
        Logger::log("Aggressive parsing found " . count($results) . " results");
        return $results;
    }
    
    /**
     * Fetch content from URL
     */
    private function fetchUrlContent($url) {
        Logger::log("Fetching content from URL: $url");
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9',
                'Accept-Language: en-US,en;q=0.5'
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_ENCODING => 'gzip, deflate'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response === false || $httpCode != 200) {
            Logger::log("Failed to fetch URL: $url (HTTP: $httpCode)");
            return null;
        }
        
        return $response;
    }
    
    /**
     * Extract clean text from HTML content (Step 4)
     */
    private function extractTextFromHtml($html) {
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML($html);
        libxml_clear_errors();
        
        // Remove script and style elements
        $xpath = new DOMXPath($doc);
        foreach ($xpath->query('//script | //style | //nav | //header | //footer | //aside | //form') as $node) {
            $node->parentNode->removeChild($node);
        }
        
        // Get text content from body
        $body = $xpath->query('//body')->item(0);
        if ($body) {
            $text = $body->textContent;
        } else {
            $text = $doc->textContent;
        }
        
        // Clean up text - remove HTML code remnants
        $text = preg_replace('/\s+/', ' ', $text);
        $text = preg_replace('/[^\x20-\x7E\x0A\x0D]/', '', $text);
        $text = trim($text);
        
        return $text;
    }
    
    /**
     * Split content into chunks of specified word count (Step 5)
     */
    private function splitIntoWordChunks($content, $minWords = 100) {
        $words = explode(' ', $content);
        $chunks = [];
        
        for ($i = 0; $i < count($words); $i += $minWords) {
            $chunk = implode(' ', array_slice($words, $i, $minWords));
            if (!empty(trim($chunk)) && str_word_count($chunk) >= $minWords) {
                $chunks[] = $chunk;
            }
        }
        
        return $chunks;
    }
    
    /**
     * Generate training data from content chunk (Step 6)
     */
    private function generateTrainingDataFromChunk($chunk, $query, $title, $url) {
        try {
            // Create input for AI training
            $input_text = "Search Query: {$query}\n\n";
            $input_text .= "Source Title: {$title}\n";
            $input_text .= "Source URL: {$url}\n\n";
            $input_text .= "Content: {$chunk}\n\n";
            $input_text .= "Please provide detailed information about this topic as the Legendary King Ravana of Sri Lanka.";
            
            // Process through AI training system
            return $this->aiTraining->processTrainingData($input_text, 'data');
            
        } catch (Exception $e) {
            Logger::error("Error generating training data from chunk: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Query collected data
     */
    public function queryData($query, $threshold = 0.3) {
        try {
            if (!file_exists($this->searchDataFile)) {
                return [
                    'success' => false,
                    'error' => 'No search data available. Collect data first using /search command.'
                ];
            }
            
            $search_data = json_decode(file_get_contents($this->searchDataFile), true) ?? [];
            
            if (empty($search_data)) {
                return [
                    'success' => false,
                    'error' => 'Search data file is empty.'
                ];
            }
            
            $relevant_results = [];
            $query_words = array_map('strtolower', explode(' ', $query));
            
            foreach ($search_data as $entry) {
                $relevance_score = $this->calculateRelevance($query_words, $entry);
                
                if ($relevance_score >= $threshold) {
                    $entry['relevance_score'] = $relevance_score;
                    $relevant_results[] = $entry;
                }
            }
            
            // Sort by relevance score (descending)
            usort($relevant_results, function($a, $b) {
                return $b['relevance_score'] <=> $a['relevance_score'];
            });
            
            return [
                'success' => true,
                'query' => $query,
                'total_entries_searched' => count($search_data),
                'relevant_results' => $relevant_results,
                'relevance_threshold' => $threshold
            ];
            
        } catch (Exception $e) {
            Logger::error("Query data error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Calculate relevance score between query and data entry
     */
    private function calculateRelevance($query_words, $entry) {
        $search_text = '';
        
        // Safely build search text
        if (isset($entry['source_title'])) {
            $search_text .= $entry['source_title'] . ' ';
        }
        if (isset($entry['source_snippet'])) {
            $search_text .= $entry['source_snippet'] . ' ';
        }
        if (isset($entry['processed_data']['prompt'])) {
            $search_text .= $entry['processed_data']['prompt'] . ' ';
        }
        if (isset($entry['processed_data']['response'])) {
            $response = $entry['processed_data']['response'];
            if (is_array($response)) {
                $response = json_encode($response);
            }
            $search_text .= $response . ' ';
        }
        
        $text_to_search = strtolower($search_text);
        
        $matches = 0;
        $total_words = count($query_words);
        
        foreach ($query_words as $word) {
            if (strpos($text_to_search, $word) !== false) {
                $matches++;
            }
        }
        
        return $total_words > 0 ? $matches / $total_words : 0;
    }
    
    /**
     * Get statistics
     */
    public function getStats() {
        $processed_search_entries = 0;
        $data_file_size = 0;
        
        if (file_exists($this->searchDataFile)) {
            $search_data = json_decode(file_get_contents($this->searchDataFile), true) ?? [];
            $processed_search_entries = count($search_data);
            $data_file_size = filesize($this->searchDataFile);
        }
        
        $training_data_entries = $this->aiTraining->getTrainingCount();
        
        return [
            'processed_search_entries' => $processed_search_entries,
            'training_data_entries' => $training_data_entries,
            'data_file_size' => $data_file_size
        ];
    }
}
?>
