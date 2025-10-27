<?php
/**
 * Password Reset Helper Class
 * Handles password reset token generation and validation
 */

class PasswordResetHelper {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Generate a password reset token
     */
    public function generateResetToken() {
        return bin2hex(random_bytes(32));
    }
    
    /**
     * Create a password reset request
     */
    public function createResetRequest($user_id, $email) {
        try {
            $token = $this->generateResetToken();
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            $query = "UPDATE users SET password_reset_token = :token, password_reset_expiry = :expiry WHERE id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':token', $token);
            $stmt->bindParam(':expiry', $expiry);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            
            return [
                'success' => true,
                'token' => $token,
                'expiry' => $expiry
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Validate a password reset token
     */
    public function validateResetToken($email, $token) {
        try {
            $query = "SELECT id FROM users WHERE email = :email AND password_reset_token = :token AND password_reset_expiry > NOW()";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':token', $token);
            $stmt->execute();
            
            if ($stmt->rowCount() == 1) {
                return [
                    'success' => true,
                    'user_id' => $stmt->fetch()['id']
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Invalid or expired token'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Reset password using token
     */
    public function resetPassword($email, $token, $new_password) {
        try {
            // Validate token first
            $validation = $this->validateResetToken($email, $token);
            if (!$validation['success']) {
                return $validation;
            }
            
            $user_id = $validation['user_id'];
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $query = "UPDATE users SET password = :password, password_reset_token = NULL, password_reset_expiry = NULL WHERE id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':password', $hashed_password);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            
            return [
                'success' => true,
                'message' => 'Password reset successfully'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Clear expired reset codes
     */
    public function clearExpiredResetCodes() {
        try {
            $query = "UPDATE users SET password_reset_token = NULL, password_reset_expiry = NULL WHERE password_reset_expiry < NOW() AND password_reset_token IS NOT NULL";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            
            return $stmt->rowCount();
        } catch (Exception $e) {
            return 0;
        }
    }
}
?>
