<?php
/**
 * AI API Handler
 * Handles AI-related API calls and text processing
 */

require_once 'logger.php';

class AiAPI {

    private $apiKey;
    private $apiUrl;

    public function __construct() {
        // You can set your AI API key here or use environment variables
        $this->apiKey = getenv('AI_API_KEY') ?: '';
        $this->apiUrl = 'https://api.openai.com/v1/chat/completions'; // Default to OpenAI

        Logger::log("AiAPI initialized");
    }

    /**
     * Compare text with reference data using AI
     */
    public function compareText($text, $referenceData) {
        if (empty($this->apiKey)) {
            return [
                'success' => false,
                'error' => 'AI API key not configured',
                'similarity_score' => 0,
                'is_related' => false
            ];
        }

        $prompt = "Compare these two texts and determine if they are related. Return a similarity score from 0 to 1:\n\n";
        $prompt .= "Text 1: " . $text . "\n\n";
        $prompt .= "Text 2: " . $referenceData . "\n\n";
        $prompt .= "Respond with only a JSON object containing 'similarity_score' (0-1) and 'is_related' (true/false)";

        $result = $this->makeAPICall($prompt);

        if ($result['success']) {
            $response = json_decode($result['response'], true);
            if ($response) {
                return [
                    'success' => true,
                    'similarity_score' => $response['similarity_score'] ?? 0,
                    'is_related' => $response['is_related'] ?? false
                ];
            }
        }

        // Fallback: simple text comparison
        $similarity = $this->simpleTextComparison($text, $referenceData);
        return [
            'success' => true,
            'similarity_score' => $similarity,
            'is_related' => $similarity > 0.3,
            'method' => 'fallback'
        ];
    }

    /**
     * Process text content and generate training data
     */
    public function processContent($content, $context = '') {
        if (empty($this->apiKey)) {
            return $this->fallbackProcessing($content, $context);
        }

        $prompt = "Process this content and create a question-answer pair for AI training about Sri Lankan tourism:\n\n";
        $prompt .= "Content: " . $content . "\n\n";
        if (!empty($context)) {
            $prompt .= "Context: " . $context . "\n\n";
        }
        $prompt .= "Return JSON with 'prompt' and 'response' fields suitable for AI training.";

        $result = $this->makeAPICall($prompt);

        if ($result['success']) {
            $response = json_decode($result['response'], true);
            if ($response && isset($response['prompt']) && isset($response['response'])) {
                return [
                    'success' => true,
                    'data' => $response
                ];
            }
        }

        // Fallback processing
        return $this->fallbackProcessing($content, $context);
    }

    /**
     * Make API call to AI service
     */
    private function makeAPICall($prompt) {
        $data = [
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'max_tokens' => 500,
            'temperature' => 0.7
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            Logger::error("AI API cURL error: " . $error);
            return ['success' => false, 'error' => $error];
        }

        if ($http_code !== 200) {
            Logger::error("AI API HTTP error: " . $http_code . " - " . $response);
            return ['success' => false, 'error' => "HTTP $http_code"];
        }

        $decoded = json_decode($response, true);
        if (!$decoded || !isset($decoded['choices'][0]['message']['content'])) {
            return ['success' => false, 'error' => 'Invalid API response'];
        }

        return [
            'success' => true,
            'response' => $decoded['choices'][0]['message']['content']
        ];
    }

    /**
     * Simple text comparison fallback
     */
    private function simpleTextComparison($text1, $text2) {
        $text1 = strtolower($text1);
        $text2 = strtolower($text2);

        $words1 = explode(' ', $text1);
        $words2 = explode(' ', $text2);

        $common = array_intersect($words1, $words2);
        $total = array_unique(array_merge($words1, $words2));

        return count($common) / count($total);
    }

    /**
     * Fallback content processing
     */
    private function fallbackProcessing($content, $context = '') {
        $words = str_word_count($content);
        $prompt = "What can you tell me about " . substr($content, 0, 100) . "...?";

        $response = "Based on the provided information: " . substr($content, 0, 200);
        if (strlen($content) > 200) {
            $response .= "...";
        }

        return [
            'success' => true,
            'data' => [
                'prompt' => $prompt,
                'response' => $response
            ],
            'method' => 'fallback'
        ];
    }

    /**
     * Call AI API with prompt
     */
    public function callAPI($prompt) {
        try {
            // Since we don't have a real AI API, simulate a response
            $response = "As Ravana, the Legendary King of Sri Lanka, I can tell you: " . 
                       "This information relates to the rich heritage and culture of my beloved Lanka. " .
                       "The knowledge you seek is part of the ancient wisdom that has been passed down through generations.";

            return $response;

        } catch (Exception $e) {
            Logger::error("AI API call failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Compare titles with reference data
     */
    public function compareTitles($title, $referenceData) {
        try {
            // Simple keyword matching for title comparison
            $title_lower = strtolower($title);
            $reference_lower = strtolower($referenceData);

            // Extract keywords from reference data
            $reference_keywords = array_filter(explode(' ', $reference_lower), function($word) {
                return strlen($word) > 3; // Only words longer than 3 characters
            });

            $matches = 0;
            foreach ($reference_keywords as $keyword) {
                if (strpos($title_lower, $keyword) !== false) {
                    $matches++;
                }
            }

            // If at least 1 keyword matches, consider it relevant
            $isMatch = $matches > 0;

            Logger::log("Title comparison: '$title' vs reference data - " . ($isMatch ? "MATCH" : "NO MATCH") . " ($matches keywords)");

            return $isMatch;

        } catch (Exception $e) {
            Logger::error("Title comparison failed: " . $e->getMessage());
            return false;
        }
    }
}
?>