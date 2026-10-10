<?php
namespace App\Controllers;

/**
 * Placeholder pages for modules that are planned but not built yet (Wallet, Marketplace, Deliveries).
 * They keep the navigation honest: a clear "coming soon" page instead of a 404.
 */
class ComingSoonController
{
    private const FEATURES = [
        'wallet'      => ['Wallet',      'fa-wallet',     'Keep a personal balance, top up and pay across your books — in one place.'],
        'marketplace' => ['Marketplace', 'fa-store',      'Discover and connect with other businesses and suppliers on Byabsayee.'],
        'deliveries'  => ['Deliveries',  'fa-truck-fast', 'Track delivery orders from dispatch to doorstep, per invoice.'],
    ];

    public function show(array $params): void
    {
        if (guest()) redirect('/login');

        // Route => feature key (the router passes only URL params, so read the first meaningful path segment).
        $path = trim((string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
        $key  = str_contains($path, 'deliveries') ? 'deliveries' : (str_contains($path, 'marketplace') ? 'marketplace' : 'wallet');
        [$title, $icon, $blurb] = self::FEATURES[$key];

        // Deliveries lives inside a book: only members of that book may open it.
        if ($key === 'deliveries') {
            $book = book_for_user($params['id'] ?? 0, 'business');
            if (!$book) { http_response_code(404); require BASE_PATH . '/views/errors/404.php'; exit; }
        }

        $pageTitle = $title . ' — Coming soon';
        require BASE_PATH . '/views/coming-soon.php';
    }
}
