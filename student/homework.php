<?php
require '../session.php';
require_role(['student']);
$gid = q('SELECT group_id FROM students WHERE user_id = ?', [uid()])->fetchColumn();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $hid = (int)($_POST['hw'] ?? 0);
    $h = $gid ? q('SELECT * FROM homework WHERE id = ? AND group_id = ?', [$hid, $gid])->fetch() : null;
    $sub = $h ? q('SELECT * FROM homework_submissions WHERE homework_id = ? AND student_id = ?', [$hid, uid()])->fetch() : null;
    $late = $h && strtotime($h['deadline']) < time();
    if (!$h) flash_set('Тапсырма табылмады.', 'err');
    elseif ($sub && !($h['allow_resubmit'] && !$late && $sub['status'] !== 'graded')) flash_set('Бұл тапсырманы қайта жүктеуге болмайды.', 'err');
    else {
        $err = ''; $file = save_upload('file', 'submissions', $err);
        if ($err) flash_set($err, 'err');
        elseif (!$file) flash_set('Файлды таңдаңыз.', 'err');
        else {
            $status = $late ? 'late' : 'submitted';
            if ($sub) { delete_file($sub['file_path']); q('UPDATE homework_submissions SET file_path=?, file_name=?, submitted_at=NOW(), status=?, score=NULL, feedback=NULL WHERE id=?', [$file['path'], $file['name'], $status, $sub['id']]); }
            else q('INSERT INTO homework_submissions (homework_id, student_id, file_path, file_name, submitted_at, status) VALUES (?,?,?,?,NOW(),?)', [$hid, uid(), $file['path'], $file['name'], $status]);
            log_action('homework_submit', $h['title']);
            notify($h['teacher_id'], 'Жаңа жұмыс тапсырылды', $_SESSION['name'] . ' — ' . $h['title'], BASE . 'submissions.php?hw=' . $hid);
            flash_set('Жұмыс сәтті жүктелді: ' . $file['name']);
        }
    }
    go('student/homework.php#h' . $hid);
}
$rows = $gid ? q('SELECT h.*, s.name AS subject, u.name AS teacher, hs.id AS sub_id, hs.status, hs.file_name AS sub_file, hs.submitted_at, hs.score, hs.feedback
                  FROM homework h JOIN subjects s ON s.id = h.subject_id JOIN users u ON u.id = h.teacher_id
                  LEFT JOIN homework_submissions hs ON hs.homework_id = h.id AND hs.student_id = ? WHERE h.group_id = ? ORDER BY h.deadline DESC', [uid(), $gid])->fetchAll() : [];
render_head('Үй тапсырмалары'); ?>
<h1>Үй тапсырмалары</h1>
<?php if (!$gid): ?><div class="alert alert-warn">Сіз әлі топқа тағайындалмағансыз.</div><?php endif; ?>
<?php if ($gid && !$rows): ?><div class="panel"><p class="muted">Тапсырмалар әлі жоқ.</p></div><?php endif; ?>
<div class="hwgrid">
<?php foreach ($rows as $r):
    $over = strtotime($r['deadline']) < time();
    $canUpload = !$r['sub_id'] || ($r['allow_resubmit'] && !$over && $r['status'] !== 'graded'); ?>
  <div class="panel" id="h<?= (int)$r['id'] ?>">
    <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start"><h2 style="margin:0"><?= e($r['title']) ?></h2><?= pill(hw_status($r['status'], $r['deadline'])) ?></div>
    <p class="muted"><?= e($r['subject']) ?> · <?= e($r['teacher']) ?><br>Мерзімі: <?= e(date('d.m.Y H:i', strtotime($r['deadline']))) ?></p>
    <?php if ($r['description']): ?><p><?= nl2br(e($r['description'])) ?></p><?php endif; ?>
    <?php if ($r['file_name']): ?><p><a href="<?= BASE ?>download.php?t=hw&id=<?= (int)$r['id'] ?>"><i class="fa-solid fa-paperclip"></i> <?= e($r['file_name']) ?></a></p><?php endif; ?>
    <?php if ($r['sub_id']): ?><p>Жүктелген: <a href="<?= BASE ?>download.php?t=sub&id=<?= (int)$r['sub_id'] ?>"><?= e($r['sub_file']) ?></a> <small>(<?= e(date('d.m.Y H:i', strtotime($r['submitted_at']))) ?>)</small></p><?php endif; ?>
    <?php if ($r['status'] === 'graded'): ?><div class="alert alert-ok">Баға: <b><?= (int)$r['score'] ?></b><?= $r['feedback'] ? '<br>' . e($r['feedback']) : '' ?></div>
    <?php elseif ($r['status'] === 'reviewing' && $r['feedback']): ?><div class="alert alert-warn"><?= e($r['feedback']) ?></div><?php endif; ?>
    <?php if ($canUpload): ?>
    <form method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="hw" value="<?= (int)$r['id'] ?>">
      <label class="field"><span><?= $r['sub_id'] ? 'Файлды ауыстыру' : 'Орындалған жұмысты жүктеу' ?> (PDF, DOC, DOCX, ZIP, сурет; ≤ 10 МБ)</span><input type="file" name="file" required></label>
      <button class="btn btn-sm" type="submit"><?= $r['sub_id'] ? 'Қайта жүктеу' : 'Жүктеу' ?></button></form>
    <?php elseif ($r['sub_id'] && $r['status'] !== 'graded'): ?><p class="muted">Қайта жүктеуге рұқсат жоқ немесе мерзім өтті.</p><?php endif; ?>
  </div>
<?php endforeach; ?></div>
<?php render_foot();
