<?php
/**
 * Display tournament prize places
 *
 * @var array $tournament Tournament row
 */
require_once __DIR__ . '/../modules/tournaments/tournament_functions.php';
$places = getTournamentPrizePlaces($tournament ?? null);
if (empty($places)): ?>
    <span class="text-muted text-sm">TBD</span>
<?php else: ?>
    <ul class="prize-place-list" style="list-style:none;margin:0;padding:0;font-size:12px;line-height:1.5;">
        <?php foreach ($places as $place): ?>
        <li><span class="text-muted"><?= e($place['label']) ?>:</span> <strong style="color:var(--primary-light)"><?= e($place['value']) ?></strong></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
