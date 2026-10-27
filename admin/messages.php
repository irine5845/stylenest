<?php
require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_layout.php';
require_role(['admin', 'manager']);
if (is_post()) {
    csrf_check(); $id = (int) post('id');
    if (post('action') === 'read') q('UPDATE contact_messages SET is_read = 1 WHERE id = ?', [$id]);
    if (post('action') === 'delete') q('DELETE FROM contact_messages WHERE id = ?', [$id]);
    redirect('admin/messages.php');
}
$msgs = fetch_all('SELECT * FROM contact_messages ORDER BY is_read, id DESC LIMIT 100');
$subs = fetch_all('SELECT * FROM newsletter_subscribers ORDER BY id DESC LIMIT 200');
admin_header('Messages', 'messages');
?>
<div class="top"><h1>Messages</h1></div>
<?php foreach ($msgs as $m): ?><div class="card" style="<?= $m['is_read'] ? '' : 'border-left:4px solid var(--taupe)' ?>"><div class="top" style="margin-bottom:.5rem"><div><strong><?= e($m['subject']) ?></strong><br><span class="hint"><?= e($m['name']) ?> &lt;<a href="mailto:<?= e($m['email']) ?>?subject=<?= e(rawurlencode('Re: ' . $m['subject'])) ?>"><?= e($m['email']) ?></a>&gt; &middot; <?= e(fmt_date($m['created_at'])) ?></span></div>
<div style="display:flex;gap:.4rem"><?php if (!$m['is_read']): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="read"><input type="hidden" name="id" value="<?= $m['id'] ?>"><button class="btn btn-sm btn-ghost">Mark as read</button></form><?php endif; ?><form method="post" onsubmit="return confirm('Delete this message?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $m['id'] ?>"><button class="btn btn-sm btn-ghost">Delete</button></form></div></div><p style="margin:0;white-space:pre-wrap"><?= e($m['message']) ?></p></div>
<?php endforeach; if (!$msgs): ?><div class="card hint">No messages yet.</div><?php endif; ?>
<div class="card"><h2>Newsletter subscribers (<?= count($subs) ?>)</h2><div class="wrap-x"><table><thead><tr><th>Email</th><th>Subscribed</th></tr></thead><tbody><?php foreach ($subs as $s): ?><tr><td><?= e($s['email']) ?></td><td><?= e(fmt_date($s['subscribed_at'])) ?></td></tr><?php endforeach; if (!$subs): ?><tr><td colspan="2" class="hint">No subscribers yet.</td></tr><?php endif; ?></tbody></table></div></div>
<?php admin_footer();
