<?php
namespace App\Controllers;
use App\Helpers\Database;

class BusinessProfileController
{
    // ── Edit business profile (book settings integration) ────────────────────
    public function edit(array $params): void
    {
        if (guest()) redirect('/login');
        $bookId = (int)$params['id'];
        $book   = $this->getBookOrFail($bookId);

        $handle  = Database::row('SELECT handle FROM business_handles WHERE book_id=?', [$bookId]);
        $profile = Database::row('SELECT * FROM business_profiles WHERE book_id=?', [$bookId]);
        if (!$profile) {
            Database::run('INSERT INTO business_profiles (book_id) VALUES (?)', [$bookId]);
            $profile = Database::row('SELECT * FROM business_profiles WHERE book_id=?', [$bookId]);
        }
        $externalLinks = json_decode($profile['external_links'] ?? '[]', true) ?? [];
        $photos        = json_decode($profile['photos'] ?? '[]', true) ?? [];
        $visibility    = json_decode($profile['visibility_flags'] ?? '{}', true) ?? [];

        $tab = $_GET['tab'] ?? 'info';
        $pageTitle = 'Business Profile — ' . e($book['name']);
        require BASE_PATH . '/views/profile/business-edit.php';
    }

    // ── Save basic business info ──────────────────────────────────────────────
    public function saveInfo(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $bookId = (int)$params['id'];
        $this->getBookOrFail($bookId);

        $data = [
            'tagline'          => trim($_POST['tagline'] ?? ''),
            'bio'              => trim($_POST['bio'] ?? ''),
            'theme_color'      => trim($_POST['theme_color'] ?? '#1a6b4a'),
            'founded_year'     => $_POST['founded_year'] ?: null,
            'ceo_name'         => trim($_POST['ceo_name'] ?? ''),
            'employee_count'   => trim($_POST['employee_count'] ?? ''),
            'industry'         => trim($_POST['industry'] ?? ''),
            'website'          => trim($_POST['website'] ?? ''),
            'whatsapp'         => trim($_POST['whatsapp'] ?? ''),
            'whatsapp_country' => trim($_POST['whatsapp_country'] ?? '+880'),
            'email'            => trim($_POST['email'] ?? ''),
            'phone'            => trim($_POST['phone'] ?? ''),
            'address'          => trim($_POST['address'] ?? ''),
            'city'             => trim($_POST['city'] ?? ''),
            'country'          => trim($_POST['country'] ?? ''),
        ];

        // Logo upload
        $logo = null;
        if (!empty($_FILES['logo']['name']) && $_FILES['logo']['error'] === 0) {
            $logo = $this->uploadImage('logo', $bookId, 'biz-logos');
        }
        // Banner upload
        $banner = null;
        if (!empty($_FILES['banner']['name']) && $_FILES['banner']['error'] === 0) {
            $banner = $this->uploadImage('banner', $bookId, 'biz-banners');
        }

        Database::run(
            'INSERT INTO business_profiles
             (book_id, tagline, bio, theme_color, founded_year, ceo_name, employee_count,
              industry, website, whatsapp, whatsapp_country, email, phone, address, city, country)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE
             tagline=VALUES(tagline), bio=VALUES(bio), theme_color=VALUES(theme_color),
             founded_year=VALUES(founded_year), ceo_name=VALUES(ceo_name),
             employee_count=VALUES(employee_count), industry=VALUES(industry),
             website=VALUES(website), whatsapp=VALUES(whatsapp), whatsapp_country=VALUES(whatsapp_country),
             email=VALUES(email), phone=VALUES(phone), address=VALUES(address),
             city=VALUES(city), country=VALUES(country)',
            [$bookId, $data['tagline']?:null, $data['bio']?:null, $data['theme_color'],
             $data['founded_year'], $data['ceo_name']?:null, $data['employee_count']?:null,
             $data['industry']?:null, $data['website']?:null, $data['whatsapp']?:null,
             $data['whatsapp_country'], $data['email']?:null, $data['phone']?:null,
             $data['address']?:null, $data['city']?:null, $data['country']?:null]
        );

        if ($logo)   Database::run('UPDATE business_profiles SET logo=? WHERE book_id=?', [$logo, $bookId]);
        if ($banner) Database::run('UPDATE business_profiles SET banner=? WHERE book_id=?', [$banner, $bookId]);

        redirect('/books/'.$bookId.'/business-profile?tab=info', ['success' => 'Business info saved.']);
    }

