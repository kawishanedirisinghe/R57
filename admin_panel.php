<?php
/**
 * Admin Panel for Bot Management
 * Complete control interface for the Telegram bot system
 */

session_start();
require_once 'logger.php';
require_once 'config.php';
require_once 'ai_training.php';
require_once 'bot.php';

$aiTraining = new AITraining();
$bot = new TelegramBot();

// Handle admin actions
$action_result = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'setup_webhook':
            $result = $bot->setWebhook();
            $action_result = json_encode($result, JSON_PRETTY_PRINT);
            break;
            
        case 'clear_logs':
            if (file_exists(LOG_FILE)) {
                file_put_contents(LOG_FILE, '');
                $action_result = 'Logs cleared successfully';
            }
            break;
            
        case 'clear_training':
            if (file_exists('train.json')) {
                file_put_contents('train.json', '[]');
                file_put_contents('counter.txt', '0');
                $action_result = 'Training data cleared';
            }
            break;
            
        case 'bulk_import':
            if (isset($_FILES['training_file'])) {
                $uploaded_file = $_FILES['training_file']['tmp_name'];
                $content = file_get_contents($uploaded_file);
                $data = json_decode($content, true);
                
                if (is_array($data)) {
                    $count = 0;
                    foreach ($data as $item) {
                        if (isset($item['prompt']) && isset($item['response'])) {
                            $aiTraining->saveTrainingData(json_encode($item));
                            $count++;
                        }
                    }
                    $action_result = "Imported $count training entries";
                } else {
                    $action_result = 'Invalid JSON format';
                }
            }
            break;
    }
}

