<?php
/**
 * Plugin Name:       SwiftLetter
 * Plugin URI:        https://example.com/swiftletter
 * Description:       Accessible newsletter compilation, refinement, and multi-format publishing.
 * Version:           1.0.1
 * Requires at least: 6.9
 * Requires PHP:      8.1
 * Author:            SwiftLetter
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       swiftletter
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SWIFTLETTER_VERSION', '1.0.1' );
define( 'SWIFTLETTER_FILE', __FILE__ );
define( 'SWIFTLETTER_DIR', plugin_dir_path( __FILE__ ) );
define( 'SWIFTLETTER_URL', plugin_dir_url( __FILE__ ) );
define( 'SWIFTLETTER_BASENAME', plugin_basename( __FILE__ ) );

$swiftletter_autoload = SWIFTLETTER_DIR . 'vendor/autoload.php';
if ( file_exists( $swiftletter_autoload ) ) {
	require_once $swiftletter_autoload;
} else {
	add_action( 'admin_notices', function () {
		echo '<div class="notice notice-error"><p><strong>SwiftLetter:</strong> Composer dependencies not installed. Run <code>composer install</code> in the plugin directory.</p></div>';
	} );
	return;
}

register_activation_hook( SWIFTLETTER_FILE, [ \SwiftLetter\Activator::class, 'activate' ] );
register_deactivation_hook( SWIFTLETTER_FILE, [ \SwiftLetter\Deactivator::class, 'deactivate' ] );

add_action( 'plugins_loaded', function () {
	\SwiftLetter\Plugin::instance()->init();
} );
