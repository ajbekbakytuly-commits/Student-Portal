<?php
require '../session.php';
require_role(['teacher']);
$asg = q('SELECT ts.id, ts.subject_id, ts.group_id, CONCAT(s.name, " — ", g.name) AS label FROM teacher_subjects ts JOIN subjects s ON s.id = ts.subject_id JOIN `groups` g ON g.id = ts.group_id WHERE ts.teacher_id = ? ORDER BY s.name, g.name', [uid()])->fetchAll();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $a = $_POST['action'] ?? ''; $id = (int)($_POST['id'] ?? 0);
    $old = $id ? q('SELECT * FROM homework WHERE id = ? AND teacher_id = ?', [$id, uid()])->fetch() : null;
    if ($a === 'save') {
        $ts = null; foreach ($asg as $x) if ((int)$x['id'] === (int)($_POST['ts'] ?? 0)) $ts = $x;
        $title = trim($_POST['title'] ?? ''); $desc = trim($_POST['description'] ?? '');
        $dt = DateTime::createFromFormat('Y-m-d\TH:i', $_POST['deadline'] ?? '');
        if ($id && !$old) $errors[] = 'Тапсырма табылмады.';
        if (!$ts) $errors[] = 'Пән мен топты таңдаңыз.';
        if ($title === '' || mb_strlen($title) > 160) $errors[] = 'Атауын енгізіңіз (160 символға дейін).';
        if (!$dt) $errors[] = 'Тапсыру мерзімін көрсетіңіз.';
        $file = null; $ferr = '';
        if (!$errors) { $file = save_upload('file', 'homework', $ferr); if ($ferr) $errors[] = $ferr; }
        if (!$errors) {
            $res = isset($_POST['allow_resubmit']) ? 1 : 0;
            if ($old) {
                q('UPDATE homework SET subject_id=?, group_id=?, title=?, description=?, deadline=?, allow_resubmit=? WHERE id=?', [$ts['subject_id'], $ts['group_id'], $title, $desc ?: null, $dt->format('Y-m-d H:i:00'), $res, $id]);
                if ($file) { delete_file($old['file_path']); q('UPDATE homework SET file_path=?, file_name=? WHERE id=?', [$file['path'], $file['name'], $id]); }
                log_action('homework_update', $title);
                notify_group($ts['group_id'], 'Тапсырма жаңартылды', $title, BASE . 'student/homework.php#h' . $id);
            } else {
                q('INSERT INTO homework (subject_id, group_id, teacher_id, title, description, deadline, file_path, file_name, allow_resubmit) VALUES (?,?,?,?,?,?,?,?,?)',
                  [$ts['subject_id'], $ts['group_id'], uid(), $title, $desc ?: null, $dt->format('Y-m-d H:i:00'), $file['path'] ?? null, $file['name'] ?? null, $res]);
                $nid = (int)$pdo->lastInsertId();
                log_action('homework_create', $title);
                notify_group($ts['group_id'], 'Жаңа үй тапсырмасы', $title . ' · мерзімі ' . $dt->format('d.m.Y H:i'), BASE . 'student/homework.php#h' . $nid);
            }
            flash_set('Тапсырма сақталды.'); go('teacher/homework.php');
        }
    } elseif ($a === 'delete' && $old) {
        foreach (q('SELECT file_path FROM homework_submissions WHERE homework_id = ?', [$id])->fetchAll() as $s) delete_file($s['file_path']);
        delete_file($old['file_path']);
        q('DELETE FROM homework WHERE id = ?', [$id]); log_action('homework_delete', $old['title']); flash_set('Тапсырма жойылды.'); go('teacher/homework.php');
    }
}
$edit = isset($_GET['edit']) ? q('SELECT * FROM homework WHERE id = ? AND teacher_id = ?', [(int)$_GET['edit'], uid()])->fetch() : null;
$editTs = null; if ($edit) foreach ($asg as $x) if ($x['subject_id'] == $edit['subject_id'] && $x['group_id'] == $edit['group_id']) $editTs = $x['id'];
$f = fn($k, $d = '') => $_POST[$k] ?? ($edit[$k] ?? $d);
$rows = q('SELECT h.*, s.name AS subject, g.name AS grp, (SELECT COUNT(*) FROM homework_submissions x WHERE x.homework_id = h.id) AS done, (SELECT COUNT(*) FROM students st WHERE st.group_id = h.group_id) AS total
           FROM homework h JOIN subjects s ON s.id = h.subject_id JOIN `groups` g ON g.id = h.group_id WHERE h.teacher_id = ? ORDER BY h.deadline DESC', [uid()])->fetchAll();
render_head('Үй тапсырмалары'); ?>
<h1>Үй тапсырмалары</h1>
<?php if ($errors): ?><div class="alert alert-err"><?php foreach ($errors as $x) echo '<div>' . e($x) . '</div>'; ?></div><?php endif; ?>
<?php if (!$asg): ?><div class="panel"><p class="muted">Алдымен әкімші сізге пән мен топ тағайындауы керек.</p></div><?php else: ?>
<details class="panel" <?= ($edit || $errors) ? 'open' : '' ?>><summary><?= $edit ? 'Тапсырманы өзгерту' : 'Жаңа тапсырма' ?></summary>
<form method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
  <div class="form-grid">
    <label class="field"><span>Пән және топ</span><select name="ts"><?= opts($asg, $_POST['ts'] ?? $editTs, 'label') ?></select></label>
    <label class="field"><span>Тапсыру мерзімі</span><input type="datetime-local" name="deadline" value="<?= e($edit ? date('Y-m-d\TH:i', strtotime($edit['deadline'])) : ($_POST['deadline'] ?? '')) ?>" required></label></div>
  <label class="field"><span>Атауы</span><input name="title" maxlength="160" value="<?= e($f('title')) ?>" required></label>
  <label class="field"><span>Сипаттамасы</span><textarea name="description"><?= e($f('description')) ?></textarea></label>
  <label class="field"><span>Әдістемелік файл (PDF, DOC, DOCX, ZIP, сурет; ≤ 10 МБ)<?= $edit && $edit['file_name'] ? ' — қазір: ' . e($edit['file_name']) : '' ?></span><input type="file" name="file"></label>
  <label class="field"><input type="checkbox" name="allow_resubmit" value="1" <?= $f('allow_resubmit') ? 'checked' : '' ?>> Студентке мерзімге дейін қайта жүктеуге рұқсат ету</label>
  <button class="btn" type="submit">Сақтау</button> <?php if ($edit): ?><a class="btn btn-ghost" href="homework.php">Бас тарту</a><?php endif; ?></form></details>
<div class="panel tablewrap"><table class="data" data-table><thead><tr><th>Тапсырма</th><th>Пән / топ</th><th>Мерзімі</th><th>Тапсырды</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td><?= e($r['title']) ?></td><td><?= e($r['subject']) ?> · <?= e($r['grp']) ?></td><td><?= e(date('d.m.Y H:i', strtotime($r['deadline']))) ?></td><td><?= (int)$r['done'] ?>/<?= (int)$r['total'] ?></td>
<td><div class="row-actions"><a class="btn btn-sm" href="<?= BASE ?>submissions.php?hw=<?= (int)$r['id'] ?>">Жұмыстар</a><a class="btn btn-ghost btn-sm" href="?edit=<?= (int)$r['id'] ?>">Өзгерту</a>
<form method="post" class="inline" data-confirm="Тапсырма мен студенттердің жүктеген файлдары жойылады. Жалғастырасыз ба?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="action" value="delete"><button class="btn btn-danger btn-sm" type="submit">Жою</button></form></div></td></tr>
<?php endforeach; ?></tbody></table></div>
<?php endif; render_foot();
