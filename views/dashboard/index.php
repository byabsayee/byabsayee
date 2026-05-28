<?php
$pageTitle = 'Dashboard — Byabsayee';
ob_start();
?>

<div class="page-header">
    <div class="page-header-left">
        <h1>My Books</h1>
        <p>Welcome back, <?= e(auth()['name']) ?></p>
    </div>
    <a href="/books/create" class="btn btn-primary">
        <svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14"><path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/></svg>
        New Book
    </a>
</div>

<?php if (empty($books)): ?>
<div class="empty-state">
    <div class="empty-icon">📒</div>
    <h3>No books yet</h3>
    <p>Create a personal book to track income and expenses,<br>or a business book for full accounting features.</p>
    <a href="/books/create" class="btn btn-primary" style="margin-top:8px">+ Create your first book</a>
</div>
<?php else: ?>
<div class="books-grid">
    <?php foreach ($books as $book):
        $bal = $book['total_in'] - $book['total_out'];
    ?>
    <a href="/books/<?= $book['id'] ?>" class="book-card" style="--book-color:<?= e($book['color']) ?>">
        <div class="book-card-header">
            <div style="display:flex;align-items:center;gap:8px;min-width:0">
                <?php if (!empty($book['logo'])): ?>
                <img src="<?= asset('uploads/'.$book['logo']) ?>"
                     style="height:24px;max-width:60px;object-fit:contain;flex-shrink:0;border-radius:3px"
                     onerror="this.style.display='none'">
                <?php endif; ?>
                <span class="book-card-name"><?= e($book['name']) ?></span>
            </div>
            <div style="display:flex;align-items:center;gap:5px;flex-shrink:0">
                <?php if (!$book['is_owner']): ?>
                <span style="font-size:10px;background:rgba(255,255,255,0.2);color:inherit;padding:2px 6px;border-radius:10px;font-weight:600;white-space:nowrap">Member</span>
                <?php endif; ?>
                <span class="book-type-badge"><?= e($book['type']) ?></span>
            </div>
        </div>
        <?php if ($book['type'] === 'personal'): ?>
        <div class="book-card-numbers">
            <div class="book-num">
                <span class="book-num-val g"><?= format_money($book['total_in']) ?></span>
                <span class="book-num-lab">Income</span>
            </div>
            <div class="book-num">
                <span class="book-num-val r"><?= format_money($book['total_out']) ?></span>
                <span class="book-num-lab">Expense</span>
            </div>
        </div>
        <div class="book-balance">
            <span>Balance</span>
            <strong style="color:<?= $bal >= 0 ? 'var(--green)' : 'var(--red)' ?>">
                <?= format_money($bal) ?>
            </strong>
        </div>
        <?php else: ?>
        <div class="book-card-numbers">
            <div class="book-num">
                <span class="book-num-val g"><?= format_money($book['total_in']) ?></span>
                <span class="book-num-lab">Sales</span>
            </div>
            <div class="book-num">
                <span class="book-num-val r"><?= format_money($book['total_out']) ?></span>
                <span class="book-num-lab">Purchases</span>
            </div>
        </div>
        <div class="book-balance">
            <span>Profit</span>
            <strong style="color:<?= $bal >= 0 ? 'var(--green)' : 'var(--red)' ?>">
                <?= format_money($bal) ?>
            </strong>
        </div>
        <?php endif; ?>
    </a>
    <?php endforeach; ?>

    <a href="/books/create" class="new-book-card">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="28" height="28">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
        </svg>
        New Book
    </a>
</div>
<?php endif; ?>

<?php $content = ob_get_clean(); require BASE_PATH . '/views/partials/layout.php'; ?>
