<?php
require '../session.php';
require_role(['student']);
$st = q('SELECT s.group_id, g.name AS grp FROM students s LEFT JOIN `groups` g ON g.id = s.group_id WHERE s.user_id = ?', [uid()])->fetch();
$gid = $st['group_id'] ?? null;
$avg = q('SELECT ROUND(AVG(score),1) FROM grades WHERE student_id = ?', [uid()])->fetchColumn();
$last = q('SELECT g.*, s.name AS subject FROM grades g JOIN subjects s ON s.id = g.subject_id WHERE g.student_id = ? ORDER BY g.graded_at DESC, g.id DESC LIMIT 5', [uid()])->fetchAll();
$active = []; $history = [];
if ($gid) {
    $active = q('SELECT h.*, s.name AS subject FROM homework h JOIN subjects s ON s.id = h.subject_id
                 WHERE h.group_id = ? AND h.deadline >= NOW() AND NOT EXISTS (SELECT 1 FROM homework_submissions x WHERE x.homework_id = h.id AND x.student_id = ?) ORDER BY h.deadline LIMIT 6', [$gid, uid()])->fetchAll();
    // Мерзімі жақындаған тапсырмалар туралы еске салу (24 сағат), әр тапсырмаға бір рет
    foreach ($active as $h) if (strtotime($h['deadline']) - time() < 86400) {
        $link = BASE . 'student/homework.php#h' . $h['id'];
        if (!q('SELECT 1 FROM notifications WHERE user_id = ? AND title = ? AND link = ?', [uid(), 'Тапсыру мерзімі жақындады', $link])->fetch())
            notify(uid(), 'Тапсыру мерзімі жақындады', $h['title'] . ' · ' . date('d.m.Y H:i', strtotime($h['deadline'])), $link);
    }
    $history = q('SELECT hs.*, h.title, h.deadline FROM homework_submissions hs JOIN homework h ON h.id = hs.homework_id WHERE hs.student_id = ? ORDER BY hs.submitted_at DESC LIMIT 5', [uid()])->fetchAll();
}
$notes = q('SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 4', [uid()])->fetchAll();
render_head('Студент кабинеті'); ?>
<h1>Сәлем, <?= e($_SESSION['name']) ?></h1><p class="muted">Студент кабинеті<?= !empty($st['grp']) ? ' · ' . e($st['grp']) : '' ?></p>
<?php if (!$gid): ?><div class="alert alert-warn">Сіз әлі топқа тағайындалмағансыз. Кесте мен тапсырмаларды көру үшін әкімшіге хабарласыңыз.</div><?php endif; ?>
<div class="cards">
  <div class="stat"><i class="fa-solid fa-star"></i><span>Орташа балл</span><b><?= $avg !== null && $avg !== false ? e($avg) : '—' ?></b></div>
  <div class="stat"><i class="fa-solid fa-file-pen"></i><span>Белсенді тапсырмалар</span><b><?= count($active) ?></b></div>
  <div class="stat"><i class="fa-solid fa-bell"></i><span>Оқылмаған хабарлар</span><b><?= unread_count() ?></b></div></div>
<div class="grid2">
  <div class="panel"><h2>Бүгінгі сабақтар</h2><?php render_today($gid ? schedule_rows('sc.group_id = ?', [$gid]) : [], 'group'); ?></div>
  <div class="panel"><h2>Ең жақын мерзімдер</h2>
    <?php foreach ($active as $h): ?><div class="lesson"><b><a href="homework.php#h<?= (int)$h['id'] ?>"><?= e($h['title']) ?></a></b><span><?= e($h['subject']) ?> · <?= e(date('d.m.Y H:i', strtotime($h['deadline']))) ?></span></div><?php endforeach; ?>
    <?php if (!$active): ?><p class="muted">Белсенді тапсырма жоқ.</p><?php endif; ?></div></div>
<div class="grid2">
  <div class="panel"><h2>Соңғы бағалар</h2>
    <?php if ($last): ?><div class="tablewrap"><table class="data"><thead><tr><th>Күні</th><th>Пән</th><th>Балл</th></tr></thead><tbody>
    <?php foreach ($last as $g): ?><tr><td><?= e(date('d.m', strtotime($g['graded_at']))) ?></td><td><?= e($g['subject']) ?></td><td><b><?= (int)$g['score'] ?></b></td></tr><?php endforeach; ?></tbody></table></div>
    <?php else: ?><p class="muted">Бағалар әлі жоқ.</p><?php endif; ?></div>
  <div class="panel"><h2>Жіберілген файлдар тарихы</h2>
    <?php foreach ($history as $h): ?><div class="lesson"><b><?= e($h['title']) ?></b><span><?= e($h['file_name']) ?> · <?= e(date('d.m.Y H:i', strtotime($h['submitted_at']))) ?></span><span><?= pill(hw_status($h['status'], $h['deadline'])) ?><?= $h['score'] !== null ? ' ' . (int)$h['score'] . ' балл' : '' ?></span></div><?php endforeach; ?>
    <?php if (!$history): ?><p class="muted">Әзірге жұмыс жіберілмеген.</p><?php endif; ?></div></div>
<div class="panel"><h2>Мұғалімдердің хабарландырулары</h2>
  <?php foreach ($notes as $n): ?><div class="notif <?= $n['is_read'] ? '' : 'new' ?>"><div><b><?= e($n['title']) ?></b><div class="muted"><?= e($n['body']) ?></div></div></div><?php endforeach; ?>
  <?php if (!$notes): ?><p class="muted">Хабарландыру жоқ.</p><?php endif; ?><a href="<?= BASE ?>notifications.php">Барлығын көру</a></div>
<?php render_foot();
