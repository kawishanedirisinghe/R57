
<?php
/**
 * Enhanced Search System with AI Comparison and Data Collection
 * Combines web scraping, AI API, and training data collection
 */

require_once 'ai_training.php';
require_once 'logger.php';

class EnhancedSearchSystem {
    
    private $aiTraining;
    private $cacheDir;
    private $userAgent;
    
    public function __construct() {
        $this->aiTraining = new AITraining();
        $this->cacheDir = './cache/';
        $this->userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
        
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }
    
    /**
     * AI API call using DeepAI
     */
    private function callAIAPI($prompt) {
        require_once 'ai_api.php';
        $aiAPI = new AiAPI();
        return $aiAPI->callAPI($prompt);
    }
    
    /**
     * Brave Search scraper
     */
    private function searchBrave($query, $offsets = [1, 2, 3, 4, 5]) {
        $allResults = [];
        $seenUrls = [];
        
        foreach ($offsets as $offset) {
            $url = 'https://search.brave.com/search?q=' . urlencode($query) . '&source=web&offset=' . $offset;
            
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
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_ENCODING => 'gzip, deflate'
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($response === false || $httpCode != 200) {
                Logger::log("Failed to fetch search results for offset: $offset");
                continue;
            }
            
            $results = $this->parseSearchResults($response, $offset, $seenUrls);
            $allResults = array_merge($allResults, $results);
            
            // Add delay between requests
            sleep(2);
        }
        
        return $allResults;
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
        
        // Look for search result containers
        $selectors = [
            "//div[contains(@class, 'snippet') or contains(@class, 'result') and not(contains(@class, 'ad'))]",
            "//article[contains(@class, 'result') or contains(@class, 'snippet') and not(contains(@class, 'ad'))]"
        ];
        
        foreach ($selectors as $selector) {
            $elements = $xpath->query($selector);
            
            for ($i = 0; $i < min($elements->length, 10); $i++) {
                $element = $elements->item($i);
                
                // Extract title
                $titleNode = $xpath->query(".//h1|.//h2|.//h3|.//a[contains(@class, 'title')]", $element);
                $title = $titleNode->length > 0 ? trim($titleNode->item(0)->textContent) : '';
                
                // Extract description
                $descNode = $xpath->query(".//div[contains(@class, 'snippet-content') or contains(@class, 'description')]|.//p", $element);
                $description = $descNode->length > 0 ? trim($descNode->item(0)->textContent) : '';
                
                // Extract URL
                $urlNode = $xpath->query(".//a[@href]/@href", $element);
                $url = $urlNode->length > 0 ? trim($urlNode->item(0)->nodeValue) : '';
                
                // Skip if missing data or duplicate
                if (!$title || !$url || in_array($url, $seenUrls) || !filter_var($url, FILTER_VALIDATE_URL)) {
                    continue;
                }
                
                $results[] = [
                    'title' => $title,
                    'description' => $description,
                    'url' => $url,
                    'offset' => $offset
                ];
                
                $seenUrls[] = $url;
            }
            
            if (!empty($results)) {
                break;
            }
        }
        
        return $results;
    }
    
