<?php

defined( 'ABSPATH' ) || exit;
/*
  Plugin Name: WP Sheet Editor - Events
  Description: Edit events in spreadsheet.
  Version: 1.1.33
  Author:      WP Sheet Editor
  Author URI:  https://wpsheeteditor.com/?utm_source=wp-admin&utm_medium=plugins-list&utm_campaign=events
  Plugin URI: https://wpsheeteditor.com/go/events-addon?utm_source=wp-admin&utm_medium=plugins-list&utm_campaign=events
  License:     GPL2
  License URI: https://www.gnu.org/licenses/gpl-2.0.html
  WC requires at least: 4.0
  WC tested up to: 9.3
  Text Domain: vg_sheet_editor_events
  Domain Path: /lang
*/
if ( isset( $_GET['wpse_troubleshoot8987'] ) ) {
    return;
}
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
if ( function_exists( 'wpsee_fs' ) ) {
    wpsee_fs()->set_basename( false, __FILE__ );
}
require_once 'vendor/vg-plugin-sdk/index.php';
require_once 'vendor/freemius/start.php';
require_once 'inc/freemius-init.php';
if ( !class_exists( 'WP_Sheet_Editor_Events' ) ) {
    /**
     * Filter rows in the spreadsheet editor.
     */
    class WP_Sheet_Editor_Events {
        private static $instance = false;

        public $plugin_url = null;

        public $plugin_dir = null;

        public $textname = 'vg_sheet_editor_events';

        public $buy_link = null;

        public $version = '1.0.32';

        var $settings = null;

        public $args = null;

        var $vg_plugin_sdk = null;

        var $post_type = 'shop_event';

        public $modules_controller = null;

        private function __construct() {
        }

        function init_plugin_sdk() {
            $this->args = array(
                'main_plugin_file'         => __FILE__,
                'show_welcome_page'        => true,
                'welcome_page_file'        => $this->plugin_dir . '/views/welcome-page-content.php',
                'website'                  => 'https://wpsheeteditor.com',
                'logo_width'               => 180,
                'logo'                     => plugins_url( '/assets/imgs/logo.svg', __FILE__ ),
                'buy_link'                 => $this->buy_link,
                'plugin_name'              => 'Bulk Edit Events',
                'plugin_prefix'            => 'wpsee_',
                'show_whatsnew_page'       => true,
                'whatsnew_pages_directory' => $this->plugin_dir . '/views/whats-new/',
                'plugin_version'           => $this->version,
                'plugin_options'           => $this->settings,
            );
            $this->vg_plugin_sdk = new VG_Freemium_Plugin_SDK($this->args);
        }

        function notify_wrong_core_version() {
            $plugin_data = get_plugin_data( __FILE__, false, false );
            ?>
			<div class="notice notice-error">
				<p><?php 
            _e( 'Please update the WP Sheet Editor plugin and all its extensions to the latest version. The features of the plugin "' . $plugin_data['Name'] . '" will be disabled temporarily because it is the newest version and it conflicts with old versions of other WP Sheet Editor plugins. The features will be enabled automatically after you install the updates.', vgse_events()->textname );
            ?></p>
			</div>
			<?php 
        }

        function init() {
            require_once __DIR__ . '/modules/init.php';
            $this->modules_controller = new WP_Sheet_Editor_CORE_Modules_Init(__DIR__, wpsee_fs());
            $integrations = array_merge( glob( __DIR__ . '/integrations/*.php' ), glob( __DIR__ . '/integrations/providers/*.php' ) );
            foreach ( $integrations as $integration_file ) {
                require_once $integration_file;
            }
            $this->plugin_url = plugins_url( '/', __FILE__ );
            $this->plugin_dir = __DIR__;
            $this->buy_link = wpsee_fs()->checkout_url();
            $this->init_plugin_sdk();
            // After core has initialized
            add_action( 'vg_sheet_editor/initialized', array($this, 'after_core_init') );
            add_action( 'admin_init', array($this, 'disable_free_plugins_when_premium_active'), 1 );
            add_action( 'init', array($this, 'after_init') );
            add_action( 'before_woocommerce_init', function () {
                if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
                    $main_file = __FILE__;
                    $parent_dir = dirname( dirname( $main_file ) );
                    $new_path = str_replace( $parent_dir, '', $main_file );
                    $new_path = wp_normalize_path( ltrim( $new_path, '\\/' ) );
                    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', $new_path, true );
                }
            } );
        }

        function after_init() {
            load_plugin_textdomain( $this->textname, false, basename( dirname( __FILE__ ) ) . '/lang/' );
        }

        function disable_free_plugins_when_premium_active() {
            $free_plugins_path = array('bulk-edit-events/events.php');
            if ( is_plugin_active( 'bulk-edit-events-premium/events.php' ) ) {
                foreach ( $free_plugins_path as $relative_path ) {
                    $path = wp_normalize_path( WP_PLUGIN_DIR . '/' . $relative_path );
                    if ( is_plugin_active( $relative_path ) ) {
                        deactivate_plugins( plugin_basename( $path ) );
                    }
                }
            }
        }

        function after_core_init() {
            if ( version_compare( VGSE()->version, '2.25.9-beta.1' ) < 0 ) {
                add_action( 'admin_notices', array($this, 'notify_wrong_core_version') );
                return;
            }
            // Override core buy link with this plugin´s
            VGSE()->buy_link = $this->buy_link;
            // Enable admin pages in case "frontend sheets" addon disabled them
            add_filter( 'vg_sheet_editor/register_admin_pages', '__return_true', 11 );
        }

        /**
         * Creates or returns an instance of this class.
         */
        static function get_instance() {
            if ( null == WP_Sheet_Editor_Events::$instance ) {
                WP_Sheet_Editor_Events::$instance = new WP_Sheet_Editor_Events();
                WP_Sheet_Editor_Events::$instance->init();
            }
            return WP_Sheet_Editor_Events::$instance;
        }

        function __set( $name, $value ) {
            $this->{$name} = $value;
        }

        function __get( $name ) {
            return $this->{$name};
        }

    }

}
if ( !function_exists( 'vgse_events' ) ) {
    function vgse_events() {
        return WP_Sheet_Editor_Events::get_instance();
    }

    vgse_events();
}