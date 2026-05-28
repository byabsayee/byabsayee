<?php
namespace App\Controllers;
use App\Helpers\Database;
use App\Helpers\Mailer;

class SettingsController
{
    public function index(): void
    {
        if (guest()) redirect('/login');
        $tab  = $_GET['tab'] ?? 'profile';
        $user = auth();
        require BASE_PATH . '/views/settings/index.php';
    }

    public function updateProfile(): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $user = auth();
        $name  = trim($_POST['name']  ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if (!$name)  redirect('/settings?tab=profile', ['error' => 'Name is required.']);
        if (!$email) redirect('/settings?tab=profile', ['error' => 'Email is required.']);

        // Check email uniqueness
        $exists = Database::row('SELECT id FROM users WHERE email=? AND id!=?', [$email, $user['id']]);
        if ($exists) redirect('/settings?tab=profile', ['error' => 'That email is already in use.']);

        Database::run(
            'UPDATE users SET name=?, email=?, phone=? WHERE id=?',
            [$name, $email, $phone ?: null, $user['id']]
        );
        redirect('/settings?tab=profile', ['success' => 'Profile updated.']);
    }

    public function updatePassword(): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $user    = auth();
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $row = Database::row('SELECT password_hash FROM users WHERE id=?', [$user['id']]);
        if (!password_verify($current, $row['password_hash'])) {
            redirect('/settings?tab=password', ['error' => 'Current password is incorrect.']);
        }
        if (strlen($new) < 8) {
            redirect('/settings?tab=password', ['error' => 'New password must be at least 8 characters.']);
        }
        if ($new !== $confirm) {
            redirect('/settings?tab=password', ['error' => 'Passwords do not match.']);
        }

        Database::run('UPDATE users SET password_hash=? WHERE id=?', [password_hash($new, PASSWORD_DEFAULT), $user['id']]);
        redirect('/settings?tab=password', ['success' => 'Password changed successfully.']);
    }

    public function updatePreferences(): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $user         = auth();
        $theme        = $_POST['theme']    ?? 'light';
        $language     = $_POST['language'] ?? 'en';
        $dateFormat   = $_POST['date_format']   ?? 'Y-m-d';
        $timezone     = $_POST['timezone']       ?? 'Asia/Dhaka';
        $currency     = $_POST['currency']       ?? 'BDT';
        $notifications= !empty($_POST['email_notifications']) ? 1 : 0;
        $twoFa        = !empty($_POST['two_fa'])               ? 1 : 0;

        try {
            Database::run(
                'UPDATE users SET theme=?, language=?, date_format=?, timezone=?, default_currency=?,
                 email_notifications=?, two_fa_enabled=? WHERE id=?',
                [$theme, $language, $dateFormat, $timezone, $currency,
                 $notifications, $twoFa, $user['id']]
            );
        } catch (\Throwable $e) {
            // Columns may not exist yet — store as JSON in a prefs column if available
            try {
                $prefs = json_encode(compact('theme','language','dateFormat','timezone','currency','notifications','twoFa'));
                Database::run('UPDATE users SET preferences=? WHERE id=?', [$prefs, $user['id']]);
            } catch (\Throwable $e2) { /* silently skip */ }
        }
        redirect('/settings?tab=preferences', ['success' => 'Preferences saved.']);
    }

    public function deleteAccount(): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $user    = auth();
        $confirm = trim($_POST['confirm_delete'] ?? '');
        if ($confirm !== 'DELETE') {
            redirect('/settings?tab=account', ['error' => 'Type DELETE to confirm account deletion.']);
        }
        // Soft-delete all books, then user
        Database::run('UPDATE books SET deleted_at=? WHERE user_id=?', [now(), $user['id']]);
        Database::run('UPDATE users SET deleted_at=?, email=? WHERE id=?',
            [now(), $user['email'].'__deleted_'.time(), $user['id']]);
        session_destroy();
        redirect('/login', ['success' => 'Your account has been deleted.']);
    }

    public function contactUs(): void
    {
        if (guest()) redirect('/login');
        csrf_verify();

        $user    = auth();
        $name    = trim($_POST['name']    ?? $user['name']  ?? '');
        $email   = trim($_POST['email']   ?? $user['email'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');

        if (!$name || !$email || !$subject || !$message) {
            redirect('/settings?tab=contact', ['error' => 'All fields are required.']);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            redirect('/settings?tab=contact', ['error' => 'Please enter a valid email address.']);
        }

        $appName    = getenv('APP_NAME') ?: 'Byabsayee';
        $supportTo  = getenv('SUPPORT_EMAIL') ?: getenv('SMTP_FROM') ?: getenv('SMTP_USER') ?: '';
        $appUrl     = rtrim(getenv('APP_URL') ?: 'https://byabsayee.com', '/');

        // Build the support email
        $html = Mailer::render('contact-support', [
            'senderName'  => $name,
            'senderEmail' => $email,
            'subject'     => $subject,
            'message'     => $message,
            'appName'     => $appName,
            'appUrl'      => $appUrl,
            'userId'      => $user['id'],
        ]);

        $sent = Mailer::send(
            ['email' => $supportTo, 'name' => $appName . ' Support'],
            "[Contact] {$subject} — from {$name}",
            $html
        );

        // Also send a confirmation email to the user
        if ($sent) {
            $confirmHtml = Mailer::render('contact-support-confirm', [
                'name'    => $name,
                'subject' => $subject,
                'message' => $message,
                'appName' => $appName,
                'appUrl'  => $appUrl,
            ]);
            Mailer::send(
                ['email' => $email, 'name' => $name],
                "[{$appName}] We received your message",
                $confirmHtml
            );
        }

        if ($sent) {
            redirect('/settings?tab=contact', ['success' => 'Your message has been sent! We\'ll get back to you soon.']);
        } else {
            redirect('/settings?tab=contact', ['error' => 'Failed to send your message. Please try again or email us directly.']);
        }
    }
}
