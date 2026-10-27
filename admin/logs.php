<?php
require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_layout.php';
require_role(['admin']);
$pg = paginate((int) fetch_value('SELECT COUNT(*) FROM admin_activity_logs'), 30, (int) get('page', '1'));
$rows = fetch_all("SELECT l.*, u.name FROM admin_activity_logs l LEFT JOIN users u ON u.id = l.user_id ORDER BY l.id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}");
admin_header('Activity log', 'logs');
?>
<div class="top"><h1>Activity log</h1></div>
<div class="card wrap-x"><table><thead><tr><th>When</th><th>Who</th><th>Action</th><th>Details</th><th>IP</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td style="white-space:nowrap"><?= e(fmt_date($r['created_at'])) ?></td><td><?= e($r['name'] ?: 'System') ?></td><td><?= e($r['action']) ?></td><td><?= e($r['details']) ?></td><td class="hint"><?= e($r['ip_address']) ?></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="5" class="hint">Nothing recorded yet.</td></tr><?php endif; ?></tbody></table></div>
<?php if ($pg['pages'] > 1): ?><nav class="pager"><?php for ($i = 1; $i <= $pg['pages']; $i++): ?><?= $i === $pg['page'] ? '<span class="cur">' . $i . '</span>' : '<a href="' . e(page_link($i)) . '">' . $i . '</a>' ?><?php endfor; ?></nav><?php endif; ?>
<?php admin_footer();
