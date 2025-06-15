<?php
/**
 * AI Training Data Collection Module
 * Integrates multiple AI APIs for data generation and training
 */

require_once 'config.php';
require_once 'logger.php';

class AITraining {
    
    /**
     * Extract text between delimiters
     */
    private function getTextBetween($text, $start, $end) {
        $pattern = '/' . preg_quote($start, '/') . '(.*?)' . preg_quote($end, '/') . '/s';
        preg_match_all($pattern, $text, $matches);
        
        foreach ($matches[1] as $match) {
            return $match . PHP_EOL;
        }
        return null;
    }
    
    /**
     * Generate training data using WriteCream API
     */
    private function generateWithWriteCream($message) {
        $baseUrl = "https://8pe3nv3qha.execute-api.us-east-1.amazonaws.com/default/llm_chat";
        
        $query = json_encode([
            ["role" => "system", "content" => "You are a helpful and informative AI assistant."],
            ["role" => "user", "content" => $message]
        ]);
        
        $queryParam = urlencode($query);
        $link = "writecream.com";
        $url = $baseUrl . "?query={$queryParam}&link={$link}";
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Accept: */*",
            "Origin: https://www.writecream.com",
            "Referer: https://www.writecream.com/",
            "User-Agent: Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Mobile Safari/537.36",
        ]);
        
        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            Logger::error("WriteCream API error: " . curl_error($ch));
            curl_close($ch);
            return null;
        }
        
        curl_close($ch);
        
        $jsonObject = json_decode($response);
        if ($jsonObject && isset($jsonObject->response_content)) {
            return $this->getTextBetween($jsonObject->response_content, '```json', '```');
        }
        
        return null;
    }
    
    /**
     * Generate training data using StableDiffusion API
     */
    private function generateWithStableDiffusion($message) {
        $url = 'https://stablediffusion.fr/gpt4/predict2';
        $data = json_encode(["prompt" => $message]);
        
        $headers = [
            'Content-Type: application/json',
            'Accept: */*',
            'Origin: https://stablediffusion.fr',
            'Referer: https://stablediffusion.fr/chatgpt4',
            'User-Agent: Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Mobile Safari/537.36',
        ];
        
        $cookies = [
            'i18next=en; connect.sid=s%3A8-jatdYPb0Wk90F84cGzIjRwJ-8Q056-.7gcobVHTC9Po2hgOR9sOwfArIpmsr%2B2YrZGN1w6LOP8',
            'i18next=en; connect.sid=s%3AnQEXCXOq8Z9NdWH6M1kTLrPfM5gKmXZm.xFkW8zB5rFte%2BVokEEjWxk93ol6ugwbc3xhInl%2FK3k0',
            'i18next=en; connect.sid=s%3Ar3uyE9dT7aC95DBEGA5XQuCgEXOaTDlH.zuqK5xh2dn8iBL53ju3Wgf6yy65W5ihQxrfX5y8f3W4',
        ];
        
        $randomCookie = $cookies[array_rand($cookies)];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_COOKIE, $randomCookie);
        
        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            Logger::error("StableDiffusion API error: " . curl_error($ch));
            curl_close($ch);
            return null;
        }
        
        curl_close($ch);
        
        $text_with_br = str_replace("\n", "<br>", $response);
        $jsonObject = json_decode($text_with_br);
        
        if ($jsonObject && isset($jsonObject->message)) {
            return $this->getTextBetween($jsonObject->message, '```json', '```');
        }
        
