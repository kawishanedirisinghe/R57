<?php
/**
 * User Analytics and Tracking System
 * Tracks user interactions and generates insights
 */

require_once 'logger.php';
require_once 'config.php';

class UserAnalytics {
    
    private $analytics_file = 'user_analytics.json';
    
    /**
     * Track user interaction
     */
    public function trackInteraction($user_id, $username, $message_type, $message_content) {
        $interaction = [
            'timestamp' => date('Y-m-d H:i:s'),
            'user_id' => $user_id,
            'username' => $username,
            'message_type' => $message_type, // data, question, command
            'message_length' => strlen($message_content),
            'message_preview' => substr($message_content, 0, 100),
            'session_id' => session_id()
        ];
        
        $this->saveInteraction($interaction);
        Logger::log("User interaction tracked: $username ($user_id) - $message_type");
    }
    
    /**
     * Save interaction to file
     */
    private function saveInteraction($interaction) {
        $analytics = $this->loadAnalytics();
        $analytics[] = $interaction;
        
        // Keep only last 1000 interactions
        if (count($analytics) > 1000) {
            $analytics = array_slice($analytics, -1000);
        }
        
        file_put_contents($this->analytics_file, json_encode($analytics, JSON_PRETTY_PRINT));
    }
    
    /**
     * Load analytics data
     */
    private function loadAnalytics() {
        if (!file_exists($this->analytics_file)) {
            return [];
        }
        
        $content = file_get_contents($this->analytics_file);
        $data = json_decode($content, true);
        
        return is_array($data) ? $data : [];
    }
    
    /**
     * Get user statistics
     */
    public function getUserStats() {
        $analytics = $this->loadAnalytics();
        
        $stats = [
            'total_interactions' => count($analytics),
            'unique_users' => count(array_unique(array_column($analytics, 'user_id'))),
            'data_submissions' => count(array_filter($analytics, fn($a) => $a['message_type'] === 'data')),
            'questions_asked' => count(array_filter($analytics, fn($a) => $a['message_type'] === 'question')),
            'commands_used' => count(array_filter($analytics, fn($a) => $a['message_type'] === 'command')),
            'today_interactions' => count(array_filter($analytics, fn($a) => date('Y-m-d', strtotime($a['timestamp'])) === date('Y-m-d'))),
            'most_active_users' => $this->getMostActiveUsers($analytics),
            'hourly_distribution' => $this->getHourlyDistribution($analytics)
        ];
        
        return $stats;
    }
    
    /**
     * Get most active users
     */
    private function getMostActiveUsers($analytics) {
        $user_counts = [];
        foreach ($analytics as $interaction) {
            $user_id = $interaction['user_id'];
            $username = $interaction['username'] ?? 'Unknown';
            $key = "$username ($user_id)";
            $user_counts[$key] = ($user_counts[$key] ?? 0) + 1;
        }
        
        arsort($user_counts);
        return array_slice($user_counts, 0, 10);
    }
    
    /**
     * Get hourly distribution
     */
    private function getHourlyDistribution($analytics) {
        $hourly = array_fill(0, 24, 0);
        foreach ($analytics as $interaction) {
            $hour = intval(date('H', strtotime($interaction['timestamp'])));
            $hourly[$hour]++;
        }
        
        return $hourly;
    }
    
    /**
     * Generate analytics report
     */
    public function generateReport() {
        $stats = $this->getUserStats();
        
        $report = [
            'generated_at' => date('Y-m-d H:i:s'),
            'summary' => $stats,
            'insights' => [
                'peak_hour' => array_search(max($stats['hourly_distribution']), $stats['hourly_distribution']),
                'avg_message_length' => $this->getAverageMessageLength(),
                'user_engagement' => $stats['total_interactions'] / max($stats['unique_users'], 1),
                'data_to_question_ratio' => $stats['data_submissions'] / max($stats['questions_asked'], 1)
            ]
        ];
        
        return $report;
    }
    
    /**
     * Get average message length
     */
    private function getAverageMessageLength() {
        $analytics = $this->loadAnalytics();
        if (empty($analytics)) return 0;
        
        $total_length = array_sum(array_column($analytics, 'message_length'));
        return round($total_length / count($analytics), 2);
    }
}
?>