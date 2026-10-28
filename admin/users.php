<?php
require '../session.php';
require_role(['admin']);
$groups = q('SELECT id, name FROM `groups` ORDER BY name')->fetchAll();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $a = $_POST['action'] ?? ''; $id = (int)($_POST['id'] ?? 0);
    $target = $id ? q('SELECT * FROM users WHERE id = ?', [$id])->fetch() : null;

    if ($a === 'save') {
        $name = trim($_POST['name'] ?? ''); $email = trim($_POST['email'] ?? ''); $username = trim($_POST['username'] ?? '');
        $pass = $_POST['password'] ?? ''; $role = $target ? $target['role'] : ($_POST['role'] ?? '');
        $gid = (int)($_POST['group_id'] ?? 0) ?: null; $spec = trim($_POST['specialty'] ?? '') ?: null;
        if ($id && !$target) $errors[] = 'Пайдаланушы табылмады.';
        if ($target && $target['role'] === 'admin') $errors[] = 'Әкімшіні осы жерде өзгертуге болмайды (профиль бетін қолданыңыз).';
        if (!in_array($role, ['student', 'teacher'], true)) $errors[] = 'Рөл дұрыс емес.';
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Аты-жөні мен дұрыс Email енгізіңіз.';
        if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $username)) $errors[] = 'Логин: 3–30 символ, латын әріптері, сан және _.';
        if (!$id && strlen($pass) < 6) $errors[] = 'Пароль кемінде 6 символ болуы керек.';
        if ($id && $pass !== '' && strlen($pass) < 6) $errors[] = 'Жаңа пароль кемінде 6 символ болуы керек.';
        if ($gid && !in_array($gid, array_column($groups, 'id'))) $errors[] = 'Топ табылмады.';
        if (!$errors && q('SELECT 1 FROM users WHERE (username = ? OR email = ?) AND id <> ?', [$username, $email, $id])->fetch()) $errors[] = 'Мұндай логин немесе Email бар.';
        if (!$errors) {
            $pdo->beginTransaction();
            if ($id) {
                q('UPDATE users SET name=?, email=?, username=? WHERE id=?', [$name, $email, $username, $id]);
                if ($pass !== '') q('UPDATE users SET password=? WHERE id=?', [password_hash($pass, PASSWORD_DEFAULT), $id]);
            } else {
                q('INSERT INTO users (name,email,username,password,role) VALUES (?,?,?,?,?)', [$name, $email, $username, password_hash($pass, PASSWORD_DEFAULT), $role]);
                $id = (int)$pdo->lastInsertId();
            }
            if ($role === 'student') q('INSERT INTO students (user_id, group_id) VALUES (?,?) ON DUPLICATE KEY UPDATE group_id = VALUES(group_id)', [$id, $gid]);
            else q('INSERT INTO teachers (user_id, specialty) VALUES (?,?) ON DUPLICATE KEY UPDATE specialty = VALUES(specialty)', [$id, $spec]);
            $pdo->commit();
            log_action($target ? 'user_update' : 'user_create', "$role: $username");
            flash_set('Сақталды.'); go('admin/users.php');
        }
    } elseif ($target && $target['role'] !== 'admin' && $id !== uid()) {
        if ($a === 'delete') { q('DELETE FROM users WHERE id = ?', [$id]); log_action('user_delete', $target['role'] . ': ' . $target['username']); flash_set('Пайдаланушы жойылды.'); }
        elseif ($a === 'toggle') {
            $ns = $target['status'] === 'active' ? 'blocked' : 'active';
            q('UPDATE users SET status = ? WHERE id = ?', [$ns, $id]);
            log_action('user_' . $ns, $target['username']); flash_set($ns === 'blocked' ? 'Аккаунт бұғатталды.' : 'Аккаунт ашылды.');
        } elseif ($a === 'reset') {
            $tmp = substr(bin2hex(random_bytes(6)), 0, 10);
            q('UPDATE users SET password = ? WHERE id = ?', [password_hash($tmp, PASSWORD_DEFAULT), $id]);
            log_action('password_reset', $target['username']);
            flash_set('Уақытша пароль (бір рет көрсетіледі): ' . $tmp, 'warn');
        }
        go('admin/users.php');
    } else go('admin/users.php');
}
$edit = null;
if (isset($_GET['edit'])) $edit = q('SELECT u.*, s.group_id, t.specialty FROM users u LEFT JOIN students s ON s.user_id = u.id LEFT JOIN teachers t ON t.user_id = u.id WHERE u.id = ? AND u.role <> "admin"', [(int)$_GET['edit']])->fetch() ?: null;
$f = ['name' => $_POST['name'] ?? ($edit['name'] ?? ''), 'email' => $_POST['email'] ?? ($edit['email'] ?? ''), 'username' => $_POST['username'] ?? ($edit['username'] ?? ''),
      'role' => $_POST['role'] ?? ($edit['role'] ?? 'student'), 'group_id' => $_POST['group_id'] ?? ($edit['group_id'] ?? ''), 'specialty' => $_POST['specialty'] ?? ($edit['specialty'] ?? '')];
