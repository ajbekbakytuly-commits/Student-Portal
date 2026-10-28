<?php
require 'session.php';
require_login();
$err = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['action'] ?? '') === 'profile') {
        $name = trim($_POST['name'] ?? ''); $email = trim($_POST['email'] ?? '');
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $err[] = 'Аты-жөні мен дұрыс Email енгізіңіз.';
        elseif (q('SELECT 1 FROM users WHERE email = ? AND id <> ?', [$email, uid()])->fetch()) $err[] = 'Бұл Email басқа аккаунтта қолданылады.';
        else {
            q('UPDATE users SET name = ?, email = ? WHERE id = ?', [$name, $email, uid()]);
            $_SESSION['name'] = $name;
            log_action('profile_update', 'Профиль өзгертілді');
            flash_set('Профиль сақталды.'); go('profile.php');
        }
    } elseif (($_POST['action'] ?? '') === 'password') {
        $cur = $_POST['current'] ?? ''; $new = $_POST['new'] ?? ''; $conf = $_POST['confirm'] ?? '';
        $hash = q('SELECT password FROM users WHERE id = ?', [uid()])->fetchColumn();
        if (!password_verify($cur, $hash)) $err[] = 'Ағымдағы пароль қате.';
        elseif (strlen($new) < 6) $err[] = 'Жаңа пароль кемінде 6 символ болуы керек.';
        elseif ($new !== $conf) $err[] = 'Жаңа парольдер сәйкес емес.';
        else {
            q('UPDATE users SET password = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), uid()]);
            session_regenerate_id(true);
            log_action('password_change', 'Пароль өзгертілді');
            flash_set('Пароль өзгертілді.'); go('profile.php');
        }
    }
}
$u = q('SELECT u.*, g.name AS group_name, t.specialty FROM users u LEFT JOIN students s ON s.user_id = u.id LEFT JOIN `groups` g ON g.id = s.group_id LEFT JOIN teachers t ON t.user_id = u.id WHERE u.id = ?', [uid()])->fetch();
render_head('Профиль'); ?>
<div class="profile-head"><div class="avatar"><?= e(mb_strtoupper(mb_substr($u['name'], 0, 1))) ?></div>
  <div><h1><?= e($u['name']) ?></h1><p class="muted">@<?= e($u['username']) ?> · <?= e(ROLE_LABELS[$u['role']]) ?><?= $u['group_name'] ? ' · ' . e($u['group_name']) : '' ?></p></div></div>
<?php if ($err): ?><div class="alert alert-err"><?php foreach ($err as $x) echo '<div>' . e($x) . '</div>'; ?></div><?php endif; ?>
<div class="grid2">
  <form class="panel" method="post"><h2>Профильді өзгерту</h2><?= csrf_field() ?><input type="hidden" name="action" value="profile">
    <label class="field"><span>Аты-жөні</span><input name="name" value="<?= e($u['name']) ?>" required></label>
    <label class="field"><span>Email</span><input type="email" name="email" value="<?= e($u['email']) ?>" required></label>
    <dl class="list"><div><dt>Тіркелген күні</dt><dd><?= e(date('d.m.Y', strtotime($u['created_at']))) ?></dd></div>
      <div><dt>Жүйеге кірген уақыт</dt><dd><?= e(date('d.m.Y H:i', $_SESSION['login_time'] ?? time())) ?></dd></div></dl>
    <button class="btn" type="submit">Сақтау</button></form>
  <form class="panel" method="post"><h2>Парольді өзгерту</h2><?= csrf_field() ?><input type="hidden" name="action" value="password">
    <label class="field"><span>Ағымдағы пароль</span><input type="password" name="current" required></label>
    <label class="field"><span>Жаңа пароль</span><input type="password" name="new" required></label>
    <label class="field"><span>Жаңа парольді қайталау</span><input type="password" name="confirm" required></label>
    <button class="btn" type="submit">Парольді өзгерту</button></form>
</div>
<?php render_foot();
