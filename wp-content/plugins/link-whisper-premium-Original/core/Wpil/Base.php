<?php

/**
 * Base controller
 */
class Wpil_Base
{
    public static $report_menu;

    /**
     * Register services
     */
    public function register()
    {
        add_filter('cron_schedules', [$this, 'cronAddMinute']);
        add_action('admin_init', [$this, 'init']);
        add_action('wpil_cron_report', [Wpil_Report::class, 'cron']);
        add_action('wpil_cron_links', [Wpil_Post::class, 'cronLinks']);
        add_action('admin_menu', [$this, 'addMenu']);
        add_action('add_meta_boxes', [$this, 'addMetaBoxes']);
        add_action('admin_enqueue_scripts', [$this, 'addScripts']);
        add_action('plugin_action_links_' . WPIL_PLUGIN_NAME, [$this, 'showSettingsLink']);
        add_action('wp_ajax_get_post_suggestions', ['Wpil_Suggestion','ajax_get_post_suggestions']);
        add_action('wp_ajax_update_suggestion_display', ['Wpil_Suggestion','ajax_update_suggestion_display']);
    }

    /**
     * Initial function
     */
    function init()
    {
        $post = Wpil_Base::getPost();

        if (!empty($_GET['type'])) { // if the current page has a "type" value
            $type = $_GET['type'];

            switch ($type) {
                case 'delete_link':
                    Wpil_Link::delete();
                    break;
                case 'inbound_suggestions_page_container':
//                    $groups = Wpil_Suggestion::getPostInboundSuggestions($post);
                    include WP_INTERNAL_LINKING_PLUGIN_DIR . '/templates/inbound_suggestions_page_container.php';
                    exit;
                    break;
            }
        }

        if (!empty($_GET['area'])) {
            switch ($_GET['area']) {
                case 'wpil_export':
                    Wpil_Export::getInstance()->export($post);
                    break;
            }
        }

        if (!empty($_POST['hidden_action'])) {
            switch ($_POST['hidden_action']) {
                case 'wpil_save_settings':
                    Wpil_Settings::save();
                    break;
                case 'activate_license':
                    Wpil_License::activate();
                    break;
            }
        }

        //add screen options
        add_action("load-" . self::$report_menu, function () {
            add_screen_option( 'report_options', array(
                'option' => 'report_options',
            ) );
        });

        //Create cron jobs
        if (!wp_next_scheduled('wpil_cron_report') ) {
            wp_schedule_event( time(), 'minute', 'wpil_cron_report');
        }
        if (!wp_next_scheduled('wpil_cron_links') ) {
            wp_schedule_event( time(), 'minute', 'wpil_cron_links');
        }
    }

    /**
     * Add new interval to cron schedule
     *
     * @param $schedules
     * @return mixed
     */
    function cronAddMinute($schedules)
    {
        $schedules['minute'] = [
            'interval' => MINUTE_IN_SECONDS,
            'display' => __('Once minute')
        ];

        return $schedules;
    }

    /**
     * This function is used for adding menu and submenus
     *
     *
     * @return  void
     */
    public function addMenu()
    {
            add_menu_page(
                __('Link Whisper', 'wpil'),
                __('Link Whisper', 'wpil'),
                'manage_categories',
                'link_whisper_license',
                [Wpil_License::class, 'init'],
                plugin_dir_url(__DIR__).'../images/lw-icon-16x16.png'
            );


        add_menu_page(
            __('Link Whisper', 'wpil'),
            __('Link Whisper', 'wpil'),
            'edit_posts',
            'link_whisper',
            [Wpil_Report::class, 'init'],
            plugin_dir_url(__DIR__). '../images/lw-icon-16x16.png'
        );

        self::$report_menu = add_submenu_page(
            'link_whisper',
            __('Internal links report', 'wpil'),
            __('Report', 'wpil'),
            'edit_posts',
            'link_whisper',
            [Wpil_Report::class, 'init']
        );

        add_submenu_page(
            'link_whisper',
            __('Settings', 'wpil'),
            __('Settings', 'wpil'),
            'manage_categories',
            'link_whisper_settings',
            [Wpil_Settings::class, 'init']
        );

        add_submenu_page(
            'link_whisper',
            __('License', 'wpil'),
            __('License', 'wpil'),
            'manage_categories',
            'link_whisper_license',
            [Wpil_License::class, 'init']
        );
    }

