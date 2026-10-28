<?php
require '../session.php';
require_role(['admin']);
$rows = q('SELECT l.*, u.name, u.role FROM activity_logs l LEFT JOIN users u ON u.id = l.user_id ORDER BY l.id DESC LIMIT 500')->fetchAll();
render_head('Әрекеттер журналы'); ?>
<h1>Әрекеттер журналы</h1><p class="muted">Соңғы 500 жазба</p>
<div class="panel tablewrap"><table class="data" data-table data-per="20"><thead><tr><th>Уақыт</th><th>Пайдаланушы</th><th>Әрекет</th><th>Мәлімет</th><th>IP</th></tr></thead><tbody>
<?php foreach ($rows as $l): ?><tr><td><?= e(date('d.m.Y H:i:s', strtotime($l['created_at']))) ?></td><td><?= e($l['name'] ?? '—') ?></td><td><span class="pill blue"><?= e($l['action']) ?></span></td><td><?= e($l['details']) ?></td><td class="mono"><?= e($l['ip']) ?></td></tr>
<?php endforeach; ?></tbody></table></div>
<?php render_foot();
