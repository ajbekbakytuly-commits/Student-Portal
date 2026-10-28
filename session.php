<?php
// Сессия, рөлдер, CSRF, хабарландырулар және беттің жалпы шаблоны
require_once __DIR__ . '/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}
$__d = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
define('BASE', preg_replace('#/(admin|teacher|student)$#', '', $__d) . '/');

const SESSION_TIMEOUT = 600;
const SESSION_WARN    = 60;
const ROLE_LABELS = ['admin' => 'Әкімші', 'teacher' => 'Мұғалім', 'student' => 'Студент'];
const WORK_TYPES  = ['Ағымдағы', 'Бақылау жұмысы', 'Практикалық', 'Емтихан', 'Үй тапсырмасы'];
const DAYS = [1 => 'Дүйсенбі', 2 => 'Сейсенбі', 3 => 'Сәрсенбі', 4 => 'Бейсенбі', 5 => 'Жұма', 6 => 'Сенбі'];
const UPLOAD_EXT = [
    'pdf' => ['application/pdf'],
    'doc' => ['application/msword', 'application/x-ole-storage', 'application/CDFV2'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/x-zip-compressed'],
    'zip' => ['application/zip', 'application/x-zip-compressed'],
    'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'], 'gif' => ['image/gif'],
];

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function is_logged_in() { return !empty($_SESSION['user_id']); }
function role() { return $_SESSION['role'] ?? ''; }
function uid() { return (int)($_SESSION['user_id'] ?? 0); }
function go($path) { header('Location: ' . BASE . $path); exit; }

function destroy_session() {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function require_login() {
    if (!is_logged_in()) go('login.php');
    $s = q('SELECT status FROM users WHERE id = ?', [uid()])->fetchColumn();
    if ($s !== 'active') { destroy_session(); go('login.php?blocked=1'); }
}
function require_role(array $roles) {
    require_login();
    if (!in_array(role(), $roles, true)) {
        http_response_code(403);
        render_head('Қолжетімсіз');
        echo '<section class="panel"><h1>403</h1><p class="muted">Бұл бетке қолжетімділігіңіз жоқ.</p><a class="btn" href="' . BASE . 'dashboard.php">Басты бетке</a></section>';
        render_foot(); exit;
    }
}
function redirect_if_logged_in() { if (is_logged_in()) go('dashboard.php'); }

function check_timeout() {
    if (!is_logged_in()) return;
    if (isset($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
        destroy_session();
        if (defined('AJAX')) { http_response_code(401); exit; }
        go('login.php?timeout=1');
    }
    $_SESSION['last_activity'] = time();
}
check_timeout();

/* CSRF */
function csrf_token() { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function csrf_field() { return '<input type="hidden" name="csrf" value="' . csrf_token() . '">'; }
function csrf_check() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(419);
        die('Қауіпсіздік қатесі (CSRF). Бетті жаңартып, қайталап көріңіз.');
    }
}

function flash_set($msg, $type = 'ok') { $_SESSION['flash'] = [$msg, $type]; }
function flash_get() { if (!empty($_SESSION['flash'])) { $m = $_SESSION['flash']; unset($_SESSION['flash']); return $m; } return null; }

/* Журнал және хабарландырулар */
function log_action($action, $details = '', $user = null) {
    q('INSERT INTO activity_logs (user_id, action, details, ip) VALUES (?,?,?,?)',
      [$user ?? (uid() ?: null), $action, mb_substr($details, 0, 500), $_SERVER['REMOTE_ADDR'] ?? null]);
}
function notify($user_id, $title, $body = '', $link = '') {
    q('INSERT INTO notifications (user_id, title, body, link) VALUES (?,?,?,?)', [$user_id, $title, mb_substr($body, 0, 500), $link]);
}
function notify_group($group_id, $title, $body = '', $link = '') {
    foreach (q('SELECT user_id FROM students WHERE group_id = ?', [$group_id])->fetchAll() as $s) notify($s['user_id'], $title, $body, $link);
}
function unread_count() { return (int)q('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [uid()])->fetchColumn(); }

/* Файл жүктеу: MIME, кеңейтім, өлшем тексеріледі; файл storage/ ішінде, тек download.php арқылы беріледі */
function save_upload(string $field, string $sub, &$err) {
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) return null;
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) { $err = 'Файлды жүктеу кезінде қате болды.'; return null; }
    if ($f['size'] > 10 * 1024 * 1024) { $err = 'Файл өлшемі 10 МБ-тан аспауы керек.'; return null; }
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!isset(UPLOAD_EXT[$ext])) { $err = 'Рұқсат етілген форматтар: PDF, DOC, DOCX, ZIP, JPG, PNG, GIF.'; return null; }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (!in_array($mime, UPLOAD_EXT[$ext], true)) { $err = 'Файл мазмұны кеңейтіміне сәйкес келмейді.'; return null; }
    $dir = __DIR__ . '/storage/' . $sub;
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $name = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) { $err = 'Файлды сақтау мүмкін болмады.'; return null; }
    return ['path' => $sub . '/' . $name, 'name' => mb_substr(basename($f['name']), 0, 200)];
}
function delete_file($path) {
    if (!$path) return;
    $full = realpath(__DIR__ . '/storage/' . $path);
    if ($full && str_starts_with($full, realpath(__DIR__ . '/storage')) && is_file($full)) unlink($full);
}

