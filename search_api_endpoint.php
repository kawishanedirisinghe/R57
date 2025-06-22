
<?php
/**
 * REST API Endpoint for Search Data
 * Provides HTTP API access to collected search data
 */

require_once 'google_search_api.php';
require_once 'logger.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$googleAPI = new GoogleSearchAPI();
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

try {
    switch ($method) {
        case 'GET':
            if (strpos($path, '/api/search/stats') !== false) {
                // Get statistics
                $stats = $googleAPI->getStats();
                echo json_encode([
                    'success' => true,
                    'data' => $stats
                ]);
                
            } elseif (strpos($path, '/api/search/query') !== false) {
                // Query data via GET parameters
                $query = $_GET['q'] ?? '';
                $threshold = floatval($_GET['threshold'] ?? 0.3);
                
                if (empty($query)) {
                    throw new Exception('Query parameter "q" is required');
                }
                
                $result = $googleAPI->queryData($query, $threshold);
                echo json_encode($result);
                
            } else {
                throw new Exception('Endpoint not found');
            }
            break;
            
        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (strpos($path, '/api/search/collect') !== false) {
                // Collect data from search
                $query = $input['query'] ?? '';
                $num_results = intval($input['num_results'] ?? 10);
                
                if (empty($query)) {
                    throw new Exception('Query is required');
                }
                
                if ($num_results < 1 || $num_results > 50) {
                    throw new Exception('num_results must be between 1 and 50');
                }
                
                $result = $googleAPI->searchAndCollect($query, $num_results);
                echo json_encode($result);
                
            } elseif (strpos($path, '/api/search/query') !== false) {
                // Query data via POST
                $query = $input['query'] ?? '';
                $threshold = floatval($input['threshold'] ?? 0.3);
                
                if (empty($query)) {
                    throw new Exception('Query is required');
                }
                
                $result = $googleAPI->queryData($query, $threshold);
                echo json_encode($result);
                
            } else {
                throw new Exception('Endpoint not found');
            }
            break;
            
        default:
            throw new Exception('Method not allowed');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    
    Logger::error("API Error: " . $e->getMessage());
}
?>
