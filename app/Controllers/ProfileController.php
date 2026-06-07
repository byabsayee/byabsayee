<?php
namespace App\Controllers;
use App\Helpers\Database;
use App\Helpers\Mailer;

class ProfileController
{
    // ── Edit profile page (authenticated user only) ────────────────────────────
    public function edit(array $params): void
    {
        if (guest()) redirect('/login');
        $user = auth();

        // Load full user row
        $user = Database::row('SELECT * FROM users WHERE id=?', [$user['id']]);

        // Load handle
        $handle = Database::row('SELECT handle FROM user_handles WHERE user_id=?', [$user['id']]);

        // Load extended profile
        $profile = Database::row('SELECT * FROM user_profiles WHERE user_id=?', [$user['id']]);
        if (!$profile) {
            Database::run('INSERT INTO user_profiles (user_id) VALUES (?)', [$user['id']]);
            $profile = Database::row('SELECT * FROM user_profiles WHERE user_id=?', [$user['id']]);
        }

        // Load education (multiple)
        try { $education = Database::query('SELECT * FROM user_education WHERE user_id=? ORDER BY sort_order,id', [$user['id']]); }
        catch (\Throwable $e) { $education = []; }

        // Load grades (multiple)
        try { $grades = Database::query('SELECT * FROM user_grades WHERE user_id=? ORDER BY sort_order,id', [$user['id']]); }
        catch (\Throwable $e) { $grades = []; }

        // Load social links
        try { $socialLinks = Database::query('SELECT * FROM user_social_links WHERE user_id=? ORDER BY sort_order,id', [$user['id']]); }
        catch (\Throwable $e) { $socialLinks = []; }

        // Load visibility settings
        $visRaw = [];
        try {
            $rows = Database::query('SELECT field_name,is_visible FROM user_profile_visibility WHERE user_id=?', [$user['id']]);
            foreach ($rows as $r) { $visRaw[$r['field_name']] = (bool)$r['is_visible']; }
        } catch (\Throwable $e) {}

        // Load user's books for business selector
        $myBooks = [];
        try {
            $myBooks = Database::query(
                'SELECT b.id, b.name, b.type, bd.designation
                 FROM books b
                 LEFT JOIN (
                     SELECT book_id, designation FROM employees
                     WHERE user_id=? AND deleted_at IS NULL
                 ) bd ON bd.book_id = b.id
                 WHERE b.user_id=? AND b.deleted_at IS NULL AND b.type="business"
                 UNION
                 SELECT b.id, b.name, b.type, e.designation
                 FROM books b
                 JOIN employees e ON e.book_id=b.id
                 WHERE e.user_id=? AND e.deleted_at IS NULL AND b.type="business"
                 ORDER BY name',
                [$user['id'], $user['id'], $user['id']]
            );
        } catch (\Throwable $e) { $myBooks = []; }

        // Load work experience
        $experience = [];
        try { $experience = Database::query('SELECT * FROM user_experience WHERE user_id=? ORDER BY sort_order,id', [$user['id']]); }
        catch (\Throwable $e) { $experience = []; }

        // Load security data (2FA methods, sessions)
        $securityData  = $this->loadSecurityData($user['id']);
        $activeMethods = $securityData['activeMethods'];
        $sessions      = $securityData['sessions'];

        $tab = $_GET['tab'] ?? 'basic';
        $pageTitle = 'My Profile';
        require BASE_PATH . '/views/profile/edit.php';
    }

    // ── Save basic info ───────────────────────────────────────────────────────
    public function saveBasic(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $user = auth();

        $name    = trim($_POST['name'] ?? '');
        $email   = strtolower(trim($_POST['email'] ?? ''));
        $phone   = trim($_POST['phone'] ?? '');
        $phoneCC = trim($_POST['phone_country_code'] ?? '+880');
        $waNum   = trim($_POST['whatsapp_number'] ?? '');
        $waCC    = trim($_POST['whatsapp_country_code'] ?? '+880');
        $dob     = $_POST['date_of_birth'] ?? null;
        $gender  = $_POST['gender'] ?? null;
        $blood   = $_POST['blood_group'] ?? null;

        if (strlen($name) < 2) redirect('/profile?tab=basic', ['error' => 'Name must be at least 2 characters.']);

        // Handle upload
        $avatarPath = null;
        if (!empty($_FILES['avatar']['name']) && $_FILES['avatar']['error'] === 0) {
            $avatarPath = $this->uploadImage('avatar', $user['id'], 'avatars');
        }

        $updateUser = 'UPDATE users SET name=?, email=?, phone=?, phone_country_code=?,
                       whatsapp_number=?, whatsapp_country_code=?, gender=?, date_of_birth=?, blood_group=?';
        $uParams = [$name, $email, $phone ?: null, $phoneCC, $waNum ?: null, $waCC,
                    $gender ?: null, $dob ?: null, $blood ?: null];
        if ($avatarPath) { $updateUser .= ', avatar=?'; $uParams[] = $avatarPath; }
        $updateUser .= ' WHERE id=?';
        $uParams[] = $user['id'];
        Database::run($updateUser, $uParams);

        // Sync session
        $_SESSION['user'] = Database::row('SELECT * FROM users WHERE id=?', [$user['id']]);

        redirect('/profile?tab=basic', ['success' => 'Basic info updated.']);
    }

