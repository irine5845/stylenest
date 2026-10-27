<?php
require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_layout.php';
$me = require_role(['admin']);
$errors = [];
if (is_post()) {
    csrf_check();
    $a = post('action');
    if ($a === 'create') {
        $name = post('name'); $email = strtolower(post('email')); $role = post('role'); $pw = (string) ($_POST['password'] ?? '');
        if (mb_strlen($name) < 2) $errors[] = 'Enter a name.';
        if (!valid_email($email)) $errors[] = 'Enter a valid email.'; elseif (fetch_value('SELECT id FROM users WHERE email = ?', [$email])) $errors[] = 'That email is already registered.';
        if (!in_array($role, ['admin', 'manager'], true)) $errors[] = 'Choose a role.';
        if ($m = password_problem($pw)) $errors[] = $m;
        if (!$errors) { q('INSERT INTO users (name,email,password_hash,role) VALUES (?,?,?,?)', [$name, $email, password_hash($pw, PASSWORD_DEFAULT), $role]); log_action('staff_created', "$email as $role"); flash('success', 'Staff account created.'); redirect('admin/users.php'); }
    } else {
        $id = (int) post('id'); $u = fetch_one("SELECT * FROM users WHERE id = ? AND role IN ('admin','manager')", [$id]);
        if (!$u) flash('error', 'Account not found.');
        elseif ($id === (int) $me['id']) flash('error', 'You cannot change your own role or status. Ask another admin.');
        elseif ($a === 'toggle') { q('UPDATE users SET is_active = ? WHERE id = ?', [$u['is_active'] ? 0 : 1, $id]); log_action('staff_status', $u['email']); flash('success', 'Account updated.'); }
        elseif ($a === 'role' && in_array(post('role'), ['admin', 'manager', 'customer'], true)) { q('UPDATE users SET role = ? WHERE id = ?', [post('role'), $id]); log_action('staff_role', $u['email'] . ' to ' . post('role')); flash('success', 'Role updated.'); }
        elseif ($a === 'reset' ) { $pw = (string) ($_POST['password'] ?? ''); if ($m = password_problem($pw)) flash('error', $m); else { q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), $id]); log_action('staff_password_reset', $u['email']); flash('success', 'Password reset.'); } }
        redirect('admin/users.php');
    }
}
$staff = fetch_all("SELECT * FROM users WHERE role IN ('admin','manager') ORDER BY role, name");
admin_header('Staff accounts', 'users');
?>
<div class="top"><h1>Staff accounts</h1></div>
<?php foreach ($errors as $e1): ?><div class="alert alert-error" role="alert"><?= e($e1) ?></div><?php endforeach; ?>
<div class="grid3"><div class="card wrap-x"><table><thead><tr><th>Name</th><th>Role</th><th>Last sign in</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($staff as $u): $self = (int) $u['id'] === (int) $me['id']; ?><tr><td><strong><?= e($u['name']) ?></strong><br><span class="hint"><?= e($u['email']) ?></span></td>
<td><?php if ($self): ?><?= e(ucfirst($u['role'])) ?> (you)<?php else: ?><form method="post" style="display:flex;gap:.3rem"><?= csrf_field() ?><input type="hidden" name="action" value="role"><input type="hidden" name="id" value="<?= $u['id'] ?>"><select name="role" aria-label="Role" style="width:auto"><?php foreach (['admin' => 'Admin', 'manager' => 'Manager', 'customer' => 'Remove staff access'] as $k => $l): ?><option value="<?= $k ?>" <?= $u['role'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select><button class="btn btn-sm btn-ghost">Set</button></form><?php endif; ?></td>
<td><?= $u['last_login_at'] ? e(fmt_date($u['last_login_at'])) : 'Never' ?></td><td><?= $u['is_active'] ? '<span class="badge badge-ok">Active</span>' : '<span class="badge badge-bad">Disabled</span>' ?></td>
<td><?php if (!$self): ?><form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $u['id'] ?>"><button class="btn btn-sm btn-ghost"><?= $u['is_active'] ? 'Disable' : 'Enable' ?></button></form>
<details style="display:inline-block"><summary class="btn btn-sm btn-ghost">Reset password</summary><form method="post" style="margin-top:.4rem"><?= csrf_field() ?><input type="hidden" name="action" value="reset"><input type="hidden" name="id" value="<?= $u['id'] ?>"><input type="password" name="password" placeholder="New password" aria-label="New password" autocomplete="new-password" required><button class="btn btn-sm" style="margin-top:.3rem">Save</button></form></details><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div>
<div class="card"><h2>Add staff member</h2><p class="hint">Admins can manage staff and view the activity log. Managers handle products, orders and reports.</p>
<form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="create">
<div class="field"><label for="n">Name</label><input id="n" name="name" type="text" required></div><div class="field"><label for="e">Email</label><input id="e" name="email" type="email" required></div>
<div class="field"><label for="r">Role</label><select id="r" name="role"><option value="manager">Manager</option><option value="admin">Admin</option></select></div>
<div class="field"><label for="p">Temporary password</label><input id="p" name="password" type="password" autocomplete="new-password" required><div class="hint">8+ characters with a letter and a number.</div></div>
<button class="btn" type="submit">Create account</button></form></div></div>
<?php admin_footer();
