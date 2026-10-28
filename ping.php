<?php
// Сессияны ұзарту (AJAX). session.php last_activity мәнін жаңартады.
define('AJAX', true);
require 'session.php';
header('Content-Type: application/json');
if (!is_logged_in()) { http_response_code(401); echo '{"ok":false}'; exit; }
echo '{"ok":true}';
