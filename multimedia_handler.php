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
            
            // Get file from Telegram
            $file_info = $bot->apiCall('getFile', ['file_id' => $file_id]);
            
            if ($file_info['ok']) {
                $file_path = $file_info['result']['file_path'];
                $file_url = "https://api.telegram.org/file/bot" . BOT_TOKEN . "/" . $file_path;
                
                // Download file
                $file_content = file_get_contents($file_url);
                $local_path = $this->upload_dir . $file_name;
                file_put_contents($local_path, $file_content);
                
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
        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line) && strlen($line) > 10) {
                $result = $this->aiTraining->processTrainingData($line, 'data');
                if ($result) {
                    $this->aiTraining->saveTrainingData($result);
                    $processed++;
                }
            }
        }
        
        return "Processed $processed lines from text file";
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