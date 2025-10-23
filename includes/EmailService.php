<?php
/**
 * Email Service Class - EmailJS Integration (Server-to-Server)
 * Handles sending emails using EmailJS REST API (with Private Key)
 * 
 * EmailJS API Docs: https://www.emailjs.com/docs/rest-api/send/
 */

require_once __DIR__ . '/../config/emailjs.php';

class EmailService {
    private $service_id;
    private $template_id;
    private $private_key;
    private $api_url = 'https://api.emailjs.com/api/v1.0/email/send';

    public function __construct() {
        // Load EmailJS config from constants or environment
        $this->service_id = defined('EMAILJS_SERVICE_ID') ? EMAILJS_SERVICE_ID : getenv('EMAILJS_SERVICE_ID');
        $this->template_id = defined('EMAILJS_TEMPLATE_ID') ? EMAILJS_TEMPLATE_ID : getenv('EMAILJS_TEMPLATE_ID');
        $this->private_key = defined('EMAILJS_PRIVATE_KEY') ? EMAILJS_PRIVATE_KEY : getenv('EMAILJS_PRIVATE_KEY');

        // Validate
        if (empty($this->service_id) || empty($this->template_id) || empty($this->private_key)) {
            error_log("[EmailJS] ERROR: Missing EmailJS credentials.");
            error_log("[EmailJS] Service ID: " . (empty($this->service_id) ? 'MISSING' : 'OK'));
            error_log("[EmailJS] Template ID: " . (empty($this->template_id) ? 'MISSING' : 'OK'));
            error_log("[EmailJS] Private Key: " . (empty($this->private_key) ? 'MISSING' : 'OK (length: ' . strlen($this->private_key) . ')'));
        }
    }

    /**
     * Send email using EmailJS API
     * Added comprehensive debugging and improved error handling
     */
    public function sendEmail($to_email, $to_name, $subject, $message) {
        try {
            if (empty($this->service_id) || empty($this->template_id) || empty($this->private_key)) {
                error_log("[EmailJS] ERROR: EmailJS credentials not configured.");
                return false;
            }

            $template_params = [
                'to_email' => $to_email,
                'to_name' => $to_name,
                'subject' => $subject,
                'message' => $message
            ];

            $payload = [
                'service_id' => $this->service_id,
                'template_id' => $this->template_id,
                'accessToken' => $this->private_key,
                'template_params' => $template_params
            ];

            $json_payload = json_encode($payload);
            
            error_log("[EmailJS] Sending email to: $to_email");
            error_log("[EmailJS] Payload size: " . strlen($json_payload) . " bytes");

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->api_url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json_payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($json_payload)
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_VERBOSE, false);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            $curl_errno = curl_errno($ch);
            curl_close($ch);

            error_log("[EmailJS] HTTP Code: $http_code");
            error_log("[EmailJS] Response: $response");

            if ($curl_error) {
                error_log("[EmailJS] CURL Error ($curl_errno): $curl_error");
                return false;
            }

            if ($http_code === 200) {
                error_log("[EmailJS] Email sent successfully to $to_email");
                return true;
            } else {
                error_log("[EmailJS] Failed to send email. HTTP Code: $http_code | Response: $response");
                
                $response_data = json_decode($response, true);
                if (isset($response_data['message'])) {
                    error_log("[EmailJS] Error Message: " . $response_data['message']);
                }
                
                return false;
            }

        } catch (Exception $e) {
            error_log("[EmailJS] Exception: " . $e->getMessage());
            error_log("[EmailJS] Stack Trace: " . $e->getTraceAsString());
            return false;
        }
    }

    /** Password Reset Email */
    public function sendPasswordResetEmail($email, $name, $reset_code) {
        $subject = 'Password Reset Code - MERS';
        $message = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <h2 style='color: #333;'>Password Reset Request</h2>
            <p>Hello {$name},</p>
            <p>We received a request to reset your password for the Mobile Emergency Response System.</p>
            <div style='background-color: #f0f0f0; padding: 20px; border-radius: 5px; text-align: center; margin: 20px 0;'>
                <p>Your password reset code is:</p>
                <p style='font-size: 36px; font-weight: bold; color: #007bff;'>{$reset_code}</p>
                <p>This code will expire in 15 minutes.</p>
            </div>
            <p>Do not share this code with anyone.</p>
        </div>";
        return $this->sendEmail($email, $name, $subject, $message);
    }

    /** Verification Email */
    public function sendVerificationEmail($email, $name, $verification_link) {
        $subject = 'Email Verification - MERS';
        $message = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <h2>Email Verification Required</h2>
            <p>Hello {$name},</p>
            <p>Please verify your email by clicking the button below:</p>
            <div style='text-align: center; margin: 30px 0;'>
                <a href='{$verification_link}' style='background-color: #007bff; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px;'>Verify Email</a>
            </div>
            <p>This link will expire in 24 hours.</p>
        </div>";
        return $this->sendEmail($email, $name, $subject, $message);
    }

    /** Alert Email */
    public function sendAlertEmail($email, $name, $alert_title, $alert_description) {
        $subject = 'Emergency Alert - MERS';
        $message = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <h2 style='color: #dc3545;'>⚠️ Emergency Alert</h2>
            <p>Hello {$name},</p>
            <div style='background-color: #fff3cd; border-left: 4px solid #dc3545; padding: 15px; margin: 20px 0;'>
                <p><strong>Alert:</strong> {$alert_title}</p>
                <p>{$alert_description}</p>
            </div>
            <p>Please take appropriate action and stay safe.</p>
        </div>";
        return $this->sendEmail($email, $name, $subject, $message);
    }
}
