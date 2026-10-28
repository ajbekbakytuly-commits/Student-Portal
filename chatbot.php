<?php
// Чат-бот: сұрақты кілт сөздер бойынша танып, дерекқордан рөлге сай жауап береді (сыртқы API қажет емес).
define('AJAX', true);
require 'session.php';
header('Content-Type: application/json; charset=utf-8');

function out($reply, $chips = null) { echo json_encode(['reply' => $reply, 'chips' => $chips ?? bot_chips(role())], JSON_UNESCAPED_UNICODE); exit; }
if (!is_logged_in() || q('SELECT status FROM users WHERE id = ?', [uid()])->fetchColumn() !== 'active') { http_response_code(401); echo '{"reply":""}'; exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals($_SESSION['csrf'] ?? '', $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) { http_response_code(419); echo '{"reply":""}'; exit; }

$body = json_decode(file_get_contents('php://input'), true);
$t = mb_strtolower(trim(mb_substr((string)($body['q'] ?? ''), 0, 300)));
if ($t === '') out('Сұрағыңызды жазыңыз.');
$has = function (array $w) use ($t) { foreach ($w as $x) if (mb_strpos($t, $x) !== false) return true; return false; };
$role = role(); $me = uid();

function lessons(array $rows, int $day, string $mode): string {
    $o = [];
    foreach ($rows as $r) if ((int)$r['day_of_week'] === $day)
        $o[] = substr($r['start_time'], 0, 5) . '–' . substr($r['end_time'], 0, 5) . ' · ' . $r['subject'] . ($r['room'] !== '' ? ' · ' . $r['room'] . ' каб.' : '') . ' · ' . ($mode === 'teacher' ? $r['group_name'] : $r['teacher']);
    return implode("\n", $o);
}
function my_schedule(string $role, int $me): array {
    if ($role === 'teacher') return [schedule_rows('sc.teacher_id = ?', [$me]), 'teacher'];
    $g = q('SELECT group_id FROM students WHERE user_id = ?', [$me])->fetchColumn();
    return [$g ? schedule_rows('sc.group_id = ?', [$g]) : [], 'group'];
}

// Сәлемдесу
if ($has(['сәлем', 'салем', 'привет', 'здравств', 'hello', 'hi ', 'ассалау']) || $t === 'hi')
    out('Сәлем, ' . $_SESSION['name'] . '! Қалай көмектесе аламын?');

// Көмек
if ($has(['көмек', 'помощ', 'help', 'не істей', 'что ты умеешь', 'мәзір']))
    out("Мен мыналарды білемін:\n• бүгінгі және ертеңгі сабақтар\n• бағалар мен орташа балл\n• үй тапсырмалары мен мерзімдер\n• хабарландырулар\n• жүйені қалай пайдалану (пароль, файл жүктеу)");

// Кесте
if ($has(['кесте', 'сабақ', 'расписан', 'урок', 'пара', 'schedule'])) {
    if ($role === 'admin') {
        $n = (int)q('SELECT COUNT(*) FROM schedules WHERE day_of_week = ?', [(int)date('N')])->fetchColumn();
        out(date('N') == 7 ? 'Бүгін жексенбі — сабақ жоқ.' : "Бүгін кестеде $n сабақ бар. Толық кесте: «Сабақ кестесі» бөлімі.");
    }
    [$rows, $mode] = my_schedule($role, $me);
    $tomorrow = $has(['ертең', 'завтра']);
    $day = (int)date('N', strtotime($tomorrow ? '+1 day' : 'today'));
    $label = $tomorrow ? 'Ертең' : 'Бүгін';
    if ($day === 7) out("$label жексенбі — сабақ жоқ.");
    $txt = lessons($rows, $day, $mode);
    out($txt !== '' ? "$label (" . DAYS[$day] . "):\n" . $txt : "$label сабақ жоқ.", ['Ертеңгі сабақтар', 'Менің бағаларым', 'Белсенді тапсырмалар']);
}

// Бағалар
if ($has(['баға', 'бағал', 'балл', 'оценк', 'үлгерім', 'успеваем', 'средн', 'орташа', 'gpa', 'grade'])) {
    if ($role === 'student') {
        $av = q('SELECT s.name, ROUND(AVG(g.score),1) a FROM grades g JOIN subjects s ON s.id = g.subject_id WHERE g.student_id = ? GROUP BY s.id ORDER BY s.name', [$me])->fetchAll();
        if (!$av) out('Сізге әлі баға қойылмаған.');
        $tot = q('SELECT ROUND(AVG(score),1) FROM grades WHERE student_id = ?', [$me])->fetchColumn();
        $l = []; foreach ($av as $a) $l[] = '• ' . $a['name'] . ': ' . $a['a'];
        out("Жалпы орташа балл: $tot\n" . implode("\n", $l) . "\n\nТолық тізім мен график: «Бағалар» бөлімі.");
    }
    if ($role === 'teacher') {
        $r = q('SELECT COUNT(*) n, ROUND(AVG(score),1) a FROM grades WHERE teacher_id = ?', [$me])->fetch();
        out($r['n'] ? "Сіз {$r['n']} баға қойдыңыз, орташа балл: {$r['a']}." : 'Сіз әлі баға қоймағансыз. «Бағалар» бөлімінде қоюға болады.');
    }
    $a = q('SELECT ROUND(AVG(score),1) FROM grades')->fetchColumn();
    out($a !== null ? "Колледждегі орташа балл: $a." : 'Жүйеде әлі баға жоқ.');
}

// Үй тапсырмалары
if ($has(['тапсырма', 'үй жұм', 'домашн', 'дедлайн', 'мерзім', 'задани', 'homework', 'срок'])) {
    if ($role === 'student') {
        $g = q('SELECT group_id FROM students WHERE user_id = ?', [$me])->fetchColumn();
        $rows = $g ? q('SELECT h.title, h.deadline, s.name sn FROM homework h JOIN subjects s ON s.id = h.subject_id WHERE h.group_id = ? AND h.deadline >= NOW() AND NOT EXISTS (SELECT 1 FROM homework_submissions x WHERE x.homework_id = h.id AND x.student_id = ?) ORDER BY h.deadline LIMIT 6', [$g, $me])->fetchAll() : [];
        if (!$rows) out('Тапсырылмаған белсенді тапсырма жоқ. 👍');
        $l = []; foreach ($rows as $r) $l[] = '• ' . $r['title'] . ' (' . $r['sn'] . ') — ' . date('d.m H:i', strtotime($r['deadline']));
        out("Тапсырылмаған тапсырмалар:\n" . implode("\n", $l) . "\n\nЖүктеу: «Үй тапсырмалары» бөлімі.");
    }
    if ($role === 'teacher') {
        $p = (int)q("SELECT COUNT(*) FROM homework_submissions hs JOIN homework h ON h.id = hs.homework_id WHERE h.teacher_id = ? AND hs.status IN ('submitted','late')", [$me])->fetchColumn();
        $n = (int)q('SELECT COUNT(*) FROM homework WHERE teacher_id = ? AND deadline >= NOW()', [$me])->fetchColumn();
        out("Белсенді тапсырмалар: $n.\nТексеруді күтетін жұмыстар: $p.");
    }
    $n = (int)q('SELECT COUNT(*) FROM homework')->fetchColumn(); $a = (int)q('SELECT COUNT(*) FROM homework WHERE deadline >= NOW()')->fetchColumn();
    out("Жүйеде $n тапсырма бар, оның $a белсенді.");
}
if ($role === 'teacher' && $has(['тексер', 'проверк', 'күтет'])) {
    $p = (int)q("SELECT COUNT(*) FROM homework_submissions hs JOIN homework h ON h.id = hs.homework_id WHERE h.teacher_id = ? AND hs.status IN ('submitted','late')", [$me])->fetchColumn();
    out($p ? "Тексеруді күтетін $p жұмыс бар. «Үй тапсырмалары» → «Жұмыстар»." : 'Тексеруді күтетін жұмыс жоқ.');
}

// Хабарландырулар
if ($has(['хабарланд', 'уведомл', 'хабар', 'notif'])) {
    $n = unread_count();
    out($n ? "Сізде $n оқылмаған хабарландыру бар. Жоғарғы панельдегі қоңырау белгісін басыңыз." : 'Оқылмаған хабарландыру жоқ.');
}

// Статистика (әкімші)
if ($role === 'admin' && $has(['статист', 'студент', 'мұғалім', 'учител', 'преподав', 'жүйе'])) {
    $c = fn($r) => (int)q('SELECT COUNT(*) FROM users WHERE role = ?', [$r])->fetchColumn();
    $g = (int)q('SELECT COUNT(*) FROM `groups`')->fetchColumn(); $s = (int)q('SELECT COUNT(*) FROM subjects')->fetchColumn();
    out("Студенттер: {$c('student')}\nМұғалімдер: {$c('teacher')}\nТоптар: $g\nПәндер: $s");
}

// Қолдану нұсқаулары
if ($has(['пароль', 'құпия сөз', 'парол']))
    out('Парольді өзгерту: жоғарғы оң жақтағы атыңызды басыңыз → «Профиль» → «Парольді өзгерту». ' . ($role !== 'admin' ? 'Парольді ұмытсаңыз, әкімшіге хабарласыңыз — ол уақытша пароль береді.' : ''));
if ($has(['жүкте', 'загруз', 'файл', 'upload']))
    out('Файл жүктеу: «Үй тапсырмалары» → керек тапсырмада файл таңдап «Жүктеу». Форматтар: PDF, DOC, DOCX, ZIP, сурет; өлшемі ≤ 10 МБ.');
if ($has(['топ', 'группа']) && $role === 'student') {
    $g = q('SELECT g.name FROM students s JOIN `groups` g ON g.id = s.group_id WHERE s.user_id = ?', [$me])->fetchColumn();
    out($g ? "Сіздің топ: $g." : 'Сіз әлі топқа тағайындалмағансыз. Әкімшіге хабарласыңыз.');
}
if ($has(['рахмет', 'спасибо', 'thanks', 'thank'])) out('Оқасы жоқ! Басқа сұрақ болса, жазыңыз.');

out('Мен бұл сұрақты түсінбедім. Кесте, баға, тапсырма немесе хабарландыру туралы сұрап көріңіз.', array_merge(bot_chips($role), ['Көмек']));