    // ── Save handle ───────────────────────────────────────────────────────────
    public function saveHandle(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $user   = auth();
        $handle = strtolower(trim(ltrim($_POST['handle'] ?? ''), '@'));

        if (!preg_match('/^[a-z0-9_]{3,40}$/', $handle)) {
            redirect('/profile?tab=basic', ['error' => 'Handle must be 3–40 characters: letters, numbers, underscores only.']);
        }

        // Check uniqueness (excluding current user)
        $existing = Database::row('SELECT user_id FROM user_handles WHERE handle=?', [$handle]);
        if ($existing && $existing['user_id'] != $user['id']) {
            redirect('/profile?tab=basic', ['error' => 'That handle is already taken.']);
        }

        try {
            if ($existing && $existing['user_id'] == $user['id']) {
                Database::run('UPDATE user_handles SET handle=? WHERE user_id=?', [$handle, $user['id']]);
            } else {
                Database::run('INSERT INTO user_handles (user_id, handle) VALUES (?,?)', [$user['id'], $handle]);
            }
        } catch (\Throwable $e) {
            redirect('/profile?tab=basic', ['error' => 'Handle save failed: ' . $e->getMessage()]);
        }

        redirect('/profile?tab=basic', ['success' => 'Handle @'.$handle.' saved.']);
    }

    // ── Save extended profile ─────────────────────────────────────────────────
    public function saveProfile(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $user = auth();

        $bio           = trim($_POST['bio'] ?? '');
        $address       = trim($_POST['address'] ?? '');
        $city          = trim($_POST['city'] ?? '');
        $country       = trim($_POST['country'] ?? '');
        $themeColor    = trim($_POST['profile_theme_color'] ?? '#1a6b4a');
        $relStatus     = $_POST['relationship_status'] ?? null;
        $expertise     = trim($_POST['expertise'] ?? '');
        $languages     = trim($_POST['languages'] ?? '');
        $hobbies       = trim($_POST['hobbies'] ?? '');
        $expYears      = !empty($_POST['experience_years']) ? (int)$_POST['experience_years'] : null;
        $bookId        = !empty($_POST['selected_book_id']) ? (int)$_POST['selected_book_id'] : null;
        $workingSince  = $_POST['working_since'] ?? null;
        $website       = trim($_POST['website'] ?? '');
        $headline      = trim($_POST['profile_cv_headline'] ?? '');
        $pubEmail      = trim($_POST['public_email'] ?? '');
        $pubPhone      = trim($_POST['public_phone'] ?? '');

        // Auto-fill designation from selected book
        $designation = '';
        if ($bookId) {
            try {
                $emp = Database::row('SELECT designation FROM employees WHERE book_id=? AND user_id=? AND deleted_at IS NULL', [$bookId, $user['id']]);
                if ($emp) $designation = $emp['designation'] ?? '';
            } catch (\Throwable $e) {}
        }

        // Banner upload
        $bannerPath = null;
        if (!empty($_FILES['profile_banner']['name']) && $_FILES['profile_banner']['error'] === 0) {
            $bannerPath = $this->uploadImage('profile_banner', $user['id'], 'banners');
        }

        Database::run(
            'INSERT INTO user_profiles (user_id, bio, address, city, country, profile_theme_color,
             relationship_status, expertise, languages, hobbies, experience_years, designation, selected_book_id,
             working_since, website, profile_cv_headline, public_email, public_phone)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE
             bio=VALUES(bio), address=VALUES(address), city=VALUES(city), country=VALUES(country),
             profile_theme_color=VALUES(profile_theme_color), relationship_status=VALUES(relationship_status),
             expertise=VALUES(expertise), languages=VALUES(languages), hobbies=VALUES(hobbies),
             experience_years=VALUES(experience_years),
             designation=VALUES(designation), selected_book_id=VALUES(selected_book_id),
             working_since=VALUES(working_since), website=VALUES(website),
             profile_cv_headline=VALUES(profile_cv_headline), public_email=VALUES(public_email),
             public_phone=VALUES(public_phone)',
            [$user['id'], $bio ?: null, $address ?: null, $city ?: null, $country ?: null,
             $themeColor, $relStatus ?: null, $expertise ?: null, $languages ?: null, $hobbies ?: null,
             $expYears, $designation ?: null,
             $bookId, $workingSince ?: null, $website ?: null, $headline ?: null,
             $pubEmail ?: null, $pubPhone ?: null]
        );

        if ($bannerPath) {
            Database::run('UPDATE user_profiles SET profile_banner=? WHERE user_id=?', [$bannerPath, $user['id']]);
        }

