<?php
/**
 * Password Reset Helper Class
 * Handles password reset operations and validations
 */

class PasswordResetHelper {
    private $db;
    private $reset_code_length = 6;
    private $reset_code_expiry_minutes = 15;
    
    public function __construct($database_connection) {
        $this->db = $database_connection;
    }
    
    /**
     * Generate a random reset code
     * @return string 6-digit reset code
     */
    public function generateResetCode() {
        return str_pad(mt_rand(0, 999999), $this->reset_code_length, '0', STR_PAD_LEFT);
    }
    
    /**
     * Get reset code expiry time
     * @return string DateTime string for expiry
     */
    public function getResetCodeExpiry() {
        return date('Y-m-d H:i:s', strtotime("+{$this->reset_code_expiry_minutes} minutes"));
    }
    
    /**
     * Create a password reset request
     * @param int $user_id User ID
     * @param string $reset_code Reset code
     * @param string $expiry_time Expiry time
     * @return bool Success status
     */
    public function createResetRequest($user_id, $reset_code, $expiry_time) {
        try {
            $query = "UPDATE users SET password_reset_code = :code, password_reset_expiry = :expiry WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':code', $reset_code);
            $stmt->bindParam(':expiry', $expiry_time);
            $stmt->bindParam(':id', $user_id);
            
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("[PasswordResetHelper] Error creating reset request: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Validate reset code
     * @param string $email User email
     * @param string $reset_code Reset code to validate
     * @return array ['valid' => bool, 'message' => string, 'user_id' => int|null]
     */
    public function validateResetCode($email, $reset_code) {
        try {
            $query = "SELECT id, password_reset_code, password_reset_expiry FROM users WHERE email = :email";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                return [
                    'valid' => false,
                    'message' => 'Invalid email address.',
                    'user_id' => null
                ];
            }
            
            $user = $stmt->fetch();
            $user_id = $user['id'];
            
            // Check if reset code matches
            if ($user['password_reset_code'] !== $reset_code) {
                return [
                    'valid' => false,
                    'message' => 'Invalid reset code.',
                    'user_id' => null
                ];
            }
            
            // Check if reset code has expired
            if (strtotime($user['password_reset_expiry']) < time()) {
                return [
                    'valid' => false,
                    'message' => 'Reset code has expired. Please request a new one.',
                    'user_id' => null
                ];
            }
            
            return [
                'valid' => true,
                'message' => 'Reset code is valid.',
                'user_id' => $user_id
            ];
            
        } catch (Exception $e) {
            error_log("[PasswordResetHelper] Error validating reset code: " . $e->getMessage());
            return [
                'valid' => false,
                'message' => 'An error occurred while validating the reset code.',
                'user_id' => null
            ];
        }
    }
    
    /**
     * Validate password strength
     * @param string $password Password to validate
     * @return array ['valid' => bool, 'message' => string]
     */
    public function validatePassword($password) {
        if (empty($password)) {
            return [
                'valid' => false,
                'message' => 'Password is required.'
            ];
        }
        
        if (strlen($password) < 8) {
            return [
                'valid' => false,
                'message' => 'Password must be at least 8 characters long.'
            ];
        }
        
        // Optional: Add more password strength requirements
        // if (!preg_match('/[A-Z]/', $password)) {
        //     return ['valid' => false, 'message' => 'Password must contain at least one uppercase letter.'];
        // }
        // if (!preg_match('/[0-9]/', $password)) {
        //     return ['valid' => false, 'message' => 'Password must contain at least one number.'];
        // }
        
        return [
            'valid' => true,
            'message' => 'Password is valid.'
        ];
    }
    
    /**
     * Reset user password
     * @param int $user_id User ID
     * @param string $new_password New password (plain text)
     * @return bool Success status
     */
    public function resetPassword($user_id, $new_password) {
        try {
            $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
            
            $query = "UPDATE users SET password = :password, password_reset_code = NULL, password_reset_expiry = NULL WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':password', $hashed_password);
            $stmt->bindParam(':id', $user_id);
            
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("[PasswordResetHelper] Error resetting password: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Clear expired reset codes (cleanup function)
     * @return int Number of records updated
     */
    public function clearExpiredResetCodes() {
        try {
            $query = "UPDATE users SET password_reset_code = NULL, password_reset_expiry = NULL WHERE password_reset_expiry IS NOT NULL AND password_reset_expiry < NOW()";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            
            return $stmt->rowCount();
        } catch (Exception $e) {
            error_log("[PasswordResetHelper] Error clearing expired reset codes: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Check if user has an active reset request
     * @param string $email User email
     * @return bool True if active reset request exists
     */
    public function hasActiveResetRequest($email) {
        try {
            $query = "SELECT id FROM users WHERE email = :email AND password_reset_code IS NOT NULL AND password_reset_expiry > NOW()";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("[PasswordResetHelper] Error checking active reset request: " . $e->getMessage());
            return false;
        }
    }
}
?>
