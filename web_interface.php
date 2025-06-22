<?php
/**
 * Web Interface for AI Training Data Collection
 * Chat-based interface for data collection and interaction
 */

// Start session only if not already started and no headers sent
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

require_once 'logger.php';
require_once 'config.php';
require_once 'ai_training.php';

$aiTraining = new AITraining();
$training_count = $aiTraining->getTrainingCount();

// Initialize chat history in session
if (!isset($_SESSION['chat_history'])) {
    $_SESSION['chat_history'] = [
        [
            'type' => 'bot',
            'message' => 'Hey! 🏛️<br><br>I am <strong>The Legendary King of Ancient Sri Lanka</strong> (Ravana), here to help collect training data for AI about my magnificent Lanka!<br><br>මම හදල තියෙන්නෙ AI නිර්මානය කිරිම සදහා ඩේටා එකතු කරන කෙනෙ විදියට ඔබ අදහා 100% සහය වීමටයි මන් කරන්නෙ ඔබ ලබා දෙන දත්ව මා ගාව තියෙනෙ IF/ELSE ඇල්ගොරිතම් එක හරහා ක්‍රියාවලියකට ලක් කර ඒවා නිවැර්ද JSON format එකට convert කරලා ඒවා AI train කරන්න පුලුවන් විදියට හදනවා.<br><br>Switch between "Send Data" and "Ask Questions" tabs above to help train the AI about Sri Lankan tourism, history, and culture!',
            'time' => date('h:i A')
        ]
    ];
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $user_message = $_POST['message'];
    $sender_type = $_POST['sender'] ?? 'data';
    
    // Add user message to chat
    $_SESSION['chat_history'][] = [
        'type' => 'user',
        'message' => htmlspecialchars($user_message),
        'time' => date('h:i A')
    ];
    
    Logger::log("Web interface request: $user_message (type: $sender_type)");
    
    if (!empty($user_message)) {
        // Add processing message
        $_SESSION['chat_history'][] = [
            'type' => 'system',
            'message' => 'The Legendary King is processing your ' . ($sender_type == 'data' ? 'data' : 'question') . '...',
            'time' => date('h:i A')
        ];
        
        $result = $aiTraining->processTrainingData($user_message, $sender_type);
        
        if ($result) {
            if ($aiTraining->saveTrainingData($result)) {
                $jsonObject = json_decode($result, true);
                
                $response_msg = '✅ <strong>Training data processed successfully!</strong><br><br>';
                
                if ($jsonObject && isset($jsonObject['prompt']) && isset($jsonObject['response'])) {
                    $response_msg .= '📝 <strong>Generated Prompt:</strong><br>';
                    $response_msg .= htmlspecialchars(substr($jsonObject['prompt'], 0, 200)) . '...<br><br>';
                    
                    $response_msg .= '🏛️ <strong>Ravana\'s Response:</strong><br>';
                    $response_msg .= htmlspecialchars(substr($jsonObject['response'], 0, 300)) . '...<br><br>';
                }
                
                $new_count = $aiTraining->getTrainingCount();
                $response_msg .= '📊 <strong>Total training entries:</strong> ' . $new_count . '<br><br>';
                $response_msg .= 'Send more quality data to help train the AI about my Lanka!';
                
                $_SESSION['chat_history'][] = [
                    'type' => 'bot',
                    'message' => $response_msg,
                    'time' => date('h:i A')
                ];
            } else {
                $_SESSION['chat_history'][] = [
                    'type' => 'bot',
                    'message' => '❌ <strong>Failed to save training data</strong><br><br>Please try again or contact support.',
                    'time' => date('h:i A')
                ];
            }
        } else {
            $error_msg = $aiTraining->logError($user_message);
            $_SESSION['chat_history'][] = [
                'type' => 'bot',
                'message' => '⚠️ <strong>Processing Error</strong><br><br>' . htmlspecialchars($error_msg),
                'time' => date('h:i A')
            ];
        }
    }
    
    // Return JSON response for AJAX
    if (isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'count' => $aiTraining->getTrainingCount()]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>AI Data Collection System</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root {
      --primary-color: #8ab4f8;
      --primary-dark: #7aa7e6;
      --secondary-color: #81c995;
      --error-color: #f28b82;
      --warning-color: #fdd663;
      --text-color: #e8eaed;
      --text-secondary: #bdc1c6;
      --bg-color: #202124;
      --bg-secondary: #2d2e30;
      --bg-tertiary: #3c4043;
      --border-color: #5f6368;
      --light-gray: #424548;
      --medium-gray: #5f6368;
      --dark-gray: #3c4043;
      --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    html, body {
      height: 100%;
      width: 100%;
      overflow: hidden;
    }

    body {
      font-family: 'Roboto', Arial, sans-serif;
      line-height: 1.6;
      color: var(--text-color);
      background-color: var(--bg-color);
      transition: background-color 0.3s, color 0.3s;
    }

    #app {
      height: 100vh;
      width: 100vw;
      display: flex;
      flex-direction: column;
    }

    .container {
      flex: 1;
      display: flex;
      flex-direction: column;
      background: var(--bg-secondary);
      overflow: hidden;
    }

    .header {
      background-color: var(--bg-tertiary);
      color: var(--text-color);
      padding: 12px 20px;
      text-align: center;
      position: relative;
      border-bottom: 1px solid var(--border-color);
      flex-shrink: 0;
    }

    .header h1 {
      font-size: 1.5rem;
      margin-bottom: 4px;
    }

    .header p {
      font-size: 0.9rem;
      color: var(--text-secondary);
    }

    .theme-toggle {
      position: absolute;
      right: 20px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      color: var(--text-secondary);
      cursor: pointer;
      font-size: 1.2rem;
      transition: color 0.3s;
    }

    .theme-toggle:hover {
      color: var(--primary-color);
    }

    .chat-container {
      display: flex;
      flex-direction: column;
      flex: 1;
      overflow: hidden;
    }

    .messages {
      flex: 1;
      padding: 16px;
      overflow-y: auto;
      background-color: var(--bg-color);
      scroll-behavior: smooth;
    }

    .message {
      margin-bottom: 12px;
      max-width: 85%;
      padding: 12px 16px;
      border-radius: 16px;
      position: relative;
      word-wrap: break-word;
      animation: fadeIn 0.3s ease-out;
      line-height: 1.5;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .user-message {
      background-color: var(--primary-color);
      color: #1a1a1a;
      margin-left: auto;
      border-bottom-right-radius: 4px;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .bot-message {
      background-color: var(--bg-tertiary);
      margin-right: auto;
      border-bottom-left-radius: 4px;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .system-message {
      background-color: var(--warning-color);
      color: #1a1a1a;
      margin: 8px auto;
      text-align: center;
      width: 90%;
      border-radius: 8px;
      padding: 10px;
      font-size: 0.9rem;
    }

    .message-time {
      font-size: 0.7rem;
      color: var(--text-secondary);
      margin-top: 4px;
      text-align: right;
    }

    .tabs {
      display: flex;
      border-bottom: 1px solid var(--border-color);
      background-color: var(--bg-secondary);
      flex-shrink: 0;
    }

    .tab {
      flex: 1;
      padding: 12px;
      text-align: center;
      cursor: pointer;
      transition: all 0.3s ease;
      border-bottom: 3px solid transparent;
      color: var(--text-secondary);
      position: relative;
      font-weight: 500;
    }

    .tab.active {
      border-bottom: 3px solid var(--primary-color);
      color: var(--primary-color);
    }

    .tab i {
      margin-right: 6px;
    }

    .tab-badge {
      position: absolute;
      top: 4px;
      right: 8px;
      background-color: var(--error-color);
      color: white;
      border-radius: 50%;
      width: 18px;
      height: 18px;
      font-size: 0.7rem;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .input-area {
      display: flex;
      padding: 12px;
      background-color: var(--bg-secondary);
      border-top: 1px solid var(--border-color);
      align-items: center;
      flex-shrink: 0;
    }

    #userInput {
      flex: 1;
      padding: 12px 18px;
      border: 1px solid var(--border-color);
      border-radius: 24px;
      font-size: 15px;
      outline: none;
      transition: all 0.3s;
      background-color: var(--bg-tertiary);
      color: var(--text-color);
      min-height: 44px;
      max-height: 120px;
      resize: none;
    }

    #userInput:focus {
      border-color: var(--primary-color);
      box-shadow: 0 0 0 2px rgba(138, 180, 248, 0.2);
    }

    #sendButton {
      margin-left: 10px;
      padding: 12px 18px;
      background-color: var(--primary-color);
      color: #1a1a1a;
      border: none;
      border-radius: 24px;
      cursor: pointer;
      font-size: 15px;
      transition: all 0.3s;
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 44px;
    }

    #sendButton:hover {
      background-color: var(--primary-dark);
      transform: translateY(-1px);
    }

    #sendButton:active {
      transform: translateY(0);
    }

    .typing-indicator {
      display: none;
      padding: 10px 16px;
      color: var(--text-secondary);
      font-style: italic;
      background-color: var(--bg-secondary);
      border-top: 1px solid var(--border-color);
      flex-shrink: 0;
    }

    .typing-indicator i {
      margin-right: 8px;
      color: var(--primary-color);
    }

    .status-bar {
      padding: 8px 12px;
      background-color: var(--bg-tertiary);
      color: var(--text-secondary);
      font-size: 0.75rem;
      display: flex;
      justify-content: space-between;
      border-top: 1px solid var(--border-color);
      flex-shrink: 0;
    }

    .status-item {
      display: flex;
      align-items: center;
    }

    .status-item i {
      margin-right: 5px;
      font-size: 0.8rem;
    }

    .online {
      color: var(--secondary-color);
    }

    /* Scrollbar styling */
    ::-webkit-scrollbar {
      width: 8px;
    }

    ::-webkit-scrollbar-track {
      background: var(--bg-secondary);
    }

    ::-webkit-scrollbar-thumb {
      background: var(--medium-gray);
      border-radius: 4px;
    }

    ::-webkit-scrollbar-thumb:hover {
      background: var(--border-color);
    }

    /* Mobile optimizations */
    @media (max-width: 768px) {
      .header h1 {
        font-size: 1.3rem;
        padding-right: 30px;
      }

      .header p {
        font-size: 0.8rem;
      }

      .message {
        max-width: 90%;
        padding: 10px 14px;
      }

      #userInput, #sendButton {
        padding: 10px 14px;
        font-size: 14px;
      }

      .tab {
        padding: 10px 8px;
        font-size: 0.9rem;
      }

      .tab i {
        margin-right: 4px;
      }
    }
  </style>