        redirect('/profile?tab=profile', ['success' => 'Profile updated.']);
    }

    // ── Save education ────────────────────────────────────────────────────────
    public function saveEducation(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $user = auth();

        // Delete existing education and re-insert
        Database::run('DELETE FROM user_education WHERE user_id=?', [$user['id']]);

        $institutes = $_POST['institute'] ?? [];
        $subjects   = $_POST['subject'] ?? [];
        $fromYears  = $_POST['from_year'] ?? [];
        $toYears    = $_POST['to_year'] ?? [];
        $isCurrent  = $_POST['is_current'] ?? [];

        foreach ($institutes as $i => $inst) {
            $inst = trim($inst);
            if (!$inst) continue;
            Database::run(
                'INSERT INTO user_education (user_id, institute, subject, from_year, to_year, is_current, sort_order)
                 VALUES (?,?,?,?,?,?,?)',
                [$user['id'], $inst, trim($subjects[$i] ?? '') ?: null,
                 $fromYears[$i] ?: null, $toYears[$i] ?: null,
                 !empty($isCurrent[$i]) ? 1 : 0, $i]
            );
        }

        // Save grades
        Database::run('DELETE FROM user_grades WHERE user_id=?', [$user['id']]);
        $levels   = $_POST['grade_level'] ?? [];
        $results  = $_POST['grade_result'] ?? [];
        $boards   = $_POST['grade_board'] ?? [];
        $years    = $_POST['grade_year'] ?? [];
        foreach ($levels as $i => $level) {
            $level = trim($level);
            if (!$level) continue;
            Database::run(
                'INSERT INTO user_grades (user_id, level, result, board, year, sort_order) VALUES (?,?,?,?,?,?)',
                [$user['id'], $level, trim($results[$i] ?? '') ?: null,
                 trim($boards[$i] ?? '') ?: null, $years[$i] ?: null, $i]
            );
        }

        redirect('/profile?tab=education', ['success' => 'Education saved.']);
    }

    // ── Save social links ─────────────────────────────────────────────────────
    public function saveSocial(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $user = auth();

        Database::run('DELETE FROM user_social_links WHERE user_id=?', [$user['id']]);

        $platforms = $_POST['platform'] ?? [];
        $urls      = $_POST['social_url'] ?? [];
        foreach ($platforms as $i => $platform) {
            $platform = trim($platform);
            $url      = trim($urls[$i] ?? '');
            if (!$platform || !$url) continue;
            Database::run(
                'INSERT INTO user_social_links (user_id, platform, url, sort_order) VALUES (?,?,?,?)',
                [$user['id'], $platform, $url, $i]
            );
        }

        redirect('/profile?tab=social', ['success' => 'Social links saved.']);
    }

    // ── Save visibility ───────────────────────────────────────────────────────
    public function saveVisibility(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $user = auth();

        $allFields = ['name','email','phone','whatsapp_number','date_of_birth','gender',
                      'blood_group','address','bio','education','grades','social_links',
                      'relationship_status','expertise','languages','hobbies','experience_years','business','designation',
                      'working_since','website','headline','experience'];

        foreach ($allFields as $field) {
            $visible = !empty($_POST['visible_'.$field]) ? 1 : 0;
            Database::run(
                'INSERT INTO user_profile_visibility (user_id, field_name, is_visible)
                 VALUES (?,?,?) ON DUPLICATE KEY UPDATE is_visible=VALUES(is_visible)',
                [$user['id'], $field, $visible]
            );
        }

        redirect('/profile?tab=visibility', ['success' => 'Visibility settings saved.']);
    }

    // ── Public profile page ───────────────────────────────────────────────────
    public function publicProfile(array $params): void
    {
        $handle = strtolower(ltrim($params['handle'] ?? '', '@'));

        $handleRow = Database::row('SELECT user_id FROM user_handles WHERE handle=?', [$handle]);
        if (!$handleRow) {
            http_response_code(404);
            $pageTitle = 'Profile Not Found';
            require BASE_PATH . '/views/errors/404.php';
            return;
        }

        $userId = $handleRow['user_id'];
        $user   = Database::row('SELECT * FROM users WHERE id=?', [$userId]);

        // Load all profile data
        $profile  = Database::row('SELECT * FROM user_profiles WHERE user_id=?', [$userId]) ?? [];
        $education= [];
        $grades   = [];
        $social   = [];

        try { $education  = Database::query('SELECT * FROM user_education WHERE user_id=? ORDER BY sort_order', [$userId]); } catch(\Throwable $e) {}
        try { $grades     = Database::query('SELECT * FROM user_grades WHERE user_id=? ORDER BY sort_order', [$userId]); } catch(\Throwable $e) {}
        try { $social     = Database::query('SELECT * FROM user_social_links WHERE user_id=? ORDER BY sort_order', [$userId]); } catch(\Throwable $e) {}
        try { $experience = Database::query('SELECT * FROM user_experience WHERE user_id=? ORDER BY sort_order', [$userId]); } catch(\Throwable $e) { $experience = []; }

        // Visibility
        $vis = [];
        try {
            $rows = Database::query('SELECT field_name,is_visible FROM user_profile_visibility WHERE user_id=?', [$userId]);
            foreach ($rows as $r) { $vis[$r['field_name']] = (bool)$r['is_visible']; }
        } catch(\Throwable $e) {}

        // Business info
        $business = null;
        if (!empty($profile['selected_book_id'])) {
            try { $business = Database::row('SELECT b.*, bd.business_name FROM books b LEFT JOIN book_business_details bd ON bd.book_id=b.id WHERE b.id=?', [$profile['selected_book_id']]); } catch(\Throwable $e) {}
        }

        $pageTitle = ($user['name'] ?? 'Profile') . ' — Byabsayee';
        require BASE_PATH . '/views/public/user-profile.php';
    }

    // ── Save work experience ──────────────────────────────────────────────────
    public function saveExperience(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $user = auth();

        Database::run('DELETE FROM user_experience WHERE user_id=?', [$user['id']]);

        $orgs      = $_POST['exp_org']      ?? [];
        $titles    = $_POST['exp_title']    ?? [];
        $types     = $_POST['exp_type']     ?? [];
        $locations = $_POST['exp_location'] ?? [];
        $starts    = $_POST['exp_start']    ?? [];
        $ends      = $_POST['exp_end']      ?? [];
        $currents  = $_POST['exp_current']  ?? [];
        $descs     = $_POST['exp_desc']     ?? [];

        foreach ($orgs as $i => $org) {
            $org   = trim($org);
            $title = trim($titles[$i] ?? '');
            if (!$org || !$title) continue;
            $isCurrent = !empty($currents[$i]) ? 1 : 0;

            $startDate = $this->parseMonthDate(trim($starts[$i] ?? ''));
            $endDate   = $isCurrent ? null : $this->parseMonthDate(trim($ends[$i] ?? ''));
            Database::run(
                'INSERT INTO user_experience
                 (user_id, organisation, job_title, employment_type, location, start_date, end_date, is_current, description, sort_order)
                 VALUES (?,?,?,?,?,?,?,?,?,?)',
                [$user['id'], $org, $title,
                 trim($types[$i] ?? 'full_time') ?: 'full_time',
                 trim($locations[$i] ?? '') ?: null,
                 $startDate, $endDate, $isCurrent,
                 trim($descs[$i] ?? '') ?: null, $i]
            );
        }

        redirect('/profile?tab=experience', ['success' => 'Work experience saved.']);
    }

    // ── Security: change password (with optional 2FA step) ───────────────────
    public function changePassword(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $user    = auth();
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $row = Database::row('SELECT password_hash, two_fa_enabled FROM users WHERE id=?', [$user['id']]);

        // Always require current password first
        if (!password_verify($current, $row['password_hash'])) {
            redirect('/profile?tab=security', ['error' => 'Current password is incorrect.']);
        }
        if (strlen($new) < 8) {
            redirect('/profile?tab=security', ['error' => 'New password must be at least 8 characters.']);
        }
        if ($new !== $confirm) {
            redirect('/profile?tab=security', ['error' => 'Passwords do not match.']);
        }

        // If 2FA is enabled, require OTP verification too
        if (!empty($row['two_fa_enabled'])) {
            $otp = trim($_POST['tfa_code'] ?? '');
            if (!$otp) {
                // Store pending change in session, ask for 2FA code
                $_SESSION['pending_pwd_change'] = [
                    'hash' => password_hash($new, PASSWORD_DEFAULT),
                    'expires' => time() + 300,
                ];
                redirect('/profile?tab=security&pwd_2fa=1', ['success' => 'Password verified. Now enter your 2FA code to confirm.']);
            }
            // Verify OTP
            if (!$this->verifySecurityOtp($user['id'], $otp)) {
                redirect('/profile?tab=security', ['error' => '2FA code incorrect or expired.']);
            }
        }

        Database::run('UPDATE users SET password_hash=? WHERE id=?', [password_hash($new, PASSWORD_DEFAULT), $user['id']]);
        // Invalidate all other sessions for security
        $this->invalidateOtherSessions($user['id']);
        redirect('/profile?tab=security', ['success' => 'Password changed. All other sessions have been signed out.']);
    }

    // ── Security: confirm password-change with 2FA code ───────────────────────
    public function confirmPasswordChange(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $user    = auth();
        $pending = $_SESSION['pending_pwd_change'] ?? null;
        $otp     = trim($_POST['tfa_code'] ?? '');

        if (!$pending || $pending['expires'] < time()) {
            unset($_SESSION['pending_pwd_change']);
            redirect('/profile?tab=security', ['error' => 'Session expired. Please try again.']);
        }
        if (!$otp || !$this->verifySecurityOtp($user['id'], $otp)) {
            redirect('/profile?tab=security&pwd_2fa=1', ['error' => '2FA code incorrect or expired.']);
        }

        Database::run('UPDATE users SET password_hash=? WHERE id=?', [$pending['hash'], $user['id']]);
        unset($_SESSION['pending_pwd_change']);
        $this->invalidateOtherSessions($user['id']);
        redirect('/profile?tab=security', ['success' => 'Password changed. All other sessions have been signed out.']);
    }

    // ── Security: add/remove 2FA methods ─────────────────────────────────────
    public function saveSecurity2FA(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $user   = auth();
        $action = $_POST['action'] ?? '';

        // ── Always require password confirmation ──────────────────────────
        $confirmPwd = $_POST['confirm_password'] ?? '';
        $row = Database::row('SELECT password_hash FROM users WHERE id=?', [$user['id']]);
        if (!password_verify($confirmPwd, $row['password_hash'])) {
            redirect('/profile?tab=security', ['error' => 'Incorrect password. No changes were made.']);
        }

        $method = $_POST['two_fa_method'] ?? '';
        $validMethods = ['email', 'whatsapp', 'app'];

        if ($action === 'add_method' && in_array($method, $validMethods)) {
            // For TOTP app: verify code before enabling
            if ($method === 'app') {
                $totpCode = trim($_POST['totp_code'] ?? '');
                $secret   = null;
                try { $r = Database::row('SELECT secret FROM user_2fa_methods WHERE user_id=? AND method="app"', [$user['id']]); $secret = $r['secret'] ?? null; } catch (\Throwable $e) {}
                if (!$secret || !Mailer::verifyTotp($secret, $totpCode)) {
                    redirect('/profile?tab=security&setup_totp=1', ['error' => 'Authenticator code invalid. Open your app, check the code matches Byabsayee, then try again.']);
                }
                Database::run(
                    'INSERT INTO user_2fa_methods (user_id, method, secret, is_enabled) VALUES (?,?,?,1)
                     ON DUPLICATE KEY UPDATE is_enabled=1, secret=VALUES(secret)',
                    [$user['id'], 'app', $secret]
                );
            } else {
                // Require OTP verification for email/whatsapp before enabling
                $otpCode = trim($_POST['otp_code'] ?? '');
                if ($otpCode) {
                    if (!$this->verifySecurityOtp($user['id'], $otpCode, $method)) {
                        redirect('/profile?tab=security', ['error' => 'OTP code incorrect or expired.']);
                    }
                    try {
                        Database::run(
                            'INSERT INTO user_2fa_methods (user_id, method, is_enabled) VALUES (?,?,1)
                             ON DUPLICATE KEY UPDATE is_enabled=1',
                            [$user['id'], $method]
                        );
                    } catch (\Throwable $e) {}
                } else {
                    // Send OTP, redirect to enter it
                    $fullUser = Database::row('SELECT * FROM users WHERE id=?', [$user['id']]);
                    $tfc = new TwoFactorController();
                    $tfc->dispatchOtp($fullUser, $method);
                    redirect('/profile?tab=security&verify_method=' . $method . '&confirm_password=' . urlencode($confirmPwd),
                             ['success' => 'OTP sent. Enter it below to enable ' . ucfirst($method) . ' 2FA.']);
                }
            }
            // Enable global 2FA flag
            Database::run('UPDATE users SET two_fa_enabled=1 WHERE id=?', [$user['id']]);
            $_SESSION['user'] = Database::row('SELECT * FROM users WHERE id=?', [$user['id']]);
            redirect('/profile?tab=security', ['success' => ucfirst($method) . ' two-factor authentication enabled.']);
        }

        if ($action === 'remove_method' && in_array($method, $validMethods)) {
            try {
                Database::run('UPDATE user_2fa_methods SET is_enabled=0 WHERE user_id=? AND method=?', [$user['id'], $method]);
            } catch (\Throwable $e) {}
            // Also update legacy column
            if ($method === ($user['two_fa_method'] ?? '')) {
                Database::run('UPDATE users SET two_fa_method=NULL WHERE id=?', [$user['id']]);
            }
            // If no methods remain, disable 2FA entirely
            $remaining = 0;
            try { $r = Database::row('SELECT COUNT(*) as c FROM user_2fa_methods WHERE user_id=? AND is_enabled=1', [$user['id']]); $remaining = (int)($r['c'] ?? 0); } catch (\Throwable $e) {}
            if ($remaining === 0) {
                Database::run('UPDATE users SET two_fa_enabled=0, two_fa_method=NULL WHERE id=?', [$user['id']]);
            }
            $_SESSION['user'] = Database::row('SELECT * FROM users WHERE id=?', [$user['id']]);
            redirect('/profile?tab=security', ['success' => ucfirst($method) . ' 2FA method removed.']);
        }

        if ($action === 'disable_all') {
            try { Database::run('UPDATE user_2fa_methods SET is_enabled=0 WHERE user_id=?', [$user['id']]); } catch (\Throwable $e) {}
            Database::run('UPDATE users SET two_fa_enabled=0, two_fa_method=NULL WHERE id=?', [$user['id']]);
            $_SESSION['user'] = Database::row('SELECT * FROM users WHERE id=?', [$user['id']]);
            redirect('/profile?tab=security', ['success' => 'All two-factor authentication methods disabled.']);
        }

        redirect('/profile?tab=security');
    }

    // ── Security: sessions ────────────────────────────────────────────────────
    public function securitySessions(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $user   = auth();
        $action = $_POST['action'] ?? '';

        // Require password confirmation
        $confirmPwd = $_POST['confirm_password'] ?? '';
        $row = Database::row('SELECT password_hash FROM users WHERE id=?', [$user['id']]);
        if (!password_verify($confirmPwd, $row['password_hash'])) {
            redirect('/profile?tab=security', ['error' => 'Incorrect password. Sessions were not cleared.']);
        }

        if ($action === 'logout_all') {
            $this->invalidateOtherSessions($user['id']);
            redirect('/profile?tab=security', ['success' => 'All other sessions have been signed out.']);
        }

        redirect('/profile?tab=security');
    }

    // ── Load security tab data ────────────────────────────────────────────────
    // ── Security: revoke a single session ────────────────────────────────────
    public function revokeSession(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $user     = auth();
        $targetId = $_POST['session_db_id'] ?? null;
        if (!$targetId) redirect('/profile?tab=security');

        try {
            $sess = Database::row('SELECT * FROM user_sessions WHERE id=? AND user_id=?', [$targetId, $user['id']]);
            if ($sess && $sess['session_id'] !== session_id()) {
                $otherSessId = $sess['session_id'];

                // 1. Remove from DB so the heartbeat won't re-insert it
                Database::run('DELETE FROM user_sessions WHERE id=?', [$targetId]);

                // 2. Physically destroy the PHP session file so the other browser
                //    gets logged out on their very next request
                $currentSessId = session_id();
                session_write_close();
                session_id($otherSessId);
                session_start();
                $_SESSION = [];
                session_destroy();
                session_id($currentSessId);
                session_start();
            }
        } catch (\Throwable $e) {
            error_log('[revokeSession] ' . $e->getMessage());
        }

        redirect('/profile?tab=security', ['success' => 'Session signed out.']);
    }

    private function loadSecurityData(int $userId): array
    {
        $activeMethods = [];
        try {
            $rows = Database::query('SELECT method, secret FROM user_2fa_methods WHERE user_id=? AND is_enabled=1', [$userId]);
            foreach ($rows as $r) $activeMethods[$r['method']] = $r;
        } catch (\Throwable $e) {}

        // Fallback: populate from legacy column
        if (empty($activeMethods)) {
            $u = Database::row('SELECT two_fa_enabled, two_fa_method FROM users WHERE id=?', [$userId]);
            if (!empty($u['two_fa_enabled']) && !empty($u['two_fa_method'])) {
                $activeMethods[$u['two_fa_method']] = ['method' => $u['two_fa_method']];
            }
        }

        $sessions = [];
        try {
            $sessions = Database::query(
                'SELECT * FROM user_sessions WHERE user_id=? ORDER BY last_active_at DESC',
                [$userId]
            );
        } catch (\Throwable $e) {}

        return ['activeMethods' => $activeMethods, 'sessions' => $sessions];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────
    private function verifySecurityOtp(int $userId, string $code, ?string $method = null): bool
    {
        // Try TOTP first
        if (!$method || $method === 'app') {
            $secret = null;
            try { $r = Database::row('SELECT secret FROM user_2fa_methods WHERE user_id=? AND method="app" AND is_enabled=1', [$userId]); $secret = $r['secret'] ?? null; } catch (\Throwable $e) {}
            if (!$secret) { try { $r = Database::row('SELECT secret FROM two_factor_auth WHERE user_id=?', [$userId]); $secret = $r['secret'] ?? null; } catch (\Throwable $e) {} }
            if ($secret && Mailer::verifyTotp($secret, $code)) return true;
        }
        // Try OTP (email/whatsapp)
        $targetMethod = $method ?: null;
        try {
            $q = $targetMethod
                ? 'SELECT otp_code, otp_expires FROM user_2fa_methods WHERE user_id=? AND method=?'
                : 'SELECT otp_code, otp_expires FROM user_2fa_methods WHERE user_id=? AND is_enabled=1 AND otp_code IS NOT NULL';
            $binds = $targetMethod ? [$userId, $targetMethod] : [$userId];
            $rows  = Database::query($q, $binds);
            foreach ($rows as $r) {
                if ($r['otp_code'] === $code && $r['otp_expires'] && strtotime($r['otp_expires']) > time()) {
                    Database::run('UPDATE user_2fa_methods SET otp_code=NULL WHERE user_id=? AND otp_code=?', [$userId, $code]);
                    return true;
                }
            }
        } catch (\Throwable $e) {}
        // Legacy fallback
        try {
            $tfa = Database::row('SELECT otp_code, otp_expires FROM two_factor_auth WHERE user_id=?', [$userId]);
            if ($tfa && $tfa['otp_code'] === $code && $tfa['otp_expires'] && strtotime($tfa['otp_expires']) > time()) {
                Database::run('UPDATE two_factor_auth SET otp_code=NULL WHERE user_id=?', [$userId]);
                return true;
            }
        } catch (\Throwable $e) {}
        return false;
    }

    private function invalidateOtherSessions(int $userId): void
    {
        try {
            Database::run(
                'DELETE FROM user_sessions WHERE user_id=? AND session_id!=?',
                [$userId, session_id()]
            );
        } catch (\Throwable $e) {}
    }

    // ── Generate CV PDF ───────────────────────────────────────────────────────
    public function generateCvPdf(array $params): void
    {
        if (guest()) redirect('/login');
        $user = auth();

        $user    = Database::row('SELECT * FROM users WHERE id=?', [$user['id']]);
        $profile = Database::row('SELECT * FROM user_profiles WHERE user_id=?', [$user['id']]) ?? [];
        $handle  = Database::row('SELECT handle FROM user_handles WHERE user_id=?', [$user['id']]);

        $education  = [];
        $grades     = [];
        $experience = [];
        $social     = [];

        try { $education  = Database::query('SELECT * FROM user_education WHERE user_id=? ORDER BY sort_order', [$user['id']]); } catch (\Throwable $e) {}
        try { $grades     = Database::query('SELECT * FROM user_grades WHERE user_id=? ORDER BY sort_order', [$user['id']]); } catch (\Throwable $e) {}
        try { $experience = Database::query('SELECT * FROM user_experience WHERE user_id=? ORDER BY sort_order', [$user['id']]); } catch (\Throwable $e) {}
        try { $social     = Database::query('SELECT * FROM user_social_links WHERE user_id=? ORDER BY sort_order', [$user['id']]); } catch (\Throwable $e) {}

        $business = null;
        if (!empty($profile['selected_book_id'])) {
            try { $business = Database::row('SELECT b.*, bd.business_name FROM books b LEFT JOIN book_business_details bd ON bd.book_id=b.id WHERE b.id=?', [$profile['selected_book_id']]); } catch (\Throwable $e) {}
        }

        // Avatar as base64 for mPDF
        $avatarB64 = '';
        if (!empty($user['avatar'])) {
            $avatarPath = config('upload.path') . '/' . $user['avatar'];
            if (file_exists($avatarPath)) {
                $ext  = strtolower(pathinfo($avatarPath, PATHINFO_EXTENSION));
                $mime = in_array($ext, ['jpg','jpeg']) ? 'image/jpeg' : ($ext === 'png' ? 'image/png' : 'image/webp');
                $avatarB64 = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($avatarPath));
            }
        }

        $socialIconMap = [
            'linkedin'=>'in','github'=>'gh','facebook'=>'fb','instagram'=>'ig',
            'twitter'=>'tw','youtube'=>'yt','website'=>'web','tiktok'=>'tk',
        ];

        $empTypeLabels = [
            'full_time'=>'Full-time','part_time'=>'Part-time','contract'=>'Contract',
            'freelance'=>'Freelance','internship'=>'Internship','volunteer'=>'Volunteer',
        ];

        $name     = $user['name'] ?? 'Unknown';
        $headline = $profile['profile_cv_headline'] ?? '';
        $email    = $profile['public_email'] ?? $user['email'] ?? '';
        $phone    = $profile['public_phone'] ?? (($user['phone_country_code'] ?? '') . ' ' . ($user['phone'] ?? ''));
        $location = implode(', ', array_filter([$profile['city'] ?? '', $profile['country'] ?? '']));
        $website  = $profile['website'] ?? '';
        $bio      = $profile['bio'] ?? '';
        $skills   = $profile['expertise'] ?? '';
        $langs    = $profile['languages'] ?? '';
        $hobbies  = $profile['hobbies'] ?? '';
        $expYears = $profile['experience_years'] ?? '';
        $dob      = !empty($user['date_of_birth']) ? date('d M Y', strtotime($user['date_of_birth'])) : '';
        $blood    = $user['blood_group'] ?? '';
        $gender   = !empty($user['gender']) ? ucfirst(str_replace('_',' ', $user['gender'])) : '';
        $themeColor = $profile['profile_theme_color'] ?? '#1a6b4a';

        $socialIcons = ['linkedin'=>'fa-linkedin','github'=>'fa-github','facebook'=>'fa-facebook',
                        'instagram'=>'fa-instagram','twitter'=>'fa-x-twitter','youtube'=>'fa-youtube',
                        'tiktok'=>'fa-tiktok','website'=>'fa-globe'];

        ob_start();
        ?><!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>

@page { margin: 12mm 14mm; }

body {
    font-family: DejaVuSans, sans-serif;
    font-size: 9pt;
    color: #222;
    line-height: 1.5;
    position: relative;
    min-height: 100vh;
}

/* HEADER */
.header {
    border-bottom: 2px solid <?= $themeColor ?>;
    padding-bottom: 10pt;
    margin-bottom: 12pt;
}

.name {
    font-size: 22pt;
    font-weight: bold;
    color: #111;
}

.headline {
    font-size: 11pt;
    color: <?= $themeColor ?>;
    font-weight: 600;
    margin-top: -80px;
}

.contact {
    margin-top: 6pt;
    font-size: 8.5pt;
    color: #555;
}

/* LAYOUT */
.top {
    width: 100%;
    overflow: hidden;
    margin-bottom: 15pt;
}

.bottom {
    width: 100%;
    clear: both;
}

.left {
    width: 48%;
    float: left;
}

.right {
    width: 48%;
    float: right;
}

/* SECTION */
.section {
    margin-bottom: 12pt;
}

.section-title {
    font-size: 9pt;
    font-weight: bold;
    color: <?= $themeColor ?>;
    margin-bottom: 6pt;
    text-transform: uppercase;
    letter-spacing: 0.5pt;
    border-bottom: 1px solid #ddd;
    padding-bottom: 3pt;
}

/* ITEMS */
.item {
    margin-bottom: 8pt;
}

.item-title {
    font-weight: bold;
    font-size: 9.5pt;
}

.item-sub {
    color: <?= $themeColor ?>;
    font-size: 8.5pt;
    font-weight: 600;
}

.item-meta {
    font-size: 7.5pt;
    color: #888;
}

.item-desc {
    font-size: 8pt;
    color: #444;
}

/* SKILLS */
.skill {
    background-color: <?= $themeColor ?>;
    color: #ffffff;
    font-size: 8px;
    font-weight: bold;
    border: 3pt solid <?= $themeColor ?>;  
}

.dits{
    line-height: 1.8;
}

/* SMALL INFO */
.info {
    font-size: 8pt;
    margin-bottom: 4pt;
}

/* AVATAR */
.avatar {
    float: right;
    width: 100pt;
    height: 100pt;
    border-radius: 50%;
    object-fit: cover;
    border: 2pt solid <?= $themeColor ?>;
}

.grade-table { 
    width: 100%; 
    border-collapse: collapse; 
    font-size: 8pt; 
}
.grade-table th { 
    background: <?= $themeColor ?>; 
    color: #ffffff; 
    font-weight: bold; 
    padding: 3pt 5pt; 
    text-align: left; 
    font-size: 7.5pt; 
    text-transform: uppercase; 
}
.grade-table td { 
    padding: 3pt 5pt; 
    border-bottom: 0.3pt solid #eee; 
    color: #333; 
}
.grade-table tr:last-child td { 
    border-bottom: none; 
}

.watermark {
    position: absolute;
    bottom: 40px;
    left: 0;
    width: 100%;
    text-align: center;
    font-size: 10px;
    color: #555;
}

</style>
</head>

<body>

<div class="header">
    <?php if ($avatarB64): ?>
        <img src="<?= $avatarB64 ?>" class="avatar">
    <?php endif; ?>

    <div class="name"><?= htmlspecialchars($name) ?></div>
    <div class="headline"><?= htmlspecialchars($headline) ?></div>

    <div class="contact">
        <?php if ($email): ?><span><?= $email ?></span><?php endif; ?>
            <br>
        <?php if ($phone): ?><span><?= $phone ?></span><?php endif; ?>
            <br>
        <?php if ($location): ?><span><?= $location ?></span><?php endif; ?>
            <br>
        <?php if ($website): ?><span><?= $website ?></span><?php endif; ?>
    </div>
</div>

<div class="top">

    <!-- LEFT COLUMN -->
    <div class="left">

        <?php if ($bio): ?>
        <div class="section">
            <div class="section-title">Profile</div>
            <div class="item-desc"><?= nl2br(htmlspecialchars($bio)) ?></div>
        </div>
        <?php endif; ?>

        <div class="section">
            <div class="section-title">Details</div>

            <?php if ($expYears): ?><div class="info">Experience: <?= $expYears ?> years</div><?php endif; ?>
            <?php if ($dob): ?><div class="info">DOB: <?= $dob ?></div><?php endif; ?>
            <?php if ($gender): ?><div class="info">Gender: <?= $gender ?></div><?php endif; ?>
            <?php if ($blood): ?><div class="info">Blood: <?= $blood ?></div><?php endif; ?>
            <?php if ($langs): ?><div class="info">Languages: <?= $langs ?></div><?php endif; ?>
            <?php if ($hobbies): ?><div class="info">Hobbies: <?= $hobbies ?></div><?php endif; ?>

        </div>

    </div>

    <!-- RIGHT COLUMN -->
    <div class="right">

        <?php if ($skills): ?>
        <div class="section">
            <div class="section-title">Skills</div>
            <div class="dits">
                <?php foreach (explode(',', $skills) as $skill): ?>
                    <span class="skill">&nbsp;<?= trim(htmlspecialchars($skill)) ?>&nbsp;</span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($experience)): ?>
        <div class="section">
            <div class="section-title">Experience</div>

            <?php foreach ($experience as $exp): ?>
            <div class="item">
                <div class="item-title"><?= htmlspecialchars($exp['job_title']) ?></div>
                <div class="item-sub"><?= htmlspecialchars($exp['organisation']) ?></div>
                <div class="item-meta">
                    <?= $exp['start_date'] ?> - <?= $exp['is_current'] ? 'Present' : $exp['end_date'] ?>
                </div>
                <?php if (!empty($exp['description'])): ?>
                    <div class="item-desc"><?= nl2br(htmlspecialchars($exp['description'])) ?></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>

        </div>
        <?php endif; ?>

    </div>

