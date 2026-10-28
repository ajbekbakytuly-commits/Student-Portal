<?php
// Қорғалған файл беру: тек құқығы бар пайдаланушыға
require 'session.php';
require_login();
$t = $_GET['t'] ?? ''; $id = (int)($_GET['id'] ?? 0); $path = null; $name = 'file'; $ok = false;

if ($t === 'hw') {
    $h = q('SELECT * FROM homework WHERE id = ?', [$id])->fetch();
    if ($h && $h['file_path']) {
        $path = $h['file_path']; $name = $h['file_name'];
        $gid = role() === 'student' ? q('SELECT group_id FROM students WHERE user_id = ?', [uid()])->fetchColumn() : null;
        $ok = role() === 'admin' || (role() === 'teacher' && (int)$h['teacher_id'] === uid()) || (role() === 'student' && $gid && (int)$gid === (int)$h['group_id']);
    }
} elseif ($t === 'sub') {
    $s = q('SELECT hs.*, h.teacher_id FROM homework_submissions hs JOIN homework h ON h.id = hs.homework_id WHERE hs.id = ?', [$id])->fetch();
    if ($s) {
        $path = $s['file_path']; $name = $s['file_name'];
        $ok = role() === 'admin' || (role() === 'teacher' && (int)$s['teacher_id'] === uid()) || (role() === 'student' && (int)$s['student_id'] === uid());
    }
}
$root = realpath(__DIR__ . '/storage');
$full = $path ? realpath($root . '/' . $path) : false;
if (!$ok || !$full || !str_starts_with($full, $root) || !is_file($full)) { http_response_code(404); die('Файл табылмады немесе қолжетімсіз.'); }

$mime = (new finfo(FILEINFO_MIME_TYPE))->file($full) ?: 'application/octet-stream';
$inline = str_starts_with($mime, 'image/') || $mime === 'application/pdf';
header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');
header('Content-Length: ' . filesize($full));
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . "; filename*=UTF-8''" . rawurlencode($name));
readfile($full);
