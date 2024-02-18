<?php

/**
 * Work with post
 */
class Wpil_Post
{
    public static $advanced_custom_fields_list = null;

    /**
     * Register services
     */
    public function register()
    {
        add_filter('wp_insert_post_data', [$this, 'addLinksToContent'], 9999, 2);
        add_action('wp_ajax_wpil_editor_reload', [$this, 'editorReload']);
        add_action('wp_ajax_wpil_is_outbound_links_added', [$this, 'isOutboundLinksAdded']);
        add_action('wp_ajax_wpil_is_inbound_links_added', [$this, 'isInboundLinksAdded']);
        add_action('draft_to_published', [$this, 'updateStatMark']);
        add_action('save_post', [$this, 'updateStatMark']);
        add_action('before_delete_post', [$this, 'deleteReferences']);
        add_action('save_post', [$this, 'addLinkToAdvancedCustomFields'], 9999, 1);
    }

    /**
     * Add links to content before post update
     */
    public static function addLinksToContent($data, $post)
    {
        //get links from DB
        $meta = get_post_meta($post['ID'], 'wpil_links', true);

        if (is_null($data)) {
            $data = get_post($post['ID'], ARRAY_A);
            $data_null = true;
        }

        if (!empty($meta)) {
            //update post text
            foreach ($meta as $link) {
                $link['sentence'] = Wpil_Word::removeQuotes($link['sentence']);
                if (!empty($link['custom_sentence'])) {
                    $changed_sentence = $link['custom_sentence'];
                } else {
                    $changed_sentence = self::getSentenceWithAnchor($link);
                }

                if (strpos($data['post_content'], $link['sentence']) === false) {
                    $sentence = addslashes($link['sentence']);
                } else {
                    $sentence = $link['sentence'];
                }

                if (strpos($data['post_content'], $sentence) !== false) {
                    $changed_sentence = self::changeByACF($data['post_content'], $link['sentence'], $changed_sentence);
                    $data['post_content'] = preg_replace('/'.preg_quote($sentence, '/').'/i', $changed_sentence, $data['post_content'], 1);
                }
            }

            if (!empty($data_null)) {
                $update = [
                    'ID' => $post['ID'],
                    'post_content' => $data['post_content']
                ];

                wp_update_post($update);
            }

            Wpil_Editor_Beaver::addLinks($meta, $post['ID']);
            Wpil_Editor_Elementor::addLinks($meta, $post['ID']);
            Wpil_Editor_Thrive::addLinks($meta, $post['ID']);
            Wpil_Editor_Origin::addLinks($meta, $post['ID']);

            if(WPIL_STATUS_LINK_TABLE_EXISTS){
                Wpil_Report::update_post_in_link_table($post['ID']);
            }
        }

        //return updated post data
        return $data;
    }

    /**
     * Check if it need to force page reload
     */
    function editorReload(){
        if (!empty($_POST['post_id'])) {
            $meta = get_post_meta((int)$_POST['post_id'], 'wpil_gutenberg_restart', true);
            if (!empty($meta)) {
                delete_post_meta((int)$_POST['post_id'], 'wpil_gutenberg_restart');
                echo 'reload';
            }
        }

        wp_die();
    }

    /**
     * Check if outbound links were added to show dialog box
     */
    function isOutboundLinksAdded(){
        if (!empty($_POST['id']) && !empty($_POST['type'])) {
            if ($_POST['type'] == 'term') {
                $meta = get_term_meta((int)$_POST['id'], 'wpil_is_outbound_links_added', true);
            } else {
                $meta = get_post_meta((int)$_POST['id'], 'wpil_is_outbound_links_added', true);
            }
            if (!empty($meta)) {
                if ($_POST['type'] == 'term') {
                    delete_term_meta((int)$_POST['id'], 'wpil_is_outbound_links_added');
                } else {
                    delete_post_meta((int)$_POST['id'], 'wpil_is_outbound_links_added');
                }
                echo 'success';
            }
        }

        wp_die();
    }

