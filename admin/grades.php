<?php
require '../session.php';
require_role(['admin']);
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $a = $_POST['action'] ?? ''; $id = (int)($_POST['id'] ?? 0);
    $g = q('SELECT gr.*, s.name AS subject FROM grades gr JOIN subjects s ON s.id = gr.subject_id WHERE gr.id = ?', [$id])->fetch();
    if ($g && $a === 'save') {
        $score = $_POST['score'] ?? ''; $type = $_POST['work_type'] ?? ''; $date = $_POST['graded_at'] ?? ''; $c = mb_substr(trim($_POST['comment'] ?? ''), 0, 255);
        $d = DateTime::createFromFormat('Y-m-d', $date);
        if (!ctype_digit((string)$score) || (int)$score > 100) $errors[] = 'Баға 0–100 аралығында болуы керек.';
        if (!in_array($type, WORK_TYPES, true)) $errors[] = 'Жұмыс түрі дұрыс емес.';
        if (!$d || $d->format('Y-m-d') !== $date) $errors[] = 'Күні дұрыс емес.';
        if (!$errors) {
            q('UPDATE grades SET score=?, work_type=?, comment=?, graded_at=? WHERE id=?', [(int)$score, $type, $c ?: null, $date, $id]);
            log_action('grade_update', "#$id {$g['subject']}: {$g['score']} → $score");
            notify($g['student_id'], 'Баға өзгертілді', $g['subject'] . ': ' . $g['score'] . ' → ' . $score, BASE . 'student/grades.php');
            flash_set('Баға сақталды.'); go('admin/grades.php');
        }
    } elseif ($g && $a === 'delete') {
        q('DELETE FROM grades WHERE id = ?', [$id]); log_action('grade_delete', "#$id {$g['subject']}: {$g['score']}"); flash_set('Баға жойылды.'); go('admin/grades.php');
    }
}
$edit = isset($_GET['edit']) ? q('SELECT * FROM grades WHERE id = ?', [(int)$_GET['edit']])->fetch() : null;
$rows = q('SELECT gr.*, u.name AS student, s.name AS subject, t.name AS teacher, g.name AS grp FROM grades gr JOIN users u ON u.id = gr.student_id JOIN subjects s ON s.id = gr.subject_id LEFT JOIN users t ON t.id = gr.teacher_id LEFT JOIN students st ON st.user_id = u.id LEFT JOIN `groups` g ON g.id = st.group_id ORDER BY gr.graded_at DESC, gr.id DESC LIMIT 500')->fetchAll();
render_head('Бағалар'); ?>
<h1>Бағалар</h1>
<?php if ($errors): ?><div class="alert alert-err"><?php foreach ($errors as $x) echo '<div>' . e($x) . '</div>'; ?></div><?php endif; ?>
<?php if ($edit): ?><form class="panel" method="post"><h2>Бағаны түзету</h2><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
  <div class="form-grid">
    <label class="field"><span>Балл (0–100)</span><input type="number" name="score" min="0" max="100" value="<?= e($_POST['score'] ?? $edit['score']) ?>" required></label>
    <label class="field"><span>Жұмыс түрі</span><select name="work_type"><?php foreach (WORK_TYPES as $w) echo '<option' . (($edit['work_type'] === $w) ? ' selected' : '') . '>' . e($w) . '</option>'; ?></select></label>
    <label class="field"><span>Күні</span><input type="date" name="graded_at" value="<?= e($edit['graded_at']) ?>" required></label>
    <label class="field"><span>Пікір</span><input name="comment" maxlength="255" value="<?= e($edit['comment']) ?>"></label></div>
  <button class="btn" type="submit">Сақтау</button> <a class="btn btn-ghost" href="grades.php">Бас тарту</a></form><?php endif; ?>
<div class="panel tablewrap"><table class="data" data-table><thead><tr><th>Күні</th><th>Студент</th><th>Топ</th><th>Пән</th><th>Түрі</th><th>Балл</th><th>Мұғалім</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td><?= e(date('d.m.Y', strtotime($r['graded_at']))) ?></td><td><?= e($r['student']) ?></td><td><?= e($r['grp'] ?? '—') ?></td><td><?= e($r['subject']) ?></td><td><?= e($r['work_type']) ?></td><td><b><?= (int)$r['score'] ?></b></td><td><?= e($r['teacher'] ?? '—') ?></td>
<td><div class="row-actions"><a class="btn btn-ghost btn-sm" href="?edit=<?= (int)$r['id'] ?>">Түзету</a>
<form method="post" class="inline" data-confirm="Бағаны жоясыз ба?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="action" value="delete"><button class="btn btn-danger btn-sm" type="submit">Жою</button></form></div></td></tr>
<?php endforeach; ?></tbody></table></div>
<?php render_foot();
