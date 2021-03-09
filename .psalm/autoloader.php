<?php // phpcs:disable
if (defined('ABSPATH')) {
    return;
}

define('ABSPATH', './vendor/johnpbloch/wordpress-core/');
define('WPINC', 'wp-includes');
define('WP_CONTENT_DIR', ABSPATH . 'wp-content');

require_once ABSPATH . WPINC . '/class-wp-rewrite.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
require_once ABSPATH . WPINC .'/plugin.php';
require_once ABSPATH . WPINC .'/load.php';
require_once ABSPATH . WPINC .'/functions.php';
require_once ABSPATH . WPINC .'/general-template.php';
require_once ABSPATH . WPINC .'/link-template.php';
require_once ABSPATH . WPINC .'/rest-api.php';
