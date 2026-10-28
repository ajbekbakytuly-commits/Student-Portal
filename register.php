<?php
// Тек студент пен мұғалім тіркеле алады. Әкімші setup.php арқылы жасалады.
require 'session.php';
redirect_if_logged_in();
$groups = q('SELECT id, name FROM `groups` ORDER BY name')->fetchAll();
$errors = [];
$v = ['name' => '', 'email' => '', 'username' => '', 'role' => 'student', 'group_id' => '', 'specialty' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach ($v as $k => $_) $v[$k] = trim($_POST[$k] ?? '');
    $password = $_POST['password'] ?? ''; $confirm = $_POST['confirm'] ?? '';

    if ($v['name'] === '' || $v['email'] === '' || $v['username'] === '' || $password === '') $errors[] = 'Барлық міндетті өрістерді толтырыңыз.';
    if ($v['email'] !== '' && !filter_var($v['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Email дұрыс емес.';
    if ($v['username'] !== '' && !preg_match('/^[A-Za-z0-9_]{3,30}$/', $v['username'])) $errors[] = 'Логин: 3–30 символ, тек латын әріптері, сан және _.';
    if ($password !== '' && strlen($password) < 6) $errors[] = 'Пароль кемінде 6 символдан тұруы керек.';
    if ($password !== $confirm) $errors[] = 'Парольдер сәйкес емес.';
    if (!in_array($v['role'], ['student', 'teacher'], true)) $errors[] = 'Рөл дұрыс емес.';
    $gid = null;
    if ($v['role'] === 'student' && $groups) {
        if (!in_array((int)$v['group_id'], array_column($groups, 'id'))) $errors[] = 'Топты таңдаңыз.';
        else $gid = (int)$v['group_id'];
    }
    if (!$errors && q('SELECT id FROM users WHERE username = ? OR email = ?', [$v['username'], $v['email']])->fetch())
        $errors[] = 'Мұндай логин немесе Email бұрын тіркелген.';

    if (!$errors) {
        $pdo->beginTransaction();
        q('INSERT INTO users (name, email, username, password, role) VALUES (?,?,?,?,?)',
          [$v['name'], $v['email'], $v['username'], password_hash($password, PASSWORD_DEFAULT), $v['role']]);
        $id = (int)$pdo->lastInsertId();
        if ($v['role'] === 'student') q('INSERT INTO students (user_id, group_id) VALUES (?,?)', [$id, $gid]);
        else q('INSERT INTO teachers (user_id, specialty) VALUES (?,?)', [$id, $v['specialty'] ?: null]);
        $pdo->commit();
        log_action('register', 'Жаңа ' . $v['role'] . ': ' . $v['username'], $id);
        flash_set('Тіркелу сәтті аяқталды. Енді жүйеге кіріңіз.');
        go('login.php');
    }
}
render_head('Тіркелу'); ?>
<section class="auth">
  <form class="card <?= $errors ? 'shake' : '' ?>" method="post" autocomplete="off" novalidate>
    <h2>Тіркелу</h2>
    <?php if ($errors): ?><div class="alert alert-err"><?php foreach ($errors as $er) echo '<div>' . e($er) . '</div>'; ?></div><?php endif; ?>
    <?= csrf_field() ?>
    <label class="field"><span>Аты-жөні</span><input type="text" name="name" value="<?= e($v['name']) ?>" required></label>
    <div class="two">
      <label class="field"><span>Email</span><input type="email" name="email" value="<?= e($v['email']) ?>" required></label>
      <label class="field"><span>Логин</span><input type="text" name="username" value="<?= e($v['username']) ?>" required></label>
    </div>
    <label class="field"><span>Мен</span>
      <select name="role" data-rolesel><option value="student" <?= $v['role'] === 'student' ? 'selected' : '' ?>>Студентпін</option><option value="teacher" <?= $v['role'] === 'teacher' ? 'selected' : '' ?>>Мұғалімін</option></select></label>
    <label class="field for-student"><span>Топ</span>
      <select name="group_id"><option value="">— таңдаңыз —</option><?= opts($groups, $v['group_id']) ?></select></label>
    <label class="field for-teacher"><span>Мамандығы (міндетті емес)</span><input type="text" name="specialty" value="<?= e($v['specialty']) ?>"></label>
    <div class="two">
      <label class="field"><span>Пароль</span><input type="password" name="password" placeholder="Кемінде 6 символ" required></label>
      <label class="field"><span>Парольді қайталау</span><input type="password" name="confirm" required></label>
    </div>
    <button class="btn btn-block" type="submit">Тіркелу</button>
    <p class="muted">Аккаунтыңыз бар ма? <a href="login.php">Кіру</a></p>
  </form>
</section>
<?php render_foot('');