    // ── Save handle ───────────────────────────────────────────────────────────
    public function saveHandle(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $bookId = (int)$params['id'];
        $this->getBookOrFail($bookId);

        $handle = strtolower(trim(ltrim($_POST['handle'] ?? ''), '@'));
        if (!preg_match('/^[a-z0-9_]{3,50}$/', $handle)) {
            redirect('/books/'.$bookId.'/business-profile?tab=info', ['error' => 'Handle must be 3–50 chars: letters, numbers, underscores only.']);
        }

        $existing = Database::row('SELECT book_id FROM business_handles WHERE handle=?', [$handle]);
        if ($existing && $existing['book_id'] != $bookId) {
            redirect('/books/'.$bookId.'/business-profile?tab=info', ['error' => 'That handle is already taken.']);
        }

        try {
            if ($existing) {
                Database::run('UPDATE business_handles SET handle=? WHERE book_id=?', [$handle, $bookId]);
            } else {
                Database::run('INSERT INTO business_handles (book_id, handle) VALUES (?,?)', [$bookId, $handle]);
            }
        } catch (\Throwable $e) {
            redirect('/books/'.$bookId.'/business-profile?tab=info', ['error' => 'Handle save failed.']);
        }

        redirect('/books/'.$bookId.'/business-profile?tab=info', ['success' => '@'.$handle.' handle saved.']);
    }

    // ── Save pages (about, terms, privacy) ───────────────────────────────────
    public function savePages(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $bookId = (int)$params['id'];
        $this->getBookOrFail($bookId);

        Database::run(
            'INSERT INTO business_profiles (book_id, page_about, page_terms, page_privacy)
             VALUES (?,?,?,?)
             ON DUPLICATE KEY UPDATE page_about=VALUES(page_about), page_terms=VALUES(page_terms), page_privacy=VALUES(page_privacy)',
            [$bookId, $_POST['page_about']?:null, $_POST['page_terms']?:null, $_POST['page_privacy']?:null]
        );

        redirect('/books/'.$bookId.'/business-profile?tab=pages', ['success' => 'Pages saved.']);
    }

    // ── Save social links ─────────────────────────────────────────────────────
    public function saveSocial(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $bookId = (int)$params['id'];
        $this->getBookOrFail($bookId);

        $fields = ['social_facebook','social_instagram','social_twitter','social_linkedin','social_youtube','social_tiktok'];
        $updates = [];
        $uParams = [];
        foreach ($fields as $f) {
            $updates[] = "$f=?";
            $uParams[] = trim($_POST[$f] ?? '') ?: null;
        }

        // External links (name+url pairs)
        $extNames = $_POST['ext_name'] ?? [];
        $extUrls  = $_POST['ext_url'] ?? [];
        $extLinks = [];
        foreach ($extNames as $i => $name) {
            $name = trim($name); $url = trim($extUrls[$i] ?? '');
            if ($name && $url) $extLinks[] = ['label'=>$name,'url'=>$url];
        }
        $updates[] = 'external_links=?';
        $uParams[] = json_encode($extLinks);

        // Build column list and placeholder list for INSERT side
        $insertCols = array_merge(['book_id'], $fields, ['external_links']);
        $insertVals = array_merge([$bookId], array_map(fn($f) => trim($_POST[$f] ?? '') ?: null, $fields), [json_encode($extLinks)]);
        $placeholders = implode(',', array_fill(0, count($insertCols), '?'));
        $colList      = implode(',', $insertCols);

        Database::run(
            "INSERT INTO business_profiles ({$colList}) VALUES ({$placeholders})
             ON DUPLICATE KEY UPDATE " . implode(',', $updates),
            array_merge($insertVals, $uParams)
        );

        redirect('/books/'.$bookId.'/business-profile?tab=social', ['success' => 'Social links saved.']);
    }

