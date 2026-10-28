<?php
require 'session.php';
redirect_if_logged_in();
$error = ''; $username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username === '' || $password === '') {
        $error = 'Логин мен парольді енгізіңіз.';
    } else {
        $user = q('SELECT id, name, username, password, role, status FROM users WHERE username = ?', [$username])->fetch();
        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] !== 'active') {
                $error = 'Аккаунт бұғатталған. Әкімшіге хабарласыңыз.';
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['login_time'] = time();
                $_SESSION['last_activity'] = time();
                log_action('login', 'Жүйеге кірді', $user['id']);
                flash_set('Қош келдіңіз, ' . $user['name'] . '!');
                go('dashboard.php');
            }
        } else {
            $error = 'Логин немесе пароль қате';
        }
    }
}
render_head('Кіру'); ?>
<section class="auth">
  <form class="card <?= $error ? 'shake' : '' ?>" method="post" novalidate>
    <h2>Кіру</h2>
    <?php if (!empty($_GET['timeout'])): ?><div class="alert alert-warn">Әрекетсіздік салдарынан сессия аяқталды. Қайта кіріңіз.</div><?php endif; ?>
    <?php if (!empty($_GET['out'])): ?><div class="alert alert-ok">Сіз жүйеден шықтыңыз.</div><?php endif; ?>
    <?php if (!empty($_GET['blocked'])): ?><div class="alert alert-err">Аккаунтыңыз бұғатталған.</div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-err"><?= e($error) ?></div><?php endif; ?>
    <?= csrf_field() ?>
    <label class="field"><span>Логин</span><input type="text" name="username" value="<?= e($username) ?>" required autofocus></label>
    <label class="field"><span>Пароль</span><input type="password" name="password" required></label>
    <button class="btn btn-block" type="submit">Кіру</button>
    <p class="muted">Аккаунтыңыз жоқ па? <a href="register.php">Тіркелу</a></p>
  </form>
</section>
<?php render_foot();
