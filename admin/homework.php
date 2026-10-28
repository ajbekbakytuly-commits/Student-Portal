<?php
require '../session.php';
require_role(['admin']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $a = $_POST['action'] ?? ''; $id = (int)($_POST['id'] ?? 0);
    $h = q('SELECT * FROM homework WHERE id = ?', [$id])->fetch();
    if ($h && $a === 'deadline') {
        $dt = DateTime::createFromFormat('Y-m-d\TH:i', $_POST['deadline'] ?? '');
        if ($dt) {
            q('UPDATE homework SET deadline = ? WHERE id = ?', [$dt->format('Y-m-d H:i:00'), $id]);
            log_action('homework_deadline', '#' . $id . ' → ' . $dt->format('d.m.Y H:i'));
            notify_group($h['group_id'], 'Тапсырма мерзімі өзгерді', $h['title'] . ': ' . $dt->format('d.m.Y H:i'), BASE . 'student/homework.php#h' . $id);
            flash_set('Мерзім жаңартылды.');
        } else flash_set('Күні дұрыс емес.', 'err');
    } elseif ($h && $a === 'delete') {
        foreach (q('SELECT file_path FROM homework_submissions WHERE homework_id = ?', [$id])->fetchAll() as $s) delete_file($s['file_path']);
        delete_file($h['file_path']);
        q('DELETE FROM homework WHERE id = ?', [$id]); log_action('homework_delete', $h['title']); flash_set('Тапсырма жойылды.');
    }
    go('admin/homework.php');
}
$rows = q('SELECT h.*, s.name AS subject, g.name AS grp, u.name AS teacher, (SELECT COUNT(*) FROM homework_submissions x WHERE x.homework_id = h.id) AS done, (SELECT COUNT(*) FROM students st WHERE st.group_id = h.group_id) AS total
           FROM homework h JOIN subjects s ON s.id = h.subject_id JOIN `groups` g ON g.id = h.group_id JOIN users u ON u.id = h.teacher_id ORDER BY h.deadline DESC')->fetchAll();
render_head('Үй тапсырмалары'); ?>
<h1>Үй тапсырмалары</h1><p class="muted">Тапсырмаларды мұғалімдер жасайды; әкімші мерзімін өзгерте, жоя және жұмыстарды қарай алады.</p>
<div class="panel tablewrap"><table class="data" data-table><thead><tr><th>Тапсырма</th><th>Пән / топ</th><th>Мұғалім</th><th>Тапсырды</th><th>Мерзімі</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td><?= e($r['title']) ?></td><td><?= e($r['subject']) ?> · <?= e($r['grp']) ?></td><td><?= e($r['teacher']) ?></td><td><?= (int)$r['done'] ?>/<?= (int)$r['total'] ?></td>
<td><form method="post" class="row-actions"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="action" value="deadline"><input type="datetime-local" name="deadline" value="<?= e(date('Y-m-d\TH:i', strtotime($r['deadline']))) ?>" style="width:200px"><button class="btn btn-ghost btn-sm" type="submit">Сақтау</button></form></td>
<td><div class="row-actions"><a class="btn btn-ghost btn-sm" href="<?= BASE ?>submissions.php?hw=<?= (int)$r['id'] ?>">Жұмыстар</a>
<form method="post" class="inline" data-confirm="Тапсырма мен студенттердің жүктеген файлдары жойылады. Жалғастырасыз ба?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="action" value="delete"><button class="btn btn-danger btn-sm" type="submit">Жою</button></form></div></td></tr>
<?php endforeach; ?></tbody></table></div>
<?php render_foot();
