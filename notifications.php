<?php
require 'session.php';
require_login();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['action'] ?? '') === 'all') q('UPDATE notifications SET is_read = 1 WHERE user_id = ?', [uid()]);
    else q('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?', [(int)($_POST['id'] ?? 0), uid()]);
    go('notifications.php');
}
$list = q('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 100', [uid()])->fetchAll();
render_head('Хабарландырулар'); ?>
<h1>Хабарландырулар</h1>
<div class="panel">
  <?php if ($list): ?>
  <form method="post" class="actions" style="margin:0 0 6px"><?= csrf_field() ?><input type="hidden" name="action" value="all"><button class="btn btn-ghost btn-sm" type="submit">Барлығын оқылған деп белгілеу</button></form>
  <?php foreach ($list as $n): ?>
    <div class="notif <?= $n['is_read'] ? '' : 'new' ?>">
      <div style="flex:1"><b><?= e($n['title']) ?></b>
        <?php if ($n['body']): ?><div class="muted"><?= e($n['body']) ?></div><?php endif; ?>
        <small><?= e(date('d.m.Y H:i', strtotime($n['created_at']))) ?></small>
        <?php if ($n['link']): ?> · <a href="<?= e($n['link']) ?>">Ашу</a><?php endif; ?></div>
      <?php if (!$n['is_read']): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$n['id'] ?>"><button class="btn btn-ghost btn-sm" type="submit">Оқылды</button></form><?php endif; ?>
    </div>
  <?php endforeach; else: ?><p class="muted">Хабарландыру жоқ.</p><?php endif; ?>
</div>
<?php render_foot();
