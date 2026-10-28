<?php
require '../session.php';
require_role(['teacher']);
define('CHARTS', true);
$asg = q('SELECT ts.id, s.name AS subject, g.id AS gid, g.name AS grp, (SELECT COUNT(*) FROM students st WHERE st.group_id = g.id) AS n
          FROM teacher_subjects ts JOIN subjects s ON s.id = ts.subject_id JOIN `groups` g ON g.id = ts.group_id WHERE ts.teacher_id = ? ORDER BY s.name, g.name', [uid()])->fetchAll();
$today = schedule_rows('sc.teacher_id = ?', [uid()]);
$pending = (int)q("SELECT COUNT(*) FROM homework_submissions hs JOIN homework h ON h.id = hs.homework_id WHERE h.teacher_id = ? AND hs.status IN ('submitted','late')", [uid()])->fetchColumn();
$hwCount = (int)q('SELECT COUNT(*) FROM homework WHERE teacher_id = ?', [uid()])->fetchColumn();
$avg = q('SELECT ROUND(AVG(score),1) FROM grades WHERE teacher_id = ?', [uid()])->fetchColumn();
$chart = q('SELECT CONCAT(s.name, " · ", g.name) AS label, ROUND(AVG(gr.score),1) AS a FROM grades gr JOIN subjects s ON s.id = gr.subject_id JOIN students st ON st.user_id = gr.student_id JOIN `groups` g ON g.id = st.group_id WHERE gr.teacher_id = ? GROUP BY s.id, g.id', [uid()])->fetchAll();
render_head('Мұғалім кабинеті'); ?>
<h1>Сәлем, <?= e($_SESSION['name']) ?></h1><p class="muted">Мұғалім кабинеті</p>
<div class="cards">
  <div class="stat"><i class="fa-solid fa-layer-group"></i><span>Менің топтарым</span><b><?= count($asg) ?></b></div>
  <div class="stat"><i class="fa-solid fa-file-pen"></i><span>Тапсырмалар</span><b><?= $hwCount ?></b></div>
  <div class="stat"><i class="fa-solid fa-inbox"></i><span>Тексеруді күтетін</span><b><?= $pending ?></b></div>
  <div class="stat"><i class="fa-solid fa-star"></i><span>Қойған бағаның орташасы</span><b><?= $avg !== null && $avg !== false ? e($avg) : '—' ?></b></div></div>
<div class="grid2">
  <div class="panel"><h2>Бүгінгі сабақтар</h2><?php render_today($today, 'teacher'); ?></div>
  <div class="panel"><h2>Үлгерім статистикасы</h2><div class="chart-box"><canvas id="c1"></canvas></div></div></div>
<div class="panel"><h2>Менің пәндерім мен топтарым</h2>
<?php if ($asg): ?><div class="tablewrap"><table class="data"><thead><tr><th>Пән</th><th>Топ</th><th>Студент</th><th></th></tr></thead><tbody>
<?php foreach ($asg as $a): ?><tr><td><?= e($a['subject']) ?></td><td><?= e($a['grp']) ?></td><td><?= (int)$a['n'] ?></td><td><a class="btn btn-ghost btn-sm" href="grades.php?a=<?= (int)$a['id'] ?>">Бағалар</a></td></tr><?php endforeach; ?></tbody></table></div>
<?php else: ?><p class="muted">Сізге әлі пән мен топ тағайындалмаған. Әкімшіге хабарласыңыз.</p><?php endif; ?></div>
<?php ob_start(); ?>
mkChart('c1',{type:'bar',data:{labels:<?= json_encode(array_column($chart, 'label'), JSON_UNESCAPED_UNICODE) ?>,datasets:[{label:'Орташа балл',data:<?= json_encode(array_map('floatval', array_column($chart, 'a'))) ?>,backgroundColor:'#3B82F6',borderRadius:8}]},options:{maintainAspectRatio:false,scales:{y:{min:0,max:100}},plugins:{legend:{display:false}}}});
<?php render_foot(ob_get_clean());
