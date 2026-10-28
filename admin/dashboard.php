<?php
require '../session.php';
require_role(['admin']);
define('CHARTS', true);
$cnt = fn($sql) => (int)q($sql)->fetchColumn();
$stats = [
    ['fa-user-graduate', 'Студенттер', $cnt("SELECT COUNT(*) FROM users WHERE role='student'")],
    ['fa-chalkboard-user', 'Мұғалімдер', $cnt("SELECT COUNT(*) FROM users WHERE role='teacher'")],
    ['fa-book', 'Пәндер', $cnt('SELECT COUNT(*) FROM subjects')],
    ['fa-layer-group', 'Топтар', $cnt('SELECT COUNT(*) FROM `groups`')],
    ['fa-file-pen', 'Үй тапсырмалары', $cnt('SELECT COUNT(*) FROM homework')],
    ['fa-star', 'Орташа үлгерім', ($a = q('SELECT ROUND(AVG(score),1) FROM grades')->fetchColumn()) !== null ? $a : '—'],
];
$bySubject = q('SELECT s.name, ROUND(AVG(g.score),1) AS a FROM grades g JOIN subjects s ON s.id = g.subject_id GROUP BY s.id ORDER BY s.name')->fetchAll();
$sub = array_column(q('SELECT status, COUNT(*) c FROM homework_submissions GROUP BY status')->fetchAll(), 'c', 'status');
$logs = q('SELECT l.*, u.name FROM activity_logs l LEFT JOIN users u ON u.id = l.user_id ORDER BY l.id DESC LIMIT 8')->fetchAll();
render_head('Басқару панелі'); ?>
<h1>Басқару панелі</h1><p class="muted">Колледждің жалпы жағдайы</p>
<div class="cards"><?php foreach ($stats as [$i, $l, $v]): ?><div class="stat"><i class="fa-solid <?= $i ?>"></i><span><?= $l ?></span><b><?= e($v) ?></b></div><?php endforeach; ?></div>
<div class="actions" style="margin:0 0 20px"><a class="btn" href="users.php">Пайдаланушы қосу</a><a class="btn btn-ghost" href="schedule.php">Кесте жасау</a><a class="btn btn-ghost" href="subjects.php">Пән қосу</a><a class="btn btn-ghost" href="groups.php">Топ қосу</a></div>
<div class="grid2">
  <div class="panel"><h2>Пәндер бойынша орташа балл</h2><div class="chart-box"><canvas id="c1"></canvas></div></div>
  <div class="panel"><h2>Тапсырмаларды тапсыру статистикасы</h2><div class="chart-box"><canvas id="c2"></canvas></div></div>
</div>
<div class="panel"><h2>Соңғы әрекеттер</h2><div class="tablewrap"><table class="data"><thead><tr><th>Уақыт</th><th>Пайдаланушы</th><th>Әрекет</th><th>Мәлімет</th></tr></thead><tbody>
<?php foreach ($logs as $l): ?><tr><td><?= e(date('d.m H:i', strtotime($l['created_at']))) ?></td><td><?= e($l['name'] ?? '—') ?></td><td><span class="pill blue"><?= e($l['action']) ?></span></td><td><?= e($l['details']) ?></td></tr><?php endforeach; ?>
<?php if (!$logs): ?><tr><td colspan="4" class="muted">Әзірге жазба жоқ.</td></tr><?php endif; ?></tbody></table></div></div>
<?php
ob_start(); ?>
mkChart('c1',{type:'bar',data:{labels:<?= json_encode(array_column($bySubject, 'name'), JSON_UNESCAPED_UNICODE) ?>,datasets:[{label:'Орташа балл',data:<?= json_encode(array_map('floatval', array_column($bySubject, 'a'))) ?>,backgroundColor:'#3B82F6',borderRadius:8}]},options:{maintainAspectRatio:false,scales:{y:{min:0,max:100}},plugins:{legend:{display:false}}}});
mkChart('c2',{type:'doughnut',data:{labels:['Тапсырылды','Тексерілуде','Бағаланды','Кешіктірілді'],datasets:[{data:[<?= (int)($sub['submitted'] ?? 0) ?>,<?= (int)($sub['reviewing'] ?? 0) ?>,<?= (int)($sub['graded'] ?? 0) ?>,<?= (int)($sub['late'] ?? 0) ?>],backgroundColor:['#3B82F6','#FBBF24','#34D399','#F87171'],borderWidth:0}]},options:{maintainAspectRatio:false,cutout:'65%'}});
<?php render_foot(ob_get_clean());