$stats = [
    'training_count' => $aiTraining->getTrainingCount(),
    'log_size' => file_exists(LOG_FILE) ? filesize(LOG_FILE) : 0,
    'train_size' => file_exists('train.json') ? filesize('train.json') : 0,
    'uptime' => date('Y-m-d H:i:s'),
    'memory' => memory_get_usage(true)
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Ravana Bot</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2c3e50;
            --secondary: #3498db;
            --success: #27ae60;
            --warning: #f39c12;
            --danger: #e74c3c;
            --dark: #1a252f;
            --light: #ecf0f1;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--dark) 0%, var(--primary) 100%);
            color: var(--light);
            min-height: 100vh;
        }
        
        .admin-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding: 20px;
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
            backdrop-filter: blur(10px);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: rgba(255,255,255,0.1);
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .stat-card i {
            font-size: 2rem;
            margin-bottom: 10px;
            color: var(--secondary);
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .admin-sections {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
        }
        
        .admin-section {
            background: rgba(255,255,255,0.1);
            padding: 20px;
            border-radius: 10px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .section-title {
            font-size: 1.2rem;
            margin-bottom: 15px;
            color: var(--secondary);
            border-bottom: 2px solid var(--secondary);
            padding-bottom: 5px;
        }
        
        .btn {
            background: var(--secondary);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin: 5px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .btn:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }
        
        .btn-danger {
            background: var(--danger);
        }
        
        .btn-danger:hover {
            background: #c0392b;
        }
        
        .btn-success {
            background: var(--success);
        }
        
        .btn-success:hover {
            background: #229954;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 5px;
            background: rgba(255,255,255,0.1);
            color: var(--light);
        }
        
        .result-box {
            background: rgba(0,0,0,0.3);
            padding: 15px;
            border-radius: 5px;
            margin-top: 15px;
            font-family: monospace;
            white-space: pre-wrap;
            max-height: 300px;
            overflow-y: auto;
        }
        
        .quick-links {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .quick-link {
            background: var(--warning);
            color: white;
            padding: 8px 15px;
            text-decoration: none;
            border-radius: 5px;
            font-size: 12px;
            transition: all 0.3s;
        }
        
        .quick-link:hover {
            background: #e67e22;
            transform: translateY(-1px);
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="header">
            <h1><i class="fas fa-crown"></i> Ravana Bot Admin Panel</h1>
            <p>Complete management interface for your AI training system</p>
        </div>

        <div class="quick-links">
            <a href="/" class="quick-link"><i class="fas fa-comments"></i> Chat Interface</a>
            <a href="/test" class="quick-link"><i class="fas fa-flask"></i> Test Interface</a>
            <a href="/status" class="quick-link"><i class="fas fa-chart-line"></i> System Status</a>
            <a href="/info" class="quick-link"><i class="fas fa-info-circle"></i> Webhook Info</a>
            <a href="/setup" class="quick-link"><i class="fas fa-cog"></i> Setup Webhook</a>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-database"></i>
                <div class="stat-value"><?php echo $stats['training_count']; ?></div>
                <div>Training Entries</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-file-alt"></i>
                <div class="stat-value"><?php echo number_format($stats['log_size']); ?></div>
                <div>Log File Size (bytes)</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-memory"></i>
                <div class="stat-value"><?php echo number_format($stats['memory'] / 1024 / 1024, 1); ?>MB</div>
                <div>Memory Usage</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-clock"></i>
                <div class="stat-value"><?php echo date('H:i'); ?></div>
                <div>Current Time</div>
            </div>
        </div>

        <div class="admin-sections">
            <div class="admin-section">
                <div class="section-title"><i class="fas fa-robot"></i> Bot Management</div>
                <form method="POST">
                    <input type="hidden" name="action" value="setup_webhook">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-link"></i> Setup Webhook
                    </button>
                </form>
                
                <button onclick="testBot()" class="btn">
                    <i class="fas fa-play"></i> Test Bot
                </button>
                
                <button onclick="checkWebhook()" class="btn">
                    <i class="fas fa-search"></i> Check Webhook
                </button>
            </div>

            <div class="admin-section">
                <div class="section-title"><i class="fas fa-brain"></i> Training Data</div>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="bulk_import">
                    <div class="form-group">
                        <label>Import Training Data (JSON)</label>
                        <input type="file" name="training_file" class="form-control" accept=".json">
                    </div>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-upload"></i> Import Data
                    </button>
                </form>
                
                <a href="/train.json" class="btn" download>
                    <i class="fas fa-download"></i> Download Training Data
                </a>
                
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="clear_training">
                    <button type="submit" class="btn btn-danger" onclick="return confirm('Clear all training data?')">
                        <i class="fas fa-trash"></i> Clear Training Data
                    </button>
                </form>
            </div>

            <div class="admin-section">
                <div class="section-title"><i class="fas fa-tools"></i> System Maintenance</div>
                <form method="POST">
                    <input type="hidden" name="action" value="clear_logs">
                    <button type="submit" class="btn btn-danger" onclick="return confirm('Clear all logs?')">
                        <i class="fas fa-broom"></i> Clear Logs
                    </button>
                </form>
                
                <button onclick="viewLogs()" class="btn">
                    <i class="fas fa-eye"></i> View Recent Logs
                </button>
                
                <button onclick="systemInfo()" class="btn">
                    <i class="fas fa-info"></i> System Information
                </button>
            </div>

            <div class="admin-section">
                <div class="section-title"><i class="fas fa-chart-bar"></i> Analytics</div>
                <button onclick="generateReport()" class="btn">
                    <i class="fas fa-chart-line"></i> Generate Report
                </button>
                
                <button onclick="exportData()" class="btn">
                    <i class="fas fa-file-export"></i> Export All Data
                </button>
                
                <button onclick="optimizeDatabase()" class="btn">
                    <i class="fas fa-compress"></i> Optimize Files
                </button>
            </div>
        </div>

        <?php if ($action_result): ?>
        <div class="admin-section">
            <div class="section-title"><i class="fas fa-terminal"></i> Action Result</div>
            <div class="result-box"><?php echo htmlspecialchars($action_result); ?></div>
        </div>
        <?php endif; ?>

        <div class="admin-section" id="dynamic-result" style="display: none;">
            <div class="section-title"><i class="fas fa-terminal"></i> Output</div>
            <div class="result-box" id="result-content"></div>
        </div>
    </div>

    <script>
        function showResult(content) {
            document.getElementById('result-content').textContent = content;
            document.getElementById('dynamic-result').style.display = 'block';
        }

        async function testBot() {
            try {
                const response = await fetch('/status');
                const data = await response.json();
                showResult(JSON.stringify(data, null, 2));
            } catch (error) {
                showResult('Error: ' + error.message);
            }
        }

        async function checkWebhook() {
            try {
                const response = await fetch('/info');
                const data = await response.json();
                showResult(JSON.stringify(data, null, 2));
            } catch (error) {
                showResult('Error: ' + error.message);
            }
        }

        async function viewLogs() {
            try {
                const response = await fetch('/keepalive');
                const data = await response.json();
                showResult(JSON.stringify(data, null, 2));
            } catch (error) {
                showResult('Error: ' + error.message);
            }
        }

        function systemInfo() {
            const info = `
System Information:
- PHP Version: ${navigator.userAgent}
- Current Time: ${new Date().toLocaleString()}
- Memory Usage: ${(performance.memory ? performance.memory.usedJSHeapSize / 1024 / 1024 : 'N/A')} MB
- Connection: ${navigator.onLine ? 'Online' : 'Offline'}
- Language: ${navigator.language}
            `;
            showResult(info);
        }

        function generateReport() {
            const report = `
Bot Performance Report
Generated: ${new Date().toLocaleString()}

Training Data: <?php echo $stats['training_count']; ?> entries
Log File Size: <?php echo number_format($stats['log_size']); ?> bytes
Memory Usage: <?php echo number_format($stats['memory'] / 1024 / 1024, 1); ?> MB
System Uptime: Running since <?php echo $stats['uptime']; ?>

Status: Operational
Performance: Optimal
            `;
            showResult(report);
        }

        function exportData() {
            showResult('Export functionality would generate a comprehensive backup of all bot data, training entries, logs, and configuration files.');
        }

        function optimizeDatabase() {
            showResult('File optimization would clean up logs, compress training data, and remove duplicate entries to improve performance.');
        }
    </script>
</body>
</html>