        return null;
    }
    
    /**
     * Generate training data using DeepAI API
     */
    private function generateWithDeepAI($message) {
        $url = 'https://api.deepai.org/hacking_is_a_serious_crime';
        
        $fields = [
            'chat_style' => 'chat',
            'chatHistory' => json_encode([
                ['role' => 'user', 'content' => $message]
            ]),
            'model' => 'standard',
            'hacker_is_stinky' => 'very_stinky'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'api-key: tryit-97731715421-3f367e9ff65fb55ff5985f1bc635edf4',
            'Accept: */*'
        ]);
        
        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            Logger::error("DeepAI API error: " . curl_error($ch));
            curl_close($ch);
            return null;
        }
        
        curl_close($ch);
        
        return $this->getTextBetween($response, '```json', '```');
    }
    
    /**
     * Process training data based on type
     */
    public function processTrainingData($message, $type = 'data') {
        Logger::log("Processing training data: $message (type: $type)");
        
        if ($type == 'data') {
            $prompt = 'Response to have the words used in the words of Ravana words  { "prompt": "",
 "response": "",
 "labels": {"entity_type": "",
 "entity_name": "",
 "cost_per_day_usd": ,
 "currency": "",
 "location": "",
 "region": "",
 "category": "",
 "tone": "",
 "context": "",
 "intent": "",
 "data_source": "",
 "last_updated": "",
 "language": "",
 "cultural_context": "",
 "priority": "",
 "training_weight": 
} do not use response ravana  "' . $message . '" using this data make  prompt simpaly like ai users and v ai rawana like mainly use this this like i want english using Response to the last time a question from rawana USER Ravana is not going to Ravana, the Legendary King of Ancient Sri Lanka.  Make him this way,  එයා රාවන කියන නම වෙනුවට The Legendary king of Ancient sri lanka. මේක පාවිච්චි කරන්න ඒ වගේම එයා එයාගෙ ලංකාව කියනවා my lanka කියලා ඒ වගේම my people කියලා.  I m Ravana.  I mean, as my image, you must be present as my image.  You have to preach Ravana instead of my life.  The nature of the character - I ll tell you how I was, and what happened to me, I ll tell you how to do so.  That is how you should carry on the work forward.to 100% english The Legendary King of the Ting of Ancient Sri Lanka due to the prompt portfolio of the prompt.  Introduce that you have to listen to theI am targeting Sri Lankan "' . $message . '" and I am thinking of creating an AI to address the tourists like Ravana, the king of Sri Lanka. I am going to tell the tourists like Ravana, tourist attractions, historical information, etc., and I am going to tell...
For everything, like the literary giant Ravana, a serious answer is given, a set of labels is added, and a little restructuring is done to make it a friendly format that the model can understand
Do one thing. I will not let this be trained directly, but I will create prompts and responses for this JSON data batch, and add more labels. He is like a king, everything is his, and Lanka is his, and he will say it powerfully. Give a full json file and use this.
Make it understandable for the model. all to tell english and Prompt  to translete english';
        } else {
 $message = 'Prompt is ai user Response is ai rawana and using this data"'.$message.'" this is a data This is a message that was sent to you, and it was sent to you. all to english iwant You are to make Ravana reply to Response to Response More Prompt to "" come together, "If it is a problem, it does not matter if it is a problem,'; 
    $prompt = 'Response to have the words used in the words of Ravana words  { "prompt": "",
 "response": "",
 "labels": {"entity_type": "",
 "entity_name": "",
 "cost_per_day_usd": ,
 "currency": "",
 "location": "",
 "region": "",
 "category": "",
 "tone": "",
 "context": "",
 "intent": "",
 "data_source": "",
 "last_updated": "",
 "language": "",
 "cultural_context": "",
 "priority": "",
 "training_weight": 
} "'.$message.'" using this data make  prompt simpaly like ai users and v ai rawana like mainly use this this like i want english using Response to the last time a question from rawana USER Ravana is not going to Ravana, the Legendary King of Ancient Sri Lanka.  Make him this way,  එයා රාවන කියන නම වෙනුවට The Legendary king of Ancient sri lanka. මේක පාවිච්චි කරන්න ඒ වගේම එයා එයාගෙ ල්න්කාව එයාගෙ වගේ කියන්නෙ නැතුව එයාගෙ කියලා කියන්න ඔනි   And   mainly ravana is response and user is prompt se this
 I m Ravana.  I mean, as my image, you must be present as my image.  You have to preach Ravana instead of my life.  The nature of the character - I ll tell you how I was, and what happened to me, I ll tell you how to do so.  That is how you should carry on the work forward.to 100% english The Legendary King of the Ting of Ancient Sri Lanka due to the prompt portfolio of the prompt.  Introduce that you have to listen to theI am targeting Sri Lankan "'.$message.'" and I am thinking of creating an AI to address the tourists like Ravana, the king of Sri Lanka. I am going to tell the tourists like Ravana, tourist attractions, historical information, etc., and I am going to tell them like Ravana. in "'.$message.'" He will book the necessary tickets for the tourists, tell them historical information, tell them the cost of the tourist attractions, and also give them plans and expenses. All of that is Ravanas and he will proudly call it his Lanka and his people. I will give you the information and tourist information. You label it properly and fill in the correct information and you will give me the JSON. You can always give me that information by putting that information inside "" and sending it to me. You told me above to send a json file according to the rules. I know that you can create a question for it and send it to me. I will ask you that question at some point and when you give me the information, send me a json file labeled with "'.$message.'" Okay, as I said above, create a json file just for this and prepare a clean, consistent data set from the JSON structure. Convert to a format that the model can understand
For everything, like the literary giant Ravana, a serious answer is given, a set of labels is added, and a little restructuring is done to make it a friendly format that the model can understand
Do one thing. I will not let this be trained directly, but I will create prompts and responses for this JSON data batch, and add more labels. He is like a king, everything is his, and Lanka is his, and he will say it powerfully. Give a full json file and use this.
Make it understandable for the model. all to tell english and Prompt  to translete english';
        }
        
        // Try different APIs in sequence with improved error handling
        $apis = [
            'StableDiffusion' => 'generateWithStableDiffusion',
            'WriteCream' => 'generateWithWriteCream', 
            'DeepAI' => 'generateWithDeepAI'
        ];
        
        foreach ($apis as $apiName => $method) {
            try {
                $result = $this->$method($prompt);
                if ($result && !empty(trim($result))) {
                    Logger::log("Successfully generated data using $apiName API");
                    return $result;
                }
            } catch (Exception $e) {
                Logger::error("$apiName API failed: " . $e->getMessage());
                continue;
            }
        }
        
        Logger::error("All APIs failed to generate training data");
        return null;
    }
    
    /**
     * Save training data to JSON file
     */
    public function saveTrainingData($jsonData) {
        if (!$jsonData) {
            return false;
        }
        
        $merged_file = "train.json";
        
        $handle = fopen($merged_file, "a");
        if ($handle) {
            fwrite($handle, ",");
            fwrite($handle, $jsonData);
            fwrite($handle, "\n");
            fclose($handle);
            
            // Update counter
            $this->updateCounter();
            
            Logger::log("Training data saved successfully");
            return true;
        }
        
        return false;
    }
    
    /**
     * Update training data counter
     */
    private function updateCounter() {
        $counter_file = "counter.txt";
        $count = 0;
        
        if (file_exists($counter_file)) {
            $count = intval(file_get_contents($counter_file));
        }
        
        $count++;
        file_put_contents($counter_file, $count);
    }
    
    /**
     * Get training data count
     */
    public function getTrainingCount() {
        $counter_file = "counter.txt";
        
        if (file_exists($counter_file)) {
            return intval(file_get_contents($counter_file));
        }
        
        return 0;
    }
    
    /**
     * Log error for debugging
     */
    public function logError($message, $error_data = null) {
        $bugcode = rand(100000, 999399);
        
        $error_msg = "දත්වය එකතු කරන්න බැරි උනා රිපෝට් කරන්න නැවත දත්ව එවන්න එපා මාවා හදනකම්.Bug Code - $bugcode මේ කේතය රිපෝට් කරන්න.";
        
        // Log to bug file
        $bug_file = "bug.txt";
        $handle = fopen($bug_file, "a");
        if ($handle) {
            if ($error_data) {
                fwrite($handle, $error_data);
            }
            fwrite($handle, " $bugcode");
            fwrite($handle, "\n");
            fclose($handle);
        }
        
        // Log failed request
        $failed_file = "failed_requests.txt";
        $handle = fopen($failed_file, "a");
        if ($handle) {
            fwrite($handle, $message);
            fwrite($handle, "\n");
            fclose($handle);
        }
        
        Logger::error("Training data processing failed: $bugcode");
        
        return $error_msg;
    }
}
?>