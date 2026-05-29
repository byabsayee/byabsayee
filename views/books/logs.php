<?php
$pageTitle = 'Activity Logs — ' . e($book['name']);
ob_start();
?>

<div class="page-header">
    <div class="page-header-left">
        <div class="breadcrumb">
            <a href="/books/<?= $book['id'] ?>">Books</a> <span>›</span>
            <span>Activity Logs</span>
        </div>
        <h1><i class="fa-solid fa-clock-rotate-left" style="color:var(--brand)"></i> Activity Logs</h1>
        <p>Every action recorded in this book — who did what, when and how.</p>
    </div>
</div>

<?php if (!empty($_SESSION['flash_success'])): ?>
<div class="alert alert-success" style="margin-bottom:16px"><i class="fa-solid fa-check-circle"></i> <?= e($_SESSION['flash_success']) ?></div>
<?php unset($_SESSION['flash_success']); endif; ?>

<!-- Filter bar -->
<div style="display:flex;gap:10px;align-items:center;margin-bottom:18px;flex-wrap:wrap">
    <form method="GET" style="display:flex;gap:8px;align-items:center;flex:1;min-width:0">
        <select name="filter" onchange="this.form.submit()" style="padding:8px 12px;border:1px solid var(--border);border-radius:var(--radius);background:var(--white);font-size:13px;color:var(--text)">
            <option value="">All Actions</option>
            <?php foreach ($actionTypes as $at): ?>
            <option value="<?= e($at['action']) ?>" <?= $filter === $at['action'] ? 'selected' : '' ?>>
                <?= e(ucfirst(str_replace('.', ' ', $at['action']))) ?>
            </option>
            <?php endforeach; ?>
        </select>
        <span style="font-size:13px;color:var(--text-muted)"><?= number_format($total) ?> entries</span>
    </form>
</div>

<?php if (empty($logs)): ?>
<div class="table-wrap">
    <div class="empty-state">
        <div class="empty-icon"><i class="fa-solid fa-clock-rotate-left"></i></div>
        <h3>No logs yet</h3>
        <p>Activity will be recorded as actions happen in this book.</p>
    </div>
</div>
<?php else: ?>

<div class="table-wrap" style="margin-bottom:20px">
    <table>
        <thead>
            <tr>
                <th width="32"></th>
                <th>Action</th>
                <th>Description</th>
                <th>Who</th>
                <th>IP</th>
                <th>When</th>
                <th>Changes</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($logs as $log): ?>
        <tr<?php if ($log['link_url']): ?> class="log-row-link" onclick="window.location='<?= e($log['link_url']) ?>'" title="Go to <?= e($log['action_label']) ?>"<?php endif; ?>>
            <td style="text-align:center;padding:10px 8px">
                <span style="color:<?= $log['icon_color'] ?>;font-size:14px">
                    <i class="fa-solid <?= $log['icon'] ?>"></i>
                </span>
            </td>
            <td>
                <span style="font-size:12px;font-weight:700;padding:2px 8px;border-radius:12px;background:var(--bg);color:var(--text-muted);white-space:nowrap">
                    <?= e($log['action'] ?? '') ?>
                </span>
            </td>
            <td style="max-width:280px">
                <div style="font-size:13.5px;font-weight:500;color:var(--text)"><?= e($log['description'] ?? $log['action_label']) ?></div>
                <?php if (!empty($log['subject_type']) && !empty($log['subject_id'])): ?>
                <div style="font-size:11.5px;color:var(--text-muted)"><?= e($log['subject_type']) ?> #<?= (int)$log['subject_id'] ?></div>
                <?php endif; ?>
            </td>
            <td>
                <span style="font-size:13px;font-weight:600;color:var(--text)">
                    <?= e($log['user_name'] ?? 'System') ?>
                </span>
            </td>
            <td class="td-muted" style="font-size:12px;font-family:monospace">
                <?= e($log['ip_address'] ?? '—') ?>
            </td>
            <td class="td-muted" style="white-space:nowrap;font-size:12px">
                <?= e(date('d M Y', strtotime($log['created_at']))) ?><br>
                <span style="color:var(--text-muted)"><?= e(date('g:i:s a', strtotime($log['created_at']))) ?></span>
            </td>
            <td>
                <?php if (!empty($log['old_data']) || !empty($log['new_data'])): ?>
                <button class="btn btn-sm btn-secondary" onclick="toggleChanges(this, event)" style="font-size:11px;padding:3px 8px">
                    <i class="fa-solid fa-code-branch"></i> Show
                </button>
                <div class="log-changes" style="display:none;margin-top:8px;font-size:11px;font-family:monospace;background:var(--bg);border:1px solid var(--border);border-radius:6px;padding:8px;max-width:320px;word-break:break-all">
                    <?php if (!empty($log['old_data'])): ?>
                    <div style="color:var(--red);margin-bottom:4px"><strong>Before:</strong><br><?= e(json_encode($log['old_data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($log['new_data'])): ?>
                    <div style="color:var(--green)"><strong>After:</strong><br><?= e(json_encode($log['new_data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></div>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <span style="font-size:11px;color:var(--text-muted)">—</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div style="display:flex;gap:6px;justify-content:center;flex-wrap:wrap;margin-bottom:24px">
    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
    <a href="?page=<?= $p ?>&filter=<?= urlencode($filter) ?>"
       style="padding:6px 12px;border:1px solid var(--border);border-radius:var(--radius);font-size:13px;text-decoration:none;
              background:<?= $p === $page ? 'var(--brand)' : 'var(--white)' ?>;
              color:<?= $p === $page ? '#fff' : 'var(--text)' ?>">
        <?= $p ?>
    </a>
    <?php endfor; ?>
</div>
<?php endif; ?>

<?php endif; ?>

<script>
function toggleChanges(btn, e) {
    if (e) e.stopPropagation();
    var box = btn.nextElementSibling;
    if (box.style.display === 'none') {
        box.style.display = 'block';
        btn.innerHTML = '<i class="fa-solid fa-code-branch"></i> Hide';
    } else {
        box.style.display = 'none';
        btn.innerHTML = '<i class="fa-solid fa-code-branch"></i> Show';
    }
}
</script>

<style>
.log-row-link { cursor: pointer; }
.log-row-link:hover td { background: var(--bg) !important; }
</style>

<?php $content = ob_get_clean(); require BASE_PATH . '/views/partials/layout.php'; ?>
