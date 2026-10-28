<?php
require '../session.php';
require_role(['admin']);
$teachers = q("SELECT id, name FROM users WHERE role = 'teacher' AND status = 'active' ORDER BY name")->fetchAll();
$subjects = q('SELECT id, name FROM subjects ORDER BY name')->fetchAll();
$groups = q('SELECT id, name FROM `groups` ORDER BY name')->fetchAll();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['action'] ?? '') === 'add') {
        [$t, $s, $g] = [(int)$_POST['teacher_id'], (int)$_POST['subject_id'], (int)$_POST['group_id']];
        if (in_array($t, array_column($teachers, 'id')) && in_array($s, array_column($subjects, 'id')) && in_array($g, array_column($groups, 'id'))) {
            q('INSERT IGNORE INTO teacher_subjects (teacher_id, subject_id, group_id) VALUES (?,?,?)', [$t, $s, $g]);
            log_action('assignment_add', "мұғалім #$t, пән #$s, топ #$g");
            notify($t, 'Жаңа тағайындау', 'Сізге пән мен топ тағайындалды.', BASE . 'teacher/dashboard.php');
            flash_set('Тағайындалды.');
        } else flash_set('Деректер дұрыс емес.', 'err');
    } elseif (($_POST['action'] ?? '') === 'delete') {
        q('DELETE FROM teacher_subjects WHERE id = ?', [(int)$_POST['id']]); log_action('assignment_delete', '#' . (int)$_POST['id']); flash_set('Жойылды.');
    }
    go('admin/assignments.php');
}
$rows = q('SELECT ts.id, u.name AS teacher, s.name AS subject, g.name AS grp FROM teacher_subjects ts JOIN users u ON u.id = ts.teacher_id JOIN subjects s ON s.id = ts.subject_id JOIN `groups` g ON g.id = ts.group_id ORDER BY u.name, s.name, g.name')->fetchAll();
render_head('Мұғалім тағайындау'); ?>
<h1>Мұғалімдерге пән мен топ тағайындау</h1>
<form class="panel" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="add">
  <div class="form-grid">
    <label class="field"><span>Мұғалім</span><select name="teacher_id" required><?= opts($teachers) ?></select></label>
    <label class="field"><span>Пән</span><select name="subject_id" required><?= opts($subjects) ?></select></label>
    <label class="field"><span>Топ</span><select name="group_id" required><?= opts($groups) ?></select></label></div>
  <button class="btn" type="submit">Тағайындау</button></form>
<div class="panel tablewrap"><table class="data" data-table><thead><tr><th>Мұғалім</th><th>Пән</th><th>Топ</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td><?= e($r['teacher']) ?></td><td><?= e($r['subject']) ?></td><td><?= e($r['grp']) ?></td>
<td><form method="post" data-confirm="Тағайындауды алып тастайсыз ба?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="action" value="delete"><button class="btn btn-danger btn-sm" type="submit">Жою</button></form></td></tr>
<?php endforeach; ?></tbody></table></div>
<?php render_foot();