/* Тапсырма мәртебесі */
function hw_status($s, $deadline) {
    $m = ['submitted' => ['Тапсырылды', 'blue'], 'reviewing' => ['Тексерілуде', 'warn'], 'graded' => ['Бағаланды', 'ok'], 'late' => ['Кешіктірілді', 'err']];
    if ($s === null) return strtotime($deadline) < time() ? $m['late'] : ['Тапсырылмаған', 'mute'];
    return $m[$s];
}
function pill($a) { return '<span class="pill ' . $a[1] . '">' . $a[0] . '</span>'; }
function opts(array $rows, $sel = null, $label = 'name', $val = 'id') {
    $h = '';
    foreach ($rows as $r) $h .= '<option value="' . e($r[$val]) . '"' . ((string)$sel === (string)$r[$val] ? ' selected' : '') . '>' . e($r[$label]) . '</option>';
    return $h;
}

/* Кесте */
function schedule_rows(string $where, array $params): array {
    return q("SELECT sc.*, s.name AS subject, u.name AS teacher, g.name AS group_name
              FROM schedules sc JOIN subjects s ON s.id = sc.subject_id JOIN users u ON u.id = sc.teacher_id JOIN `groups` g ON g.id = sc.group_id
              WHERE $where ORDER BY sc.day_of_week, sc.start_time", $params)->fetchAll();
}
function render_week(array $rows, int $w, string $mode) {
    $mon = strtotime('monday this week') + $w * 7 * 86400;
    echo '<div class="tabs"><a class="' . ($w === 0 ? 'on' : '') . '" href="?w=0">Осы апта</a><a class="' . ($w === 1 ? 'on' : '') . '" href="?w=1">Келесі апта</a></div><div class="week">';
    foreach (DAYS as $n => $name) {
        $d = $mon + ($n - 1) * 86400;
        echo '<div class="day' . (date('Y-m-d', $d) === date('Y-m-d') ? ' today' : '') . '"><h3>' . $name . ' <small>' . date('d.m', $d) . '</small></h3>';
        $any = false;
        foreach ($rows as $r) if ((int)$r['day_of_week'] === $n) {
            $any = true;
            echo '<div class="lesson"><b>' . e($r['subject']) . '</b><span>' . substr($r['start_time'], 0, 5) . '–' . substr($r['end_time'], 0, 5) . ($r['room'] !== '' ? ' · ' . e($r['room']) . ' каб.' : '') . '</span><span>' . e($mode === 'teacher' ? $r['group_name'] : $r['teacher']) . '</span></div>';
        }
        if (!$any) echo '<p class="muted">Сабақ жоқ</p>';
        echo '</div>';
    }
    echo '</div>';
}
function render_today(array $rows, string $mode) {
    $n = (int)date('N'); $any = false;
    foreach ($rows as $r) if ((int)$r['day_of_week'] === $n) {
        $any = true;
        echo '<div class="lesson"><b>' . e($r['subject']) . '</b><span>' . substr($r['start_time'], 0, 5) . '–' . substr($r['end_time'], 0, 5) . ($r['room'] !== '' ? ' · ' . e($r['room']) . ' каб.' : '') . '</span><span>' . e($mode === 'teacher' ? $r['group_name'] : $r['teacher']) . '</span></div>';
    }
    if (!$any) echo '<p class="muted">Бүгін сабақ жоқ.</p>';
}

/* Навигация */
function nav_items($role) {
    $c = [['copilot.php', 'Microsoft Copilot', 'fa-wand-magic-sparkles'], ['notifications.php', 'Хабарландырулар', 'fa-bell'], ['profile.php', 'Профиль', 'fa-user']];
    $m = [
        'admin' => [['admin/dashboard.php', 'Басқару панелі', 'fa-gauge'], ['admin/users.php', 'Пайдаланушылар', 'fa-users'], ['admin/subjects.php', 'Пәндер', 'fa-book'],
            ['admin/groups.php', 'Топтар', 'fa-layer-group'], ['admin/assignments.php', 'Мұғалім тағайындау', 'fa-user-gear'], ['admin/schedule.php', 'Сабақ кестесі', 'fa-calendar-days'],
            ['admin/grades.php', 'Бағалар', 'fa-star'], ['admin/homework.php', 'Үй тапсырмалары', 'fa-file-pen'], ['admin/logs.php', 'Әрекеттер журналы', 'fa-clock-rotate-left']],
        'teacher' => [['teacher/dashboard.php', 'Кабинет', 'fa-gauge'], ['teacher/grades.php', 'Бағалар', 'fa-star'], ['teacher/homework.php', 'Үй тапсырмалары', 'fa-file-pen'], ['teacher/schedule.php', 'Сабақ кестесі', 'fa-calendar-days']],
        'student' => [['student/dashboard.php', 'Кабинет', 'fa-gauge'], ['student/grades.php', 'Бағалар', 'fa-star'], ['student/homework.php', 'Үй тапсырмалары', 'fa-file-pen'], ['student/schedule.php', 'Сабақ кестесі', 'fa-calendar-days']],
    ];
    return array_merge($m[$role] ?? [], $c);
}


/* Чат-бот: жылдам сұрақтар */
function bot_chips($role) {
    return ['student' => ['Бүгінгі сабақтар', 'Менің бағаларым', 'Белсенді тапсырмалар', 'Хабарландырулар'],
            'teacher' => ['Бүгінгі сабақтар', 'Тексеруді күтетін жұмыстар', 'Менің бағаларым', 'Хабарландырулар'],
            'admin'   => ['Жүйе статистикасы', 'Бүгінгі сабақтар', 'Үй тапсырмалары', 'Көмек']][$role] ?? ['Көмек'];
}

/* Шаблон */
function render_head($title, $page = '') {
    $auth = is_logged_in(); ?>
<!DOCTYPE html>
<html lang="kk">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> — Student Portal</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Onest:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="<?= BASE ?>css/style.css">
</head>
<body class="<?= $auth ? 'app' : 'pub' ?>" data-base="<?= e(BASE) ?>" data-timeout="<?= SESSION_TIMEOUT ?>" data-warn="<?= SESSION_WARN ?>"<?= $auth ? ' data-csrf="' . csrf_token() . '"' : '' ?>>
<?php if (!$auth): ?>
<header class="pubnav">
  <a class="brand" href="<?= BASE ?>index.php"><i class="fa-solid fa-graduation-cap"></i> Student Portal</a>
  <nav><a href="<?= BASE ?>register.php">Тіркелу</a><a class="btn btn-sm" href="<?= BASE ?>login.php">Кіру</a></nav>
</header>
<main class="pubmain">
<?php else:
    $role = role(); $n = unread_count(); ?>
<aside class="side" id="side">
  <a class="brand" href="<?= BASE ?>dashboard.php"><i class="fa-solid fa-graduation-cap"></i> Student Portal</a>
  <?php foreach (nav_items($role) as [$href, $label, $icon]): ?>
    <a class="nav <?= str_ends_with($_SERVER['SCRIPT_NAME'], '/' . $href) ? 'active' : '' ?>" href="<?= BASE . $href ?>"><i class="fa-solid <?= $icon ?>"></i><?= $label ?></a>
  <?php endforeach; ?>
  <div class="sp"></div>
  <a class="nav" href="<?= BASE ?>logout.php"><i class="fa-solid fa-right-from-bracket"></i>Шығу</a>
</aside>
<div class="main">
<header class="top">
  <button class="iconbtn" id="burger" type="button" aria-label="Мәзір"><i class="fa-solid fa-bars"></i></button>
  <div class="search"><i class="fa-solid fa-magnifying-glass"></i><input id="gsearch" type="search" placeholder="Кестеден іздеу…"></div>
  <a class="iconbtn" href="<?= BASE ?>notifications.php" aria-label="Хабарландырулар"><i class="fa-regular fa-bell"></i><?php if ($n): ?><span class="badge"><?= $n ?></span><?php endif; ?></a>
  <a class="who" href="<?= BASE ?>profile.php"><div><b><?= e($_SESSION['name']) ?></b><small><?= e(ROLE_LABELS[$role] ?? '') ?></small></div><span class="avatar"><?= e(mb_strtoupper(mb_substr($_SESSION['name'], 0, 1))) ?></span></a>
</header>
<main class="content">
<?php endif;
    if ($f = flash_get()) echo '<div class="toast toast-' . e($f[1]) . '" id="flash" role="status">' . e($f[0]) . '</div><script>setTimeout(function(){var f=document.getElementById("flash");f&&f.remove()},6000)</script>';
}

function render_foot($js = '') {
    if (!is_logged_in()) { echo '</main><footer class="foot">Student Portal · колледж оқу процесін басқару жүйесі</footer></body></html>'; return; } ?>
</main></div>
<div class="toast toast-warn" id="timeout-toast" hidden role="alert">
  <div><strong>Сессияңыз аяқталуға жақын</strong>
    <p>Әрекетсіздіктен <b id="toast-count">60</b> секундтан кейін шығасыз.</p>
    <div class="bar"><i id="toast-bar"></i></div></div>
  <button class="btn btn-sm" id="toast-extend" type="button">Ұзарту</button>
</div>
<button class="cb-open" id="cb-open" type="button" aria-label="Чат-бот"><i class="fa-regular fa-comments"></i></button>
<section class="cb" id="cb" hidden aria-label="Чат-бот">
  <header><b><i class="fa-solid fa-robot"></i> Көмекші</b><button type="button" id="cb-close" aria-label="Жабу"><i class="fa-solid fa-xmark"></i></button></header>
  <div class="cb-log" id="cb-log" aria-live="polite"><div class="cb-msg">Сәлем, <?= e($_SESSION['name']) ?>! Мен кесте, бағалар және тапсырмалар туралы жауап бере аламын. Төмендегі сұрақты таңдаңыз немесе өзіңіз жазыңыз.</div></div>
  <div class="cb-chips" id="cb-chips"><?php foreach (bot_chips(role()) as $c) echo '<button type="button">' . e($c) . '</button>'; ?></div>
  <form id="cb-form" autocomplete="off"><input id="cb-input" maxlength="300" placeholder="Сұрағыңызды жазыңыз…"><button class="btn btn-sm" type="submit" aria-label="Жіберу"><i class="fa-solid fa-paper-plane"></i></button></form>
</section>
<?php if (defined('CHARTS')): ?><script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script><?php endif; ?>
<script src="<?= BASE ?>js/app.js"></script>
<script src="<?= BASE ?>js/chatbot.js"></script>
<?php if ($js) echo '<script>' . $js . '</script>'; ?>
</body>
</html>
<?php }
