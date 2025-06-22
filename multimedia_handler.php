<?php
/**
 * Multimedia Handler for Telegram Bot
 * Processes images, documents, audio, and video files
 */

require_once 'logger.php';
require_once 'config.php';
require_once 'ai_training.php';

class MultimediaHandler {
    
    private $upload_dir = 'uploads/';
    private $aiTraining;
    
    public function __construct() {
        $this->aiTraining = new AITraining();
        $this->ensureUploadDir();
    }
    
    /**
     * Ensure upload directory exists
     */
    private function ensureUploadDir() {
        if (!file_exists($this->upload_dir)) {
            mkdir($this->upload_dir, 0755, true);
        }
    }
    
    /**
     * Handle document upload from Telegram
     */
    public function handleDocument($bot, $chat_id, $document) {
        try {
            $file_id = $document['file_id'];
            $file_name = $document['file_name'] ?? 'document_' . time();
            $file_size = $document['file_size'] ?? 0;
            
            Logger::log("Processing document: $file_name (Size: $file_size bytes)");
            
            // Check file size limit (5MB)
            if ($file_size > 5 * 1024 * 1024) {
                return [
                    'success' => false,
                    'message' => "File too large. Maximum size is 5MB."
                ];
            }
            
            // Check for duplicate file based on file_id
            $processed_file = 'processed_files.txt';
            if (file_exists($processed_file)) {
                $processed_ids = file_get_contents($processed_file);
                if (strpos($processed_ids, $file_id) !== false) {
                    return [
                        'success' => false,
                        'message' => "File already processed. Please upload a new file."
                    ];
                }
            }
            
            // Get file from Telegram
            $file_info = $this->makeApiCall($bot, 'getFile', ['file_id' => $file_id]);
            
            if ($file_info['ok']) {
                $file_path = $file_info['result']['file_path'];
                $file_url = "https://api.telegram.org/file/bot" . BOT_TOKEN . "/" . $file_path;
                
                // Download file
                $file_content = file_get_contents($file_url);
                $local_path = $this->upload_dir . $file_name;
                file_put_contents($local_path, $file_content);
                
                // Mark file as processed
                file_put_contents($processed_file, $file_id . "\n", FILE_APPEND);
                
                // Process based on file type
                $result = $this->processFile($local_path, $file_name, $chat_id);
                
                return [
                    'success' => true,
                    'message' => "File processed successfully: $result",
                    'file_path' => $local_path
                ];
            } else {
                throw new Exception("Failed to get file info: " . json_encode($file_info));
            }
            
        } catch (Exception $e) {
            Logger::error("Document handling error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => "Error processing file: " . $e->getMessage()
            ];
        }
    }
    
    /**
     * Process uploaded file based on type
     */
    private function processFile($file_path, $file_name, $chat_id) {
        $extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        switch ($extension) {
            case 'json':
                return $this->processJsonFile($file_path);
            case 'txt':
                return $this->processTextFile($file_path);
            case 'csv':
                return $this->processCsvFile($file_path);
            case 'jpg':
            case 'jpeg':
            case 'png':
                return $this->processImageFile($file_path, $chat_id);
            default:
                return "File type not supported for processing";
        }
    }
    
    /**
     * Process JSON training data file
     */
    private function processJsonFile($file_path) {
        $content = file_get_contents($file_path);
        $data = json_decode($content, true);
        
        if (!is_array($data)) {
            return "Invalid JSON format";
        }
        
        $processed = 0;
        foreach ($data as $item) {
            if (isset($item['prompt']) && isset($item['response'])) {
                $this->aiTraining->saveTrainingData(json_encode($item));
                $processed++;
            }
        }
        
        return "Processed $processed training entries from JSON file";
    }
    
    /**
     * Process text file for training data
     */
    private function processTextFile($file_path) {
        $content = file_get_contents($file_path);
        $lines = explode("\n", $content);
        
        $processed = 0;
        $valid_lines = [];
        
        // Collect valid lines first
        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line) && strlen($line) > 10) {
                $valid_lines[] = $line;
            }
        }
        
        // Process lines in batches to avoid overwhelming the system
        $batch_size = 5; // Process 5 lines at a time
        $batches = array_chunk($valid_lines, $batch_size);
        
        foreach ($batches as $batch) {
            $batch_text = implode("\n", $batch);
            $result = $this->aiTraining->processTrainingData($batch_text, 'data');
            if ($result) {
                $this->aiTraining->saveTrainingData($result);
                $processed += count($batch);
            }
            
            // Add a small delay between batches to prevent API rate limiting
            usleep(500000); // 0.5 second delay
        }
        
        return "Processed $processed lines from text file in " . count($batches) . " batches";
    }
    
    /**
     * Process CSV file
     */
    private function processCsvFile($file_path) {
        $handle = fopen($file_path, 'r');
        $processed = 0;
        
        while (($data = fgetcsv($handle)) !== false) {
            if (count($data) >= 2) {
                $training_data = [
                    'prompt' => $data[0],
                    'response' => $data[1],
                    'labels' => [
                        'entity_type' => $data[2] ?? 'tourism',
                        'location' => $data[3] ?? 'Sri Lanka',
                        'category' => $data[4] ?? 'general',
                        'data_source' => 'csv_import',
                        'last_updated' => date('Y-m-d'),
                        'language' => 'English'
                    ]
                ];
                
                $this->aiTraining->saveTrainingData(json_encode($training_data));
                $processed++;
            }
        }
        
        fclose($handle);
        return "Processed $processed entries from CSV file";
    }
    
    /**
     * Process image file (placeholder for future OCR integration)
     */
    private function processImageFile($file_path, $chat_id) {
        // Future: Implement OCR to extract text from images
        // For now, just store the image
        return "Image file stored. OCR processing will be available in future updates.";
    }
    
    /**
     * Get upload statistics
     */
    public function getUploadStats() {
        if (!file_exists($this->upload_dir)) {
            return ['total_files' => 0, 'total_size' => 0];
        }
        
        $files = scandir($this->upload_dir);
        $total_files = count($files) - 2; // Exclude . and ..
        $total_size = 0;
        
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..') {
                $total_size += filesize($this->upload_dir . $file);
            }
        }
        
        return [
            'total_files' => $total_files,
            'total_size' => $total_size,
            'total_size_mb' => round($total_size / 1024 / 1024, 2)
        ];
    }
    
    /**
     * Make API call through bot instance
     */
    private function makeApiCall($bot, $method, $data = []) {
        if (method_exists($bot, 'apiCall')) {
            return $bot->apiCall($method, $data);
        }
        
        // Fallback to direct API call if needed
        $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/" . $method;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true);
    }
    
    /**
     * Clean old uploads
     */
    public function cleanOldUploads($days = 7) {
        if (!file_exists($this->upload_dir)) {
            return 0;
        }
        
        $files = scandir($this->upload_dir);
        $cleaned = 0;
        $cutoff = time() - ($days * 24 * 60 * 60);
        
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..') {
                $file_path = $this->upload_dir . $file;
                if (filemtime($file_path) < $cutoff) {
                    unlink($file_path);
                    $cleaned++;
                }
            }
        }
        
        Logger::log("Cleaned $cleaned old upload files");
        return $cleaned;
    }
}
?>
