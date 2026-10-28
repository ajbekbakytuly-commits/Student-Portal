<?php
require '../session.php';
require_role(['admin']);
$groups = q('SELECT id, name FROM `groups` ORDER BY name')->fetchAll();
$subjects = q('SELECT id, name FROM subjects ORDER BY name')->fetchAll();
$teachers = q("SELECT id, name FROM users WHERE role = 'teacher' ORDER BY name")->fetchAll();
$errors = [];

function conflict($id, $day, $st, $en, $gid, $tid, $room) {
    $r = q("SELECT group_id, teacher_id, room FROM schedules WHERE day_of_week = ? AND start_time < ? AND end_time > ? AND id <> ? AND (group_id = ? OR teacher_id = ? OR (room <> '' AND room = ?)) LIMIT 1",
           [$day, $en, $st, $id, $gid, $tid, $room])->fetch();
    if (!$r) return null;
    if ((int)$r['group_id'] === $gid) return 'Бұл топтың осы уақытта басқа сабағы бар.';
    if ((int)$r['teacher_id'] === $tid) return 'Мұғалімнің осы уақытта басқа сабағы бар.';
    return 'Кабинет осы уақытта бос емес.';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $a = $_POST['action'] ?? ''; $id = (int)($_POST['id'] ?? 0);
    if ($a === 'save') {
        $gid = (int)$_POST['group_id']; $sid = (int)$_POST['subject_id']; $tid = (int)$_POST['teacher_id'];
        $day = (int)$_POST['day']; $st = $_POST['start'] ?? ''; $en = $_POST['end'] ?? ''; $room = trim($_POST['room'] ?? '');
        if (!in_array($gid, array_column($groups, 'id')) || !in_array($sid, array_column($subjects, 'id')) || !in_array($tid, array_column($teachers, 'id'))) $errors[] = 'Топ, пән және мұғалімді таңдаңыз.';
        if ($day < 1 || $day > 6) $errors[] = 'Күнді таңдаңыз.';
        if (!preg_match('/^\d{2}:\d{2}$/', $st) || !preg_match('/^\d{2}:\d{2}$/', $en) || $st >= $en) $errors[] = 'Уақыт дұрыс емес: аяқталуы басталуынан кейін болуы керек.';
        if (mb_strlen($room) > 30) $errors[] = 'Кабинет атауы тым ұзын.';
        if (!$errors && ($c = conflict($id, $day, $st, $en, $gid, $tid, $room))) $errors[] = 'Қақтығыс: ' . $c;
        if (!$errors) {
            if ($id) q('UPDATE schedules SET group_id=?, subject_id=?, teacher_id=?, day_of_week=?, start_time=?, end_time=?, room=? WHERE id=?', [$gid, $sid, $tid, $day, $st, $en, $room, $id]);
            else q('INSERT INTO schedules (group_id, subject_id, teacher_id, day_of_week, start_time, end_time, room) VALUES (?,?,?,?,?,?,?)', [$gid, $sid, $tid, $day, $st, $en, $room]);
            log_action($id ? 'schedule_update' : 'schedule_create', DAYS[$day] . " $st–$en, топ #$gid");
            notify_group($gid, 'Сабақ кестесі өзгерді', DAYS[$day] . ', ' . $st . '–' . $en, BASE . 'student/schedule.php');
            notify($tid, 'Сабақ кестесі өзгерді', DAYS[$day] . ', ' . $st . '–' . $en, BASE . 'teacher/schedule.php');
            flash_set('Кесте сақталды.'); go('admin/schedule.php');
        }
    } elseif ($a === 'delete' && $id) {
        $r = q('SELECT * FROM schedules WHERE id = ?', [$id])->fetch();
        if ($r) {
            q('DELETE FROM schedules WHERE id = ?', [$id]); log_action('schedule_delete', '#' . $id);
            notify_group($r['group_id'], 'Сабақ кестесінен сабақ алынды', DAYS[$r['day_of_week']], BASE . 'student/schedule.php');
            notify($r['teacher_id'], 'Сабақ кестесінен сабақ алынды', DAYS[$r['day_of_week']], BASE . 'teacher/schedule.php');
            flash_set('Жойылды.');
        }
        go('admin/schedule.php');
    }
}
$edit = isset($_GET['edit']) ? q('SELECT * FROM schedules WHERE id = ?', [(int)$_GET['edit']])->fetch() : null;
$v = fn($k, $dk, $d = '') => $_POST[$k] ?? ($edit[$dk] ?? $d);
$fg = (int)($_GET['g'] ?? 0);
$rows = schedule_rows($fg ? 'sc.group_id = ?' : '1=1', $fg ? [$fg] : []);
render_head('Сабақ кестесі'); ?>
<h1>Сабақ кестесі</h1>
<?php if ($errors): ?><div class="alert alert-err"><?php foreach ($errors as $x) echo '<div>' . e($x) . '</div>'; ?></div><?php endif; ?>
<details class="panel" <?= ($edit || $errors) ? 'open' : '' ?>><summary><?= $edit ? 'Сабақты өзгерту' : 'Жаңа сабақ' ?></summary>
<form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
  <div class="form-grid">
    <label class="field"><span>Топ</span><select name="group_id"><?= opts($groups, $v('group_id', 'group_id')) ?></select></label>
    <label class="field"><span>Пән</span><select name="subject_id"><?= opts($subjects, $v('subject_id', 'subject_id')) ?></select></label>
    <label class="field"><span>Мұғалім</span><select name="teacher_id"><?= opts($teachers, $v('teacher_id', 'teacher_id')) ?></select></label>
    <label class="field"><span>Күні</span><select name="day"><?php foreach (DAYS as $n => $d) echo '<option value="' . $n . '"' . ((int)$v('day', 'day_of_week', 1) === $n ? ' selected' : '') . '>' . $d . '</option>'; ?></select></label>
    <label class="field"><span>Басталуы</span><input type="time" name="start" value="<?= e(substr($v('start', 'start_time', '09:00'), 0, 5)) ?>" required></label>
    <label class="field"><span>Аяқталуы</span><input type="time" name="end" value="<?= e(substr($v('end', 'end_time', '10:30'), 0, 5)) ?>" required></label>
    <label class="field"><span>Кабинет</span><input name="room" maxlength="30" value="<?= e($v('room', 'room')) ?>"></label></div>
  <button class="btn" type="submit">Сақтау</button> <?php if ($edit): ?><a class="btn btn-ghost" href="schedule.php">Бас тарту</a><?php endif; ?></form></details>
<form method="get" class="panel"><label class="field" style="max-width:300px;margin:0"><span>Топ бойынша сүзгі</span><select name="g" onchange="this.form.submit()"><option value="0">Барлық топтар</option><?= opts($groups, $fg) ?></select></label></form>
<div class="panel tablewrap"><table class="data" data-table><thead><tr><th>Күні</th><th>Уақыты</th><th>Топ</th><th>Пән</th><th>Мұғалім</th><th>Каб.</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td><?= DAYS[$r['day_of_week']] ?></td><td><?= substr($r['start_time'], 0, 5) ?>–<?= substr($r['end_time'], 0, 5) ?></td><td><?= e($r['group_name']) ?></td><td><?= e($r['subject']) ?></td><td><?= e($r['teacher']) ?></td><td><?= e($r['room']) ?></td>
<td><div class="row-actions"><a class="btn btn-ghost btn-sm" href="?edit=<?= (int)$r['id'] ?>">Өзгерту</a>
<form method="post" class="inline" data-confirm="Сабақты кестеден жоясыз ба?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="action" value="delete"><button class="btn btn-danger btn-sm" type="submit">Жою</button></form></div></td></tr>
<?php endforeach; ?></tbody></table></div>
<?php render_foot();
