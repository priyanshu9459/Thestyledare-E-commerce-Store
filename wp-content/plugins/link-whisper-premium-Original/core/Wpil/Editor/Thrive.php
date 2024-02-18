<?php

/**
 * Thrive editor
 *
 * Class Wpil_Editor_Thrive
 */
class Wpil_Editor_Thrive
{
    /**
     * Add links
     *
     * @param $meta
     * @param $post_id
     */
    public static function addLinks($meta, $post_id)
    {
        $thrive = get_post_meta($post_id, 'tve_updated_post', true);

        if (!empty($thrive)) {
            $thrive_before = get_post_meta($post_id, 'tve_content_before_more', true);
            foreach ($meta as $link) {
                $changed_sentence = Wpil_Post::getSentenceWithAnchor($link);
                if (strpos($thrive, $link['sentence']) === false) {
                    $link['sentence'] = addslashes($link['sentence']);
                }
                $thrive_before = preg_replace('/' . preg_quote($link['sentence'], '/') . '/i', $changed_sentence, $thrive_before, 1);
                $thrive = preg_replace('/' . preg_quote($link['sentence'], '/') . '/i', $changed_sentence, $thrive, 1);
            }

            update_post_meta($post_id, 'tve_updated_post', $thrive);
            update_post_meta($post_id, 'tve_content_before_more', $thrive_before);
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
        $thrive = get_post_meta($post_id, 'tve_updated_post', true);

        if (!empty($thrive)) {
            var_dump($thrive);
            $thrive_before = get_post_meta($post_id, 'tve_content_before_more', true);

            preg_match('|<a .+'.$url.'.+>'.$anchor.'</a>|i', $thrive,  $matches);
            if (!empty($matches[0])) {
                $url = addslashes($url);
                $anchor = addslashes($anchor);
            }

            $thrive_before = preg_replace('|<a [^>]+'.$url.'[^>]+>'.$anchor.'</a>|i', $anchor,  $thrive_before);
            $thrive = preg_replace('|<a [^>]+'.$url.'[^>]+>'.$anchor.'</a>|i', $anchor,  $thrive);

            update_post_meta($post_id, 'tve_updated_post', $thrive);
            update_post_meta($post_id, 'tve_content_before_more', $thrive_before);
        }
    }
}