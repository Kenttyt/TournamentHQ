<?php
/**
 * Shared Footer Component
 */
?>
<?php if ($user): ?>
    </main><!-- /.page-content -->
</div><!-- /.main-wrapper -->
<?php endif; ?>

<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script src="<?= url('/assets/js/main.js') ?>"></script>
<?= isset($extraJs) ? $extraJs : '' ?>
</body>
</html>
