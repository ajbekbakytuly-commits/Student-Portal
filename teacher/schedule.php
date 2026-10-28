<?php
require '../session.php';
require_role(['teacher']);
$w = (int)($_GET['w'] ?? 0) === 1 ? 1 : 0;
render_head('Сабақ кестесі'); ?>
<h1>Менің сабақ кестем</h1><p class="muted">Кестені әкімші басқарады. Өзгерту қажет болса, әкімшіге хабарласыңыз.</p>
<?php render_week(schedule_rows('sc.teacher_id = ?', [uid()]), $w, 'teacher'); render_foot();
