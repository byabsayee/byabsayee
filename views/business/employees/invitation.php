<?php
$pageTitle = 'Book Invitation — Byabsayee';
$bookName  = $inv['business_name'] ?? $inv['book_name'];

ob_start();
?>

<div style="min-height:70vh;display:flex;align-items:center;justify-content:center">
<div style="max-width:480px;width:100%;text-align:center">

    <div style="width:64px;height:64px;border-radius:50%;background:var(--brand-light);display:flex;align-items:center;justify-content:center;margin:0 auto 20px">
        <i class="fa-solid fa-envelope-open-text" style="font-size:28px;color:var(--brand)"></i>
    </div>

    <h1 style="font-size:22px;margin-bottom:8px">You've been invited!</h1>
    <p style="color:var(--text-muted);margin-bottom:24px;font-size:15px">
        <strong><?= e($inv['inviter_name']) ?></strong> has invited you to join
        <strong>"<?= e($bookName) ?>"</strong><?= $inv['designation_name'] ? ' as <strong>'.e($inv['designation_name']).'</strong>' : '' ?>.
    </p>

    <div class="card" style="text-align:left;margin-bottom:20px">
        <p class="card-title">Details</p>
        <div style="font-size:13px;display:flex;flex-direction:column;gap:6px">
            <div><span style="color:var(--text-muted)">Book:</span> <?= e($bookName) ?></div>
            <div><span style="color:var(--text-muted)">Invited by:</span> <?= e($inv['inviter_name']) ?></div>
            <?php if ($inv['designation_name']): ?>
            <div><span style="color:var(--text-muted)">Your role:</span> <span class="badge badge-blue"><?= e($inv['designation_name']) ?></span></div>
            <?php endif; ?>
            <div><span style="color:var(--text-muted)">Expires:</span> <?= format_date($inv['expires_at']) ?></div>
            <div><span style="color:var(--text-muted)">Sent to:</span> <?= e($inv['email']) ?></div>
        </div>
    </div>

    <div style="display:flex;gap:12px;justify-content:center">
        <form method="POST" action="/invitations/<?= e($inv['token']) ?>/respond">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="reject">
            <button type="submit" class="btn btn-secondary" style="min-width:120px">
                <i class="fa-solid fa-xmark"></i> Decline
            </button>
        </form>
        <form method="POST" action="/invitations/<?= e($inv['token']) ?>/respond">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="accept">
            <button type="submit" class="btn btn-primary" style="min-width:120px">
                <i class="fa-solid fa-check"></i> Accept Invitation
            </button>
        </form>
    </div>
</div>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/partials/layout.php';
?>
