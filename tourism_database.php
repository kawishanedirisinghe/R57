<?php
/**
 * Tourism Database System
 * Comprehensive Sri Lankan tourism information and booking system
 */

require_once 'logger.php';
require_once 'config.php';

class TourismDatabase {
    
    private $attractions_file = 'tourism_attractions.json';
    private $bookings_file = 'tourism_bookings.json';
    
    /**
     * Get all tourist attractions
     */
    public function getAttractions($category = null, $region = null) {
        $attractions = $this->loadAttractions();
        
        if ($category) {
            $attractions = array_filter($attractions, fn($a) => $a['category'] === $category);
        }
        
        if ($region) {
            $attractions = array_filter($attractions, fn($a) => $a['region'] === $region);
        }
        
        return array_values($attractions);
    }
    
    /**
     * Load attractions from file
     */
    private function loadAttractions() {
        if (!file_exists($this->attractions_file)) {
            return $this->getDefaultAttractions();
        }
        
        $content = file_get_contents($this->attractions_file);
        $data = json_decode($content, true);
        
        return is_array($data) ? $data : $this->getDefaultAttractions();
    }
    
    /**
     * Get default Sri Lankan attractions
     */
    private function getDefaultAttractions() {
        return [
            [
                'id' => 1,
                'name' => 'Sigiriya Rock Fortress',
                'description' => 'Ancient rock fortress and palace ruins of King Kashyapa',
                'category' => 'Historical',
                'region' => 'Central Province',
                'location' => 'Dambulla',
                'entrance_fee' => 4500,
                'currency' => 'LKR',
                'rating' => 4.8,
                'opening_hours' => '7:00 AM - 5:30 PM',
                'best_time' => 'Early morning or late afternoon',
                'ravana_story' => 'From my Lanka, this mighty rock stands as a testament to royal power and architectural mastery.'
            ],
            [
                'id' => 2,
                'name' => 'Temple of the Sacred Tooth Relic',
                'description' => 'Sacred Buddhist temple housing the tooth relic of Buddha',
                'category' => 'Religious',
                'region' => 'Central Province',
                'location' => 'Kandy',
                'entrance_fee' => 1500,
                'currency' => 'LKR',
                'rating' => 4.7,
                'opening_hours' => '5:30 AM - 8:00 PM',
                'best_time' => 'During Esala Perahera festival',
                'ravana_story' => 'My people have long revered this sacred site, a jewel in the crown of my Lanka.'
            ],
            [
                'id' => 3,
                'name' => 'Galle Fort',
                'description' => 'Historic fort built by Portuguese and fortified by Dutch',
                'category' => 'Historical',
                'region' => 'Southern Province',
                'location' => 'Galle',
                'entrance_fee' => 0,
                'currency' => 'LKR',
                'rating' => 4.6,
                'opening_hours' => '24 hours',
                'best_time' => 'Sunset hours',
                'ravana_story' => 'These coastal fortifications remind me of the maritime strength of my Lanka.'
            ],
            [
                'id' => 4,
                'name' => 'Yala National Park',
                'description' => 'Premier wildlife sanctuary famous for leopards and elephants',
                'category' => 'Wildlife',
                'region' => 'Southern Province',
                'location' => 'Hambantota',
                'entrance_fee' => 6000,
                'currency' => 'LKR',
                'rating' => 4.5,
                'opening_hours' => '6:00 AM - 6:00 PM',
                'best_time' => 'February to July',
                'ravana_story' => 'The wild beasts of this land have always been part of my kingdom\'s natural glory.'
            ],
            [
                'id' => 5,
                'name' => 'Adam\'s Peak (Sri Pada)',
                'description' => 'Sacred mountain with footprint shrine',
                'category' => 'Religious',
                'region' => 'Central Province',
                'location' => 'Ratnapura District',
                'entrance_fee' => 0,
                'currency' => 'LKR',
                'rating' => 4.9,
                'opening_hours' => 'Climbing season: December to May',
                'best_time' => 'Early morning climb for sunrise',
                'ravana_story' => 'This sacred peak has witnessed the devotion of my people for millennia.'
            ]
        ];
    }
    
    /**
     * Add new attraction
     */
    public function addAttraction($attraction_data) {
        $attractions = $this->loadAttractions();
        
        $new_attraction = array_merge([
            'id' => $this->getNextId($attractions),
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => 'system'
        ], $attraction_data);
        
        $attractions[] = $new_attraction;
        
        file_put_contents($this->attractions_file, json_encode($attractions, JSON_PRETTY_PRINT));
        Logger::log("New attraction added: " . $attraction_data['name']);
        
        return $new_attraction;
    }
    
