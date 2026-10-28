<?php
require '../session.php';
require_role(['student']);
$gid = q('SELECT group_id FROM students WHERE user_id = ?', [uid()])->fetchColumn();
$w = (int)($_GET['w'] ?? 0) === 1 ? 1 : 0;
render_head('Сабақ кестесі'); ?>
<h1>Сабақ кестесі</h1>
<?php if ($gid) render_week(schedule_rows('sc.group_id = ?', [$gid]), $w, 'group'); else echo '<div class="panel muted">Сіз әлі топқа тағайындалмағансыз.</div>'; render_foot();
