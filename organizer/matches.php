<?php
header('Location: /TournamentHQ/organizer/bracket_generator.php' . (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''));
exit;