</head>
<body class="dark-mode">
  <div id="app">
    <div class="container">
      <div class="header">
        <h1><i class="fas fa-crown"></i> AI Data Collection System</h1>
        <p>Contribute to AI training by sending data or questions</p>
        <button class="theme-toggle" id="themeToggle" title="Toggle dark mode">
          <i class="fas fa-moon"></i>
        </button>
      </div>

      <div class="tabs">
        <div class="tab active" data-sender="data" id="dataTab">
          <i class="fas fa-database"></i> Send Data
          <span class="tab-badge" id="dataBadge"><?php echo $training_count; ?></span>
        </div>
        <div class="tab" data-sender="question" id="questionTab">
          <i class="fas fa-question-circle"></i> Ask Questions
        </div>
      </div>

      <div class="chat-container">
        <div class="messages" id="chatBox">
          <?php foreach ($_SESSION['chat_history'] as $chat): ?>
            <div class="message <?php echo $chat['type']; ?>-message">
              <?php echo $chat['message']; ?>
              <div class="message-time">Today at <?php echo $chat['time']; ?></div>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="typing-indicator" id="typingIndicator">
          <i class="fas fa-circle-notch fa-spin"></i> AI is processing your request...
        </div>

        <div class="input-area">
          <textarea id="userInput" placeholder="Enter training data here..." autocomplete="off" rows="1"></textarea>
          <button id="sendButton">
            <i class="fas fa-paper-plane"></i> Send
          </button>
        </div>

        <div class="status-bar">
          <div class="status-item">
            <i class="fas fa-circle online"></i>
            <span>Bot Online</span>
          </div>
          <div class="status-item">
            <i class="fas fa-database"></i>
            <span><?php echo $training_count; ?> entries</span>
          </div>
          <div class="status-item">
            <i class="fas fa-clock"></i>
            <span><?php echo date('H:i'); ?></span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>
    let currentSender = 'data';

    // Tab switching
    document.querySelectorAll('.tab').forEach(tab => {
      tab.addEventListener('click', function() {
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        currentSender = this.getAttribute('data-sender');
        
        const input = document.getElementById('userInput');
        if (currentSender === 'data') {
          input.placeholder = 'Enter training data here...';
        } else {
          input.placeholder = 'Ask your question here...';
        }
      });
    });

    // Theme toggle
    document.getElementById('themeToggle').addEventListener('click', function() {
      document.body.classList.toggle('light-mode');
      document.body.classList.toggle('dark-mode');
      const icon = this.querySelector('i');
      if (document.body.classList.contains('light-mode')) {
        icon.className = 'fas fa-sun';
      } else {
        icon.className = 'fas fa-moon';
      }
    });

    // Send message
    function sendMessage() {
      const input = document.getElementById('userInput');
      const message = input.value.trim();
      
      if (!message) return;

      // Add user message to chat
      addMessage('user', message);
      input.value = '';

      // Show typing indicator
      document.getElementById('typingIndicator').style.display = 'block';

      // Send to server
      fetch('', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `message=${encodeURIComponent(message)}&sender=${currentSender}&ajax=1`
      })
      .then(response => response.json())
      .then(data => {
        document.getElementById('typingIndicator').style.display = 'none';
        // Reload page to show server response
        location.reload();
      })
      .catch(error => {
        document.getElementById('typingIndicator').style.display = 'none';
        addMessage('bot', '❌ Error processing your request. Please try again.');
      });
    }

    // Add message to chat
    function addMessage(type, message) {
      const chatBox = document.getElementById('chatBox');
      const messageDiv = document.createElement('div');
      messageDiv.className = `message ${type}-message`;
      messageDiv.innerHTML = `
        ${message}
        <div class="message-time">Today at ${new Date().toLocaleTimeString('en-US', {hour: '2-digit', minute: '2-digit'})}</div>
      `;
      chatBox.appendChild(messageDiv);
      chatBox.scrollTop = chatBox.scrollHeight;
    }

    // Send button click
    document.getElementById('sendButton').addEventListener('click', sendMessage);

    // Enter key to send
    document.getElementById('userInput').addEventListener('keypress', function(e) {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
      }
    });

    // Auto-resize textarea
    document.getElementById('userInput').addEventListener('input', function() {
      this.style.height = 'auto';
      this.style.height = Math.min(this.scrollHeight, 120) + 'px';
    });

    // Auto-scroll to bottom
    document.getElementById('chatBox').scrollTop = document.getElementById('chatBox').scrollHeight;
  </script>
</body>
</html>