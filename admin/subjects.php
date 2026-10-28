<?php
require '../session.php';
require_role(['admin']);
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $a = $_POST['action'] ?? ''; $id = (int)($_POST['id'] ?? 0); $name = trim($_POST['name'] ?? '');
    if ($a === 'save') {
        if ($name === '' || mb_strlen($name) > 100) $errors[] = 'Атауын енгізіңіз (100 символға дейін).';
        elseif (q('SELECT 1 FROM subjects WHERE name = ? AND id <> ?', [$name, $id])->fetch()) $errors[] = 'Мұндай атау бар.';
        else {
            if ($id) q('UPDATE subjects SET name = ? WHERE id = ?', [$name, $id]); else q('INSERT INTO subjects (name) VALUES (?)', [$name]);
            log_action('subjects_save', $name); flash_set('Сақталды.'); go('admin/subjects.php');
        }
    } elseif ($a === 'delete' && $id) {
        q('DELETE FROM subjects WHERE id = ?', [$id]); log_action('subjects_delete', '#' . $id); flash_set('Жойылды.'); go('admin/subjects.php');
    }
}
$edit = isset($_GET['edit']) ? q('SELECT * FROM subjects WHERE id = ?', [(int)$_GET['edit']])->fetch() : null;
$rows = q('SELECT s.*, (SELECT COUNT(*) FROM teacher_subjects ts WHERE ts.subject_id = s.id) AS n FROM subjects s ORDER BY s.name')->fetchAll();
render_head('Пәндер'); ?>
<h1>Пәндер</h1>
<?php if ($errors): ?><div class="alert alert-err"><?php foreach ($errors as $x) echo '<div>' . e($x) . '</div>'; ?></div><?php endif; ?>
<form class="panel" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
  <label class="field"><span>Атауы</span><input name="name" value="<?= e($_POST['name'] ?? $edit['name'] ?? '') ?>" required maxlength="100"></label>
  <button class="btn" type="submit"><?= $edit ? 'Сақтау' : 'Қосу' ?></button> <?php if ($edit): ?><a class="btn btn-ghost" href="subjects.php">Бас тарту</a><?php endif; ?></form>
<div class="panel tablewrap"><table class="data" data-table><thead><tr><th>Атауы</th><th>Тағайындаулар</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td><?= e($r['name']) ?></td><td><?= (int)$r['n'] ?></td><td><div class="row-actions"><a class="btn btn-ghost btn-sm" href="?edit=<?= (int)$r['id'] ?>">Өзгерту</a>
<form method="post" class="inline" data-confirm="«<?= e($r['name']) ?>» жойылса, байланысты бағалар, кесте және тапсырмалар да жойылады. Жалғастырасыз ба?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="action" value="delete"><button class="btn btn-danger btn-sm" type="submit">Жою</button></form></div></td></tr>
<?php endforeach; ?></tbody></table></div>
<?php render_foot();
