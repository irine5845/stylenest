# StyleNest: fashion e-commerce website

A PHP and MySQL online fashion store with a customer storefront, shopping cart, checkout,
M-Pesa (Daraja STK Push) and cash on delivery, customer accounts, order tracking and an
admin dashboard. Built from the StyleNest project proposal.

## What is included

| Area | Features |
| --- | --- |
| Storefront | Home, collections (Women, Men, Accessories), shop with search, filters (category, size, colour, price) and sorting, product pages with size/colour stock, quick add, About, Contact, newsletter |
| Cart and checkout | Session cart saved to the database for signed-in customers, live stock checks, delivery fee by county, free-delivery threshold, guest or account checkout |
| Payments | M-Pesa STK Push with secure callback and STK Query fallback, cash on delivery, retry for failed payments, a **simulation mode** for testing on localhost |
| Accounts | Register, sign in, profile, change password, saved addresses, order history, order tracking (also for guests) |
| Admin | Dashboard, products and variants (image upload), categories, inventory, orders and status updates, customers, payments, sales reports with CSV export, staff accounts and roles, messages and subscribers, activity log |
| Security | Password hashing, prepared statements, output escaping, CSRF tokens, session regeneration, login throttling, role-based access, safe image uploads, locked-down folders |

## Quick start with XAMPP (Windows, macOS or Linux)

1. Install XAMPP (PHP 8.1 or newer) and start **Apache** and **MySQL** from the control panel.
2. Copy the `stylenest` folder into XAMPP's web folder (`C:\xampp\htdocs\` on Windows).
3. Open **http://localhost/phpmyadmin** and click **Import**. Import these two files, in this order:
   1. `database/schema.sql` (creates the `stylenest_db` database and all tables)
   2. `database/seed.sql` (adds categories, 15 sample products and two demo accounts)
4. Open **http://localhost/stylenest/**. The default XAMPP database login (`root`, no password) already works.

If your MySQL password is different, copy `config/env.example.php` to `config/env.php` and edit the `db` section.

### Demo accounts (change these before going live)

| Role | Email | Password |
| --- | --- | --- |
| Admin | admin@stylenest.co.ke | Admin@12345 |
| Customer | customer@example.com | Customer@123 |

Admin dashboard: **http://localhost/stylenest/admin/**

To create your own admin or reset a password from the command line:
`php database/create_admin.php you@example.com "Your Name" "StrongPassword1"`

## Try a purchase

Add something to the bag, go to checkout and choose **M-Pesa**. Because M-Pesa starts in
`simulate` mode, no real request is sent. The payment page shows buttons to simulate a
successful or cancelled payment, and the order updates exactly as it would with the real callback.

## Turning on real M-Pesa

1. Register at https://developer.safaricom.co.ke and create an app to get your **consumer key**,
   **consumer secret**, **shortcode** and **passkey**.
2. Copy `config/env.example.php` to `config/env.php` and set the `mpesa` section:
   `mode` to `live`, `env` to `sandbox` (testing) or `production`, plus your credentials.
3. Daraja must reach your callback over **public HTTPS**. Set `callback_url` to
   `https://your-domain/mpesa_callback.php` (on localhost use a tunnel such as ngrok).
4. Change `callback_secret` to a long random string. It is added to the callback URL so only
   Safaricom's request is accepted.
5. Test in the sandbox first. Go-live needs Safaricom's approval and a real till or paybill.

Payments are confirmed only by the server-to-server callback (or a server-side STK Query),
never by the customer's browser. The amount paid is checked against the order total.
The customer's PIN is entered on their phone and never reaches this site.

## Setting it up for your business

* **Business details, delivery fees, free-delivery limit, low-stock threshold**: `config/env.php`
  (copy from `config/env.example.php`). The delivery fee for `local_county` is `local_fee`; other counties pay `other_fee`.
* **Products and photos**: Admin > Products. The seeded product pictures are simple illustrations so
  the site looks complete on day one. Upload real photography (portrait 4:5, JPG, PNG or WebP).
  Also replace `assets/img/hero.svg`, `about.svg` and the collection images in Admin > Categories.
* **Wording**: Review the text in `index.php` and `about.php` (for example the benefits strip) so it describes
  your real delivery and payment policies.
* **Colours and fonts**: `assets/css/style.css` (variables at the top). Fonts load from Google Fonts;
  if you need the site to work fully offline, download and self-host them.

## Going live checklist

- [ ] Create `config/env.php`, set `debug` to `false`, use a dedicated database user with a strong password (not `root`)
- [ ] Change or delete both demo accounts, then create your own admin
- [ ] Change `mpesa.callback_secret`
- [ ] Install an SSL certificate and uncomment the HTTPS redirect in `.htaccess`
- [ ] Use PHP 8.1+ with the `pdo_mysql`, `curl` and `mbstring` extensions enabled
- [ ] Make sure `uploads/` and `storage/` are writable by the web server
- [ ] Set up regular database backups (for example through your host's cPanel)
- [ ] Check that `https://your-domain/config/env.example.php` and `/database/schema.sql` return "Forbidden"
- [ ] Review Kenya's Data Protection Act requirements for your privacy notice and customer consent

Apache is assumed (the `.htaccess` files block access to private folders). On Nginx, add equivalent
`deny all` rules for `config/`, `includes/`, `database/` and `storage/`, and block PHP execution in `uploads/`.

## Folder structure

```
stylenest/
  index.php, shop.php, product.php, cart.php, checkout.php, pay.php      storefront pages
  order.php, track.php, account.php, orders.php, login.php, register.php  orders and accounts
  contact.php, about.php, newsletter.php, mpesa_callback.php
  admin/          dashboard and management pages
  api/            small JSON endpoints (quick add, payment status)
  includes/       shared PHP: database, auth, CSRF, cart, orders, M-Pesa, layout
  config/         settings (env.example.php, copy to env.php)
  database/       schema.sql, seed.sql, create_admin.php
  assets/         css, js, images
  uploads/        product photos added through the admin
  storage/        runtime files (cached M-Pesa token)
```

## Roles

* **Admin**: everything, including staff accounts and the activity log.
* **Manager**: products, categories, inventory, orders, customers, payments, reports and messages.
* **Customer**: shop, account and own orders.

## Good to know (limits of this first release)

* Order and contact emails are not sent automatically yet. New orders and messages appear in the admin.
  Email or SMS notifications are a natural next step (see section 20 of the proposal).
* Stock is reserved when an order is placed and returned if the order is cancelled. M-Pesa orders that are not paid within 2 hours are cancelled automatically and their stock is released.
* The out-of-the-box test data and automated checks were run against PHP 8.3 and MariaDB 10.11.
  The layout was written to be responsive but please review it in your own browsers and phones.