    /**
     * Check if inbound links were added to show dialog box
     */
    function isInboundLinksAdded(){
        if (!empty($_POST['id']) && !empty($_POST['type'])) {
            if ($_POST['type'] == 'term') {
                $meta = get_term_meta((int)$_POST['id'], 'wpil_is_inbound_links_added', true);
            } else {
                $meta = get_post_meta((int)$_POST['id'], 'wpil_is_inbound_links_added', true);
            }
            if (!empty($meta)) {
                if ($_POST['type'] == 'term') {
                    delete_term_meta((int)$_POST['id'], 'wpil_is_inbound_links_added');
                } else {
                    delete_post_meta((int)$_POST['id'], 'wpil_is_inbound_links_added');
                }
                echo 'success';
            }
        }

        wp_die();
    }

    /**
     * Get post links
     *
     * @param $post_id
     * @return array Links data
     */
    public static function getPostLinks($post_id)
    {
        $post_links = get_post_meta($post_id, 'wpil_add_links', true);

        //check if links array is slashed
        if (!is_array($post_links))
        {
            $post_links = json_decode(stripslashes($post_links), true);
        }

        if (empty($post_links))
        {
            $post_links = [];
        }

        //add additional fields to array
        foreach ($post_links as $key => $post_link)
        {
            $post_links[$key]['existing'] = true;
            $post_links[$key]['checked'] = 1;
            $post_links[$key]['sentence_with_anchor'] = self::getSentenceWithAnchor($post_link);
            $post_links[$key]['date_published'] = get_the_date('', $post_link['from_post_id']);

            if (empty($post_link['key'])) {
                $post_links[$key]['key'] = sha1($post_link['sentence'].'#'.$post_link['anchor_rooted'].'#'.$post_link['to_post_id']);
            }
        }

        return $post_links;
    }

    /**
     * Insert links into sentence
     *
     * @param $sentence
     * @param $anchor
     * @param $url
     * @param $to_post_id
     * @return string
     */
    public static function getSentenceWithAnchor($link) {
        //get URL
        preg_match('/<a href="([^\"]+)"[^>]+>(.*)<\/a>/i', $link['sentence_with_anchor'], $matches);
        $url = $matches[1];

        //get anchor from source sentence
        $words = [];
        $word_start = false;
        $word_end = 0;
        preg_match_all('/<span[^>]+>([^<]+)<\/span>/i', $matches[2], $matches);
        if (count($matches[1])) {
            foreach ($matches[1] as $word) {
                if ($word_start === false) {
                    $word_start = stripos($link['sentence'], $word);
                    $word_end = $word_start + strlen($word);
                } else {
                    $word_end = stripos($link['sentence'], $word, $word_end) + strlen($word);
                }

                $words[] = $word;
            }
        }

        //get start position by nearest whitespace
        $start = 0;
        $i = 0;
        while(strpos($link['sentence'], ' ', $start+1) < $word_start && $i < 100) {
            $start = strpos($link['sentence'], ' ', $start+1);
            $next_whitespace = strpos($link['sentence'], ' ', $start+1);
            $tag = strpos($link['sentence'], '>', $start +1);
            if ($tag && $tag < $next_whitespace) {
                $start = $tag;
            }
            $tag = strpos($link['sentence'], '(', $start +1);
            if ($tag && $tag < $next_whitespace) {
                $start = $tag;
            }
            $i++;
        }
        if ($start) {
            $start++;
        }

        //get end position by nearest whitespace
        $end = 0;
        $prev_end = 0;
        while($end < $word_end && $end !== false) {
            $prev_end = $end;
            $end = strpos($link['sentence'], ' ', $end + 1);
            $tag = strpos($link['sentence'], ')', $prev_end +1);
            if ($tag && $tag < $end) {
                $end = $tag;
            }
        }

        if (substr($link['sentence'], $end-1, 1) == ',') {
            $end -= 1;
        }

        if ($end === false) {
            $end = strlen($link['sentence']);
        }

        $anchor = substr($link['sentence'], $start, $end - $start);

        //add target blank if needed
        $blank = '';
        if ((int)get_option('wpil_2_links_open_new_tab', 0) == 1) {
            $blank = ' target="_blank" rel="noopener" ';
        }

        //add slashes to the anchor if it doesn't found in the sentence
        if (stripos(addslashes($link['sentence']), $anchor) === false) {
            $anchor = addslashes($anchor);
        }

        $anchor2 = str_replace('$', '\\$', $anchor);

        //add link to sentence
        $sentence = preg_replace('/'.preg_quote($anchor, '/').'/i', '<a href="'.$url.'" ' . $blank . '>'.$anchor2.'</a>', addslashes($link['sentence']), 1);

        $sentence = str_replace('$', '\\$', $sentence);

        return $sentence;
    }

