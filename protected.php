<?php
require 'session.php';
require_login();

$sid = session_id();
$maskedSid = substr($sid, 0, 6) . '••••••••' . substr($sid, -4);
render_head('Қорғалған бет', 'protected.php'); ?>
<section class="page">
  <div class="secure">
    <div class="shield">
      <svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l8 3v6c0 4.5-3.2 8.3-8 9-4.8-.7-8-4.5-8-9V6l8-3z"/><path class="tick" d="M8.5 12.5l2.5 2.5 4.5-5"/></svg>
    </div>
    <div>
      <h1><?= e($_SESSION['name']) ?></h1>
      <p class="status"><span class="pulse"></span> Сіздің жеке сессияңыз белсенді</p>
    </div>
  </div>

  <dl class="list">
    <div><dt>user_id</dt><dd><?= (int)$_SESSION['user_id'] ?></dd></div>
    <div><dt>username</dt><dd><?= e($_SESSION['username']) ?></dd></div>
    <div><dt>Сессия идентификаторы</dt><dd class="mono"><?= e($maskedSid) ?></dd></div>
    <div><dt>Автоматты шығуға дейін</dt><dd class="mono" data-session-clock>10:00</dd></div>
  </dl>

  <div class="actions">
    <a class="btn btn-ghost" href="dashboard.php">Кабинетке оралу</a>
    <a class="btn btn-danger" href="logout.php">Шығу</a>
  </div>
</section>
<?php render_foot();