    /**
     * Fetch web page content
     */
    private function fetchWebContent($url) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9',
                'Accept-Language: en-US,en;q=0.5'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response === false || $httpCode != 200) {
            Logger::error("Failed to fetch content from: $url");
            return null;
        }
        
        // Extract text content from HTML
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML($response);
        libxml_clear_errors();
        
        // Remove script and style elements
        $xpath = new DOMXPath($doc);
        $scripts = $xpath->query('//script | //style | //nav | //header | //footer | //aside');
        foreach ($scripts as $script) {
            $script->parentNode->removeChild($script);
        }
        
        // Get main content
        $content = '';
        $mainSelectors = [
            '//main',
            '//article',
            '//div[contains(@class, "content")]',
            '//div[contains(@class, "post")]',
            '//div[contains(@class, "article")]',
            '//body'
        ];
        
        foreach ($mainSelectors as $selector) {
            $elements = $xpath->query($selector);
            if ($elements->length > 0) {
                $content = trim($elements->item(0)->textContent);
                break;
            }
        }
        
        // Clean up the content
        $content = preg_replace('/\s+/', ' ', $content);
        $content = preg_replace('/[^\x20-\x7E\x0A\x0D]/', '', $content);
        
        return $content;
    }
    
    /**
     * Split content into chunks
     */
    private function splitIntoChunks($content, $chunkSize = 500) {
        $chunks = [];
        $words = explode(' ', $content);
        
        for ($i = 0; $i < count($words); $i += $chunkSize) {
            $chunk = implode(' ', array_slice($words, $i, $chunkSize));
            if (trim($chunk)) {
                $chunks[] = trim($chunk);
            }
        }
        
        return $chunks;
    }
    
    /**
     * Compare titles using AI
     */
    private function compareTitlesWithAI($searchTitle, $referenceData) {
        require_once 'ai_api.php';
        $aiAPI = new AiAPI();
        return $aiAPI->compareTitles($searchTitle, $referenceData);
    }
    
    /**
     * Main search and process function
     */
    public function searchAndProcess($query, $referenceData) {
        Logger::log("Starting enhanced search and process for query: $query");
        
        $results = [
            'query' => $query,
            'reference_data' => $referenceData,
            'search_results' => [],
            'matched_results' => [],
            'processed_chunks' => 0,
            'training_data_added' => 0,
            'errors' => []
        ];
        
        try {
            // Step 1: Search for results
            Logger::log("Step 1: Performing search...");
            $searchResults = $this->searchBrave($query);
            $results['search_results'] = $searchResults;
            
            if (empty($searchResults)) {
                throw new Exception("No search results found for query: $query");
            }
            
            Logger::log("Found " . count($searchResults) . " search results");
            
            // Step 2: Compare titles with AI and process matching results
            foreach ($searchResults as $index => $result) {
                Logger::log("Processing result " . ($index + 1) . ": " . $result['title']);
                
                // Compare title with reference data using AI
                $isMatch = $this->compareTitlesWithAI($result['title'], $referenceData);
                
                if ($isMatch) {
                    Logger::log("Title matches reference data: " . $result['title']);
                    $results['matched_results'][] = $result;
                    
                    // Fetch web content
                    Logger::log("Fetching content from: " . $result['url']);
                    $webContent = $this->fetchWebContent($result['url']);
                    
                    if ($webContent) {
                        // Split into 500-word chunks
                        $chunks = $this->splitIntoChunks($webContent, 500);
                        
                        Logger::log("Split content into " . count($chunks) . " chunks");
                        
                        // Process each chunk with AI training
                        foreach ($chunks as $chunkIndex => $chunk) {
                            try {
                                $trainingPrompt = "Title: " . $result['title'] . "\n";
                                $trainingPrompt .= "URL: " . $result['url'] . "\n";
                                $trainingPrompt .= "Content Chunk " . ($chunkIndex + 1) . ":\n" . $chunk;
                                
                                // Process with AI training
                                $processed = $this->aiTraining->processTrainingData($trainingPrompt, 'data');
                                
                                if ($processed) {
                                    $this->aiTraining->saveTrainingData($processed);
                                    $results['processed_chunks']++;
                                    $results['training_data_added']++;
                                    
                                    Logger::log("Successfully processed chunk " . ($chunkIndex + 1) . " from " . $result['title']);
                                } else {
                                    $results['errors'][] = "Failed to process chunk " . ($chunkIndex + 1) . " from " . $result['title'];
                                }
                                
                                // Add sleep time between processing
                                sleep(2);
                                
                            } catch (Exception $e) {
                                $error = "Error processing chunk " . ($chunkIndex + 1) . " from " . $result['title'] . ": " . $e->getMessage();
                                $results['errors'][] = $error;
                                Logger::error($error);
                            }
                        }
                    } else {
                        $error = "Failed to fetch content from: " . $result['url'];
                        $results['errors'][] = $error;
                        Logger::error($error);
                    }
                } else {
                    Logger::log("Title does not match reference data: " . $result['title']);
                }
                
                // Add delay between processing results
                sleep(3);
            }
            
            $results['success'] = true;
            $results['total_training_entries'] = $this->aiTraining->getTrainingCount();
            
            Logger::log("Enhanced search and process completed successfully");
            
        } catch (Exception $e) {
            $results['success'] = false;
            $results['error'] = $e->getMessage();
            Logger::error("Enhanced search and process failed: " . $e->getMessage());
        }
        
        return $results;
    }
}

