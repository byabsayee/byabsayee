<?php
// =============================================================================
// app/Controllers/EmployeeController.php
// Handles: employees, designations, invitations, book_members
// =============================================================================

namespace App\Controllers;

use App\Helpers\Database;

class EmployeeController
{
    // =========================================================================
    // PERMISSION DEFINITIONS
    // All modules and their available actions
    // =========================================================================
    public static function permissionModules(): array
    {
        return [
            // ── Financial ──────────────────────────────────────────────
            'invoices'            => ['view', 'create', 'edit', 'delete', 'record_payment'],
            'funds'               => ['view', 'create', 'edit', 'delete'],
            'expenses'            => ['view', 'create', 'edit', 'delete'],
            'dues'                => ['view', 'create', 'edit', 'delete', 'pay'],
            'debts'               => ['view', 'create', 'edit', 'delete', 'pay'],
            // ── Inventory & Sales ──────────────────────────────────────
            'products'            => ['view', 'create', 'edit', 'delete', 'adjust_stock'],
            'returns'             => ['view', 'create', 'delete'],
            'coupons'             => ['view', 'create', 'edit', 'delete'],
            'deliveries'          => ['view', 'create', 'edit', 'delete'],
            // ── Contacts ──────────────────────────────────────────────
            'customers'           => ['view', 'create', 'edit', 'delete'],
            'suppliers'           => ['view', 'create', 'edit', 'delete'],
            'contacts'            => ['view', 'create', 'edit', 'delete'],
            // ── People ────────────────────────────────────────────────
            'employees'           => ['view', 'create', 'edit', 'delete', 'invite', 'manage_designations'],
            // ── Reporting & Admin ──────────────────────────────────────
            'reports'             => ['view'],
            'logs'                => ['view'],
            'book_settings'       => ['view', 'edit', 'delete'],
        ];
    }

    public static function defaultPermissions(): array
    {
        $perms = [];
        foreach (self::permissionModules() as $mod => $actions) {
            foreach ($actions as $action) {
                $perms[$mod][$action] = false;
            }
        }
        return $perms;
    }

