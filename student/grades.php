<?php
require '../session.php';
require_role(['student']);
define('CHARTS', true);
$grades = q('SELECT g.*, s.name AS subject FROM grades g JOIN subjects s ON s.id = g.subject_id WHERE g.student_id = ? ORDER BY g.graded_at DESC, g.id DESC', [uid()])->fetchAll();
$avgs = q('SELECT s.name, ROUND(AVG(g.score),1) AS a, COUNT(*) AS n FROM grades g JOIN subjects s ON s.id = g.subject_id WHERE g.student_id = ? GROUP BY s.id ORDER BY s.name', [uid()])->fetchAll();
$dyn = q('SELECT graded_at AS d, ROUND(AVG(score),1) AS a FROM grades WHERE student_id = ? GROUP BY graded_at ORDER BY graded_at', [uid()])->fetchAll();
render_head('Бағалар'); ?>
<h1>Бағаларым</h1>
<?php if (!$grades): ?><div class="panel"><p class="muted">Бағалар әлі қойылмаған.</p></div><?php else: ?>
<div class="cards"><?php foreach ($avgs as $a): ?><div class="stat"><span><?= e($a['name']) ?></span><b><?= e($a['a']) ?></b><span><?= (int)$a['n'] ?> баға</span></div><?php endforeach; ?></div>
<div class="panel"><h2>Үлгерім динамикасы</h2><div class="chart-box"><canvas id="c1"></canvas></div></div>
<div class="panel tablewrap"><table class="data" data-table><thead><tr><th>Күні</th><th>Пән</th><th>Түрі</th><th>Балл</th><th>Пікір</th></tr></thead><tbody>
<?php foreach ($grades as $g): ?><tr><td><?= e(date('d.m.Y', strtotime($g['graded_at']))) ?></td><td><?= e($g['subject']) ?></td><td><?= e($g['work_type']) ?></td><td><b><?= (int)$g['score'] ?></b></td><td><?= e($g['comment']) ?></td></tr><?php endforeach; ?></tbody></table></div>
<?php endif;
ob_start(); ?>
mkChart('c1',{type:'line',data:{labels:<?= json_encode(array_map(fn($r) => date('d.m', strtotime($r['d'])), $dyn)) ?>,datasets:[{label:'Орташа балл',data:<?= json_encode(array_map('floatval', array_column($dyn, 'a'))) ?>,borderColor:'#60A5FA',backgroundColor:'rgba(59,130,246,.15)',fill:true,tension:.3}]},options:{maintainAspectRatio:false,scales:{y:{min:0,max:100}},plugins:{legend:{display:false}}}});
<?php render_foot($grades ? ob_get_clean() : (ob_end_clean() ?: ''));
