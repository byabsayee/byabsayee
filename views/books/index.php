<?php
$pageTitle = 'My Books — Byabsayee';
ob_start();
$searchQ = htmlspecialchars($_GET['q'] ?? '', ENT_QUOTES);
?>

<div class="page-header">
    <div class="page-header-left">
        <h1>Books</h1>
        <p>Welcome back, <?= e(auth()['name']) ?></p>
    </div>
    <a href="/books/create" class="btn btn-primary">
        <svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14"><path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/></svg>
        New Book
    </a>
</div>

<!-- Search bar -->
<form method="GET" action="/books" class="books-search-form" style="margin-bottom:24px">
    <div class="books-search-wrap">
        <i class="fa-solid fa-magnifying-glass books-search-icon"></i>
        <input type="text" name="q" class="books-search-input"
               placeholder="Search any book…"
               value="<?= $searchQ ?>"
               autocomplete="off">
        <?php if ($searchQ): ?>
        <a href="/books" class="books-search-clear" title="Clear search">
            <i class="fa-solid fa-xmark"></i>
        </a>
        <?php endif; ?>
    </div>
</form>

<?php if (empty($myBooks) && empty($sharedBooks)): ?>
<div class="empty-state">
    <div class="empty-icon">📒</div>
    <?php if ($searchQ): ?>
    <h3>No books found</h3>
    <p>No books match &ldquo;<?= e($_GET['q'] ?? '') ?>&rdquo;.</p>
    <a href="/books" class="btn btn-secondary" style="margin-top:8px">Clear search</a>
    <?php else: ?>
    <h3>No books yet</h3>
    <p>Create a personal book to track income and expenses,<br>or a business book for full accounting features.</p>
    <a href="/books/create" class="btn btn-primary" style="margin-top:8px">+ Create your first book</a>
    <?php endif; ?>
</div>

<?php else: ?>

<?php
// Reusable book card renderer
function renderBookCard(array $book): void {
    $bal = $book['total_in'] - $book['total_out'];
    $typeBadgeClass = $book['type'] === 'business' ? 'book-type-badge--business' : 'book-type-badge--personal';
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
            <span class="book-type-badge <?= $typeBadgeClass ?>"><?= strtoupper(e($book['type'])) ?></span>
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
<?php
}
?>

<!-- My Books Category -->
<div class="books-category">
    <div class="books-category-header">
        <h2 class="books-category-title">
            <i class="fa-solid fa-book-open"></i> My Books
        </h2>
        <span class="books-category-count"><?= count($myBooks) ?></span>
    </div>

    <?php if (empty($myBooks)): ?>
    <div class="books-category-empty">
        <?php if ($searchQ): ?>
        <p>No owned books match your search.</p>
        <?php else: ?>
        <p>You haven't created any books yet. <a href="/books/create">Create one now →</a></p>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="books-grid">
        <?php foreach ($myBooks as $book): renderBookCard($book); endforeach; ?>
        <?php if (!$searchQ): ?>
        <a href="/books/create" class="new-book-card">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="28" height="28">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
            </svg>
            New Book
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Shared With Me Category -->
<?php if (!empty($sharedBooks) || !$searchQ): ?>
<div class="books-category" style="margin-top:32px">
    <div class="books-category-header">
        <h2 class="books-category-title">
            <i class="fa-solid fa-share-nodes"></i> Shared with Me
        </h2>
        <span class="books-category-count"><?= count($sharedBooks) ?></span>
    </div>

    <?php if (empty($sharedBooks)): ?>
    <div class="books-category-empty">
        <?php if ($searchQ): ?>
        <p>No shared books match your search.</p>
        <?php else: ?>
        <p>Books shared with you by other users will appear here.</p>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="books-grid">
        <?php foreach ($sharedBooks as $book): renderBookCard($book); endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php endif; ?>

<style>
/* ── Books page search ─────────────────────────────────────── */
.books-search-form { max-width: 440px; }
.books-search-wrap {
    position: relative;
    display: flex;
    align-items: center;
}
.books-search-icon {
    position: absolute;
    left: 12px;
    color: var(--text-muted);
    font-size: 13px;
    pointer-events: none;
}
.books-search-input {
    width: 100%;
    padding: 9px 36px 9px 34px;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 14px;
    background: var(--bg);
    color: var(--text);
    outline: none;
    transition: border-color .15s;
}
.books-search-input:focus { border-color: var(--brand); }
.books-search-clear {
    position: absolute;
    right: 10px;
    color: var(--text-muted);
    font-size: 13px;
    line-height: 1;
    padding: 4px;
}
.books-search-clear:hover { color: var(--text); }

/* ── Books category ────────────────────────────────────────── */
.books-category-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 14px;
}
.books-category-title {
    font-size: 15px;
    font-weight: 700;
    color: var(--text);
    display: flex;
    align-items: center;
    gap: 7px;
    margin: 0;
}
.books-category-title i { color: var(--brand); font-size: 13px; }
.books-category-count {
    font-size: 11px;
    font-weight: 700;
    background: var(--border);
    color: var(--text-muted);
    padding: 2px 7px;
    border-radius: 10px;
}
.books-category-empty {
    padding: 18px 0;
    color: var(--text-muted);
    font-size: 14px;
}
.books-category-empty a { color: var(--brand); text-decoration: none; font-weight: 600; }

/* ── Book type badge colours ───────────────────────────────── */
.book-type-badge--business {
    background: #1a6b4a !important;
    color: #fff !important;
}
.book-type-badge--personal {
    background: #2563eb !important;
    color: #fff !important;
}
</style>

<?php $content = ob_get_clean(); require BASE_PATH . '/views/partials/layout.php'; ?>
