<?php

/**
 * Beaver editor
 *
 * Class Wpil_Editor_Beaver
 */
class Wpil_Editor_Beaver
{
    /**
     * Add links
     *
     * @param $meta
     * @param $post_id
     */
    public static function addLinks($meta, $post_id)
    {
        $beaver = get_post_meta($post_id, '_fl_builder_data', true);

        if (!empty($beaver)) {
            foreach ($meta as $link) {
                //change sentence
                $changed_sentence = Wpil_Post::getSentenceWithAnchor($link);
                $sentence = addslashes(trim($link['sentence']));
                if (substr($sentence, 0, 3) == '<p>' && substr($sentence, -4) == '</p>') {
                    $sentence = substr($sentence, 3, -4);
                }

                //update beaver post content
                foreach ($beaver as $key => $item) {
                    if (!empty($item->settings->text)) {
                        if (strpos($item->settings->text, $sentence) !== false) {
                            $beaver[$key]->settings->text = preg_replace('/' . preg_quote($sentence, '/') . '/i', $changed_sentence, $beaver[$key]->settings->text, 1);
                        }
                    }

                    if (!empty($item->settings->html)) {
                        if (strpos($item->settings->html, $sentence) !== false) {
                            $beaver[$key]->settings->html = preg_replace('/' . preg_quote($sentence, '/') . '/i', $changed_sentence, $beaver[$key]->settings->html, 1);
                        }
                    }
                }
            }

            update_post_meta($post_id, '_fl_builder_data', $beaver);
            update_post_meta($post_id, '_fl_builder_draft', $beaver);
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
        $beaver = get_post_meta($post_id, '_fl_builder_data', true);

        if (!empty($beaver)) {
            foreach ($beaver as $key => $item) {
                if (!empty($item->settings->text)) {
                    preg_match('|<a .+'.$url.'.+>'.$anchor.'</a>|i', $item->settings->text,  $matches);
                    if (!empty($matches[0])) {
                        $beaver[$key]->settings->text = preg_replace('|<a [^>]+'.$url.'[^>]+>'.$anchor.'</a>|i', $anchor,  $beaver[$key]->settings->text);
                    }
                }

                if (!empty($item->settings->html)) {
                    preg_match('|<a .+'.$url.'.+>'.$anchor.'</a>|i', $item->settings->html,  $matches);
                    if (!empty($matches[0])) {
                        $beaver[$key]->settings->html = preg_replace('|<a [^>]+'.$url.'[^>]+>'.$anchor.'</a>|i', $anchor,  $beaver[$key]->settings->html);
                    }
                }
            }

            update_post_meta($post_id, '_fl_builder_data', $beaver);
            update_post_meta($post_id, '_fl_builder_draft', $beaver);
        }
    }
}
