<?php
/**
 * Shared helper functions
 */

/**
 * Generate a URL relative to the application base path.
 * Auto-detects base path from the current script's directory.
 * Works whether deployed at root domain or a subdirectory.
 */
function url(string $path = ''): string {
    static $base = null;
    if ($base === null) {
        if (PHP_SAPI === 'cli') {
            $base = '';
        } else {
            // Determine base path from helpers.php location relative to document root.
            // This is always consistent regardless of which entry point called it.
            $helpersDir = str_replace('\\', '/', __DIR__);
            $docRoot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? '');
            if ($docRoot !== '' && str_starts_with($helpersDir, $docRoot)) {
                $base = dirname(substr($helpersDir, strlen($docRoot)));
            } else {
                $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');
            }
        }
    }
    if ($path === '') {
        return $base !== '' ? $base : '/';
    }
    return $base . '/' . ltrim($path, '/');
}

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