    /**
     * Get post content
     *
     * @param $post_id integer
     * @return string
     */
    public static function getPostContent($post_id)
    {
        $post = get_post($post_id);

        return !empty($post->post_content) ? $post->post_content : '';
    }

    /**
     * Move links from post meta to post content
     */
    public static function cronLinks()
    {
        global $wpdb;

        $posts = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}postmeta WHERE meta_key = 'wpil_add_links' ORDER BY meta_id ASC limit 1");
        if (count($posts)) {
            $post = $posts[0];

            update_post_meta($post->post_id, 'wpil_links', unserialize($post->meta_value));
            delete_post_meta($post->post_id, 'wpil_add_links');

            self::addLinksToContent(null, ['ID' => $post->post_id]);

            file_put_contents(WP_INTERNAL_LINKING_PLUGIN_DIR . 'links.log', date('d.m.Y H:i') . "\n" . $post->meta_value . "\n\n", FILE_APPEND);
        }
    }

    /**
     * Set mark for post to update report
     *
     * @param $post_id
     */
    public static function updateStatMark($post_id)
    {   
        // don't save links for revisions
        if(wp_is_post_revision($post_id)){
            return;
        }

        // clear the meta flag
        update_post_meta($post_id, 'wpil_sync_report3', 0);

        if (get_option('wpil_option_update_reporting_data_on_save', false)) {
            Wpil_Report::fillMeta();
            if(WPIL_STATUS_LINK_TABLE_EXISTS){
                Wpil_Report::remove_post_from_link_table(new Wpil_Model_Post($post_id));
                Wpil_Report::fillWpilLinkTable();
            }
            Wpil_Report::refreshAllStat();
        }else{
            if(WPIL_STATUS_LINK_TABLE_EXISTS){
                $post = new Wpil_Model_Post($post_id);
                // if the current post has the Thrive builder active, load the Thrive content
                $thrive_active = get_post_meta($post->id, 'tcb_editor_enabled', true);
                if(!empty($thrive_active)){
                    $thrive_content = get_post_meta($post->id, 'tve_updated_post', true);
                    if($thrive_content){
                        $post->setContent($thrive_content);
                    }
                }
                // update the links stored in the link table
                Wpil_Report::update_post_in_link_table($post);
                // update the meta data for the post
                Wpil_Report::statUpdate($post, true);
                // and update the link counts for the posts that this one links to
                Wpil_Report::updateReportInternallyLinkedPosts($post);
            }
        }
    }

    /**
     * Delete all post meta on post delete
     *
     * @param $post_id
     */
    public static function deleteReferences($post_id)
    {
        foreach (array_merge(Wpil_Report::$meta_keys, ['wpil_sync_report3', 'wpil_sync_report2_time']) as $key) {
            delete_post_meta($post_id, $key);
        }
        if(WPIL_STATUS_LINK_TABLE_EXISTS){
            // remove the current post from the links table and the links that point to it
            Wpil_Report::remove_post_from_link_table(new Wpil_Model_Post($post_id), true);
        }
    }

    /**
     * Get linked post Ids for current post
     *
     * @param $post
     * @return string
     */
    public static function getLinkedPostIDs($post)
    {
        $linked_post_ids = [$post->id];
        $links_inbound = Wpil_Report::getInternalInboundLinks($post);
        foreach ($links_inbound as $link) {
            if (!empty($link->post->id)) {
                $linked_post_ids[] = $link->post->id;
            }
        }

        return implode(',', $linked_post_ids);
    }

    /**
     * Get all Advanced Custom Fields names
     *
     * @return array
     */
    public static function getAdvancedCustomFieldsList()
    {
        if (is_null(self::$advanced_custom_fields_list)) {
            global $wpdb;

            $fields = [];
            $fields_query = $wpdb->get_results("SELECT DISTINCT post_excerpt FROM {$wpdb->prefix}posts WHERE post_name LIKE 'field_%'");
            foreach ($fields_query as $field) {
                if (trim($field->post_excerpt)) {
                    $fields[] = $field->post_excerpt;
                }
            }

            self::$advanced_custom_fields_list = $fields;
        }

        return self::$advanced_custom_fields_list;
    }

    /**
     * Add link to the content in advanced custom fields
     *
     * @param $link
     * @param $post
     */
    public static function addLinkToAdvancedCustomFields($post_id)
    {
        $meta = get_post_meta($post_id, 'wpil_links', true);

        if (!empty($meta)) {
            foreach ($meta as $link) {
                $fields = self::getAdvancedCustomFieldsList();
                if (!empty($fields)) {
                    foreach ($fields as $field) {
                        if ($content = get_post_meta($post_id, $field, true)) {
                            if (strpos($content, $link['sentence']) !== false) {
                                $changed_sentence = self::getSentenceWithAnchor($link);
                                $content = preg_replace('/' . preg_quote($link['sentence'], '/') . '/i', $changed_sentence, $content, 1);
                                update_post_meta($post_id, $field, $content);
                            }
                        }
                    }
                }
            }

            //remove DB record with links
            delete_post_meta($post_id, 'wpil_links');
        }
    }

    /**
     * Get all posts with the same language
     *
     * @param $post_id
     * @return array
     */
    public static function getSameLanguagePosts($post_id)
    {
        global $wpdb;
        $ids = [];

        $language = $wpdb->get_var("SELECT language_code FROM {$wpdb->prefix}icl_translations WHERE element_id = $post_id AND (element_type = 'post_page' OR element_type = 'post_post') ");

        if (!empty($language)) {
            $result = $wpdb->get_results("SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE element_id != $post_id AND language_code = '$language' AND (element_type = 'post_page' OR element_type = 'post_post') ");
            foreach ($result as $row) {
                $ids[] = $row->element_id;
            }
        }

        return $ids;
    }

    public static function getAnchors($post)
    {
        preg_match_all('|<a [^>]+>([^<]+)</a>|i', $post->getContent(), $matches);

        if (!empty($matches[1])) {
            return $matches[1];
        }

        return [];
    }

    /**
     * Get URLs from post content
     *
     * @param $post
     * @return array|mixed
     */
    public static function getUrls($post)
    {
        preg_match_all('#<a\s.*?(?:href=[\'"](.*?)[\'"]).*?>#is', $post->getContent(),$matches);

        if (!empty($matches[1])) {
            return $matches[1];
        }

        return [];
    }

    /**
     * Change sentence if it located inside embedded ACF blocks
     *
     * @param $content
     * @param $sentence
     * @param $changed_sentence
     * @return string
     */
    public static function changeByACF($content, $sentence, $changed_sentence){
        //find all blocks
        $blocks = [];
        $end = 0;
        while(strpos($content, '<!-- wp:acf', $end) !== false) {
            $begin = strpos($content, '<!-- wp:acf', $end);
            $end = strpos($content, '-->', $begin);
            $blocks[] = [$begin, $end];
        }

        //change sentence
        if (!empty($blocks)) {
            $pos = strpos($content, $sentence);
            foreach ($blocks as $block) {
                if ($block[0] < $pos && $block[1] > $pos) {
                    $changed_sentence = str_replace('"', "'", $changed_sentence);
                }
            }
        }

        return $changed_sentence;
    }
}
