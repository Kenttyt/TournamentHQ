<?php
header('Location: /TournamentHQ/admin/bracket_generator.php' . (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''));
exit;