</div>

<!-- BOTTOM FULL-WIDTH SECTION -->
<div class="bottom">

    <?php if (!empty($education)): ?>
    <div class="section">
        <div class="section-title">Education</div>

        <?php foreach ($education as $edu): ?>
        <div class="item">
            <div class="item-title"><?= htmlspecialchars($edu['institute']) ?></div>
            <div class="item-sub"><?= htmlspecialchars($edu['subject']) ?></div>
            <div class="item-meta">
                <?= $edu['from_year'] ?> - <?= $edu['is_current'] ? 'Present' : $edu['to_year'] ?>
            </div>
        </div>
        <?php endforeach; ?>

        <?php if (!empty($grades)): ?>
        <table class="grade-table" style="margin-top: 15px;">
            <tr><th>Level</th><th>Result</th><th>Board</th><th>Year</th></tr>
            <?php foreach ($grades as $g): ?>
            <tr>
            <td><?= htmlspecialchars($g['level']) ?></td>
            <td><strong style="color:<?= $themeColor ?>"><?= htmlspecialchars($g['result'] ?? '—') ?></strong></td>
            <td><?= htmlspecialchars($g['board'] ?? '') ?></td>
            <td><?= htmlspecialchars($g['year'] ?? '') ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>

<div class="watermark">
    Generated using Byabsayee <img src="/favicon-32x32.png" alt="Byabsayee" onerror="this.style.display='none'" style="margin-left: 5px; vertical-align: middle; width: 15px;">
