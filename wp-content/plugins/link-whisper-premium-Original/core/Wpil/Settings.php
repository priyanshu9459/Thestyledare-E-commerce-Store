<?php

/**
 * Work with settings
 */
class Wpil_Settings
{
    public static $ignore_phrases = null;
    public static $ignore_words = null;
    public static $keys = [
        'wpil_2_ignore_numbers',
        'wpil_2_post_types',
        'wpil_2_links_open_new_tab',
        'wpil_2_ll_use_h123',
        'wpil_2_ll_pairs_mode',
        'wpil_2_ll_pairs_rank_pc',
        'wpil_2_debug_mode',
        'wpil_option_update_reporting_data_on_save',
        'wpil_skip_sentences',
        'wpil_selected_language'
    ];

    /**
     * Register services
     */
    public function register()
    {
    }

    /**
     * Show settings page
     */
    public static function init()
    {
        $types_active = Wpil_Settings::getPostTypes();
        $types_available = get_post_types(['public' => true]);
        $types_available['category'] = 'category';
        $types_available['post_tag'] = 'post_tag';

        include WP_INTERNAL_LINKING_PLUGIN_DIR . '/templates/wpil_settings_v2.php';
    }

    /**
     * Get ignore phrases
     */
    public static function getIgnorePhrases()
    {
        if (is_null(self::$ignore_phrases)) {
            $phrases = [];
            foreach (self::getIgnoreWords() as $word) {
                if (strpos($word, ' ') !== false) {
                    $phrases[] = preg_replace('/\s+/', ' ',$word);
                }
            }

            self::$ignore_phrases = $phrases;
        }

        return self::$ignore_phrases;
    }

    /**
     * Get ignore words
     */
    public static function getIgnoreWords()
    {
        if (is_null(self::$ignore_words)) {
            $words = get_option('wpil_2_ignore_words', null);

            if (is_null($words)) {
                // get the user's current language
                $selected_language = self::getSelectedLanguage();
                $ignore_words_file = '';

                // pick the correct ignore list based on the current language.
                switch($selected_language){
                    case 'spanish':
                        $ignore_words_file = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/ignore_word_lists/ES_ignore_words.txt';
                        break;
                    case 'french':
                        $ignore_words_file = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/ignore_word_lists/FR_ignore_words.txt';
                        break;
                    case 'german':
                        $ignore_words_file = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/ignore_word_lists/DE_ignore_words.txt';
                        break;
                    case 'russian':
                        $ignore_words_file = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/ignore_word_lists/RU_ignore_words.txt';
                        break;
                    case 'portuguese':
                        $ignore_words_file = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/ignore_word_lists/PT_ignore_words.txt';
                        break;
                    case 'dutch':
                        $ignore_words_file = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/ignore_word_lists/NL_ignore_words.txt';
                        break;
                    default:
                        $ignore_words_file = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/ignore_word_lists/EN_ignore_words.txt';
                        break;
                }
                
                $words = file($ignore_words_file);
                
                foreach($words as $key => $word) {
                    $words[$key] = trim(Wpil_Word::strtolower($word));
                }
                
            } else {
                $words = explode("\n", $words);
                $words = array_unique($words);
                sort($words);

                foreach($words as $key => $word) {
                    $words[$key] = trim(Wpil_Word::strtolower($word));
                }
            }

            self::$ignore_words = $words;
        }
        
        return self::$ignore_words;
    }

    /**
     * Gets all current ignore word lists.
     * The word list for the language the user is currently using is loaded from the settings.
     * All other languages are loaded from the word files
     **/
    public static function getAllIgnoreWordLists(){
        $current_language       = self::getSelectedLanguage();
        $supported_languages    = self::getSupportedLanguages();
        $all_ignore_lists       = array();

        // go over all currently supported languages
        foreach($supported_languages as $language_id => $supported_language){

            // if the current language is the user's selected one
            if($language_id === $current_language){
                
                $words = get_option('wpil_2_ignore_words', null);
                if(is_null($words)){
                    $words = self::getIgnoreWords();
                }

                $words = explode("\n", $words);
                $words = array_unique($words);
                sort($words);
                foreach($words as $key => $word) {
                    $words[$key] = trim(Wpil_Word::strtolower($word));
                }
                
                $all_ignore_lists[$language_id] = $words;
            }else{
                $ignore_words_file = '';

                switch($language_id){
                    case 'spanish':
                        $ignore_words_file = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/ignore_word_lists/ES_ignore_words.txt';
                        break;
                    case 'french':
                        $ignore_words_file = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/ignore_word_lists/FR_ignore_words.txt';
                        break;
                    case 'german':
                        $ignore_words_file = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/ignore_word_lists/DE_ignore_words.txt';
                        break;
                    case 'russian':
                        $ignore_words_file = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/ignore_word_lists/RU_ignore_words.txt';
                        break;
                    case 'portuguese':
                        $ignore_words_file = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/ignore_word_lists/PT_ignore_words.txt';
                        break;
                    case 'dutch':
                        $ignore_words_file = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/ignore_word_lists/NL_ignore_words.txt';
                        break;
                    default:
                        $ignore_words_file = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/ignore_word_lists/EN_ignore_words.txt';
                        break;
                }

                $words = array();
                if(file_exists($ignore_words_file)){
                    $words = file($ignore_words_file);
                }else{
                    // if there is no word file, skip to the next one
                    continue;
                }
                
                if(empty($words)){
                    $words = array();
                }
                
                foreach($words as $key => $word) {
                    $words[$key] = trim(Wpil_Word::strtolower($word));
                }
                
                $all_ignore_lists[$language_id] = $words;
            }
        }

        return $all_ignore_lists;
    }

