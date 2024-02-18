<?php

/**
 * Work with words and sentences
 */
class Wpil_Word
{
    /**
     * Register services
     */
    public function register()
    {
    }

    /**
     * Clean sentence from ignore phrases and divide to words
     *
     * @param $sentence
     * @return array
     */
    public static function cleanFromIgnorePhrases($sentence)
    {
        $phrases = Wpil_Settings::getIgnorePhrases();
        $sentence = self::clearFromUnicode($sentence);
        $sentence = trim(preg_replace('/\s+/', ' ', $sentence));
        $sentence = str_ireplace($phrases, '', $sentence);

        return explode(' ', $sentence);
    }

    /**
     * Get words from sentence
     *
     * @param $sentence
     * @return array
     */
    public static function getWords($sentence)
    {
        $sentence = self::clearFromUnicode($sentence);
        $words = explode(' ', str_replace(['"', '!', '?', ',', ')','(', '.', '`', "'", ':', ';', '|'], '', $sentence));
        foreach ($words as $key => $word) {
            $word = trim($word);

            if (!empty($word)) {
                $words[$key] = self::strtolower($word);
            }
        }

        return $words;
    }

    /**
     * Clear the sentence of Unicode whitespace symbols
     *
     * @param $sentence
     * @return string
     */
    public static function clearFromUnicode($sentence)
    {   
        $selected_lang = (defined('WPIL_CURRENT_LANGUAGE_SELECTED')) ? WPIL_CURRENT_LANGUAGE_SELECTED : 'english';
        
        if('russian' === $selected_lang){
            // just remove a limited set of chars since Cyrillic chars can be defined with a pair of UTF-8 hex codes.
            // So what is a control char in latin-01, in the Cyrillic char set can be the first hex code in the "Э" char.
            // And removing the "control" hex code breaks the "Э" char.
            $sentence = preg_replace('/[\x00-\x1F\x7F]/', ' ', $sentence);
            return $sentence;
        }elseif('spanish' === $selected_lang){
            $urlEncodedWhiteSpaceChars   = '%81,%7F,%8D,%8F,%C2%90,%C2,%90,%9D,%C2%A0,%A0,%C2%AD,%08,%09,%0A,%0D';
        }elseif('french' === $selected_lang){
            $urlEncodedWhiteSpaceChars   = '%81,%7F,%8D,%8F,%C2%90,%C2,%90,%9D,%C2%A0,%C2%AD,%AD,%08,%09,%0A,%0D';
        }elseif('portuguese' === $selected_lang){
            $urlEncodedWhiteSpaceChars   = '%81,%7F,%8D,%8F,%C2%90,%C2,%90,%9D,%C2%A0,%A0,%C2%AD,%08,%09,%0A,%0D';
        }else{
            $urlEncodedWhiteSpaceChars   = '%81,%7F,%8D,%8F,%C2%90,%C2,%90,%9D,%C2%A0,%A0,%C2%AD,%AD,%08,%09,%0A,%0D';
        }

        $temp = explode(',', $urlEncodedWhiteSpaceChars);
        $sentence  = urlencode($sentence);
        foreach($temp as $v){
            $sentence  =  str_replace($v, ' ', $sentence);
        }
        $sentence = urldecode($sentence);

        return $sentence;
    }

    /**
     * Clean words from ignore words
     *
     * @param $words
     * @return mixed
     */
    public static function cleanIgnoreWords($words)
    {
        $ignore_words = Wpil_Settings::getIgnoreWords();
        $ignore_numbers = get_option(WPIL_OPTION_IGNORE_NUMBERS, 1);

        foreach ($words as $key => $word) {
            if (($ignore_numbers && is_numeric(str_replace(['.', ',', '$'], '', $word))) || in_array($word, $ignore_words)) {
                unset($words[$key]);
            }
        }

        return $words;
    }

    /**
     * Divice text to words and Stem them
     *
     * @param $text
     * @return array
     */
    public static function getStemmedWords($text)
    {
        $words = Wpil_Word::cleanFromIgnorePhrases($text);
        $words = array_unique(Wpil_Word::cleanIgnoreWords($words));

        foreach ($words as $key_word => $word) {
            $words[$key_word] = Wpil_Stemmer::Stem($word);
        }

        return $words;
    }
    
    /**
     * A strtolower function for use on languages that are accented, or non latin.
     * 
     * @param string $string (The text to be lowered)
     * @return string (The string that's been put into lower case)
     */
    public static function strtolower($string){
        // if the wamania project is active, use their strtolower function
        if(class_exists('Wamania\\Snowball\\Utf8')){
            return Wamania\Snowball\Utf8::strtolower($string);
        }else{
            return strtolower($string);
        }
    }

    /**
     * Remove quotes in the begin and in the end of sentence
     *
     * @param $sentence
     * @return false|string
     */
    public static function removeQuotes($sentence)
    {
        if (substr($sentence, 0, 1) == '"' || substr($sentence, 0, 1) == "'") {
            $sentence = substr($sentence, 1);
        }

        if (substr($sentence, -1) == '"' || substr($sentence, -1) == "'") {
            $sentence = substr($sentence, 0,  -1);
        }

        return $sentence;
    }
}
