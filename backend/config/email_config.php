<?php
// email configuration file
define('EMAIL_ENABLED', true);
define('SMTP_HOST', 'smtp.example.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
define('SMTP_secure', 'tls');
define('FROM_EMAIL', 'noreply@taskflow.com');
define('FROM_NAME', 'TaskFlow System');

class EmailTemplates {
    public static function taskAssignment($task, $user) {
        return [
            'subject' => "New Task Assigned: {$task['title']}",
            'body' => "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                    <h2 style='color: #667eea;'>New Task Assigned</h2>
                    <p>Hello {$user['first_name']},</p>
                    <p>A new task has been assigned to you:</p>
                    
                    <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                        <h3 style='margin-top: 0; color: #333;'>{$task['title']}</h3>
                        <p><strong>Description:</strong> " . ($task['description'] ?: 'No description') . "</p>
                        <p><strong>Priority:</strong> <span style='color: " . self::getPriorityColor($task['priority']) . ";'>{$task['priority']}</span></p>
                        <p><strong>Due Date:</strong> " . ($task['due_date'] ?: 'No due date') . "</p>
                        <p><strong>Project:</strong> " . ($task['project_name'] ?: 'No project') . "</p>
                    </div>
                    
                    <p>Please log in to your TaskFlow account to view and update this task.</p>
                    
                    <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;'>
                        <p style='color: #666; font-size: 14px;'>Best regards,<br>TaskFlow Team</p>
                    </div>
                </div>
            "
        ];
    }

    public static function taskReminder($task, $user) {
        return [
            'subject' => "Task Reminder: [{$task['title']}]",
            'body' => "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                    <h2 style='color: #f56565; '>Task Reminder</h2>
                    <p>Hello {$user['first_name']},</p>
                    <p>This is a reminder about the upcoming task:</p>
                    <div style='background: #fff3cd; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #ffc107;'>
                        <h3 style='margin-top: 0; color: #333;'>{$task['title']}</h3>
                        <p><strong>Due date:</strong> {$task['due_date']}</p>
                        <p><strong>Priority:</strong> <span style='color: " . self::getPriorityColor($task['priority']) . ";'>{$task['priority']}</span></p>
                        <p><strong>Status:</strong> {$task['status']}</p>
                    </div>

                    <p>Please ensure to complete this task by the due date, update the task status or contact your team lead if you need any assistance.</p>

                    <div style=margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;>
                        <p style='color: #666; font-size: 14px; '>Best Regards,<br>TaskFlow Team</p>
                    </div>
                </div>
            "
        ];
    }

    public static function dailyDigest($user, $tasks) {
        $completedCount = count(array_filter($tasks, fn($t) => $t['status'] === 'completed'));
        $pendingCount = count(array_filter($tasks, fn($t) => $t['status'] === 'pending'));
        $overdueCount = count(array_filter($tasks, fn($t) => $t['status'] === 'pending' && $t['due_date'] && $t['due_date'] < date('Y-m-d')));
        
        return [
            'subject' => "Daily Task Summary - " . date('M j, Y'),
            'body' => "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                    <h2 style='color: #667eea;'>Daily Task Summary</h2>
                    <p>Hello {$user['first_name']},</p>
                    <p>Here's your task summary for " . date('M j, Y') . ":</p>
                    
                    <div style='display: flex; gap: 20px; margin: 20px 0;'>
                        <div style='flex: 1; background: #d4edda; padding: 15px; border-radius: 8px; text-align: center;'>
                            <h3 style='margin: 0; color: #155724;'>{$completedCount}</h3>
                            <p style='margin: 5px 0 0 0; color: #155724;'>Completed</p>
                        </div>
                        <div style='flex: 1; background: #fff3cd; padding: 15px; border-radius: 8px; text-align: center;'>
                            <h3 style='margin: 0; color: #856404;'>{$pendingCount}</h3>
                            <p style='margin: 5px 0 0 0; color: #856404;'>Pending</p>
                        </div>
                        <div style='flex: 1; background: #f8d7da; padding: 15px; border-radius: 8px; text-align: center;'>
                            <h3 style='margin: 0; color: #721c24;'>{$overdueCount}</h3>
                            <p style='margin: 5px 0 0 0; color: #721c24;'>Overdue</p>
                        </div>
                    </div>
                    
                    <p>Log in to TaskFlow to view and manage your tasks.</p>
                    
                    <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;'>
                        <p style='color: #666; font-size: 14px;'>Best regards,<br>TaskFlow Team</p>
                    </div>
                </div>
            "
        ];
    }
    
    private static function getPriorityColor($priority) {
        switch ($priority) {
            case 'high': return '#dc3545';
            case 'medium': return '#ffc107';
            case 'low': return '#28a745';
            default: return '#6c757d';
        }
    }
}
?> 
