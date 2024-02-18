<?php

class Wpil_StemmerLoader{

    public function register(){
        self::load_word_stemmer();
    }
    
    public static function load_word_stemmer(){
        
        $selected_language = Wpil_Settings::getSelectedLanguage();
        $stemmer_file = '';

        switch($selected_language){
            case 'spanish':
                $stemmer_file = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/word_stemmers/ES_Stemmer.php';
                define('WPIL_CURRENT_LANGUAGE_SELECTED' , 'spanish');
                break;
            case 'french':
                $stemmer_file = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/word_stemmers/FR_Stemmer.php';
                define('WPIL_CURRENT_LANGUAGE_SELECTED' , 'french');
                break;
            case 'german':
                $stemmer_file = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/word_stemmers/DE_Stemmer.php';
                define('WPIL_CURRENT_LANGUAGE_SELECTED' , 'german');
                break;
            case 'russian':
                $stemmer_file = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/word_stemmers/RU_Stemmer.php';
                define('WPIL_CURRENT_LANGUAGE_SELECTED' , 'russian');
                break;
            case 'portuguese':
                $stemmer_file = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/word_stemmers/PT_Stemmer.php';
                define('WPIL_CURRENT_LANGUAGE_SELECTED' , 'portuguese');
                break;
            case 'dutch':
                $stemmer_file = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/word_stemmers/NL_Stemmer.php';
                define('WPIL_CURRENT_LANGUAGE_SELECTED' , 'dutch');
                break;
            default:
                $stemmer_file = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/word_stemmers/EN_Stemmer.php';
                define('WPIL_CURRENT_LANGUAGE_SELECTED' , 'english');
                break;
        }

        include_once(WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/word_stemmers/vendor/autoload.php');
        include_once($stemmer_file);
    }
}
?>