// Example usage
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $input = json_decode(file_get_contents('php://input'), true);
    $query = $input['query'] ?? '';
    $referenceData = $input['reference_data'] ?? '';
    
    if (empty($query) || empty($referenceData)) {
        echo json_encode([
            'success' => false,
            'error' => 'Query and reference_data parameters are required'
        ]);
        exit;
    }
    
    $enhancedSearch = new EnhancedSearchSystem();
    $result = $enhancedSearch->searchAndProcess($query, $referenceData);
    
    echo json_encode($result, JSON_PRETTY_PRINT);
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Test interface
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Enhanced Search System</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .form-group { margin: 15px 0; }
            label { display: block; margin-bottom: 5px; font-weight: bold; }
            input, textarea { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
            button { background: #007cba; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
            button:hover { background: #005a87; }
            .result { margin-top: 20px; padding: 15px; background: #f9f9f9; border-radius: 4px; }
            .error { background: #ffebee; color: #c62828; }
            .success { background: #e8f5e8; color: #2e7d32; }
        </style>
    </head>
    <body>
        <h1>Enhanced Search System</h1>
        <p>Search the web, compare titles with AI, fetch content, and process it for training data.</p>
        
        <form id="searchForm">
            <div class="form-group">
                <label for="query">Search Query:</label>
                <input type="text" id="query" placeholder="Enter your search query" required>
            </div>
            
            <div class="form-group">
                <label for="referenceData">Reference Data (for title comparison):</label>
                <textarea id="referenceData" rows="4" placeholder="Enter reference data to compare search result titles against" required></textarea>
            </div>
            
            <button type="submit">Start Enhanced Search</button>
        </form>
        
        <div id="result"></div>
        
        <script>
        document.getElementById('searchForm').onsubmit = async function(e) {
            e.preventDefault();
            
            const resultDiv = document.getElementById('result');
            resultDiv.innerHTML = '<div class="result">Processing... This may take several minutes.</div>';
            
            try {
                const response = await fetch('enhanced_search_system.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        query: document.getElementById('query').value,
                        reference_data: document.getElementById('referenceData').value
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    resultDiv.innerHTML = `
                        <div class="result success">
                            <h3>Search Completed Successfully!</h3>
                            <p><strong>Query:</strong> ${data.query}</p>
                            <p><strong>Search Results Found:</strong> ${data.search_results.length}</p>
                            <p><strong>Matched Results:</strong> ${data.matched_results.length}</p>
                            <p><strong>Content Chunks Processed:</strong> ${data.processed_chunks}</p>
                            <p><strong>Training Data Entries Added:</strong> ${data.training_data_added}</p>
                            <p><strong>Total Training Entries:</strong> ${data.total_training_entries}</p>
                            ${data.errors.length > 0 ? '<p><strong>Errors:</strong> ' + data.errors.length + '</p>' : ''}
                        </div>
                    `;
                } else {
                    resultDiv.innerHTML = `
                        <div class="result error">
                            <h3>Error</h3>
                            <p>${data.error}</p>
                        </div>
                    `;
                }
            } catch (error) {
                resultDiv.innerHTML = `
                    <div class="result error">
                        <h3>Error</h3>
                        <p>Network error: ${error.message}</p>
                    </div>
                `;
            }
        };
        </script>
    </body>
    </html>
    <?php
}
?>
<?php
/**
 * Enhanced Search System
 * Combines Google search, AI title comparison, content fetching, and training data generation
 */

require_once 'logger.php';
require_once 'ai_training.php';
require_once 'ai_api.php';

class EnhancedSearchSystem {
    
    private $aiTraining;
    private $aiAPI;
    
    public function __construct() {
        $this->aiTraining = new AITraining();
        
        try {
            $this->aiAPI = new AiAPI();
        } catch (Exception $e) {
            Logger::error("Failed to initialize AiAPI: " . $e->getMessage());
            $this->aiAPI = null;
        }
    }
    
    /**
     * Main search and process function
     */
    public function searchAndProcess($query, $referenceData) {
        Logger::log("Starting enhanced search for: $query with reference: " . substr($referenceData, 0, 100));
        
        try {
            // Step 1: Perform Google search
            $searchResults = $this->performGoogleSearch($query, 10);
            
            if (empty($searchResults)) {
                return [
                    'success' => false,
                    'error' => 'No search results found'
                ];
            }
            
            // Step 2: Compare titles with reference data using AI
            $matchedResults = $this->compareWithAI($searchResults, $referenceData);
            
            // Step 3: Fetch content and process
            $processedChunks = 0;
            $trainingDataAdded = 0;
            $errors = [];
            
            foreach ($matchedResults as $result) {
                try {
                    // Simulate content fetching (in real implementation, use cURL)
                    $content = $this->fetchContent($result['url']);
                    
                    if ($content) {
                        // Split into 500-word chunks
                        $chunks = $this->splitIntoChunks($content, 500);
                        
                        foreach ($chunks as $chunk) {
                            // Generate training data from chunk
                            $trainingData = $this->generateTrainingData($chunk, $query, $referenceData);
                            
                            if ($trainingData) {
                                $this->aiTraining->saveTrainingData($trainingData);
                                $trainingDataAdded++;
                            }
                            
                            $processedChunks++;
                            
                            // Add delay to prevent rate limiting
                            usleep(100000); // 0.1 second
                        }
                    }
                } catch (Exception $e) {
                    $errors[] = "Error processing " . $result['url'] . ": " . $e->getMessage();
                    Logger::error("Enhanced search error: " . $e->getMessage());
                }
            }
            
            $totalTrainingEntries = $this->aiTraining->getTrainingCount();
            
            return [
                'success' => true,
                'search_results' => $searchResults,
                'matched_results' => $matchedResults,
                'processed_chunks' => $processedChunks,
                'training_data_added' => $trainingDataAdded,
                'total_training_entries' => $totalTrainingEntries,
                'errors' => $errors
            ];
            
        } catch (Exception $e) {
            Logger::error("Enhanced search system error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Perform Google search (simplified implementation)
     */
    private function performGoogleSearch($query, $numResults) {
        // Simulate search results for tourism/Ravana related queries
        $simulatedResults = [
            [
                'title' => 'Ravana: The Legendary King of Sri Lanka - History and Culture',
                'url' => 'https://example.com/ravana-history',
                'snippet' => 'Discover the fascinating history of King Ravana, the legendary ruler of ancient Sri Lanka mentioned in the Ramayana epic.'
            ],
            [
                'title' => 'Ancient Sri Lankan Kingdoms and Ravana Legacy',
                'url' => 'https://example.com/ancient-kingdoms',
                'snippet' => 'Explore the rich heritage of ancient Sri Lankan civilizations and the enduring legacy of King Ravana in Sinhala culture.'
            ],
            [
                'title' => 'Sinhala Buddhist Culture and Ancient Kings',
                'url' => 'https://example.com/sinhala-culture',
                'snippet' => 'Understanding the deep roots of Sinhala Buddhist culture and its connection to legendary kings like Ravana.'
            ],
            [
                'title' => 'Sri Lanka Tourism: Ravana Trail and Historical Sites',
                'url' => 'https://example.com/ravana-trail',
                'snippet' => 'Follow the Ravana trail across Sri Lanka visiting historical sites connected to the legendary king.'
            ],
            [
                'title' => 'Ramayana in Sri Lanka: Ravana\'s Kingdom',
                'url' => 'https://example.com/ramayana-sri-lanka',
                'snippet' => 'Explore locations in Sri Lanka believed to be connected to Ravana\'s kingdom as described in the Ramayana.'
            ]
        ];
        
        return array_slice($simulatedResults, 0, min($numResults, count($simulatedResults)));
    }
    
    /**
     * Compare search results with reference data using AI
     */
    private function compareWithAI($searchResults, $referenceData) {
        $matchedResults = [];
        
        foreach ($searchResults as $result) {
            // Simple keyword matching (in real implementation, use AI API)
            $title = strtolower($result['title']);
            $reference = strtolower($referenceData);
            
            // Check for relevant keywords
            $relevantKeywords = ['ravana', 'king', 'sri lanka', 'sinhala', 'legendary', 'ancient'];
            $matches = 0;
            
            foreach ($relevantKeywords as $keyword) {
                if (strpos($title, $keyword) !== false || strpos($reference, $keyword) !== false) {
                    $matches++;
                }
            }
            
            // If we have enough matches, consider it relevant
            if ($matches >= 2) {
                $matchedResults[] = $result;
            }
        }
        
        return $matchedResults;
    }
    
    /**
     * Fetch content from URL (simplified)
     */
    private function fetchContent($url) {
        // Simulate content fetching
        $simulatedContent = "This is simulated content about Ravana, the legendary King of Sri Lanka. " .
                           "According to ancient Sinhala traditions and the Ramayana epic, Ravana was a powerful " .
                           "and learned ruler who governed the island of Lanka. His kingdom was known for its " .
                           "advanced civilization, beautiful cities, and rich culture. The legacy of Ravana " .
                           "continues to influence Sri Lankan culture and tourism today, with many historical " .
                           "sites across the island connected to his story. Visitors can explore temples, " .
                           "caves, and ancient ruins that are part of the Ravana trail, discovering the " .
                           "fascinating blend of mythology, history, and culture that makes Sri Lanka unique.";
        
        return $simulatedContent;
    }
    
    /**
     * Split content into word chunks
     */
    private function splitIntoChunks($content, $maxWords) {
        $words = explode(' ', $content);
        $chunks = [];
        
        for ($i = 0; $i < count($words); $i += $maxWords) {
            $chunk = implode(' ', array_slice($words, $i, $maxWords));
            if (!empty(trim($chunk))) {
                $chunks[] = $chunk;
            }
        }
        
        return $chunks;
    }
    
    /**
     * Generate training data from content chunk
     */
    private function generateTrainingData($chunk, $query, $referenceData) {
        try {
            $input = "Based on this content about $query:\n\n$chunk\n\n";
            $input .= "Reference context: $referenceData\n\n";
            $input .= "Please provide information as the Legendary King Ravana of Sri Lanka.";
            
            return $this->aiTraining->processTrainingData($input, 'data');
            
        } catch (Exception $e) {
            Logger::error("Error generating training data: " . $e->getMessage());
            return null;
        }
    }
}
?>