    /**
     * Get selected post types
     *
     * @return mixed|void
     */
    public static function getPostTypes()
    {
        return get_option('wpil_2_post_types', ['post', 'page', 'category', 'post_tag']);
    }

    public static function getPostTypesWithoutTerms()
    {
        $types = self::getPostTypes();
        if (($key = array_search('category', $types)) !== false) {
            unset($types[$key]);
        }
        if (($key = array_search('post_tag', $types)) !== false) {
            unset($types[$key]);
        }

        return $types;
    }

    /**
     * Gets the currently supported languages
     * 
     * @return array
     **/
    public static function getSupportedLanguages(){
        $languages = array(
            'english'       => 'English',
            'spanish'       => 'Español',
            'french'        => 'Français',
            'german'        => 'Deutsch',
            'russian'       => 'Русский',
            'portuguese'    => 'Português',
            'dutch'         => 'Dutch',
        );
        
        return $languages;
    }

    /**
     * Gets the currently selected language
     * 
     * @return array
     **/
    public static function getSelectedLanguage(){
        return get_option('wpil_selected_language', 'english');
    }

    public static function getProcessingBatchSize(){
        $batch_size = (int) get_option('wpil_option_suggestion_batch_size', 500);
        if($batch_size < 10){
            $batch_size = 10;
        }
        return $batch_size;
    }

    /**
     * This function is used handle settting page submission
     *
     * @return  void
     */
    public static function save()
    {
        if (isset($_POST['wpil_save_settings_nonce'])
            && wp_verify_nonce($_POST['wpil_save_settings_nonce'], 'wpil_save_settings')
            && isset($_POST['hidden_action'])
            && $_POST['hidden_action'] == 'wpil_save_settings'
        ) {
            //prepare ignore words to save
            $ignore_words = preg_split("/\R/", sanitize_textarea_field(stripslashes(trim($_POST['ignore_words']))));
            $ignore_words = array_unique($ignore_words);
            $ignore_words = array_filter(array_map('trim', $ignore_words));
            sort($ignore_words);
            $ignore_words = implode(PHP_EOL, $ignore_words);

            //update ignore words
            update_option(WPIL_OPTION_IGNORE_WORDS, $ignore_words);
            
            if (empty($_POST[WPIL_OPTION_POST_TYPES]))
            {
                $_POST[WPIL_OPTION_POST_TYPES] = [];
            }

            //save other settings
            $opt_keys = self::$keys;
            foreach($opt_keys as $opt_key) {
                if (array_key_exists($opt_key, $_POST)) {
                    update_option($opt_key, $_POST[$opt_key]);
                }
            }

            wp_redirect(admin_url('admin.php?page=link_whisper_settings&success'));
            exit;
        }
    }

    public static function getSkipSentences()
    {
        return get_option('wpil_skip_sentences') !== false ? get_option('wpil_skip_sentences') : 3;
    }

    /**
     * Check if WPML installed and has at least 2 languages
     *
     * @return bool
     */
    public static function wpml_enabled()
    {
        global $wpdb;

        // if WPML is activated
        if(function_exists('icl_object_id') || class_exists('SitePress')){

            $languages_count = $wpdb->get_var("SELECT count(*) FROM {$wpdb->prefix}icl_languages WHERE active = 1");

            if (!empty($languages_count) && $languages_count > 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get checked term types
     *
     * @return array
     */
    public static function getTermTypes()
    {
        $taxonomies = [];
        if (in_array('category', Wpil_Settings::getPostTypes())) {
            $taxonomies[] = 'category';
        }
        if (in_array('post_tag', Wpil_Settings::getPostTypes())) {
            $taxonomies[] = 'post_tag';
        }

        return $taxonomies;
    }
}
