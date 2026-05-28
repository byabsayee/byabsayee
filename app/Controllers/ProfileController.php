<?php
namespace App\Controllers;
use App\Helpers\Database;

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
             relationship_status, expertise, experience_years, designation, selected_book_id,
             working_since, website, profile_cv_headline, public_email, public_phone)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE
             bio=VALUES(bio), address=VALUES(address), city=VALUES(city), country=VALUES(country),
             profile_theme_color=VALUES(profile_theme_color), relationship_status=VALUES(relationship_status),
             expertise=VALUES(expertise), experience_years=VALUES(experience_years),
             designation=VALUES(designation), selected_book_id=VALUES(selected_book_id),
             working_since=VALUES(working_since), website=VALUES(website),
             profile_cv_headline=VALUES(profile_cv_headline), public_email=VALUES(public_email),
             public_phone=VALUES(public_phone)',
            [$user['id'], $bio ?: null, $address ?: null, $city ?: null, $country ?: null,
             $themeColor, $relStatus ?: null, $expertise ?: null, $expYears, $designation ?: null,
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
                      'relationship_status','expertise','experience_years','business','designation',
                      'working_since','website','headline'];

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

        try { $education = Database::query('SELECT * FROM user_education WHERE user_id=? ORDER BY sort_order', [$userId]); } catch(\Throwable $e) {}
        try { $grades    = Database::query('SELECT * FROM user_grades WHERE user_id=? ORDER BY sort_order', [$userId]); } catch(\Throwable $e) {}
        try { $social    = Database::query('SELECT * FROM user_social_links WHERE user_id=? ORDER BY sort_order', [$userId]); } catch(\Throwable $e) {}

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

    // ── Helpers ───────────────────────────────────────────────────────────────
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
