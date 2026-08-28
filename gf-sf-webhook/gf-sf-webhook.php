<?php
/**
 * Plugin Name: GF SF Webhook
 * Description: Sends mapped Gravity Forms submissions to a configured Salesforce webhook.
 * Version: 1.1.2
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Update URI: https://github.com/cchatterton/gf-sf-webhook
 * Author: AlphaSys
 * Author URI: https://alphasys.com.au
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: gf-sf-webhook
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GFSF_VERSION', '1.1.2' );
define( 'GFSF_PLUGIN_FILE', __FILE__ );
define( 'GFSF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once GFSF_PLUGIN_DIR . 'functions/settings.php';
require_once GFSF_PLUGIN_DIR . 'functions/submission.php';
require_once GFSF_PLUGIN_DIR . 'functions/webhook.php';
require_once GFSF_PLUGIN_DIR . 'functions/resend.php';
require_once GFSF_PLUGIN_DIR . 'functions/github-updater.php';
