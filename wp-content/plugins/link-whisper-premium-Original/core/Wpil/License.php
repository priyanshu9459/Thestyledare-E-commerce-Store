<?php

/**
 * Work with licenses
 */
class Wpil_License
{
    /**
     * Register services
     */
    public function register()
    {

    }

    public static function init()
    {
        if (!empty($_GET['wpil_deactivate']))
        {
            update_option(WPIL_OPTION_LICENSE_STATUS, 'invalid');
            update_option(WPIL_OPTION_LICENSE_LAST_ERROR, $message='Deactivated manually');
        }

        include WP_INTERNAL_LINKING_PLUGIN_DIR . '/templates/wpil_license.php';
    }

    /**
     * Check if license is valid
     *
     * @return bool
     */


    /**
     * Get license key
     *
     * @param bool $key
     * @return bool|mixed|void
     */
    public static function getKey($key = false)
    {
        if (empty($key)) {
            $key = get_option('wpil_2_license_key');
        }

        if (stristr($key, '-')) {
            $ks = explode('-', $key);
            $key = $ks[1];
        }

        return $key;
    }

    /**
     * Check new license
     *
     * @param $license_key
     * @param bool $silent
     */
    public static function check($license_key, $silent = true)
    {
        $base_url_path = 'admin.php?page=link_whisper_license';
        $item_id = self::getItemId($license_key);
        $license = Wpil_License::getKey($license_key);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL, WPIL_STORE_URL);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS,
            "edd_action=activate_license&license={$license}&item_id={$item_id}&url=".urlencode(home_url()));
        $data = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        update_option(WPIL_OPTION_LICENSE_CHECK_TIME, date('c'));

        if (empty($data) || $code !== 200) {
            $error_message = curl_error($ch);

            if ($error_message) {
                $message = $error_message;
            } else {
                $message = "$code response code on activation, please try again or check code";
            }
        } else {
            $license_data = json_decode($data);

            if ($license_data->success === false) {
                $message = self::getMessage($license, $license_data);
            } else {
                update_option(WPIL_OPTION_LICENSE_STATUS, $license_data->license);
                update_option(WPIL_OPTION_LICENSE_KEY, $license);
                update_option(WPIL_OPTION_LICENSE_DATA, var_export($license_data, true));

                if (!$silent) {
                    $base_url = admin_url($base_url_path);
                    $message = __("License key `%s` was activated", 'wpil');
                    $message = sprintf($message, $license);
                    $redirect = add_query_arg(array('sl_activation' => 'true', 'message' => urlencode($message)), $base_url);
                    wp_redirect($redirect);
                    exit;
                } else {
                    return;
                }
            }
        }
        curl_close($ch);

        update_option(WPIL_OPTION_LICENSE_STATUS, 'invalid');
        update_option(WPIL_OPTION_LICENSE_LAST_ERROR, $message);

        if (!$silent) {
            $base_url = admin_url($base_url_path);
            $redirect = add_query_arg(array('sl_activation' => 'false', 'msg' => urlencode($message)), $base_url);
            wp_redirect($redirect);
            exit;
        }
    }

    /**
     * Get current license ID
     *
     * @param string $license_key
     * @return false|string
     */
    public static function getItemId($license_key = '')
    {
        if ($license_key && stristr($license_key, '-')) {
            $ks = explode('-', $license_key);
            return $ks[0];
        }

        $item_id = file_get_contents(dirname(__DIR__) . '/../store-item-id.txt');

        return $item_id;
    }

    /**
     * Get license message
     *
     * @param $license
     * @param $license_data
     * @return string
     */
    public static function getMessage($license, $license_data)
    {
        switch ($license_data->error) {
            case 'expired' :
                $d = date_i18n(get_option('date_format'), strtotime($license_data->expires, current_time('timestamp')));
                $message = sprintf('Your license key %s expired on %s. Please renew your subscription to continue using Link Whisper.', $license, $d);
                break;

            case 'revoked' :
                $message = 'Your License Key `%s` has been disabled';
                break;

            case 'missing' :
                $message = 'Missing License `%s`';
                break;

            case 'invalid' :
            case 'site_inactive' :
                $message = 'The License Key `%s` is not active for this URL.';
                break;

            case 'item_name_mismatch' :
                $message = 'It appears this License Key (%s) is used for a different product. Please log into your linkwhisper.com user account to find your Link Whisper License Key.';
                break;

            case 'no_activations_left':
                $message = 'The License Key `%s` has reached its activation limit. Please upgrade your subscription to add more sites.';
                break;

            case 'invalid_item_id':
                $message = 'The License Key `%s` doesn\'t go to any known products. Fairly often this is caused by a mistake in entering the License Key.';
                break;
    
            default :
                $message = "Error on activation: " . $license_data->error;
                break;
        }

        if (stristr($message, '%s')) {
            $message = sprintf($message, $license);
        }

        return $message;
    }

    /**
     * Activate license
     */
    public static function activate()
    {
        if (!isset($_POST['hidden_action']) || $_POST['hidden_action'] != 'activate_license' || !check_admin_referer('wpil_activate_license_nonce', 'wpil_activate_license_nonce')) {
            return;
        }

        $license = sanitize_text_field(trim($_POST['wpil_license_key']));

        self::check($license, $silent = false);
    }
}
