<?php
// views/coming-soon.php — shared placeholder for planned modules. Expects $title, $icon, $blurb, $pageTitle.
ob_start();
?>
<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fa-solid <?= e($icon) ?>"></i> <?= e($title) ?></h1>
    </div>
</div>

<div class="card" style="max-width:560px;margin:40px auto;text-align:center;padding:40px 28px">
    <div style="font-size:44px;color:var(--brand);margin-bottom:14px"><i class="fa-solid <?= e($icon) ?>"></i></div>
    <span class="badge badge-gray" style="margin-bottom:12px">Coming soon</span>
    <h2 style="font-size:20px;margin-bottom:8px"><?= e($title) ?> is on the way</h2>
    <p style="color:var(--text-muted);margin-bottom:22px"><?= e($blurb) ?></p>
    <a href="javascript:history.back()" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Go back</a>
</div>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/partials/layout.php';
