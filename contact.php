<?php
require __DIR__ . '/includes/bootstrap.php';
$store = config('store'); $errors = []; $v = ['name' => '', 'email' => '', 'subject' => '', 'message' => ''];
$me = current_user(); if ($me) { $v['name'] = $me['name']; $v['email'] = $me['email']; }
if (is_post()) {
    csrf_check();
    foreach ($v as $k => $_) $v[$k] = post($k);
    if (post('website') !== '') redirect('contact.php');           // honeypot: real people leave this blank
    if (mb_strlen($v['name']) < 2) $errors['name'] = 'Enter your name.';
    if (!valid_email($v['email'])) $errors['email'] = 'Enter a valid email address.';
    if (mb_strlen($v['subject']) < 3) $errors['subject'] = 'Add a short subject.';
    if (mb_strlen($v['message']) < 10) $errors['message'] = 'Tell us a little more (at least 10 characters).';
    if (mb_strlen($v['message']) > 3000) $errors['message'] = 'Please keep your message under 3,000 characters.';
    $recent = (int) fetch_value('SELECT COUNT(*) FROM contact_messages WHERE email = ? AND created_at > (NOW() - INTERVAL 10 MINUTE)', [strtolower($v['email'])]);
    if (!$errors && $recent >= 3) $errors['message'] = 'You have sent several messages recently. Please wait a few minutes.';
    if (!$errors) {
        q('INSERT INTO contact_messages (name,email,subject,message) VALUES (?,?,?,?)', [$v['name'], strtolower($v['email']), mb_substr($v['subject'], 0, 160), $v['message']]);
        flash('success', 'Thanks, we received your message and will reply by email.');
        redirect('contact.php');
    }
}
$pageTitle = 'Contact us'; $activeNav = 'contact';
require __DIR__ . '/includes/header.php';
function ce(array $e, string $k): string { return isset($e[$k]) ? '<div class="error" role="alert">' . e($e[$k]) . '</div>' : ''; }
?>
<div class="wrap" style="padding-bottom:4rem">
  <div class="page-head"><h1>Contact us</h1><p class="muted">Questions about sizing, an order or a delivery? We are happy to help.</p></div>
  <div class="cart-layout" style="grid-template-columns:1fr 360px">
    <section class="panel">
      <h2>Send a message</h2>
      <form method="post" novalidate><?= csrf_field() ?>
        <div style="position:absolute;left:-9999px" aria-hidden="true"><label>Leave this empty<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
        <div class="row">
          <div class="field <?= isset($errors['name']) ? 'has-error' : '' ?>"><label for="n">Your name</label><input id="n" name="name" type="text" value="<?= e($v['name']) ?>" required><?= ce($errors, 'name') ?></div>
          <div class="field <?= isset($errors['email']) ? 'has-error' : '' ?>"><label for="e">Email</label><input id="e" name="email" type="email" value="<?= e($v['email']) ?>" required><?= ce($errors, 'email') ?></div>
        </div>
        <div class="field <?= isset($errors['subject']) ? 'has-error' : '' ?>"><label for="s">Subject</label><input id="s" name="subject" type="text" value="<?= e($v['subject']) ?>" required><?= ce($errors, 'subject') ?></div>
        <div class="field <?= isset($errors['message']) ? 'has-error' : '' ?>"><label for="m">Message</label><textarea id="m" name="message" required><?= e($v['message']) ?></textarea><?= ce($errors, 'message') ?></div>
        <button class="btn" type="submit">Send message</button>
      </form>
    </section>
    <aside class="panel">
      <h2>Find us</h2>
      <p><strong>Address</strong><br><?= e($store['address']) ?></p>
      <p><strong>Hours</strong><br><?= e($store['hours']) ?></p>
      <p><strong>Phone</strong><br><a href="tel:<?= e(preg_replace('/\s+/', '', $store['phone'])) ?>"><?= e($store['phone']) ?></a></p>
      <p><strong>Email</strong><br><a href="mailto:<?= e($store['email']) ?>"><?= e($store['email']) ?></a></p>
      <a class="btn btn-ghost btn-sm" href="https://wa.me/<?= e($store['whatsapp']) ?>">Chat on WhatsApp</a>
    </aside>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