    // ── Save visibility ───────────────────────────────────────────────────────
    public function saveVisibility(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $bookId = (int)$params['id'];
        $this->getBookOrFail($bookId);

        $allFields = ['tagline','bio','logo','banner','founded_year','ceo_name','employee_count',
                      'industry','email','phone','whatsapp','address','social','external_links',
                      'page_about','page_terms','page_privacy','photos'];
        $vis = [];
        foreach ($allFields as $f) { $vis[$f] = !empty($_POST['visible_'.$f]); }

        Database::run(
            'UPDATE business_profiles SET visibility_flags=? WHERE book_id=?',
            [json_encode($vis), $bookId]
        );

        redirect('/books/'.$bookId.'/business-profile?tab=visibility', ['success' => 'Visibility saved.']);
    }

    // ── Upload photo to gallery ───────────────────────────────────────────────
    public function uploadPhoto(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $bookId = (int)$params['id'];
        $this->getBookOrFail($bookId);

        if (empty($_FILES['photo']['name']) || $_FILES['photo']['error'] !== 0) {
            redirect('/books/'.$bookId.'/business-profile?tab=photos', ['error' => 'No file uploaded.']);
        }

        $path = $this->uploadImage('photo', $bookId, 'biz-photos');
        if (!$path) {
            redirect('/books/'.$bookId.'/business-profile?tab=photos', ['error' => 'Upload failed. Max 5MB, JPG/PNG/WEBP only.']);
        }

        $profile = Database::row('SELECT photos FROM business_profiles WHERE book_id=?', [$bookId]);
        $photos  = json_decode($profile['photos'] ?? '[]', true) ?? [];
        $photos[] = $path;
        Database::run('UPDATE business_profiles SET photos=? WHERE book_id=?', [json_encode($photos), $bookId]);

        redirect('/books/'.$bookId.'/business-profile?tab=photos', ['success' => 'Photo uploaded.']);
    }

    // ── Public business profile ───────────────────────────────────────────────
    public function publicProfile(array $params): void
    {
        $handle = strtolower(ltrim($params['handle'] ?? '', '@'));

        $handleRow = Database::row('SELECT book_id FROM business_handles WHERE handle=?', [$handle]);
        if (!$handleRow) {
            http_response_code(404);
            $pageTitle = 'Business Not Found';
            require BASE_PATH . '/views/errors/404.php';
            return;
        }

        $bookId   = $handleRow['book_id'];
        $book     = Database::row('SELECT * FROM books WHERE id=? AND deleted_at IS NULL', [$bookId]);
        $details  = Database::row('SELECT * FROM book_business_details WHERE book_id=?', [$bookId]);
        $profile  = Database::row('SELECT * FROM business_profiles WHERE book_id=?', [$bookId]) ?? [];
        $exLinks  = json_decode($profile['external_links'] ?? '[]', true) ?? [];
        $photos   = json_decode($profile['photos'] ?? '[]', true) ?? [];
        $vis      = json_decode($profile['visibility_flags'] ?? '{}', true) ?? [];

        $pageTitle = ($details['business_name'] ?? $book['name'] ?? 'Business') . ' — Byabsayee';
        require BASE_PATH . '/views/public/business-profile.php';
    }

    // ── Helpers ───────────────────────────────────────────────────────────────
    private function uploadImage(string $field, int $bookId, string $folder): ?string
    {
        $allowed = ['jpg','jpeg','png','webp','gif'];
        $file    = $_FILES[$field] ?? null;
        if (!$file || $file['error'] !== 0) return null;
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed) || $file['size'] > 5*1024*1024) return null;
        $dir  = config('upload.path') . '/' . $folder;
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $name = $folder.'_'.$bookId.'_'.date('Ymd_His').'_'.bin2hex(random_bytes(4)).'.'.$ext;
        if (move_uploaded_file($file['tmp_name'], $dir.'/'.$name)) {
            return $folder.'/'.$name;
        }
        return null;
    }

        private function getBookOrFail(int $id): array
    {
        $book = book_for_user($id, 'business');
        if (!$book) { http_response_code(404); require BASE_PATH.'/views/errors/404.php'; exit; }
        return $book;
    }
        if (!$book) { http_response_code(403); die('Access denied.'); }
        return $book;
    }
}
