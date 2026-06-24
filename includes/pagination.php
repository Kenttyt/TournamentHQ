<?php
/**
 * Reusable pagination control.
 * Expected variables: $pagination (array from paginate()), $baseUrl (string)
 * $baseUrl should NOT include ?page= — this partial appends it.
 */
if (!isset($pagination) || $pagination['totalPages'] <= 1) return;

$page = $pagination['page'];
$totalPages = $pagination['totalPages'];
$total = $pagination['total'];

$separator = str_contains($baseUrl, '?') ? '&' : '?';
$baseUrlClean = preg_replace('/[?&]page=\d+/', '', $baseUrl);
if (!str_contains($baseUrlClean, '?')) $separator = '?';

$makeUrl = fn(int $p) => $baseUrlClean . $separator . 'page=' . $p;

$range = 2;
$start = max(1, $page - $range);
$end = min($totalPages, $page + $range);
?>
<div class="pagination-wrap">
    <span class="pagination-info">Page <?= $page ?> of <?= $totalPages ?> (<?= number_format($total) ?> results)</span>
    <div class="pagination-links">
        <?php if ($page > 1): ?>
            <a href="<?= $makeUrl(1) ?>" class="pagination-btn" title="First">&laquo;</a>
            <a href="<?= $makeUrl($page - 1) ?>" class="pagination-btn" title="Previous">&lsaquo;</a>
        <?php endif; ?>

        <?php if ($start > 1): ?>
            <a href="<?= $makeUrl(1) ?>" class="pagination-btn">1</a>
            <?php if ($start > 2): ?><span class="pagination-dots">...</span><?php endif; ?>
        <?php endif; ?>

        <?php for ($i = $start; $i <= $end; $i++):
            $activeClass = $i === $page ? ' active' : '';
        ?>
            <a href="<?= $makeUrl($i) ?>" class="pagination-btn<?= $activeClass ?>"><?= $i ?></a>
        <?php endfor; ?>

        <?php if ($end < $totalPages): ?>
            <?php if ($end < $totalPages - 1): ?><span class="pagination-dots">...</span><?php endif; ?>
            <a href="<?= $makeUrl($totalPages) ?>" class="pagination-btn"><?= $totalPages ?></a>
        <?php endif; ?>

        <?php if ($page < $totalPages): ?>
            <a href="<?= $makeUrl($page + 1) ?>" class="pagination-btn" title="Next">&rsaquo;</a>
            <a href="<?= $makeUrl($totalPages) ?>" class="pagination-btn" title="Last">&raquo;</a>
        <?php endif; ?>
    </div>
</div>
