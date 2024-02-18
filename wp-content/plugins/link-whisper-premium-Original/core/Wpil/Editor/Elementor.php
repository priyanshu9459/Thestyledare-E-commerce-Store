<?php

/**
 * Elementor editor
 *
 * Class Wpil_Editor_Elementor
 */
class Wpil_Editor_Elementor
{
    /**
     * Add links
     *
     * @param $meta
     * @param $post_id
     */
    public static function addLinks($meta, $post_id)
    {
        $elementor = get_post_meta($post_id, '_elementor_data', true);

        if (!empty($elementor)) {
            $elementor = str_replace(['\u2013', '\u2019'], ['–', "'"], $elementor);
            $elementor = addslashes($elementor);
            foreach ($meta as $link) {
                $changed_sentence = Wpil_Post::getSentenceWithAnchor($link);
                $changed_sentence = str_replace('"', "'", $changed_sentence);

                for ($i=0; $i<3; $i++) {
                    if (stripos($elementor, $link['sentence']) === false) {
                        $link['sentence'] = str_replace(['"', '/'], ['\"', '\/'], $link['sentence']);
                    }
                }

                if (stripos($elementor, $link['sentence']) === false) {
                    $elementor = self::findSentence($elementor, $link['sentence'], $changed_sentence);
                } else {
                    $elementor = preg_replace('/'.preg_quote($link['sentence'], '/').'/i', $changed_sentence, $elementor, 1);
                }
            }

            update_post_meta($post_id, '_elementor_data', $elementor);
        }
    }

    /**
     * Delete link
     *
     * @param $post_id
     * @param $url
     * @param $anchor
     */
    public static function deleteLink($post_id, $url, $anchor)
    {
        $elementor = get_post_meta($post_id, '_elementor_data', true);

        if (!empty($elementor)) {
            preg_match('|<a .+'.$url.'.+>'.$anchor.'</a>|i', $elementor,  $matches);
            if (!empty($matches[0])) {
                $elementor = preg_replace('|<a [^>]+'.$url.'[^>]+>'.$anchor.'</a>|i', $anchor,  $elementor);
            } else {
                $url = str_replace('/', '\\\\/', $url);
                $anchor = str_replace('/', '\\\\/', $anchor);
                $elementor = preg_replace('|<a [^>]+'.$url.'[^>]+>'.$anchor.'<\\\\/a>|i', $anchor, $elementor);
            }

            update_post_meta($post_id, '_elementor_data', $elementor);
        }
    }

    /**
     * Cut sentence to find entry
     *
     * @param $text
     * @param $sentence
     * @param $changed_sentence
     * @return string|string[]|null
     */
    public static function findSentence($text, $sentence, $changed_sentence)
    {
        $shift = strlen($changed_sentence) - strpos($changed_sentence, '</a>') - 4;
        for ($i = 1; $i < $shift; $i++) {
            if (strpos($text, substr($sentence, 0, -$i))) {
                $sentence = substr($sentence, 0, -$i);
                $changed_sentence = substr($changed_sentence, 0, -$i);
                $text = preg_replace('/'.preg_quote($sentence, '/').'/i', $changed_sentence, $text, 1);
                break;
            }
        }

        return $text;
    }
}