</div>

</body>
</html>
<?php
        $html = ob_get_clean();

        try {
            $mpdf = new \Mpdf\Mpdf([
                'mode'             => 'utf-8',
                'format'           => 'A4',
                'margin_top'       => 0,
                'margin_bottom'    => 0,
                'margin_left'      => 0,
                'margin_right'     => 0,
                'tempDir'          => sys_get_temp_dir(),
                'default_font'     => 'dejavusans',
                'useSubstitutions' => true,
            ]);
            $mpdf->SetTitle(($name ?? 'CV') . ' — Curriculum Vitae');
            $mpdf->SetAuthor($name ?? 'Byabsayee');
            $mpdf->SetSubject('Curriculum Vitae');
            $mpdf->showWatermarkText = false;
            $mpdf->WriteHTML($html);
            $safeName = preg_replace('/[^a-z0-9\-]/i', '-', $name);
            $mpdf->Output('CV-' . $safeName . '.pdf', 'D'); // D = download
        } catch (\Throwable $e) {
            header('Content-Type: text/html; charset=utf-8');
            echo '<div style="font-family:sans-serif;padding:40px;max-width:600px">'
                . '<h3 style="color:#c00">CV PDF Error</h3>'
                . '<p>' . htmlspecialchars($e->getMessage()) . '</p>'
                . '<p>Please ensure mPDF is installed via <code>composer install</code>.</p></div>';
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Accepts any month/date string the browser might send and returns
     * a clean "YYYY-MM-01" string, or null if unparseable.
     * Handles: "YYYY-MM", "YYYY-MM-01", locale variants like "12 | 02 | 2020", etc.
     */
    private function parseMonthDate(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') return null;

        // Strip every non-digit character, leaving only numbers
        $digits = preg_replace('/\D+/', ' ', $raw);
        $parts  = array_values(array_filter(explode(' ', $digits), fn($p) => $p !== ''));

        if (empty($parts)) return null;

        // Sort parts: identify year (4-digit) and month (1-2 digit)
        $year  = null;
        $month = null;
        foreach ($parts as $p) {
            if (strlen($p) === 4 && (int)$p >= 1900 && (int)$p <= 2100) {
                $year = (int)$p;
            } elseif ($month === null && (int)$p >= 1 && (int)$p <= 12) {
                $month = (int)$p;
            }
        }

        // Fallback: if no 4-digit year found, try strtotime on the raw string
        if ($year === null) {
            $ts = strtotime($raw);
            if ($ts === false) return null;
            return date('Y-m-01', $ts);
        }

        if ($month === null) $month = 1;

        return sprintf('%04d-%02d-01', $year, $month);
    }
    private function uploadImage(string $field, int $userId, string $folder): ?string
    {
        $allowed = ['jpg','jpeg','png','webp','gif'];
        $file    = $_FILES[$field] ?? null;
        if (!$file || $file['error'] !== 0) return null;
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed) || $file['size'] > 5*1024*1024) return null;
        $dir  = config('upload.path') . '/' . $folder;
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $name = $folder.'_'.$userId.'_'.date('Ymd_His').'_'.bin2hex(random_bytes(4)).'.'.$ext;
        if (move_uploaded_file($file['tmp_name'], $dir.'/'.$name)) {
            return $folder.'/'.$name;
        }
        return null;
    }
}
