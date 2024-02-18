<div class="wrap wpil_styles" id="settings_page">
    <?=Wpil_Base::showVersion()?>
    <h1 class="wp-heading-inline"><?php _e('Link Whisper Settings', 'wpil'); ?></h1>
    <hr class="wp-header-end">
    <div id="poststuff">
        <div id="post-body" class="metabox-holder">
            <div id="post-body-content" style="position: relative;">
                <?php if (isset($_REQUEST['success'])) : ?>
                    <div class="notice update notice-success" id="wpil_message" >
                        <p><?php _e('Settings has been updated successfully!', 'wpil'); ?></p>
                    </div>
                <?php endif; ?>
                <form name="frmSaveSettings" id="frmSaveSettings" action='' method='post'>
                    <?php wp_nonce_field('wpil_save_settings','wpil_save_settings_nonce'); ?>
                    <input type="hidden" name="hidden_action" value="wpil_save_settings" />
                    <table class="form-table">
                        <tbody>
                        <tr>
                            <td scope='row'><?php _e('Open Links in a New Tab', 'wpil'); ?></td>
                            <td>
                                <input type="hidden" name="wpil_2_links_open_new_tab" value="0" />
                                <input type="checkbox" name="wpil_2_links_open_new_tab" <?=get_option('wpil_2_links_open_new_tab')==1?'checked':''?> value="1" />
                            </td>
                        </tr>
                        <tr>
                            <td scope='row'><?php _e('Ignore Numbers', 'wpil'); ?></td>
                            <td>
                                <input type="hidden" name="wpil_2_ignore_numbers" value="0" />
                                <input type="checkbox" name="wpil_2_ignore_numbers" <?=get_option('wpil_2_ignore_numbers')==1?'checked':''?> value="1" />
                            </td>
                        </tr>
                        <tr>
                            <td scope='row'><?php _e('Selected Language', 'wpil'); ?></td>
                            <td>
                                <select id="wpil-selected-language" name="wpil_selected_language">
                                    <?php
                                        $languages = Wpil_Settings::getSupportedLanguages();
                                        $selected_language = Wpil_Settings::getSelectedLanguage();
                                    ?>
                                    <?php foreach($languages as $language_key => $language_name) : ?>
                                        <option value="<?php echo $language_key; ?>" <?php selected($language_key, $selected_language); ?>><?php echo $language_name; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="hidden" id="wpil-currently-selected-language" value="<?php echo $selected_language; ?>">
                                <input type="hidden" id="wpil-currently-selected-language-confirm-text-1" value="<?php echo esc_attr__('Changing Link Whisper\'s language will replace the current Words to be Ignored with a new list of words.', 'wpil') ?>">
                                <input type="hidden" id="wpil-currently-selected-language-confirm-text-2" value="<?php echo esc_attr__('If you\'ve added any words to the Words to be Ignored area, this will erase them.', 'wpil') ?>">
                            </td>
                        </tr>
                        <tr>
                            <td scope='row'><?php _e('Words to be Ignored', 'wpil'); ?></td>
                            <td>
                                <?php
                                    $lang_data = array();
                                    foreach(Wpil_Settings::getAllIgnoreWordLists() as $lang_id => $words){
                                        $lang_data[$lang_id] = $words;
                                    }
                                ?>
                                <textarea name='ignore_words' id='ignore_words' class='regular-text' rows=10><?php echo esc_textarea(implode("\n", $lang_data[$selected_language])); ?></textarea>
                                <p><i><?php _e('Add each word with new line', 'wpil'); ?></i></p>
                                <input type="hidden" id="wpil-available-language-word-lists" value="<?php echo esc_attr( wp_json_encode($lang_data, JSON_UNESCAPED_UNICODE) ); ?>">
                            </td>
                        </tr>
                        <tr>
                            <td scope='row'><?php _e('Post Types to Create Links For', 'wpil'); ?></td>
                            <td>
                                <?php foreach ($types_available as $type) : ?>
                                    <input type="checkbox" name="wpil_2_post_types[]" value="<?=$type?>" <?=in_array($type, $types_active)?'checked':''?>><label><?=ucfirst($type)?></label><br>
                                <?php endforeach; ?>
                                <p><i><?php _e('After changing the post type selection, please go to the Report page and click the "Reset Data" button to clear the old link data.', 'wpil'); ?></i></p>
                            </td>
                        </tr>
                        <tr>
                            <td scope="row"><?php _e('Number of Sentences to Skip', 'wpil'); ?></td>
                            <td>
                                <select name="wpil_skip_sentences">
                                    <?php for($i = 0; $i <= 10; $i++) : ?>
                                        <option value="<?=$i?>" <?=$i==Wpil_Settings::getSkipSentences() ? 'selected' : '' ?>><?=$i?></option>
                                    <?php endfor; ?>
                                </select>
                                <label for="wpil_skip_sentences"><?php _e('Sentences. Link Whisper will not suggest links for this number of sentences appearing at the beginning of a post.', 'wpil'); ?></label>
                            </td>
                        </tr>
                        <tr>
                            <td scope='row'>
                                <span class="debug-settings-carrot">
                                    <?php _e('Debug Settings', 'wpil'); ?>
                                </span>
                            </td>
                            <td>
                                <div class="debug-setting-control">
                                    <input type="hidden" name="wpil_2_debug_mode" value="0" />
                                    <input type='checkbox' name="wpil_2_debug_mode" <?=get_option('wpil_2_debug_mode')==1?'checked':''?> value="1" />
                                    <label><?php _e('Enable Debug Mode?', 'wpil'); ?></label>
                                    <p><i><?php _e('If you\'re having errors, or it seems that data is missing, activating Debug Mode may be useful in diagnosing the problem.', 'wpil'); ?></i></p>
                                    <p><i><?php _e('Enabling Debug Mode will cause your site to display any errors or code problems it\'s expiriencing instead of hiding them from view.', 'wpil'); ?></i></p>
                                    <p><i><?php _e('These error notices may be visible to your site\'s visitors, so it\'s recommended to only use this for limited periods of time.', 'wpil'); ?></i></p>
                                    <p><i><?php _e('(If you are already debugging with WP_DEBUG, then there\'s no need to activate this.)', 'wpil'); ?></i></p>
                                    <br>
                                </div>
                                <div class="debug-setting-control">
                                    <input type="hidden" name="wpil_option_update_reporting_data_on_save" value="0" />
                                    <input type='checkbox' name="wpil_option_update_reporting_data_on_save" <?=get_option('wpil_option_update_reporting_data_on_save')==1?'checked':''?> value="1" />
                                    <label><?php _e('Run a check for un-indexed posts on each post save?', 'wpil'); ?></label>
                                    <p><i><?php _e('Checking this will tell Link Whisper to look for any posts that haven\'t been indexed for the link reports every time a post is saved.', 'wpil'); ?></i></p>
                                    <p><i><?php _e('In most cases this isn\'t necessary, but if you\'re finding that some of your posts aren\'t displaying in the reports screens, this may fix it.', 'wpil'); ?></i></p>
                                    <p><i><?php _e('One word of caution: If you have many un-indexed posts on the site, this may cause memory / timeout errors.', 'wpil'); ?></i></p>
                                    <br>
                                </div>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                    <p class='submit'>
                        <input type='submit' name='btnsave' id='btnsave' value='Save Settings' class='button-primary' />
                    </p>
                </form>
            </div>
        </div>
    </div>
</div>
