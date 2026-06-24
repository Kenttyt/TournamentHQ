<?php
/**
 * Shared helper functions
 */

/**
 * Calculate pagination parameters.
 * Returns array with: page, perPage, total, totalPages, offset
 */
function paginate(int $total, int $perPage = 20): array {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $totalPages = max(1, (int)ceil($total / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    return [
        'page'        => $page,
        'perPage'     => $perPage,
        'total'       => $total,
        'totalPages'  => $totalPages,
        'offset'      => $offset,
    ];
}
