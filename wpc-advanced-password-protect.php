<?php
/*
Plugin Name: WPC Advanced Password Protect for WooCommerce
Plugin URI: https://wpclever.net/
Description: WPC Advanced Password Protect provides ultimate protection for your online stores with sophisticated accessibility rules to restrict visitors.
Version: 1.0.6
Author: WPClever
Author URI: https://wpclever.net
Text Domain: wpc-advanced-password-protect
Domain Path: /languages/
Requires Plugins: woocommerce
Requires at least: 4.0
Tested up to: 6.6
WC requires at least: 3.0
WC tested up to: 9.2
*/

defined( 'ABSPATH' ) || exit;

! defined( 'WPCPP_VERSION' ) && define( 'WPCPP_VERSION', '1.0.6' );
! defined( 'WPCPP_LITE' ) && define( 'WPCPP_LITE', __FILE__ );
! defined( 'WPCPP_FILE' ) && define( 'WPCPP_FILE', __FILE__ );
! defined( 'WPCPP_URI' ) && define( 'WPCPP_URI', plugin_dir_url( __FILE__ ) );
! defined( 'WPCPP_DIR' ) && define( 'WPCPP_DIR', plugin_dir_path( __FILE__ ) );
! defined( 'WPCPP_SUPPORT' ) && define( 'WPCPP_SUPPORT', 'https://wpclever.net/support?utm_source=support&utm_medium=wpcpp&utm_campaign=wporg' );
! defined( 'WPCPP_REVIEWS' ) && define( 'WPCPP_REVIEWS', 'https://wordpress.org/support/plugin/wpc-advanced-password-protect/reviews/?filter=5' );
! defined( 'WPCPP_CHANGELOG' ) && define( 'WPCPP_CHANGELOG', 'https://wordpress.org/plugins/wpc-advanced-password-protect/#developers' );
! defined( 'WPCPP_DISCUSSION' ) && define( 'WPCPP_DISCUSSION', 'https://wordpress.org/support/plugin/wpc-advanced-password-protect' );
! defined( 'WPC_URI' ) && define( 'WPC_URI', WPCPP_URI );

include 'includes/dashboard/wpc-dashboard.php';
include 'includes/kit/wpc-kit.php';
include 'includes/hpos.php';

if ( ! function_exists( 'wpcpp_init' ) ) {
	add_action( 'plugins_loaded', 'wpcpp_init', 11 );

	function wpcpp_init() {
		// load text-domain
		load_plugin_textdomain( 'wpc-advanced-password-protect', false, basename( __DIR__ ) . '/languages/' );

		if ( ! function_exists( 'WC' ) || ! version_compare( WC()->version, '3.0', '>=' ) ) {
			add_action( 'admin_notices', 'wpcpp_notice_wc' );

			return null;
		}

		if ( ! class_exists( 'WPCleverWpcpp' ) && class_exists( 'WC_Product' ) ) {
			class WPCleverWpcpp {
				function __construct() {
					require_once trailingslashit( WPCPP_DIR ) . 'includes/class-backend.php';
					require_once trailingslashit( WPCPP_DIR ) . 'includes/class-frontend.php';
				}
			}

			new WPCleverWpcpp();
		}

		return null;
	}
}

if ( ! function_exists( 'wpcpp_notice_wc' ) ) {
	function wpcpp_notice_wc() {
		?>
        <div class="error">
            <p><strong>WPC Advanced Password Protect</strong> require WooCommerce version 3.0 or greater.</p>
        </div>
		<?php
	}
}
