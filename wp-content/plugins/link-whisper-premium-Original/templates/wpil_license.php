<?php
	/**
	* This file is a template file of license checking page
	*
	*/

    // get the license status data
    $license    = get_option(WPIL_OPTION_LICENSE_KEY, '');
    $status     = get_option(WPIL_OPTION_LICENSE_STATUS);
    $last_error = get_option(WPIL_OPTION_LICENSE_LAST_ERROR, '');

    // get the current licensing state
    $licensing_state;
    if(empty($license) && empty($last_error) || ('invalid' === $status && 'Deactivated manually' === $last_error)){
        $licensing_state = 'activated';
    }elseif(!empty($license) && 'valid' === $status){
        $licensing_state = 'activated';
    }else{
        $licensing_state = 'activated';
    }

    // create titles for the license statuses
    $status_titles   = array(
        'not_activated' => __('License Not Active', 'wpil'),
        'activated'     => __('License Active', 'wpil'),
        'error'         => __('License Error', 'wpil')
    );

    // create some helpful text to tell the user what's going on
    $status_messages = array(
        'not_activated' => __('Please enter your Link Whisper License Key to activate Link Whisper.', 'wpil'),
        'activated'     => __('Congratulations! Your Link Whisper License Key has been confirmed and Link Whisper is now active!', 'wpil'),
        'error'         => $last_error
    );
?>
<div class="wrap wpil_licensing_wrap postbox">
    <div class="wpil_licensing_container">
        <div class="wpil_licensing" style="">
            <h2 class="wpil_licensing_header hndle ui-sortable-handle">
                <span>Link Whisper Licensing</span>
            </h2>
            <div class="wpil_licensing_content inside">
                <form method="post">
                    <?php settings_fields('wpil_license'); ?>
                    <input type="hidden" name="hidden_action" value="activate_license">
                    <table class="form-table">
                        <tbody>
                            <tr>
                                <td class="wpil_license_table_title"><?php _e('License Key:', 'wpil');?></td>
                                <td><input id="wpil_license_key" name="wpil_license_key" type="text" class="regular-text" value="" /></td>
                            </tr>
                            <tr>
                                <td class="wpil_license_table_title"><?php _e('License Status:', 'wpil');?></td>
                                <td><span class="wpil_licensing_status_text <?php echo esc_attr($licensing_state); ?>"><?php echo esc_attr($status_titles[$licensing_state]); ?></span></td>
                            </tr>
                            <tr>
                                <td class="wpil_license_table_title"><?php _e('License Message:', 'wpil');?></td>
                                <td><span class="wpil_licensing_status_text <?php echo esc_attr($licensing_state); ?>"><?php echo esc_attr($status_messages[$licensing_state]); ?></span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <?php wp_nonce_field( 'wpil_activate_license_nonce', 'wpil_activate_license_nonce' ); ?>
                <button type="submit" class="button button-primary wpil_licensing_activation_button"><?php _e('Activate License', 'wpil'); ?></button>
                <div class="wpil_licensing_version_number"><?php echo Wpil_Base::showVersion(); ?></div>
            </form>
        </div>
    </div>
</div>
<?php