    /**
     * Get next available ID
     */
    private function getNextId($attractions) {
        if (empty($attractions)) return 1;
        return max(array_column($attractions, 'id')) + 1;
    }
    
    /**
     * Search attractions by keyword
     */
    public function searchAttractions($keyword) {
        $attractions = $this->loadAttractions();
        $keyword = strtolower($keyword);
        
        return array_filter($attractions, function($attraction) use ($keyword) {
            return strpos(strtolower($attraction['name']), $keyword) !== false ||
                   strpos(strtolower($attraction['description']), $keyword) !== false ||
                   strpos(strtolower($attraction['location']), $keyword) !== false;
        });
    }
    
    /**
     * Get attractions by category
     */
    public function getCategories() {
        $attractions = $this->loadAttractions();
        $categories = array_unique(array_column($attractions, 'category'));
        sort($categories);
        return $categories;
    }
    
    /**
     * Get attractions by region
     */
    public function getRegions() {
        $attractions = $this->loadAttractions();
        $regions = array_unique(array_column($attractions, 'region'));
        sort($regions);
        return $regions;
    }
    
    /**
     * Create booking
     */
    public function createBooking($booking_data) {
        $bookings = $this->loadBookings();
        
        $new_booking = array_merge([
            'id' => $this->getNextBookingId($bookings),
            'booking_date' => date('Y-m-d H:i:s'),
            'status' => 'pending'
        ], $booking_data);
        
        $bookings[] = $new_booking;
        
        file_put_contents($this->bookings_file, json_encode($bookings, JSON_PRETTY_PRINT));
        Logger::log("New booking created: " . $new_booking['id']);
        
        return $new_booking;
    }
    
    /**
     * Load bookings from file
     */
    private function loadBookings() {
        if (!file_exists($this->bookings_file)) {
            return [];
        }
        
        $content = file_get_contents($this->bookings_file);
        $data = json_decode($content, true);
        
        return is_array($data) ? $data : [];
    }
    
    /**
     * Get next booking ID
     */
    private function getNextBookingId($bookings) {
        if (empty($bookings)) return 'BK001';
        
        $last_id = max(array_column($bookings, 'id'));
        $number = intval(substr($last_id, 2)) + 1;
        
        return 'BK' . str_pad($number, 3, '0', STR_PAD_LEFT);
    }
    
    /**
     * Get travel recommendations based on preferences
     */
    public function getRecommendations($preferences = []) {
        $attractions = $this->loadAttractions();
        
        $scored_attractions = [];
        foreach ($attractions as $attraction) {
            $score = 0;
            
            // Base score from rating
            $score += $attraction['rating'] * 10;
            
            // Preference matching
            if (isset($preferences['category']) && $attraction['category'] === $preferences['category']) {
                $score += 20;
            }
            
            if (isset($preferences['region']) && $attraction['region'] === $preferences['region']) {
                $score += 15;
            }
            
            if (isset($preferences['budget'])) {
                if ($attraction['entrance_fee'] <= $preferences['budget']) {
                    $score += 10;
                }
            }
            
            $scored_attractions[] = array_merge($attraction, ['recommendation_score' => $score]);
        }
        
        // Sort by score
        usort($scored_attractions, fn($a, $b) => $b['recommendation_score'] <=> $a['recommendation_score']);
        
        return array_slice($scored_attractions, 0, 5);
    }
    
    /**
     * Generate tourism statistics
     */
    public function getStatistics() {
        $attractions = $this->loadAttractions();
        $bookings = $this->loadBookings();
        
        return [
            'total_attractions' => count($attractions),
            'categories' => count($this->getCategories()),
            'regions' => count($this->getRegions()),
            'total_bookings' => count($bookings),
            'average_rating' => round(array_sum(array_column($attractions, 'rating')) / count($attractions), 2),
            'most_popular_category' => $this->getMostPopularCategory($attractions),
            'most_popular_region' => $this->getMostPopularRegion($attractions)
        ];
    }
    
    /**
     * Get most popular category
     */
    private function getMostPopularCategory($attractions) {
        $categories = array_count_values(array_column($attractions, 'category'));
        arsort($categories);
        return array_key_first($categories);
    }
    
    /**
     * Get most popular region
     */
    private function getMostPopularRegion($attractions) {
        $regions = array_count_values(array_column($attractions, 'region'));
        arsort($regions);
        return array_key_first($regions);
    }
}
?>