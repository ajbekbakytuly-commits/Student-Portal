<?php
// Тапсырылған жұмыстар: мұғалім (өз тапсырмасы) және әкімші
require 'session.php';
require_role(['teacher', 'admin']);
$hid = (int)($_GET['hw'] ?? 0);
$h = q('SELECT h.*, s.name AS subject, g.name AS group_name FROM homework h JOIN subjects s ON s.id = h.subject_id JOIN `groups` g ON g.id = h.group_id WHERE h.id = ?', [$hid])->fetch();
if (!$h || (role() === 'teacher' && (int)$h['teacher_id'] !== uid())) { http_response_code(404); render_head('Табылмады'); echo '<div class="panel">Тапсырма табылмады.</div>'; render_foot(); exit; }
$back = role() === 'admin' ? 'admin/homework.php' : 'teacher/homework.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $sid = (int)($_POST['sub_id'] ?? 0);
    $sub = q('SELECT * FROM homework_submissions WHERE id = ? AND homework_id = ?', [$sid, $hid])->fetch();
    $scoreRaw = trim($_POST['score'] ?? '');
    $fb = mb_substr(trim($_POST['feedback'] ?? ''), 0, 500);
    if (!$sub) flash_set('Жұмыс табылмады.', 'err');
    elseif ($scoreRaw !== '' && (!ctype_digit($scoreRaw) || (int)$scoreRaw > 100)) flash_set('Баға 0–100 аралығында болуы керек.', 'err');
    else {
        $score = $scoreRaw === '' ? null : (int)$scoreRaw;
        $status = $score === null ? 'reviewing' : 'graded';
        q('UPDATE homework_submissions SET score = ?, feedback = ?, status = ? WHERE id = ?', [$score, $fb ?: null, $status, $sid]);
        notify($sub['student_id'], $score === null ? 'Жұмысыңыз тексерілуде' : 'Жұмысыңыз бағаланды', $h['title'] . ($score !== null ? ' — ' . $score . ' балл' : ''), BASE . 'student/homework.php#h' . $hid);
        log_action('submission_review', 'Тапсырма #' . $hid . ', студент #' . $sub['student_id'] . ($score !== null ? ', балл: ' . $score : ''));
        flash_set('Сақталды.');
    }
    go('submissions.php?hw=' . $hid);
}
$rows = q('SELECT u.id, u.name, hs.id AS sub_id, hs.status, hs.submitted_at, hs.file_name, hs.score, hs.feedback
           FROM students st JOIN users u ON u.id = st.user_id LEFT JOIN homework_submissions hs ON hs.student_id = u.id AND hs.homework_id = ?
           WHERE st.group_id = ? ORDER BY u.name', [$hid, $h['group_id']])->fetchAll();
$done = count(array_filter($rows, fn($r) => $r['sub_id']));
render_head('Тапсырылған жұмыстар'); ?>
<a class="muted" href="<?= BASE . $back ?>">← Тапсырмаларға оралу</a>
<h1><?= e($h['title']) ?></h1>
<p class="muted"><?= e($h['subject']) ?> · <?= e($h['group_name']) ?> · Мерзімі: <?= e(date('d.m.Y H:i', strtotime($h['deadline']))) ?> · Тапсырды: <?= $done ?>/<?= count($rows) ?></p>
<div class="panel tablewrap"><table class="data" data-table>
  <thead><tr><th>Студент</th><th>Мәртебе</th><th>Уақыты</th><th>Файл</th><th>Бағалау</th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?>
    <tr><td><?= e($r['name']) ?></td>
      <td><?= pill(hw_status($r['status'], $h['deadline'])) ?></td>
      <td><?= $r['sub_id'] ? e(date('d.m.Y H:i', strtotime($r['submitted_at']))) : '—' ?></td>
      <td><?= $r['sub_id'] ? '<a href="' . BASE . 'download.php?t=sub&id=' . (int)$r['sub_id'] . '"><i class="fa-solid fa-file"></i> ' . e($r['file_name']) . '</a>' : '—' ?></td>
      <td><?php if ($r['sub_id']): ?>
        <form method="post" class="row-actions"><?= csrf_field() ?><input type="hidden" name="sub_id" value="<?= (int)$r['sub_id'] ?>">
          <input type="number" name="score" min="0" max="100" value="<?= e($r['score']) ?>" placeholder="Балл" style="width:80px">
          <input name="feedback" value="<?= e($r['feedback']) ?>" placeholder="Пікір" style="width:200px" maxlength="500">
          <button class="btn btn-sm" type="submit">Сақтау</button></form>
        <?php else: ?><span class="muted">—</span><?php endif; ?></td></tr>
  <?php endforeach; ?></tbody></table>
  <p class="muted">Баллды бос қалдырып сақтасаңыз, жұмыс «Тексерілуде» күйіне өтеді.</p></div>
<?php render_foot();
