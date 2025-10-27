<?php
/**
 * Semaphore SMS API Integration Class
 * Handles sending SMS notifications via Semaphore API
 */
class SemaphoreAPI {
    private $api_key;
    private $api_url;
    private $sender_name;
    
    public function __construct($api_key = null, $api_url = null, $sender_name = null) {
        $this->api_key = $api_key ?: (defined('SEMAPHORE_API_KEY') ? SEMAPHORE_API_KEY : getenv('SEMAPHORE_API_KEY'));
        $this->api_url = $api_url ?: (defined('SEMAPHORE_API_URL') ? SEMAPHORE_API_URL : 'https://api.semaphore.co/api/v4/messages');
        $this->sender_name = $sender_name ?: (defined('SEMAPHORE_SENDER_NAME') ? SEMAPHORE_SENDER_NAME : 'MERS');
        
        // Validate API key is set
        if (empty($this->api_key) || $this->api_key === 'your_semaphore_api_key_here') {
            throw new Exception('Semaphore API key is not configured. Please set SEMAPHORE_API_KEY in config/semaphore.php or as an environment variable.');
        }
    }
    
    /**
     * Send SMS message to a single recipient
     * 
     * @param string $phone_number Phone number (format: 639XXXXXXXXX or 09XXXXXXXXX)
     * @param string $message SMS message content
     * @return array Response from API
     */
    public function sendSMS($phone_number, $message) {
        // Validate phone number format
        $phone_number = $this->formatPhoneNumber($phone_number);
        
        if (!$phone_number) {
            return [
                'success' => false,
                'error' => 'Invalid phone number format'
            ];
        }
        
        // Truncate message to 160 characters (SMS limit)
        $message = substr($message, 0, 160);
        
        $data = [
            'apikey' => $this->api_key,
            'number' => $phone_number,
            'message' => $message,
            'sendername' => $this->sender_name
        ];
        
        return $this->makeRequest($data);
    }
    
    /**
     * Send SMS to multiple recipients
     * 
     * @param array $phone_numbers Array of phone numbers
     * @param string $message SMS message content
     * @return array Array of responses for each recipient
     */
    public function sendBulkSMS($phone_numbers, $message) {
        $results = [];
        
        foreach ($phone_numbers as $phone) {
            $results[] = $this->sendSMS($phone, $message);
        }
        
        return $results;
    }
    
    /**
     * Format phone number to international format (639XXXXXXXXX)
     * 
     * @param string $phone Phone number in various formats
     * @return string|false Formatted phone number or false if invalid
     */
    private function formatPhoneNumber($phone) {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Handle different formats
        if (strlen($phone) === 10 && substr($phone, 0, 1) === '9') {
            // Format: 09XXXXXXXXX -> 639XXXXXXXXX
            return '63' . $phone;
        } elseif (strlen($phone) === 11 && substr($phone, 0, 2) === '09') {
            // Format: 09XXXXXXXXX -> 639XXXXXXXXX
            return '63' . substr($phone, 1);
        } elseif (strlen($phone) === 12 && substr($phone, 0, 2) === '63') {
            // Already in correct format: 639XXXXXXXXX
            return $phone;
        }
        
        return false;
    }
    
    /**
     * Make HTTP request to Semaphore API
     * 
     * @param array $data POST data
     * @return array API response
     */
    private function makeRequest($data) {
        try {
            $ch = curl_init();
            
            curl_setopt_array($ch, [
                CURLOPT_URL => $this->api_url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($data),
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/x-www-form-urlencoded'
                ]
            ]);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            
            curl_close($ch);
            
            if ($curl_error) {
                return [
                    'success' => false,
                    'error' => 'cURL Error: ' . $curl_error
                ];
            }
            
            $response_data = json_decode($response, true);
            
            if ($http_code === 200 && isset($response_data[0])) {
                return [
                    'success' => true,
                    'message_id' => $response_data[0]['message_id'] ?? null,
                    'status' => $response_data[0]['status'] ?? 'sent',
                    'response' => $response_data[0]
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $response_data['error'] ?? 'Failed to send SMS',
                    'http_code' => $http_code
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Exception: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get SMS account balance
     * 
     * @return array Account balance information
     */
    public function getBalance() {
        try {
            $ch = curl_init();
            
            curl_setopt_array($ch, [
                CURLOPT_URL => 'https://api.semaphore.co/api/v4/account?apikey=' . $this->api_key,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false
            ]);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            curl_close($ch);
            
            $response_data = json_decode($response, true);
            
            if ($http_code === 200) {
                return [
                    'success' => true,
                    'balance' => $response_data['credit_balance'] ?? 0,
                    'response' => $response_data
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Failed to retrieve balance'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Exception: ' . $e->getMessage()
            ];
        }
    }
}
