<?php
// Әкімшінің бастапқы аккаунты. Тек әкімші жоқ болғанда және тек localhost-тан жұмыс істейді.
// Әкімші жасалған соң бұл файлды жойған дұрыс.
require 'session.php';
$local = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
$exists = (bool)q("SELECT 1 FROM users WHERE role = 'admin' LIMIT 1")->fetch();
$errors = []; $v = ['name' => '', 'email' => '', 'username' => ''];

if (!$exists && $local && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach ($v as $k => $_) $v[$k] = trim($_POST[$k] ?? '');
    $password = $_POST['password'] ?? '';
    if ($v['name'] === '' || !filter_var($v['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Аты-жөні мен дұрыс Email енгізіңіз.';
    if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $v['username'])) $errors[] = 'Логин: 3–30 символ, латын әріптері, сан және _.';
    if (strlen($password) < 8) $errors[] = 'Әкімші паролі кемінде 8 символ болуы керек.';
    if (!$errors && q('SELECT 1 FROM users WHERE username = ? OR email = ?', [$v['username'], $v['email']])->fetch()) $errors[] = 'Мұндай логин немесе Email бар.';
    if (!$errors) {
        q("INSERT INTO users (name, email, username, password, role) VALUES (?,?,?,?, 'admin')", [$v['name'], $v['email'], $v['username'], password_hash($password, PASSWORD_DEFAULT)]);
        log_action('admin_setup', 'Әкімші жасалды: ' . $v['username'], $pdo->lastInsertId());
        flash_set('Әкімші жасалды. Енді setup.php файлын жойыңыз да, кіріңіз.');
        go('login.php');
    }
}
render_head('Бастапқы баптау'); ?>
<section class="auth">
<?php if ($exists): ?>
  <div class="card"><h2>Әкімші бұрын жасалған</h2><p class="muted">Бұл бет енді қолжетімсіз. Қауіпсіздік үшін <code>setup.php</code> файлын жойыңыз.</p><a class="btn" href="login.php">Кіру</a></div>
<?php elseif (!$local): ?>
  <div class="card"><h2>Қолжетімсіз</h2><p class="muted">Бастапқы баптау тек сервердің өзінен (localhost) жұмыс істейді.</p></div>
<?php else: ?>
  <form class="card" method="post" novalidate>
    <h2>Әкімшіні жасау</h2>
    <?php if ($errors): ?><div class="alert alert-err"><?php foreach ($errors as $er) echo '<div>' . e($er) . '</div>'; ?></div><?php endif; ?>
    <?= csrf_field() ?>
    <label class="field"><span>Аты-жөні</span><input name="name" value="<?= e($v['name']) ?>" required></label>
    <label class="field"><span>Email</span><input type="email" name="email" value="<?= e($v['email']) ?>" required></label>
    <label class="field"><span>Логин</span><input name="username" value="<?= e($v['username']) ?>" required></label>
    <label class="field"><span>Пароль (кемінде 8 символ)</span><input type="password" name="password" required></label>
    <button class="btn btn-block" type="submit">Әкімші жасау</button>
  </form>
<?php endif; ?>
</section>
<?php render_foot();
