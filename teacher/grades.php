<?php
require '../session.php';
require_role(['teacher']);
$asg = q('SELECT ts.id, ts.subject_id, ts.group_id, s.name AS sn, g.name AS gn FROM teacher_subjects ts JOIN subjects s ON s.id = ts.subject_id JOIN `groups` g ON g.id = ts.group_id WHERE ts.teacher_id = ? ORDER BY s.name, g.name', [uid()])->fetchAll();
foreach ($asg as &$x) $x['label'] = $x['sn'] . ' — ' . $x['gn']; unset($x);
$aid = (int)($_GET['a'] ?? $_POST['a'] ?? 0); $cur = null;
foreach ($asg as $x) if ((int)$x['id'] === $aid) $cur = $x;
if (!$cur && $asg) $cur = $asg[0];
$errors = [];

if ($cur && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $gid = (int)($_POST['grade_id'] ?? 0); $stu = (int)($_POST['student_id'] ?? 0);
    $score = $_POST['score'] ?? ''; $type = $_POST['work_type'] ?? ''; $date = $_POST['graded_at'] ?? ''; $c = mb_substr(trim($_POST['comment'] ?? ''), 0, 255);
    $d = DateTime::createFromFormat('Y-m-d', $date);
    if (!q('SELECT 1 FROM students WHERE user_id = ? AND group_id = ?', [$stu, $cur['group_id']])->fetch()) $errors[] = 'Студентті таңдаңыз.';
    if (!ctype_digit((string)$score) || (int)$score > 100) $errors[] = 'Баға 0–100 аралығында болуы керек.';
    if (!in_array($type, WORK_TYPES, true)) $errors[] = 'Жұмыс түрін таңдаңыз.';
    if (!$d || $d->format('Y-m-d') !== $date) $errors[] = 'Күні дұрыс емес.';
    if (!$errors) {
        if ($gid) {
            $old = q('SELECT score FROM grades WHERE id = ? AND teacher_id = ? AND subject_id = ? AND student_id = ?', [$gid, uid(), $cur['subject_id'], $stu])->fetchColumn();
            if ($old === false) $errors[] = 'Баға табылмады.';
            else {
                q('UPDATE grades SET score=?, work_type=?, comment=?, graded_at=? WHERE id=?', [(int)$score, $type, $c ?: null, $date, $gid]);
                log_action('grade_update', "#$gid {$cur['sn']}: $old → $score"); notify($stu, 'Баға өзгертілді', $cur['sn'] . ': ' . $old . ' → ' . $score, BASE . 'student/grades.php');
            }
        } else {
            q('INSERT INTO grades (student_id, subject_id, teacher_id, score, work_type, comment, graded_at) VALUES (?,?,?,?,?,?,?)', [$stu, $cur['subject_id'], uid(), (int)$score, $type, $c ?: null, $date]);
            log_action('grade_create', "{$cur['sn']}, студент #$stu: $score"); notify($stu, 'Жаңа баға', $cur['sn'] . ': ' . $score . ' балл', BASE . 'student/grades.php');
        }
        if (!$errors) { flash_set('Баға сақталды.'); go('teacher/grades.php?a=' . $cur['id']); }
    }
}
$students = $cur ? q('SELECT u.id, u.name, (SELECT ROUND(AVG(score),1) FROM grades g WHERE g.student_id = u.id AND g.subject_id = ? AND g.teacher_id = ?) AS avg
                      FROM students st JOIN users u ON u.id = st.user_id WHERE st.group_id = ? ORDER BY u.name', [$cur['subject_id'], uid(), $cur['group_id']])->fetchAll() : [];
$hist = $cur ? q('SELECT g.*, u.name AS student FROM grades g JOIN users u ON u.id = g.student_id JOIN students st ON st.user_id = u.id
                  WHERE g.subject_id = ? AND g.teacher_id = ? AND st.group_id = ? ORDER BY g.graded_at DESC, g.id DESC', [$cur['subject_id'], uid(), $cur['group_id']])->fetchAll() : [];
$edit = null;
if ($cur && isset($_GET['edit'])) foreach ($hist as $h) if ((int)$h['id'] === (int)$_GET['edit']) $edit = $h;
$f = fn($k, $d = '') => $_POST[$k] ?? ($edit[$k] ?? $d);
render_head('Бағалар'); ?>
<h1>Бағалар</h1>
<?php if (!$cur): ?><div class="panel"><p class="muted">Сізге әлі пән мен топ тағайындалмаған. Әкімшіге хабарласыңыз.</p></div><?php else: ?>
<form method="get" class="panel"><label class="field" style="max-width:420px;margin:0"><span>Пән және топ</span><select name="a" onchange="this.form.submit()"><?= opts($asg, $cur['id'], 'label') ?></select></label></form>
<?php if ($errors): ?><div class="alert alert-err"><?php foreach ($errors as $x) echo '<div>' . e($x) . '</div>'; ?></div><?php endif; ?>
<form class="panel" method="post"><h2><?= $edit ? 'Бағаны өзгерту' : 'Баға қою' ?></h2><?= csrf_field() ?><input type="hidden" name="a" value="<?= (int)$cur['id'] ?>"><input type="hidden" name="grade_id" value="<?= (int)($edit['id'] ?? 0) ?>">
  <div class="form-grid">
    <label class="field"><span>Студент</span><select name="student_id" <?= $edit ? 'style="pointer-events:none;opacity:.7"' : '' ?>><?= opts($students, $f('student_id')) ?></select></label>
    <label class="field"><span>Балл (0–100)</span><input type="number" name="score" min="0" max="100" value="<?= e($f('score')) ?>" required></label>
    <label class="field"><span>Жұмыс түрі</span><select name="work_type"><?php foreach (WORK_TYPES as $w) echo '<option' . ($f('work_type') === $w ? ' selected' : '') . '>' . e($w) . '</option>'; ?></select></label>
    <label class="field"><span>Күні</span><input type="date" name="graded_at" value="<?= e($f('graded_at', date('Y-m-d'))) ?>" required></label>
    <label class="field"><span>Пікір</span><input name="comment" maxlength="255" value="<?= e($f('comment')) ?>"></label></div>
  <button class="btn" type="submit">Сақтау</button> <?php if ($edit): ?><a class="btn btn-ghost" href="?a=<?= (int)$cur['id'] ?>">Бас тарту</a><?php endif; ?></form>
<div class="grid2">
  <div class="panel"><h2>Студенттер</h2><div class="tablewrap"><table class="data"><thead><tr><th>Студент</th><th>Орташа балл</th></tr></thead><tbody>
    <?php foreach ($students as $s): ?><tr><td><?= e($s['name']) ?></td><td><?= $s['avg'] !== null ? e($s['avg']) : '—' ?></td></tr><?php endforeach; ?></tbody></table></div></div>
  <div class="panel"><h2>Бағалар тарихы</h2><div class="tablewrap"><table class="data" data-table data-per="8"><thead><tr><th>Күні</th><th>Студент</th><th>Түрі</th><th>Балл</th><th></th></tr></thead><tbody>
    <?php foreach ($hist as $h): ?><tr><td><?= e(date('d.m.Y', strtotime($h['graded_at']))) ?></td><td><?= e($h['student']) ?></td><td><?= e($h['work_type']) ?></td><td><b><?= (int)$h['score'] ?></b></td><td><a class="btn btn-ghost btn-sm" href="?a=<?= (int)$cur['id'] ?>&edit=<?= (int)$h['id'] ?>">Өзгерту</a></td></tr><?php endforeach; ?></tbody></table></div></div>
</div>
<?php endif; render_foot();
