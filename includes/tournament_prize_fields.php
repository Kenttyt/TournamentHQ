<?php
/**
 * Prize pool form fields — Champion through 4th Place
 *
 * @var string $namePrefix  '' for single tournament edit, or 'cat_' for category rows (uses [] arrays)
 */
$namePrefix = $namePrefix ?? '';
$arraySuffix = ($namePrefix === 'cat_') ? '[]' : '';
$champion = $values['prize_champion'] ?? '';
$second   = $values['prize_2nd'] ?? '';
$third    = $values['prize_3rd'] ?? '';
$fourth   = $values['prize_4th'] ?? '';
$regFee   = $values['registration_fee'] ?? '';
?>
<div class="prize-pool-fields">
    <label class="form-label" style="margin-bottom: 10px;">Prize Pool</label>
    <div class="form-row">
        <div class="form-group">
            <label class="form-label text-xs">Champion</label>
            <input type="text" name="<?= $namePrefix ?>prize_champion<?= $arraySuffix ?>" class="form-control" placeholder="e.g. ₱10,000" value="<?= e($champion) ?>">
        </div>
        <div class="form-group">
            <label class="form-label text-xs">2nd Place</label>
            <input type="text" name="<?= $namePrefix ?>prize_2nd<?= $arraySuffix ?>" class="form-control" placeholder="e.g. ₱5,000" value="<?= e($second) ?>">
        </div>
    </div>
    <div class="form-row" style="margin-bottom: 0;">
        <div class="form-group">
            <label class="form-label text-xs">3rd Place</label>
            <input type="text" name="<?= $namePrefix ?>prize_3rd<?= $arraySuffix ?>" class="form-control" placeholder="e.g. ₱3,000" value="<?= e($third) ?>">
        </div>
        <div class="form-group">
            <label class="form-label text-xs">4th Place</label>
            <input type="text" name="<?= $namePrefix ?>prize_4th<?= $arraySuffix ?>" class="form-control" placeholder="e.g. ₱1,500" value="<?= e($fourth) ?>">
        </div>
    </div>
    <div class="form-group" style="margin-top: 14px; margin-bottom: 0;">
        <label class="form-label">Registration Fee</label>
        <input type="text" name="<?= $namePrefix ?>registration_fee<?= $arraySuffix ?>" class="form-control" placeholder="e.g. ₱500 or Free" value="<?= e($regFee) ?>">
        <span class="form-hint">Amount each player pays to join this tournament (leave blank if none).</span>
    </div>
</div>
