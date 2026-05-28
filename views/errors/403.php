<?php
$pageTitle = '403 — Access Denied';
ob_start();
?>
<div style="min-height:60vh;display:flex;align-items:center;justify-content:center">
    <div style="text-align:center;max-width:420px">
        <div style="font-size:56px;margin-bottom:16px">🔒</div>
        <h1 style="font-size:26px;margin-bottom:8px">Access Denied</h1>
        <p style="color:var(--text-muted);margin-bottom:24px;font-size:15px">
            You don't have permission to access this section.<br>
            Contact the book owner if you think this is a mistake.
        </p>
        <a href="javascript:history.back()" class="btn btn-secondary" style="margin-right:8px">← Go Back</a>
        <a href="/dashboard" class="btn btn-primary">Dashboard</a>
    </div>
</div>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/partials/layout.php';
