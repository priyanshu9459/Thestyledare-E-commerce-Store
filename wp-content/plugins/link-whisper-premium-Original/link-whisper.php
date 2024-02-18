<?php
/**
 * Plugin Name: Link Whisper
 * Plugin URI: https://linkwhisper.com
 * Version: 0.9.7
 * Description: Quickly build smart internal links both to and from your content. Additionally, gain valuable insights with in-depth internal link reporting.
 * Author: Link Whisper
 * Author URI: https://linkwhisper.com
 * Text Domain: wpil
 */

function removeDirectory($dir) {
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (is_dir($dir. DIRECTORY_SEPARATOR .$object) && !is_link($dir."/".$object))
                    removeDirectory($dir. DIRECTORY_SEPARATOR .$object);
                else
                    unlink($dir. DIRECTORY_SEPARATOR .$object);
            }
        }
        rmdir($dir);
    }
}

if (is_dir(ABSPATH . 'wp-content/plugins/link-whisper/')) {
    $plugins = get_option('active_plugins');
    foreach ($plugins as $key => $plugin) {
        if ($plugin == 'link-whisper/link-whisper.php') {
            unset($plugins[$key]);
        }
    }
    update_option('active_plugins', $plugins);
    removeDirectory(ABSPATH . 'wp-content/plugins/link-whisper/');
}

//autoloader
spl_autoload_unregister( 'wpil_autoloader' );
spl_autoload_register( 'wpil_autoloader_premium' );
function wpil_autoloader_premium( $class_name ) {
    if ( false !== strpos( $class_name, 'Wpil' ) ) {
        $classes_dir = realpath( plugin_dir_path( __FILE__ ) ) . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR;
        $class_file = str_replace( '_', DIRECTORY_SEPARATOR, $class_name ) . '.php';
        require_once $classes_dir . $class_file;
    }
}

define( 'WPIL_STORE_URL', 'https://linkwhisper.com');
define( 'WP_INTERNAL_LINKING_PLUGIN_DIR', plugin_dir_path(__FILE__));
define( 'WP_INTERNAL_LINKING_PLUGIN_URL', plugin_dir_url(__FILE__));
define( 'WPIL_OPTION_LL_HIDE', 'WPIL_OPTION_LL_HIDE');
define( 'WPIL_PLUGIN_NAME', plugin_basename( __FILE__ ));
define( 'WPIL_OPTION_LL_PAIRS_MODE', 'wpil_2_ll_pairs_mode');
define( 'WPIL_OPTION_LL_PAIRS_MODE_NO', 'wpil_2_ll_pairs_mode_no');
define( 'WPIL_OPTION_LL_PAIRS_MODE_EXACT', 'wpil_2_ll_pairs_mode_exact');
define( 'WPIL_OPTION_LL_PAIRS_MODE_ANYWHERE', 'wpil_2_ll_pairs_mode_anywhere');
define( 'WPIL_OPTION_IGNORE_WORDS', 'wpil_2_ignore_words');
define( 'WPIL_OPTION_IGNORE_NUMBERS', 'wpil_2_ignore_numbers');
define( 'WPIL_OPTION_DEBUG_MODE', 'wpil_2_debug_mode');
define( 'WPIL_OPTION_UPDATE_REPORTING_DATA_ON_SAVE', 'wpil_option_update_reporting_data_on_save');
define( 'WPIL_OPTION_REDUCE_CASCADE_UPDATING', 'wpil_option_reduce_cascade_updating');
define( 'WPIL_OPTION_DONT_COUNT_INBOUND_LINKS', 'wpil_option_dont_count_inbound_links');
define( 'WPIL_OPTION_LICENSE_KEY', 'wpil_2_license_key');
define( 'WPIL_OPTION_LICENSE_CHECK_TIME', 'wpil_2_license_check_time');
define( 'WPIL_OPTION_LICENSE_STATUS', 'wpil_2_license_status');
define( 'WPIL_OPTION_LICENSE_DATA', 'wpil_2_license_data');
define( 'WPIL_OPTION_LICENSE_LAST_ERROR', 'wpil_2_license_last_error');
define( 'WPIL_OPTION_POST_TYPES', 'wpil_2_post_types');
define( 'WPIL_OPTION_LINKS_OPEN_NEW_TAB', 'wpil_2_links_open_new_tab');
define( 'WPIL_OPTION_REPORT_LAST_UPDATED', 'wpil_2_report_last_updated');
define( 'WPIL_VERSION_DEV', '18-July-2019');
define( 'WPIL_VERSION_DEV_DISPLAY', true);
define( 'WPIL_LINKS_OUTBOUND_INTERNAL_COUNT', 'wpil_links_outbound_internal_count');
define( 'WPIL_LINKS_INBOUND_INTERNAL_COUNT', 'wpil_links_inbound_internal_count');
define( 'WPIL_LINKS_OUTBOUND_EXTERNAL_COUNT', 'wpil_links_outbound_external_count');
define( 'WPIL_META_KEY_SYNC', 'wpil_sync_report3');
define( 'WPIL_META_KEY_SYNC_TIME', 'wpil_sync_report2_time');
define( 'WPIL_META_KEY_ADD_LINKS', 'wpil_add_links');
define( 'WPIL_LINK_TABLE_IS_CREATED', 'wpil_link_table_is_created');
define( 'WPIL_STATUS_LINK_TABLE_EXISTS', get_option(WPIL_LINK_TABLE_IS_CREATED, false));

Wpil_Init::register_services();

register_activation_hook(__FILE__, [Wpil_Base::class, 'activate'] );

if (is_admin())
{
    if(!class_exists( 'EDD_SL_Plugin_Updater'))
    {
        // load our custom updater if it doesn't already exist
        include (dirname(__FILE__).'/vendor/EDD_SL_Plugin_Updater.php');
    }

    if(!function_exists('get_plugin_data'))
    {
        require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }

        $license_key = trim(get_option( WPIL_OPTION_LICENSE_KEY));
        $edd_item_id = Wpil_License::getItemId($license_key);
        $license = Wpil_License::getKey($license_key);

        $plugin_data = get_plugin_data(__FILE__);
        $plugin_version = $plugin_data['Version'];

        // setup the updater
        $edd_updater = new EDD_SL_Plugin_Updater( WPIL_STORE_URL, __FILE__, array(
            'version' => $plugin_version,		// current version number
            'license' => $license,	// license key (used get_option above to retrieve from DB)
            'item_id' => $edd_item_id,	// id of this plugin
            'author' => 'Spencer Haws',	// author of this plugin
            'url' => home_url(),
            'beta' => false, // set to true if you wish customers to receive update notifications of beta releases
        ));


}


add_action('plugins_loaded', 'wpil_init');

if (!function_exists('wpil_init'))
{
    function wpil_init()
    {
        $locale = is_admin() && function_exists('get_user_locale') ? get_user_locale() : get_locale();
        $locale = apply_filters('plugin_locale', $locale, 'wpil');
        unload_textdomain('wpil');
        load_textdomain('wpil', WP_INTERNAL_LINKING_PLUGIN_DIR . 'languages/' . "wpil-" . $locale . '.mo');
        load_plugin_textdomain('wpil', false, WP_INTERNAL_LINKING_PLUGIN_DIR . 'languages');
    }
}