    // =========================================================================
    // LIST  →  GET /books/{id}/employees
    // =========================================================================
    public function index(array $params): void
    {
        if (guest()) redirect('/login');
        $book = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'employees', 'view')) abort_403();

        $employees = [];
        try {
            $employees = Database::query(
                'SELECT e.*, u.name AS user_name, u.email AS user_email, u.status AS user_status
                 FROM employees e
                 LEFT JOIN users u ON u.id = e.user_id
                 WHERE e.book_id = ? AND e.deleted_at IS NULL
                 ORDER BY e.name ASC',
                [$book['id']]
            );
        } catch (\Throwable $e) {}

        $designations = [];
        try {
            $designations = Database::query(
                'SELECT d.*, COUNT(e.id) AS employee_count
                 FROM designations d
                 LEFT JOIN employees e ON e.designation_id = d.id AND e.deleted_at IS NULL
                 WHERE d.book_id = ?
                 GROUP BY d.id ORDER BY d.name',
                [$book['id']]
            );
        } catch (\Throwable $e) {}

        $pending_invitations = [];
        try {
            $pending_invitations = Database::query(
                'SELECT ei.*, u.name AS inviter_name, uu.name AS invitee_name
                 FROM employee_invitations ei
                 LEFT JOIN users u  ON u.id  = ei.invited_by
                 LEFT JOIN users uu ON uu.id = ei.user_id
                 WHERE ei.book_id = ? AND ei.status = "pending"
                 ORDER BY ei.created_at DESC',
                [$book['id']]
            );
        } catch (\Throwable $e) {}

        $modules = self::permissionModules();

        require BASE_PATH . '/views/business/employees/index.php';
    }

    // =========================================================================
    // SHOW EMPLOYEE  →  GET /books/{id}/employees/{employee_id}
    // =========================================================================
    public function show(array $params): void
    {
        if (guest()) redirect('/login');
        $book     = $this->getBookOrFail($params['id']);
        $employee = $this->getEmployeeOrFail($params['employee_id'], $book['id']);

        $member = null;
        if ($employee['user_id']) {
            $member = Database::row(
                'SELECT * FROM book_members WHERE book_id=? AND user_id=?',
                [$book['id'], $employee['user_id']]
            );
        }

        $designations = Database::query(
            'SELECT * FROM designations WHERE book_id=? ORDER BY name',
            [$book['id']]
        );

        $modules = self::permissionModules();

        require BASE_PATH . '/views/business/employees/show.php';
    }

    // =========================================================================
    // ADD EMPLOYEE (offline, no account)  →  POST /books/{id}/employees/add
    // =========================================================================
    public function store(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $book = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'employees', 'create')) abort_403();

        $name = trim($_POST['name'] ?? '');
        if (!$name) redirect('/books/'.$book['id'].'/employees', ['error' => 'Name is required.']);

        $desigId   = $_POST['designation_id'] ? (int)$_POST['designation_id'] : null;
        $desigName = trim($_POST['designation_name'] ?? '') ?: null;

        // Resolve or auto-create designation
        if ($desigId) {
            $d = Database::row('SELECT name, permissions FROM designations WHERE id=? AND book_id=?', [$desigId, $book['id']]);
            if ($d) {
                $desigName   = $d['name'];
                $permissions = json_decode($d['permissions'] ?? '{}', true) ?? self::defaultPermissions();
            }
        } elseif ($desigName) {
            // Auto-save new designation so it persists for future use
            $existing = Database::row('SELECT id, permissions FROM designations WHERE book_id=? AND name=?', [$book['id'], $desigName]);
            if ($existing) {
                $desigId     = (int)$existing['id'];
                $permissions = json_decode($existing['permissions'] ?? '{}', true) ?? self::defaultPermissions();
            } else {
                Database::run('INSERT INTO designations (book_id, name, permissions) VALUES (?,?,?)',
                    [$book['id'], $desigName, json_encode(self::defaultPermissions())]);
                $desigId = (int)Database::get()->lastInsertId();
                $permissions = self::defaultPermissions();
            }
        }

        if (!isset($permissions)) $permissions = self::defaultPermissions();

        // Generate unique employee code for this book (e.g. EMP-0001)
        $empCode = 'EMP-0001';
        try {
            $last = Database::row(
                "SELECT emp_code FROM employees WHERE book_id=? AND emp_code IS NOT NULL ORDER BY id DESC LIMIT 1",
                [$book['id']]
            );
            if ($last && preg_match('/(\d+)$/', $last['emp_code'], $m)) {
                $empCode = 'EMP-' . str_pad((int)$m[1] + 1, 4, '0', STR_PAD_LEFT);
            }
        } catch (\Throwable $e) {}

        Database::run(
            'INSERT INTO employees
             (book_id, emp_code, designation_id, designation_name, name, phone, email, address,
              department, join_date, salary, salary_type, notes, status, created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $book['id'], $empCode, $desigId, $desigName, $name,
                trim($_POST['phone']       ?? '') ?: null,
                trim($_POST['email']       ?? '') ?: null,
                trim($_POST['address']     ?? '') ?: null,
                trim($_POST['department']  ?? '') ?: null,
                trim($_POST['join_date']   ?? '') ?: null,
                trim($_POST['salary']      ?? '') ?: null,
                $_POST['salary_type'] ?? 'monthly',
                trim($_POST['notes']       ?? '') ?: null,
                $_POST['status'] ?? 'active',
                now()
            ]
        );

        $empId = (int)\App\Helpers\Database::get()->lastInsertId();

        // Auto-save department if new
        if (!empty($_POST['department'])) {
            $dept = trim($_POST['department']);
            // (department is stored per-employee; nothing extra needed — datalist pulls from employees table)
        }

        \App\Services\ActivityLogger::write(
            $book['id'], auth()['id'],
            'employee.created', 'Employee', $empId,
            $name . ' added as employee' . ($desigName ? ' (' . $desigName . ')' : '')
        );

        redirect('/books/'.$book['id'].'/employees', ['success' => $name.' added as employee.']);
    }

    // =========================================================================
    // EDIT EMPLOYEE  →  POST /books/{id}/employees/{employee_id}/edit
    // =========================================================================
    public function update(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $book     = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'employees', 'edit')) abort_403();
        $employee = $this->getEmployeeOrFail($params['employee_id'], $book['id']);

        $desigId   = !empty($_POST['designation_id']) ? (int)$_POST['designation_id'] : null;
        $desigName = trim($_POST['designation_name'] ?? '') ?: null;
        if ($desigId) {
            $d = Database::row('SELECT name FROM designations WHERE id=? AND book_id=?', [$desigId, $book['id']]);
            if ($d) $desigName = $d['name'];
        }

        Database::run(
            'UPDATE employees SET designation_id=?, designation_name=?, name=?, phone=?, email=?,
             address=?, department=?, join_date=?, salary=?, salary_type=?, notes=?, status=?
             WHERE id=? AND book_id=?',
            [
                $desigId, $desigName,
                trim($_POST['name']        ?? ''),
                trim($_POST['phone']       ?? '') ?: null,
                trim($_POST['email']       ?? '') ?: null,
                trim($_POST['address']     ?? '') ?: null,
                trim($_POST['department']  ?? '') ?: null,
                trim($_POST['join_date']   ?? '') ?: null,
                trim($_POST['salary']      ?? '') ?: null,
                $_POST['salary_type'] ?? 'monthly',
                trim($_POST['notes']       ?? '') ?: null,
                $_POST['status'] ?? 'active',
                $employee['id'], $book['id']
            ]
        );

        redirect('/books/'.$book['id'].'/employees/'.$employee['id'], ['success' => 'Employee updated.']);
    }

    // =========================================================================
    // DELETE EMPLOYEE  →  POST /books/{id}/employees/{employee_id}/delete
    // =========================================================================
    public function delete(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $book     = $this->getBookOrFail($params['id']);
        $employee = $this->getEmployeeOrFail($params['employee_id'], $book['id']);

        Database::run('UPDATE employees SET deleted_at=? WHERE id=? AND book_id=?', [now(), $employee['id'], $book['id']]);

        redirect('/books/'.$book['id'].'/employees', ['success' => e($employee['name']).' removed.']);
    }

    // =========================================================================
    // UPDATE EMPLOYEE PERMISSIONS  →  POST /books/{id}/employees/{employee_id}/permissions
    // =========================================================================
    public function updatePermissions(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $book     = $this->getBookOrFail($params['id']);
        $employee = $this->getEmployeeOrFail($params['employee_id'], $book['id']);

        $permissions = $this->parsePermissionsFromPost();

        // Update book_member if they have a login
        if ($employee['user_id']) {
            $member = Database::row('SELECT id FROM book_members WHERE book_id=? AND user_id=?', [$book['id'], $employee['user_id']]);
            if ($member) {
                Database::run('UPDATE book_members SET permissions=? WHERE id=?', [json_encode($permissions), $member['id']]);
            }
        }

        redirect('/books/'.$book['id'].'/employees/'.$employee['id'], ['success' => 'Permissions updated.']);
    }

    // =========================================================================
    // SEND INVITATION  →  POST /books/{id}/employees/invite
    // =========================================================================
    public function invite(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $book = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'employees', 'invite')) abort_403();

        $email    = strtolower(trim($_POST['email'] ?? ''));
        $desigId  = !empty($_POST['designation_id']) ? (int)$_POST['designation_id'] : null;
        $desigName= trim($_POST['designation_name'] ?? '') ?: null;

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            redirect('/books/'.$book['id'].'/employees', ['error' => 'Invalid email address.']);
        }

        // Check if already a member
        $existingMember = Database::row(
            'SELECT bm.id FROM book_members bm JOIN users u ON u.id=bm.user_id WHERE bm.book_id=? AND u.email=?',
            [$book['id'], $email]
        );
        if ($existingMember) {
            redirect('/books/'.$book['id'].'/employees', ['error' => 'This user is already a member of this book.']);
        }

        // Check for pending invitation
        $existing = Database::row(
            'SELECT id FROM employee_invitations WHERE book_id=? AND email=? AND status="pending"',
            [$book['id'], $email]
        );
        if ($existing) {
            redirect('/books/'.$book['id'].'/employees', ['error' => 'A pending invitation already exists for this email.']);
        }

        // Resolve designation
        $permissions = $this->parsePermissionsFromPost();
        if ($desigId) {
            $d = Database::row('SELECT name, permissions FROM designations WHERE id=? AND book_id=?', [$desigId, $book['id']]);
            if ($d) {
                $desigName   = $d['name'];
                $permissions = json_decode($d['permissions'], true) ?? $permissions;
            }
        }

        // Find user by email
        $invitedUser = Database::row('SELECT id FROM users WHERE email=? AND deleted_at IS NULL', [$email]);

        $token   = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+7 days'));

        Database::run(
            'INSERT INTO employee_invitations
             (book_id, invited_by, email, user_id, designation_id, designation_name, permissions, token, expires_at)
             VALUES (?,?,?,?,?,?,?,?,?)',
            [
                $book['id'], auth()['id'], $email,
                $invitedUser['id'] ?? null,
                $desigId, $desigName,
                json_encode($permissions),
                $token, $expires
            ]
        );

        // Create in-app notification if user exists
        if ($invitedUser) {
            $bookDetails = Database::row('SELECT * FROM book_business_details WHERE book_id=?', [$book['id']]);
            $bookName    = $bookDetails['business_name'] ?? $book['name'];
            $inviter     = auth();

            Database::run(
                'INSERT INTO notifications (user_id, book_id, type, title, body, action_url, data, created_at)
                 VALUES (?,?,?,?,?,?,?,?)',
                [
                    $invitedUser['id'], $book['id'],
                    'invitation',
                    'Book Invitation',
                    $inviter['name'].' invited you to join "'.$bookName.'"'
                        . ($desigName ? ' as '.$desigName : '').'. Accept or decline below.',
                    '/invitations/'.$token,
                    json_encode(['token' => $token, 'book_id' => $book['id']]),
                    now()
                ]
            );
        }

        // Send email (try, don't fail if email not configured)
        $this->sendInvitationEmail($email, $token, $book, $desigName);

        redirect('/books/'.$book['id'].'/employees', ['success' => 'Invitation sent to '.$email.'.']);
    }

    // =========================================================================
    // CANCEL INVITATION  →  POST /books/{id}/employees/invitations/{inv_id}/cancel
    // =========================================================================
    public function cancelInvitation(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $book = $this->getBookOrFail($params['id']);

        Database::run(
            'UPDATE employee_invitations SET status="expired" WHERE id=? AND book_id=?',
            [$params['inv_id'], $book['id']]
        );

        redirect('/books/'.$book['id'].'/employees', ['success' => 'Invitation cancelled.']);
    }

    // =========================================================================
    // ACCEPT INVITATION  →  GET /invitations/{token}
    // =========================================================================
    public function acceptPage(array $params): void
    {
        if (guest()) redirect('/login?redirect=/invitations/'.$params['token']);

        $inv = Database::row(
            'SELECT ei.*, b.name AS book_name, u.name AS inviter_name,
                    bd.business_name
             FROM employee_invitations ei
             JOIN books b ON b.id = ei.book_id
             LEFT JOIN book_business_details bd ON bd.book_id = ei.book_id
             JOIN users u ON u.id = ei.invited_by
             WHERE ei.token = ? AND ei.status = "pending"',
            [$params['token']]
        );

        if (!$inv || strtotime($inv['expires_at']) < time()) {
            redirect('/dashboard', ['error' => 'This invitation has expired or is invalid.']);
        }

        // Must match the logged-in user's email
        $user = auth();
        if (strtolower($user['email']) !== strtolower($inv['email'])) {
            redirect('/dashboard', ['error' => 'This invitation was sent to a different email address ('.$inv['email'].').']);
        }

        require BASE_PATH . '/views/business/employees/invitation.php';
    }

    // =========================================================================
    // RESPOND TO INVITATION  →  POST /invitations/{token}/respond
    // =========================================================================
    public function respondInvitation(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();

        $inv = Database::row(
            'SELECT * FROM employee_invitations WHERE token=? AND status="pending"',
            [$params['token']]
        );
        if (!$inv) redirect('/dashboard', ['error' => 'Invitation not found or already responded.']);

        $user   = auth();
        $action = $_POST['action'] ?? '';

        if (strtolower($user['email']) !== strtolower($inv['email'])) {
            redirect('/dashboard', ['error' => 'This invitation belongs to a different email.']);
        }

        if ($action === 'accept') {
            // Create or update book_member
            $existing = Database::row('SELECT id FROM book_members WHERE book_id=? AND user_id=?', [$inv['book_id'], $user['id']]);
            if (!$existing) {
                Database::run(
                    'INSERT INTO book_members (book_id, user_id, designation_id, designation_name, permissions, status)
                     VALUES (?,?,?,?,?,?)',
                    [
                        $inv['book_id'], $user['id'],
                        $inv['designation_id'], $inv['designation_name'],
                        $inv['permissions'],
                        'active'
                    ]
                );
            }

            // Create employee record if needed
            $empExists = Database::row('SELECT id FROM employees WHERE book_id=? AND user_id=?', [$inv['book_id'], $user['id']]);
            if (!$empExists) {
                // Generate employee code (same logic as manual add)
                $empCode = 'EMP-0001';
                try {
                    $lastEmp = Database::row(
                        "SELECT emp_code FROM employees WHERE book_id=? AND emp_code IS NOT NULL ORDER BY id DESC LIMIT 1",
                        [$inv['book_id']]
                    );
                    if ($lastEmp && preg_match('/(\d+)$/', $lastEmp['emp_code'], $em)) {
                        $empCode = 'EMP-' . str_pad((int)$em[1] + 1, 4, '0', STR_PAD_LEFT);
                    }
                } catch (\Throwable $e) {}

                Database::run(
                    'INSERT INTO employees (book_id, user_id, emp_code, designation_id, designation_name, invitation_id, name, email, status, created_at)
                     VALUES (?,?,?,?,?,?,?,?,?,?)',
                    [
                        $inv['book_id'], $user['id'], $empCode,
                        $inv['designation_id'], $inv['designation_name'],
                        $inv['id'], $user['name'], $user['email'],
                        'active', now()
                    ]
                );
            }

            Database::run(
                'UPDATE employee_invitations SET status="accepted", user_id=?, responded_at=? WHERE id=?',
                [$user['id'], now(), $inv['id']]
            );

            // Mark notification as read
            Database::run(
                'UPDATE notifications SET read_at=? WHERE user_id=? AND data LIKE ?',
                [now(), $user['id'], '%"token":"'.$params['token'].'"%']
            );

            \App\Services\ActivityLogger::write(
                $inv['book_id'], $user['id'],
                'employee.created', 'Employee', $user['id'],
                $user['name'] . ' joined as employee' . ($inv['designation_name'] ? ' (' . $inv['designation_name'] . ')' : '') . ' via invitation'
            );

            redirect('/books/'.$inv['book_id'], ['success' => 'Welcome! You have joined the book.']);

        } elseif ($action === 'reject') {
            Database::run(
                'UPDATE employee_invitations SET status="rejected", user_id=?, responded_at=? WHERE id=?',
                [$user['id'], now(), $inv['id']]
            );

            redirect('/dashboard', ['success' => 'Invitation declined.']);
        }

        redirect('/dashboard');
    }

    // =========================================================================
    // DESIGNATIONS — CRUD
    // =========================================================================
    public function storeDesignation(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $book = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'employees', 'manage_designations')) abort_403();

        $name = trim($_POST['name'] ?? '');
        if (!$name) redirect('/books/'.$book['id'].'/employees', ['error' => 'Designation name is required.']);

        $permissions = $this->parsePermissionsFromPost();

        Database::run(
            'INSERT INTO designations (book_id, name, permissions) VALUES (?,?,?)',
            [$book['id'], $name, json_encode($permissions)]
        );

        redirect('/books/'.$book['id'].'/employees', ['success' => '"'.$name.'" designation created.']);
    }

    public function updateDesignation(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $book = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'employees', 'manage_designations')) abort_403();

        $name = trim($_POST['name'] ?? '');
        if (!$name) redirect('/books/'.$book['id'].'/employees', ['error' => 'Designation name is required.']);

        $permissions = $this->parsePermissionsFromPost();

        Database::run(
            'UPDATE designations SET name=?, permissions=?, updated_at=? WHERE id=? AND book_id=?',
            [$name, json_encode($permissions), now(), $params['desig_id'], $book['id']]
        );

        redirect('/books/'.$book['id'].'/employees', ['success' => 'Designation updated.']);
    }

    public function deleteDesignation(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $book = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'employees', 'manage_designations')) abort_403();

        Database::run('DELETE FROM designations WHERE id=? AND book_id=?', [$params['desig_id'], $book['id']]);

        redirect('/books/'.$book['id'].'/employees', ['success' => 'Designation deleted.']);
    }

    // =========================================================================
    // GET DESIGNATION PERMISSIONS (AJAX)  →  GET /books/{id}/employees/designations/{desig_id}/permissions
    // =========================================================================
    public function getDesignationPermissions(array $params): void
    {
        if (guest()) { echo '{}'; exit; }
        $book  = $this->getBookOrFail($params['id']);
        $desig = Database::row('SELECT permissions FROM designations WHERE id=? AND book_id=?', [$params['desig_id'], $book['id']]);
        header('Content-Type: application/json');
        echo $desig ? $desig['permissions'] : '{}';
        exit;
    }

    // =========================================================================
    // TERMINATE EMPLOYEE  →  POST /books/{id}/employees/{employee_id}/terminate
    // Sets employee status=terminated, revokes book access, sends notification.
    // Employee stays in the list but loses all book access and dashboard visibility.
    // =========================================================================
    public function terminate(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $book     = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'employees', 'delete')) abort_403();
        $employee = $this->getEmployeeOrFail($params['employee_id'], $book['id']);

        $reason = trim($_POST['reason'] ?? '');

        // Must not terminate the owner
        if ((int)$employee['user_id'] === (int)$book['user_id']) {
            redirect('/books/'.$book['id'].'/employees/'.$employee['id'],
                ['error' => 'Cannot terminate the book owner.']);
        }

        // Update employee status
        Database::run(
            'UPDATE employees SET status="terminated" WHERE id=? AND book_id=?',
            [$employee['id'], $book['id']]
        );

        // Revoke book_members access so book disappears from their dashboard
        if ($employee['user_id']) {
            Database::run(
                'UPDATE book_members SET status="terminated" WHERE book_id=? AND user_id=?',
                [$book['id'], $employee['user_id']]
            );

            // Send in-app termination notification
            $bookDetails = Database::row('SELECT * FROM book_business_details WHERE book_id=?', [$book['id']]);
            $bookName    = $bookDetails['business_name'] ?? $book['name'];
            $body = 'Your access to "' . $bookName . '" has been terminated by ' . (auth()['name'] ?? 'the owner') . '.';
            if ($reason) $body .= ' Reason: ' . $reason;

            try {
                Database::run(
                    'INSERT INTO notifications (user_id, type, title, body, created_at)
                     VALUES (?,?,?,?,?)',
                    [
                        $employee['user_id'],
                        'warning',
                        'Employment Terminated — ' . $bookName,
                        $body,
                        now()
                    ]
                );
            } catch (\Throwable $e) {}
        }

        \App\Services\ActivityLogger::write(
            $book['id'], auth()['id'],
            'employee.terminated', 'Employee', $employee['id'],
            $employee['name'] . ' terminated' . ($reason ? ': ' . $reason : '')
        );

        redirect('/books/'.$book['id'].'/employees/'.$employee['id'],
            ['success' => $employee['name'] . ' has been terminated.']);
    }

    // =========================================================================
    // REINSTATE EMPLOYEE  →  POST /books/{id}/employees/{employee_id}/reinstate
    // Reverses a termination — restores active status and book access.
    // =========================================================================
    public function reinstate(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $book     = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'employees', 'edit')) abort_403();
        $employee = $this->getEmployeeOrFail($params['employee_id'], $book['id']);

        Database::run(
            'UPDATE employees SET status="active" WHERE id=? AND book_id=?',
            [$employee['id'], $book['id']]
        );

        if ($employee['user_id']) {
            Database::run(
                'UPDATE book_members SET status="active" WHERE book_id=? AND user_id=?',
                [$book['id'], $employee['user_id']]
            );

            // Notify reinstatement
            $bookDetails = Database::row('SELECT * FROM book_business_details WHERE book_id=?', [$book['id']]);
            $bookName    = $bookDetails['business_name'] ?? $book['name'];
            try {
                Database::run(
                    'INSERT INTO notifications (user_id, type, title, body, created_at)
                     VALUES (?,?,?,?,?)',
                    [
                        $employee['user_id'],
                        'info',
                        'Reinstatement — ' . $bookName,
                        'Your employment at "' . $bookName . '" has been reinstated.',
                        now()
                    ]
                );
            } catch (\Throwable $e) {}
        }

        \App\Services\ActivityLogger::write(
            $book['id'], auth()['id'],
            'employee.reinstated', 'Employee', $employee['id'],
            $employee['name'] . ' reinstated'
        );

        redirect('/books/'.$book['id'].'/employees/'.$employee['id'],
            ['success' => $employee['name'] . ' has been reinstated.']);
    }

    // =========================================================================
    // PAY SALARY  →  POST /books/{id}/employees/{employee_id}/salary/pay
    // =========================================================================
    public function paySalary(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $book     = $this->getBookOrFail($params['id']);
        $employee = $this->getEmployeeOrFail($params['employee_id'], $book['id']);

        $amount  = (float)($_POST['amount'] ?? 0);
        $method  = trim($_POST['payment_method'] ?? 'cash');
        $period  = trim($_POST['period_label'] ?? '');
        $note    = trim($_POST['note'] ?? '');
        $from    = trim($_POST['period_from'] ?? '') ?: null;
        $to      = trim($_POST['period_to']   ?? '') ?: null;

        if ($amount <= 0) {
            redirect('/books/'.$book['id'].'/employees/'.$employee['id'], ['error' => 'Amount must be greater than zero.']);
        }

        // Auto-create expense (fix: use correct column names expense_date, category_id)
        $expenseId = null;
        try {
            $expenseTitle = 'Salary — ' . $employee['name']
                . ($period ? ' (' . $period . ')' : '');

            // Find or create Salary category
            $salaryCat = Database::row(
                'SELECT id FROM expense_categories WHERE book_id=? AND name="Salary" LIMIT 1',
                [$book['id']]
            );
            if (!$salaryCat) {
                Database::run(
                    'INSERT INTO expense_categories (book_id, name, icon) VALUES (?,?,?)',
                    [$book['id'], 'Salary', 'fa-money-bill-wave']
                );
                $catId = Database::lastId();
            } else {
                $catId = $salaryCat['id'];
            }

            Database::run(
                'INSERT INTO expenses (book_id, category_id, title, amount, expense_date, paid_to, note, created_by, created_at)
                 VALUES (?,?,?,?,?,?,?,?,?)',
                [
                    $book['id'], $catId, $expenseTitle, $amount,
                    date('Y-m-d'), $employee['name'], $note ?: null,
                    auth()['id'], now()
                ]
            );
            $expenseId = Database::lastId();
        } catch (\Throwable $e) {
            error_log('[Salary] Expense creation failed: ' . $e->getMessage());
        }

        // Record salary payment
        Database::run(
            'INSERT INTO employee_salary_payments
             (book_id, employee_id, expense_id, amount, period_label, period_from, period_to, payment_method, note, created_by, created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)',
            [
                $book['id'], $employee['id'], $expenseId,
                $amount, $period ?: null, $from, $to,
                $method, $note ?: null, auth()['id'], now()
            ]
        );

        redirect(
            '/books/'.$book['id'].'/employees/'.$employee['id'],
            ['success' => 'Salary of '.format_money($amount).' paid to '.e($employee['name']).'.']
        );
    }

    // =========================================================================
    // SEND INVITE FOR OFFLINE EMPLOYEE  →  POST /books/{id}/employees/{employee_id}/send-invite
    // =========================================================================
    public function sendInviteForEmployee(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $book     = $this->getBookOrFail($params['id']);
        $employee = $this->getEmployeeOrFail($params['employee_id'], $book['id']);

        $email = strtolower(trim($_POST['email'] ?? $employee['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            redirect('/books/'.$book['id'].'/employees/'.$employee['id'], ['error' => 'Valid email address required.']);
        }

        // Reuse the main invite logic
        $_POST['email']            = $email;
        $_POST['designation_id']   = $employee['designation_id'] ?? '';
        $_POST['designation_name'] = $employee['designation_name'] ?? '';
        // Copy employee's current permissions to _POST so invite() picks them up
        // (invite() calls parsePermissionsFromPost which reads $_POST['perm'])

        $this->invite($params);
    }
    private function parsePermissionsFromPost(): array
    {
        $modules     = self::permissionModules();
        $permissions = [];
        foreach ($modules as $mod => $actions) {
            foreach ($actions as $action) {
                $permissions[$mod][$action] = isset($_POST['perm'][$mod][$action]);
            }
        }
        return $permissions;
    }

    private function sendInvitationEmail(string $email, string $token, array $book, ?string $desigName): void
    {
        try {
            $bookDetails = Database::row('SELECT * FROM book_business_details WHERE book_id=?', [$book['id']]);
            $bookName    = $bookDetails['business_name'] ?? $book['name'];
            $inviter     = auth();
            $appUrl      = rtrim(getenv('APP_URL') ?: ('http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')), '/');
            $link        = $appUrl . '/invitations/' . $token;
            $appName     = getenv('APP_NAME') ?: 'Byabsayee';

            $html = \App\Helpers\Mailer::render('invitation', [
                'inviterName'  => $inviter['name'],
                'bookName'     => $bookName,
                'designation'  => $desigName ?? '',
                'inviteLink'   => $link,
                'appName'      => $appName,
                'appUrl'       => $appUrl,
                'expiryDate'   => date('F j, Y', strtotime('+7 days')),
            ]);

            \App\Helpers\Mailer::send(
                $email,
                $inviter['name'] . ' invited you to join "' . $bookName . '" on ' . $appName,
                $html
            );
        } catch (\Throwable $e) {
            error_log('[EmployeeController] Invitation email failed: ' . $e->getMessage());
        }
    }

    private function getBookOrFail(string $id): array
    {
        try {
            $book = Database::row(
                'SELECT * FROM books WHERE id=? AND deleted_at IS NULL AND (user_id=? OR EXISTS(
                    SELECT 1 FROM book_members WHERE book_id=books.id AND user_id=? AND status="active"
                ))',
                [$id, auth()['id'], auth()['id']]
            );
        } catch (\Throwable $e) {
            $book = Database::row(
                'SELECT * FROM books WHERE id=? AND user_id=? AND deleted_at IS NULL',
                [$id, auth()['id']]
            );
        }
        if (!$book) { http_response_code(404); require BASE_PATH.'/views/errors/404.php'; exit; }
        return $book;
    }

    private function getEmployeeOrFail(string $employeeId, int $bookId): array
    {
        $emp = Database::row('SELECT * FROM employees WHERE id=? AND book_id=? AND deleted_at IS NULL', [$employeeId, $bookId]);
        if (!$emp) { http_response_code(404); require BASE_PATH.'/views/errors/404.php'; exit; }
        return $emp;
    }
}
