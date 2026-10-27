<?php
/**
 * StyleNest configuration.
 *
 * Copy this file to  config/env.php  and edit the copy. env.php is ignored by Git
 * and should never be uploaded anywhere public. If env.php does not exist the
 * values below are used, which work with a default XAMPP install.
 */
return [
    'app_name'    => 'StyleNest',
    'base_url'    => '',            // leave blank to auto-detect, e.g. '/stylenest'
    'debug'       => true,          // set to false on the live server
    'timezone'    => 'Africa/Nairobi',

    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'stylenest_db',
        'user'    => 'root',        // XAMPP default
        'pass'    => '',            // XAMPP default
        'charset' => 'utf8mb4',
    ],

    'store' => [
        'email'    => 'hello@stylenest.co.ke',
        'phone'    => '+254 700 000 000',
        'whatsapp' => '254700000000',
        'address'  => 'Oginga Odinga Street, Kisumu, Kenya',
        'hours'    => 'Monday to Saturday, 9am to 6pm',
    ],

    // Delivery charges in KSh
    'delivery' => [
        'local_county' => 'Kisumu',   // county that gets the local rate
        'local_fee'    => 250,
        'other_fee'    => 450,
        'free_over'    => 8000,       // orders above this ship free (0 disables)
    ],

    'low_stock_threshold' => 5,

    /*
     * M-Pesa (Safaricom Daraja)
     *   mode 'simulate' : no network calls. Lets you test checkout on localhost.
     *   mode 'live'     : real Daraja calls. 'env' picks sandbox or production.
     * Daraja must reach callback_url over public HTTPS, so on localhost use a
     * tunnel such as ngrok or Cloudflare Tunnel while testing.
     */
    'mpesa' => [
        'mode'             => 'simulate',
        'env'              => 'sandbox',        // 'sandbox' or 'production'
        'consumer_key'     => '',
        'consumer_secret'  => '',
        'shortcode'        => '174379',         // sandbox shortcode
        'passkey'          => '',
        'transaction_type' => 'CustomerPayBillOnline',   // or CustomerBuyGoodsOnline
        'callback_url'     => '',               // https://yourdomain/mpesa_callback.php
        'callback_secret'  => 'change-this-long-random-string',
    ],
];