$rf = $_GET['role'] ?? '';
$users = q('SELECT u.*, g.name AS group_name FROM users u LEFT JOIN students s ON s.user_id = u.id LEFT JOIN `groups` g ON g.id = s.group_id' . (in_array($rf, ['admin', 'teacher', 'student']) ? ' WHERE u.role = ' . $pdo->quote($rf) : '') . ' ORDER BY u.role, u.name')->fetchAll();
render_head('Пайдаланушылар'); ?>
<h1>Пайдаланушылар</h1>
<?php if ($errors): ?><div class="alert alert-err"><?php foreach ($errors as $x) echo '<div>' . e($x) . '</div>'; ?></div><?php endif; ?>
<details class="panel" <?= ($edit || $errors) ? 'open' : '' ?>><summary><?= $edit ? 'Пайдаланушыны өзгерту' : 'Жаңа пайдаланушы' ?></summary>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
    <div class="form-grid">
      <label class="field"><span>Аты-жөні</span><input name="name" value="<?= e($f['name']) ?>" required></label>
      <label class="field"><span>Email</span><input type="email" name="email" value="<?= e($f['email']) ?>" required></label>
      <label class="field"><span>Логин</span><input name="username" value="<?= e($f['username']) ?>" required></label>
      <label class="field"><span>Пароль<?= $edit ? ' (бос қалдырсаңыз өзгермейді)' : '' ?></span><input type="password" name="password" autocomplete="new-password"></label>
      <?php if (!$edit): ?><label class="field"><span>Рөл</span><select name="role" data-rolesel><option value="student" <?= $f['role'] === 'student' ? 'selected' : '' ?>>Студент</option><option value="teacher" <?= $f['role'] === 'teacher' ? 'selected' : '' ?>>Мұғалім</option></select></label>
      <?php else: ?><input type="hidden" data-rolesel value="<?= e($edit['role']) ?>"><?php endif; ?>
      <label class="field for-student"><span>Топ</span><select name="group_id"><option value="">— жоқ —</option><?= opts($groups, $f['group_id']) ?></select></label>
      <label class="field for-teacher"><span>Мамандығы</span><input name="specialty" value="<?= e($f['specialty']) ?>"></label>
    </div>
    <button class="btn" type="submit">Сақтау</button> <?php if ($edit): ?><a class="btn btn-ghost" href="users.php">Бас тарту</a><?php endif; ?></form></details>
<div class="tabs"><a class="<?= $rf === '' ? 'on' : '' ?>" href="users.php">Барлығы</a><a class="<?= $rf === 'student' ? 'on' : '' ?>" href="?role=student">Студенттер</a><a class="<?= $rf === 'teacher' ? 'on' : '' ?>" href="?role=teacher">Мұғалімдер</a></div>
<div class="panel tablewrap"><table class="data" data-table><thead><tr><th>Аты-жөні</th><th>Логин</th><th>Email</th><th>Рөл</th><th>Топ</th><th>Күйі</th><th></th></tr></thead><tbody>
<?php foreach ($users as $u): $own = $u['role'] === 'admin' || (int)$u['id'] === uid(); ?>
<tr><td><?= e($u['name']) ?></td><td><?= e($u['username']) ?></td><td><?= e($u['email']) ?></td><td><?= e(ROLE_LABELS[$u['role']]) ?></td><td><?= e($u['group_name'] ?? '—') ?></td>
<td><?= pill($u['status'] === 'active' ? ['Белсенді', 'ok'] : ['Бұғатталған', 'err']) ?></td>
<td><?php if (!$own): ?><div class="row-actions">
  <a class="btn btn-ghost btn-sm" href="?edit=<?= (int)$u['id'] ?>">Өзгерту</a>
  <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><input type="hidden" name="action" value="toggle"><button class="btn btn-ghost btn-sm" type="submit"><?= $u['status'] === 'active' ? 'Бұғаттау' : 'Ашу' ?></button></form>
  <form method="post" class="inline" data-confirm="Уақытша пароль жасалсын ба? Ескі пароль жарамсыз болады."><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><input type="hidden" name="action" value="reset"><button class="btn btn-ghost btn-sm" type="submit">Пароль қалпына келтіру</button></form>
  <form method="post" class="inline" data-confirm="«<?= e($u['name']) ?>» және оның барлық бағалары мен жұмыстары жойылады. Жалғастырасыз ба?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><input type="hidden" name="action" value="delete"><button class="btn btn-danger btn-sm" type="submit">Жою</button></form></div><?php endif; ?></td></tr>
<?php endforeach; ?></tbody></table></div>
<?php render_foot();
