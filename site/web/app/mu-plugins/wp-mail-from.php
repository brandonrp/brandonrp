<?php

/**
 * Plugin Name: WP Mail From (SendGrid verified sender)
 * Description: Sets wp_mail From to the SendGrid-verified address (single sender).
 */

add_filter('wp_mail_from', function ($email) {
    return 'me@brandonrp.com';
});

add_filter('wp_mail_from_name', function ($name) {
    return 'Brandon RP';
});
