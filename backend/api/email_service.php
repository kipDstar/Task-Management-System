<?php
require_once __DIR__ . '/../config/email_config.php';

class EmailService {
    private $mailer;
    
    public function __construct() {
        if (!EMAIL_ENABLED) {
            return;
        }
        
        // For now, we'll use PHP's built-in mail function
        // In production, you'd use PHPMailer library
        $this->mailer = null;
    }
    
    public function sendEmail($to, $subject, $body, $isHtml = true) {
        if (!EMAIL_ENABLED) {
            error_log("Email would be sent to: $to - Subject: $subject");
            return true;
        }
        
        $headers = [];
        $headers[] = 'From: ' . FROM_NAME . ' <' . FROM_EMAIL . '>';
        $headers[] = 'Reply-To: ' . FROM_EMAIL;
        $headers[] = 'MIME-Version: 1.0';
        
        if ($isHtml) {
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
        } else {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        }
        
        $headers[] = 'X-Mailer: TaskFlow System';
        
        $result = mail($to, $subject, $body, implode("\r\n", $headers));
        
        if (!$result) {
            error_log("Failed to send email to: $to");
            return false;
        }
        
        return true;
    }
    
    public function sendTaskAssignment($task, $user) {
        $template = EmailTemplates::taskAssignment($task, $user);
        return $this->sendEmail($user['email'], $template['subject'], $template['body']);
    }
    
    public function sendTaskReminder($task, $user) {
        $template = EmailTemplates::taskReminder($task, $user);
        return $this->sendEmail($user['email'], $template['subject'], $template['body']);
    }
    
    public function sendDailyDigest($user, $tasks) {
        $template = EmailTemplates::dailyDigest($user, $tasks);
        return $this->sendEmail($user['email'], $template['subject'], $template['body']);
    }
    
    public function sendBulkEmail($users, $subject, $body) {
        $successCount = 0;
        foreach ($users as $user) {
            if ($this->sendEmail($user['email'], $subject, $body)) {
                $successCount++;
            }
        }
        return $successCount;
    }
}

// Global email service instance
function getEmailService() {
    static $emailService = null;
    if ($emailService === null) {
        $emailService = new EmailService();
    }
    return $emailService;
}
?> 