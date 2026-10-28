<?php
require 'session.php';
if (is_logged_in()) log_action('logout', 'Жүйеден шықты');
destroy_session();
header('Location: login.php' . (!empty($_GET['timeout']) ? '?timeout=1' : '?out=1'));
exit;