    /**
     * Get post or term by ID from GET or POST request
     *
     * @return Wpil_Model_Post|null
     */
    public static function getPost()
    {
        if (!empty($_REQUEST['term_id'])) {
            $post = new Wpil_Model_Post((int)$_REQUEST['term_id'], 'term');
        } elseif (!empty($_REQUEST['post_id'])) {
            $post = new Wpil_Model_Post((int)$_REQUEST['post_id']);
        } else {
            $post = null;
        }

        return $post;
    }

    /**
     * Show plugin version
     *
     * @return string
     */
    public static function showVersion()
    {
        $plugin_data = get_plugin_data(WP_INTERNAL_LINKING_PLUGIN_DIR . 'link-whisper.php');

        return "<p style='float: right'>version <b>".$plugin_data['Version']."</b></p>";
    }

    /**
     * Show extended error message
     *
     * @param $errno
     * @param $errstr
     * @param $error_file
     * @param $error_line
     */
    public static function handleError($errno, $errstr, $error_file, $error_line)
    {
        if (stristr($errstr, "WordPress could not establish a secure connection to WordPress.org")) {
            return;
        }

        $file = 'n/a';
        $func = 'n/a';
        $line = 'n/a';
        $debugTrace = debug_backtrace();
        if (isset($debugTrace[1])) {
            $file = isset($debugTrace[1]['file']) ? $debugTrace[1]['file'] : 'n/a';
            $line = isset($debugTrace[1]['line']) ? $debugTrace[1]['line'] : 'n/a';
        }
        if (isset($debugTrace[2])) {
            $func = $debugTrace[2]['function'] ? $debugTrace[2]['function'] : 'n/a';
        }

        $out = "call from <b>$file</b>, $func, $line";

        $trace = '';
        $bt = debug_backtrace();
        $sp = 0;
        foreach($bt as $k=>$v) {
            extract($v);

            $args = '';
            if (isset($v['args'])) {
                $args2 = array();
                foreach($v['args'] as $k => $v) {
                    if (!is_scalar($v)) {
                        $args2[$k] = "Array";
                    }
                    else {
                        $args2[$k] = $v;
                    }
                }
                $args = implode(", ", $args2);
            }

            $file = substr($file,1+strrpos($file,"/"));
            $trace .= str_repeat("&nbsp;",++$sp);
            $trace .= "file=<b>$file</b>, line=$line,
									function=$function(".
                var_export($args, true).")<br>";
        }

        $out .= $trace;

        echo "<b>Error:</b> [$errno] $errstr - $error_file:$error_line<br><br><hr><br><br>$out";
    }

    /**
     * Add meta box to the post edit page
     */
    public static function addMetaBoxes()
    {
            add_meta_box('wpil_link-articles', 'Link Whisper Suggested Links', [Wpil_Base::class, 'showSuggestionsBox'], Wpil_Settings::getPostTypes());
    }

    /**
     * Show meta box on the post edit page
     */
    public static function showSuggestionsBox()
    {
        $post_id = isset($_REQUEST['post']) ? (int)$_REQUEST['post'] : '';
        $user = wp_get_current_user();
        if ($post_id) {
            include WP_INTERNAL_LINKING_PLUGIN_DIR . '/templates/link_list_v2.php';
        }
    }

    /**
     * Add scripts to the admin panel
     *
     * @param $hook
     */
    public static function addScripts($hook)
    {
        wp_enqueue_editor();
        wp_register_script('wpil_sweetalert_script_min', WP_INTERNAL_LINKING_PLUGIN_URL . 'js/sweetalert.min.js', array('jquery'), $ver=false, $in_footer=true);
        wp_enqueue_script('wpil_sweetalert_script_min');

        $js_path = 'js/wpil_admin.js';
        $f_path = WP_INTERNAL_LINKING_PLUGIN_DIR.$js_path;
        $ver = filemtime($f_path);

        wp_register_script('wpil_admin_script', WP_INTERNAL_LINKING_PLUGIN_URL.$js_path, array('jquery'), $ver, $in_footer=true);
        wp_enqueue_script('wpil_admin_script');

        $js_path = 'js/wpil_admin_settings.js';
        $f_path = WP_INTERNAL_LINKING_PLUGIN_DIR.$js_path;
        $ver = filemtime($f_path);

        wp_register_script('wpil_admin_settings_script', WP_INTERNAL_LINKING_PLUGIN_URL.$js_path, array('jquery'), $ver, $in_footer=true);
        wp_enqueue_script('wpil_admin_settings_script');

        $style_path = 'css/wpil_admin.css';
        $f_path = WP_INTERNAL_LINKING_PLUGIN_DIR.$style_path;
        $ver = filemtime($f_path);

        wp_register_style('wpil_admin_style', WP_INTERNAL_LINKING_PLUGIN_URL.$style_path, $deps=[], $ver);
        wp_enqueue_style('wpil_admin_style');

        $ajax_url = admin_url('admin-ajax.php');

        $script_params = [];
        $script_params['ajax_url'] = $ajax_url;
        $script_params['completed'] = __('completed', 'wpil');

        $script_params["WPIL_OPTION_REPORT_LAST_UPDATED"] = get_option(WPIL_OPTION_REPORT_LAST_UPDATED);

        wp_localize_script('wpil_admin_script', 'wpil_ajax', $script_params);
    }

    /**
     * Show settings link on the plugins page
     *
     * @param $links
     * @return array
     */
    public static function showSettingsLink($links)
    {
        $links[] = '<a href="admin.php?page=link_whisper_settings">Settings</a>';

        return $links;
    }

    /**
     * Loads default LinkWhisperer settings in to database on plugin activation.
     */
    public static function activate()
    {
        // only set default option values if the options are empty
        if('' === get_option(WPIL_OPTION_LICENSE_STATUS, '')){
            update_option(WPIL_OPTION_LICENSE_STATUS, '');
        }
        if('' === get_option(WPIL_OPTION_LICENSE_KEY, '')){
            update_option(WPIL_OPTION_LICENSE_KEY, '');
        }
        if('' === get_option(WPIL_OPTION_LICENSE_DATA, '')){
            update_option(WPIL_OPTION_LICENSE_DATA, '');
        }
        if('' === get_option(WPIL_OPTION_IGNORE_NUMBERS, '')){
            update_option(WPIL_OPTION_IGNORE_NUMBERS, '1');
        }
        if('' === get_option(WPIL_OPTION_POST_TYPES, '')){
            update_option(WPIL_OPTION_POST_TYPES, ['post', 'page']);
        }
        if('' === get_option(WPIL_OPTION_LINKS_OPEN_NEW_TAB, '')){
            update_option(WPIL_OPTION_LINKS_OPEN_NEW_TAB, '0');
        }
        if('' === get_option(WPIL_OPTION_DEBUG_MODE, '')){
            update_option(WPIL_OPTION_DEBUG_MODE, '0');
        }
        if('' === get_option(WPIL_OPTION_UPDATE_REPORTING_DATA_ON_SAVE, '')){
            update_option(WPIL_OPTION_UPDATE_REPORTING_DATA_ON_SAVE, '0');
        }
        if('' === get_option(WPIL_OPTION_IGNORE_WORDS, '')){
            $ignore = "-\r\n" . implode("\r\n", Wpil_Settings::getIgnoreWords()) . "\r\n-";
            update_option(WPIL_OPTION_IGNORE_WORDS, $ignore);
        }
        if('' === get_option(WPIL_LINK_TABLE_IS_CREATED, '')){
            Wpil_Report::setupWpilLinkTable(true);
        }

        Wpil_Link::removeLinkClass();
    }
}
