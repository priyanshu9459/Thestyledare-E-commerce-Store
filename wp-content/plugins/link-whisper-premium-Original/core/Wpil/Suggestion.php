<?php

/**
 * Work with suggestions
 */
class Wpil_Suggestion
{
    public static $undeletable = false;

    /**
     * Gets the suggestions for the current post/cat on ajax call.
     * Processes the suggested posts in batches to avoid timeouts on large sites.
     **/
    public static function ajax_get_post_suggestions(){
        $post_id = intval($_POST['post_id']);
        $term_id = intval($_POST['term_id']);
        $key = intval($_POST['key']);
        $user = wp_get_current_user();

        if((empty($post_id) && empty($term_id)) || empty($key) || 999999 > $key || empty($user->ID)){
            wp_send_json(array(
                'error' => array(
                    'title' => __('Data Error', 'wpil'),
                    'text'  => __('There was some data missing while processing the site content, please refresh the page and try again.', 'wpil'),
                )
            ));
        }

        // if the nonce doesn't check out, exit
        if(!wp_verify_nonce($_POST['nonce'], 'wpil_suggestion_nonce_' . $user->ID)){
            wp_send_json(array(
                'error' => array(
                    'title' => __('Data Error', 'wpil'),
                    'text'  => __('There was an error in processing the data, please reload the page and try again.', 'wpil'),
                )
            ));
        }

        if(!empty($term_id)){
            $post = new Wpil_Model_Post($term_id, 'term');
        }else{
            $post = new Wpil_Model_Post($post_id);
        }

        $count = null;
        if(isset($_POST['count'])){
            $count = intval($_POST['count']);
        }

        $batch_size = Wpil_Settings::getProcessingBatchSize();

        if(isset($_POST['type']) && 'outbound_suggestions' === $_POST['type']){
            // get the total number of posts that we'll be going through
            if(!isset($_POST['post_count'])){
                $post_count = self::getPostProcessCount($post);
            }else{
                $post_count = intval($_POST['post_count']);
            }
            
            // get the phrases for this batch of posts
            $phrases = self::getPostSuggestions($post, null, false, null, $count);
            
            if(!empty($phrases)){
                $stored_phrases = get_transient('wpil_post_suggestions_' . $key);
                
                if(empty($stored_phrases)){
                    $stored_phrases = array($phrases);
                }else{
                    $stored_phrases[] = $phrases;
                }
                
                // store the current suggestions in a transient
                set_transient('wpil_post_suggestions_' . $key, $stored_phrases, MINUTE_IN_SECONDS * 10);
                // send back our status
                wp_send_json(array('status' => 'has_suggestions', 'post_count' => $post_count, 'batch_size' => $batch_size));
            }else{
                wp_send_json(array('status' => 'no_suggestions', 'post_count' => $post_count, 'batch_size' => $batch_size));
            }
            
        }elseif(isset($_POST['type']) && 'inbound_suggestions' === $_POST['type']){
            
            $phrases = [];
            $start = microtime(true);
            $memory_break_point = Wpil_Report::get_mem_break_point();
            
            // if the keywords list only contains newline semicolons
            if(isset($_POST['keywords']) && empty(trim(str_replace(';', '', $_POST['keywords'])))){
                // remove the "keywords" index
                unset($_POST['keywords']);
                unset($_REQUEST['keywords']);
            }

            $completed_processing_count = (isset($_POST['completed_processing_count']) && !empty($_POST['completed_processing_count'])) ? (int) $_POST['completed_processing_count'] : 0;

            $keywords = self::getKeywords($post);

            $suggested_post_ids = get_transient('wpil_inbound_suggested_post_ids_' . $key);
            // get all the suggested posts for linking TO this post
            if(empty($suggested_post_ids)){
                $search_keywords = (is_array($keywords)) ? $keywords[0] : $keywords;
                $suggested_posts = self::getInboundSuggestedPosts($search_keywords, Wpil_Post::getLinkedPostIDs($post));
                $suggested_post_ids = array();
                foreach($suggested_posts as $suggested_post){
                    $suggested_post_ids[] = $suggested_post->ID;
                }
                set_transient('wpil_inbound_suggested_post_ids_' . $key, $suggested_post_ids, MINUTE_IN_SECONDS * 10);
            }else{
                // if there are stored ids, re-save the transient to refresh the count down
                set_transient('wpil_inbound_suggested_post_ids_' . $key, $suggested_post_ids, MINUTE_IN_SECONDS * 10);
            }

            $last_post = (isset($_POST['last_post'])) ? (int) $_POST['last_post'] : 0;

            if(isset(array_flip($suggested_post_ids)[$last_post])){
                $post_ids_to_process = array_slice($suggested_post_ids, (array_search($last_post, $suggested_post_ids) + 1), $batch_size);
            }else{
                $post_ids_to_process = array_slice($suggested_post_ids, 0, $batch_size);
            }

            $process_count = 0;
            $current_post = $last_post;
            foreach ($keywords as $keyword) {
                $temp_phrases = [];
                foreach($post_ids_to_process as $post_id) {
                    if (microtime(true) - $start > 60 || ('disabled' !== $memory_break_point && memory_get_usage() > $memory_break_point) ){
                        break;
                    }

                    $links_post = new Wpil_Model_Post($post_id);
                    $current_post = $post_id;

                    //get suggestions for post
                    if (!empty($_REQUEST['keywords'])) {
                        $suggestions = self::getPostSuggestions($links_post, $post, false, $keyword);
                    } else {
                        $suggestions = self::getPostSuggestions($links_post, $post);
                    }

                    //skip if no suggestions
                    if (!empty($suggestions)) {
                        $temp_phrases = array_merge($temp_phrases, $suggestions);
                    }

                    $process_count++;
                }

                if (count($temp_phrases)) {
                    Wpil_Phrase::TitleKeywordsCheck($temp_phrases, $keyword);
                    $phrases = array_merge($phrases, $temp_phrases);
                }
            }

            // get the suggestions transient
            $stored_phrases = get_transient('wpil_post_suggestions_' . $key);
            // if there are phrases to save
            if($phrases){
                if(empty($stored_phrases)){
                    $stored_phrases = array($phrases);
                }else{
                    $stored_phrases[] = $phrases;
                }
            }
            // save the suggestion data
            set_transient('wpil_post_suggestions_' . $key, $stored_phrases, MINUTE_IN_SECONDS * 10);

            $processing_status = array( 
                    'status' => 'no_suggestions', 
                    'keywords' => $keywords,
                    'last_post' => $current_post, 
                    'post_count' => count($suggested_post_ids), 
                    'id_count_to_process' => count($post_ids_to_process),
                    'completed' => empty(count($post_ids_to_process)), // has the processing run completed? If it has, then there won't be any posts to process
                    'completed_processing_count' => ($completed_processing_count += $process_count),
                    'batch_size' => $batch_size,
                    'posts_processed' => $process_count,
            );

            if(!empty($phrases)){
                $processing_status['status'] = 'has_suggestions';
            }
            
            wp_send_json($processing_status);

        }else{
            wp_send_json(array(
                'error' => array(
                    'title' => __('Unknown Error', 'wpil'),
                    'text'  => __('The data is incomplete for processing the request, please reload the page and try again.', 'wpil'),
                )
            ));
        }
    }

    /**
     * Updates the link report displays with the suggestion results from ajax_get_post_suggestions.
     **/
    public static function ajax_update_suggestion_display(){
        $post_id = intval($_POST['post_id']);
        $term_id = intval($_POST['term_id']);
        $key = intval($_POST['key']);
        $user = wp_get_current_user();

        // if the processing specifics are missing, exit
        if((empty($post_id) && empty($term_id)) || empty($key) || 999999 > $key || empty($user->ID)){
            wp_send_json(array(
                'error' => array(
                    'title' => __('Data Error', 'wpil'),
                    'text'  => __('There was some data missing while processing the site content, please refresh the page and try again.', 'wpil'),
                )
            ));
        }

        // if the nonce doesn't check out, exit
        if(!wp_verify_nonce($_POST['nonce'], 'wpil_suggestion_nonce_' . $user->ID)){
            wp_send_json(array(
                'error' => array(
                    'title' => __('Data Error', 'wpil'),
                    'text'  => __('There was an error in processing the data, please reload the page and try again.', 'wpil'),
                )
            ));
        }

        if(!empty($term_id)){
            $post = new Wpil_Model_Post($term_id, 'term');
        }else{
            $post = new Wpil_Model_Post($post_id);
        }
        
        if('outbound_suggestions' === $_POST['type']){
            // get the suggestions from the database
            $phrases = get_transient('wpil_post_suggestions_' . $key);
            // merge them all into a suitable array
            $phrases = self::merge_phrase_suggestion_arrays($phrases);

            
            foreach($phrases as $phrase){
                usort($phrase->suggestions, function ($a, $b) {
                    if ($a->post_score == $b->post_score) {
                        return 0;
                    }
                    return ($a->post_score > $b->post_score) ? -1 : 1;
                });
            }

            $used_posts = array($post_id . ($post->type == 'term' ? 'cat' : ''));

            //remove same suggestions on top level
            foreach ($phrases as $key => $phrase) {
                $post_key = ($phrase->suggestions[0]->post->type=='term'?'cat':'') . $phrase->suggestions[0]->post->id;
                if (!empty($target) || !in_array($post_key, $used_posts)) {
                    $used_posts[] = $post_key;
                } else {
                    if (!empty(self::$undeletable)) {
                        $phrase->suggestions[0]->opacity = .5;
                    } else {
                        unset($phrase->suggestions[0]);
                    }

                }

                if (!count($phrase->suggestions)) {
                    unset($phrases[$key]);
                } else {
                    if (!empty(self::$undeletable)) {
                        $i = 1;
                        foreach ($phrase->suggestions as $suggestion) {
                            $i++;
                            if ($i > 10) {
                                $suggestion->opacity = .5;
                            }
                        }
                    } else {
                        $phrase->suggestions = array_slice($phrase->suggestions, 0, 10);
                    }
                }
            }

            if (!empty($phrases)) {
                $phrases = self::deleteWeakPhrases(array_filter($phrases));
                $phrases = self::addAnchors($phrases);
            }
            $same_category = (isset($_POST['same_category']) && !empty($_POST['same_category'])) ? true : false;
            include WP_INTERNAL_LINKING_PLUGIN_DIR . '/templates/linking_data_list_v2.php';
            // clear the transient now that we're done with it
            delete_transient('wpil_post_suggestions_' . $key);
        }elseif('inbound_suggestions' === $_POST['type']){
            $phrases = get_transient('wpil_post_suggestions_' . $key);
            $phrases = self::merge_phrase_suggestion_arrays($phrases, true);
            //add links to phrases
            Wpil_Phrase::InboundSort($phrases);
            $phrases = self::addAnchors($phrases);
            $groups = self::getInboundGroups($phrases);
            $same_category = (isset($_POST['same_category']) && !empty($_POST['same_category'])) ? true : false;
            include WP_INTERNAL_LINKING_PLUGIN_DIR . '/templates/inbound_suggestions_page_container.php';
            delete_transient('wpil_post_suggestions_' . $key);
        }

        exit;
    }

    /**
     * Merges multiple arrays of phrase data into a single array suitable for displaying.
     **/
    public static function merge_phrase_suggestion_arrays($phrase_array = array(), $inbound_suggestions = false){
        
        if(empty($phrase_array)){
            return array();
        }
        
        $merged_phrases = array();
        if(true === $inbound_suggestions){ // a simpler process is used for the inbound suggestions
            foreach($phrase_array as $phrase_batch){
                $unserialized_batch = maybe_unserialize($phrase_batch);
                if(!empty($unserialized_batch)){
                    $merged_phrases = array_merge($merged_phrases, $unserialized_batch);
                }
            }
        }else{
            foreach($phrase_array as $phrase_batch){
                $unserialized_batch = maybe_unserialize($phrase_batch);
                if(is_array($unserialized_batch) && !empty($unserialized_batch)){
                    foreach($unserialized_batch as $phrase_key => $phrase_obj){
                        if(!isset($merged_phrases[$phrase_key])){
                            $merged_phrases[$phrase_key] = $phrase_obj;
                        }else{
                            foreach($phrase_obj->suggestions as $post_id => $suggestion){
                                $merged_phrases[$phrase_key]->suggestions[$post_id] = $suggestion;
                            }
                        }
                    }
                }
            }
        }

        return $merged_phrases;
    }

    public static function getPostProcessCount($post){
        global $wpdb;
        //add all posts to array
        $post_count = 0;
        $exclude = self::getTitleQueryExclude($post);
        $post_types = implode("','", Wpil_Settings::getPostTypes());
        $results = $wpdb->get_results("SELECT COUNT('ID') AS `COUNT` FROM {$wpdb->prefix}posts WHERE 1=1 $exclude AND post_type IN ('{$post_types}') AND post_status = 'publish'");
        $post_count = $results[0]->COUNT;

        $taxonomies = Wpil_Settings::getTermTypes();
        if (!empty($taxonomies)) {
            //add all categories to array
            $exclude = "";
            if ($post->type == 'term') {
                $exclude = " AND t.term_id != {$post->id} ";
            }

            $results = $wpdb->get_results("SELECT COUNT(t.term_id)  AS `COUNT` FROM {$wpdb->prefix}term_taxonomy tt LEFT JOIN {$wpdb->prefix}terms t ON tt.term_id = t.term_id WHERE tt.taxonomy IN ('" . implode("', '", $taxonomies) . "') $exclude");
            $post_count += $results[0]->COUNT;
        }    
        
        return $post_count;
    }

    /**
     * Get link suggestions for the post
     *
     * @param $post_id
     * @param $ui
     * @param null $target_post_id
     * @return array|mixed
     */
    public static function getPostSuggestions($post, $target = null, $all = false, $keyword = null, $count = null)
    {
        global $wpdb;
        $ignored_words = Wpil_Settings::getIgnoreWords();
        $start = microtime(true);

        if ($target) {
            if(WPIL_STATUS_LINK_TABLE_EXISTS){
                $internal_links = Wpil_Report::getReportInternalInboundLinks($target);
            }else{
                $internal_links = Wpil_Report::getInternalInboundLinks($target);
            }
            
        } else {
            $internal_links = Wpil_Report::getOutboundLinks($post);
            $internal_links = $internal_links['internal'];
        }

        $used_posts = [];
        foreach ($internal_links as $link) {
            $used_posts[] = ($link->post->type == 'term' ? 'cat' : '') . $link->post->id;
        }

        //get all possible words from post titles
        $words_to_posts = self::getTitleWords($post, $target, $keyword, $count);

        //get all posts with same category
        $result = $wpdb->get_results("SELECT object_id FROM {$wpdb->prefix}term_relationships where term_taxonomy_id in (SELECT r.term_taxonomy_id FROM {$wpdb->prefix}term_relationships r inner join {$wpdb->prefix}term_taxonomy t on t.term_taxonomy_id = r.term_taxonomy_id where r.object_id = {$post->id} and t.taxonomy = 'category')");
        $category_posts = [];
        foreach ($result as $cat) {
            $category_posts[] = $cat->object_id;
        }

        $phrases = self::getPhrases($post->getContent());

        //divide text to phrases
        foreach ($phrases as $key_phrase => $phrase) {
            //get array of unique sentence words cleared from ignore phrases
            if (!empty($_REQUEST['keywords'])) {
                $sentence = trim(preg_replace('/\s+/', ' ', $phrase->text));
                $words_uniq = array_unique(Wpil_Word::getWords($sentence));
            } else {
                $words_uniq = array_unique(Wpil_Word::cleanFromIgnorePhrases($phrase->text));
            }

            $suggestions = [];
            foreach ($words_uniq as $word) {
                if (empty($_REQUEST['keywords']) && in_array($word, $ignored_words)) {
                    continue;
                }

                $word = str_replace(['.', '!', '?'], '', $word);
                $word = Wpil_Stemmer::Stem(Wpil_Word::strtolower($word));

                //skip word if no one post title has this word
                if (empty($words_to_posts[$word])) {
                    continue;
                }

                //create array with all possible posts for current word
                foreach ($words_to_posts[$word] as $p) {
                    if ($p->type == 'term') {
                        $key = 'cat' . $p->id;
                    } else  {
                        $key = $p->id;
                    }

                    if (in_array($key, $used_posts)) {
                        continue;
                    }

                    //create new suggestion
                    if (empty($suggestions[$key])) {
                        //check if post have same category with main post
                        $same_category = false;
                        if ($p->type == 'post' && in_array($p->id, $category_posts)) {
                            $same_category = true;
                        }

                        if (!is_null($target)) {
                            $suggestion_post = $post;
                        } else {
                            $suggestion_post = $p;
                        }

                        // unset the suggestions post content if it's set
                        if(isset($suggestion_post->content)){
                            $suggestion_post->content = null;
                        }
                
                        $suggestions[$key] = [
                            'post' => $suggestion_post,
                            'post_score' => $same_category ? .5 : 0,
                            'words' => []
                        ];
                    }

                    //add new word to suggestion
                    if (!in_array($word, $suggestions[$key]['words'])) {
                        $suggestions[$key]['words'][] = $word;
                        $suggestions[$key]['post_score'] += 1;
                    }
                }
            }

            //check if suggestion has at least 2 words and calculate count of close words
            foreach ($suggestions as $key => $suggestion) {
                if ((!empty($_REQUEST['keywords']) && count($suggestion['words']) != count(explode(' ', $keyword)))
                    || (empty($_REQUEST['keywords']) && count($suggestion['words']) < 2)
                ) {
                    unset ($suggestions[$key]);
                    continue;
                }

                sort($suggestion['words']);

                $close_words = self::getMaxCloseWords($suggestion['words'], $suggestion['post']->title);

                if ($close_words > 1) {
                    $suggestion['post_score'] += $close_words;
                }

                //calculate anchor score
                $close_words = self::getMaxCloseWords($suggestion['words'], $phrase->text);
                $suggestion['anchor_score'] = count($suggestion['words']);
                if ($close_words > 1) {
                    $suggestion['anchor_score'] += $close_words * 2;
                }
                $suggestion['total_score'] = $suggestion['anchor_score'] + $suggestion['post_score'];

                $phrase->suggestions[$key] = new Wpil_Model_Suggestion($suggestion);
            }

            if (!count($phrase->suggestions)) {
                unset($phrases[$key_phrase]);
                continue;
            }
            
            if(isset($_POST['type']) && 'inbound_suggestions' === $_POST['type']){
                usort($phrase->suggestions, function ($a, $b) {
                    if ($a->post_score == $b->post_score) {
                        return 0;
                    }
                    return ($a->post_score > $b->post_score) ? -1 : 1;
                });
            }
        }

        if(isset($_POST['type']) && 'inbound_suggestions' === $_POST['type']){
            //remove same suggestions on top level
            foreach ($phrases as $key => $phrase) {
                $post_key = ($phrase->suggestions[0]->post->type=='term'?'cat':'') . $phrase->suggestions[0]->post->id;
                if (!empty($target) || !in_array($post_key, $used_posts)) {
                    $used_posts[] = $post_key;
                } else {
                    if (!empty(self::$undeletable)) {
                        $phrase->suggestions[0]->opacity = .5;
                    } else {
                        unset($phrase->suggestions[0]);
                    }

                }

                if (!count($phrase->suggestions)) {
                    unset($phrases[$key]);
                } else {
                    if (!empty(self::$undeletable)) {
                        $i = 1;
                        foreach ($phrase->suggestions as $suggestion) {
                            $i++;
                            if ($i > 10) {
                                $suggestion->opacity = .5;
                            }
                        }
                    } else {
                        if (!$all) {
                            $phrase->suggestions = array_slice($phrase->suggestions, 0, 10);
                        }else{
                            $phrase->suggestions = array_values($phrase->suggestions);
                        }
                    }
                }
            }

            $phrases = self::deleteWeakPhrases($phrases);
        }

        return $phrases;
    }

    /**
     * Divide text to sentences
     *
     * @param $content
     * @return array
     */
    public static function getPhrases($content)
    {
        //divide text to sentences
        $replace = [
            ['.<', '. ', '. ', '.\\', '!<', '! ', '! ', '!\\', '?<', '? ', '? ', '?\\', '<div', '<br', '<li', '<p'],
            [".\n<", ". \n", ".\n", ".\n\\", "!\n<", "! \n", "!\n", "!\n\\", "?\n<", "? \n", "?\n", "?\n\\", "\n<div", "\n<br", "\n<li", "\n<p"]
        ];
        $content = str_ireplace($replace[0], $replace[1], $content);
        $content = preg_replace('|\.([A-Z]{1})|', ".\n$1", $content);
        $content = preg_replace('|\[[^\]]+\]|i', "\n", $content);

        $list = explode("\n", $content);
        self::removeEmptySentences($list);
        $list = array_slice($list, Wpil_Settings::getSkipSentences());

        $phrases = [''];
        foreach ($list as $item) {
            $item = trim($item);
            if (substr($item, -4) == '</p>') {
                $item = substr($item, 0, -4);
            }
            if (substr($item, -5) == '</li>') {
                $item = substr($item, 0, -5);
            }

            if (substr($item, 0, 3) == '<p>') {
                $item = substr($item, 3);
            } elseif (substr($item, 0, 3) == '<p ') {
                $item = preg_replace('|<p [^>]+>|i', '', $item);
            }

            if (substr($item, 0, 4) == '<li>') {
                $item = substr($item, 4);
            } elseif (substr($item, 0, 4) == '<li ') {
                $item = preg_replace('|<li [^>]+>|i', '', $item);
            }


            if (in_array(substr($item, -1), ['.', ',', '!', '?'])) {
                $item = substr($item, 0, -1);
            }

            $sentence = [
                'src' => $item,
                'text' => strip_tags(htmlspecialchars_decode($item))
            ];

            //search header tags
            if (strpos($item, '<h') !== false || strpos($item, '<a ') !== false || strpos($item, '</a>') !== false) {
                continue;
            }

            $sentence['text'] = trim($sentence['text']);

            //add sentence to array if it has at least 2 words
            if (!empty($sentence['text']) && count(explode(' ', $sentence['text'])) > 1) {
                $phrases = array_merge($phrases, self::getPhrasesFromSentence($sentence));
            }
        }

        unset($phrases[0]);

        return $phrases;
    }

    /**
     * Get phrases from sentence
     */
    public static function getPhrasesFromSentence($sentence)
    {
        $phrases = [];
        $replace = [', ', ': ', '; ', ' – ', ' (', ') ', ' {', '} '];
        $src = $sentence['src'];

        //change divided symbols inside tags to special codes
        preg_match_all('|<[^>]+>|', $src, $matches);
        if (!empty($matches[0])) {
            foreach ($matches[0] as $tag) {
                $tag_replaced = $tag;
                foreach ($replace as $key => $value) {
                    if (strpos($tag, $value) !== false) {
                        $tag_replaced = str_replace($value, "[rp$key]", $tag_replaced);
                    }
                }

                if ($tag_replaced != $tag) {
                    $src = str_replace($tag, $tag_replaced, $src);
                }
            }
        }

        //divide sentence to phrases
        $src = str_ireplace($replace, "\n", $src);

        //change special codes to divided symbols inside tags
        foreach ($replace as $key => $value) {
            $src = str_replace("[rp$key]", $value, $src);
        }

        $list = explode("\n", $src);

        foreach ($list as $item) {
            $phrase = new Wpil_Model_Phrase([
                'text' => trim(strip_tags(htmlspecialchars_decode($item))),
                'src' => $item,
                'sentence_text' => $sentence['text'],
                'sentence_src' => $sentence['src'],
            ]);

            if (!empty($phrase->text) && count(explode(' ', $phrase->text)) > 1) {
                $phrases[] = $phrase;
            }
        }

        return $phrases;
    }

    /**
     * Collect uniques words from all post titles
     *
     * @param $post_id
     * @param null $target
     * @return array
     */
    public static function getTitleWords($post, $target = null, $keyword = null, $count = null)
    {
        global $wpdb;

        $start = microtime(true);
        $ignore_words = Wpil_Settings::getIgnoreWords();
        $ignore_numbers = get_option(WPIL_OPTION_IGNORE_NUMBERS, 1);

        $posts = [];
        if (!is_null($target)) {
            $posts[] = $target;
        } else {
            //add all posts to array
            $exclude = self::getTitleQueryExclude($post);
            $post_types = implode("','", Wpil_Settings::getPostTypes());
            $limit  = Wpil_Settings::getProcessingBatchSize();
            $offset = intval($count) * $limit;

            //WPML
            $include = "";
            if (Wpil_Settings::wpml_enabled()) {
                $ids = Wpil_Post::getSameLanguagePosts($post->id);

                if (!empty($ids)) {
                    $include = " AND ID IN (" . implode(', ', $ids) . ") ";
                } else {
                    $include = " AND ID IS NULL ";
                }
            }

            $result = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}posts WHERE 1=1 $exclude AND post_type IN ('{$post_types}') AND post_status = 'publish' " . $include . " LIMIT {$limit} OFFSET {$offset}");

            $posts = [];
            foreach ($result as $item) {
                $posts[] = new Wpil_Model_Post($item->ID);
            }

            if (!empty(Wpil_Settings::getTermTypes())) {
                //add all categories to array
                $exclude = "";
                if ($post->type == 'term') {
                    $exclude = " AND t.term_id != {$post->id} ";
                }

                $taxonomies = Wpil_Settings::getTermTypes();
                $result = $wpdb->get_results("SELECT t.term_id, t.name FROM {$wpdb->prefix}term_taxonomy tt LEFT JOIN {$wpdb->prefix}terms t ON tt.term_id = t.term_id WHERE tt.taxonomy IN ('" . implode("', '", $taxonomies) . "') $exclude");
                foreach ($result as $term) {
                    $posts[] = new Wpil_Model_Post($term->term_id, 'term');
                }
            }
        }

        $words = [];
        foreach ($posts as $key => $p) {
            //get unique words from post title
            if (!empty($keyword)) {
                $title_words = array_unique(Wpil_Word::getWords($keyword));
            } else {
                $title_words = array_unique(Wpil_Word::getWords($p->title));
            }

            foreach ($title_words as $word) {
                $word = Wpil_Stemmer::Stem(Wpil_Word::strtolower($word));

                //check if word is not a number and is not in the ignore words list
                if (strlen($word) > 2 &&
                    (!$ignore_numbers || !is_numeric(str_replace(['.', ',', '$'], '', $word))) &&
                    (!empty($_REQUEST['keywords']) || !in_array($word, $ignore_words))
                ) {
                    $words[$word][] = $p;
                }
            }

            if ($key % 100 == 0 && microtime(true) - $start > 10) {
                break;
            }
        }

        return $words;
    }

    /**
     * Get max amount of words in group between sentence
     *
     * @param $words
     * @param $title
     * @return int
     */
    public static function getMaxCloseWords($words_used_in_suggestion, $phrase_text)
    {
        // get the individual words in the source phrase, cleaned of puncuation and spaces
        $phrase_text = Wpil_Word::getWords($phrase_text);

        // stem each word in the phrase text
        foreach ($phrase_text as $key => $value) {
            $phrase_text[$key] = Wpil_Stemmer::Stem(Wpil_Word::strtolower($value));
        }

        // loop over the phrase words, and find the largest grouping of the suggestion's words that occur in sequence in the phrase
        $max = 0;
        $temp_max = 0;
        foreach($phrase_text as $key => $phrase_word){
            if(in_array($phrase_word, $words_used_in_suggestion)){
                $temp_max++;
                if($temp_max > $max){
                    $max = $temp_max;
                }
            }else{
                if($temp_max > $max){
                    $max = $temp_max;
                }
                $temp_max = 0;
            }
        }

        return $max;
    }

    /**
     * Add anchors to sentences
     *
     * @param $sentences
     * @return mixed
     */
    public static function addAnchors($phrases)
    {
        $used_anchors = Wpil_Post::getAnchors(Wpil_Base::getPost());

        $ignored_words = Wpil_Settings::getIgnoreWords();
        foreach ($phrases as $key_phrase => $phrase) {
            //prepare rooted words array from phrase
            $words = trim(preg_replace('/\s+/', ' ', $phrase->text));
            $words = $words_real = explode(' ', $words);
            foreach ($words as $key => $value) {
                $value = str_replace(['[', ']', '(', ')', '{', '}', '.', ',', '!', '?'], '', $value);
                if (!empty($_REQUEST['keywords']) || !in_array($value, $ignored_words)) {
                    $words[$key] = Wpil_Stemmer::Stem(Wpil_Word::strtolower(strip_tags($value)));
                } else {
                    unset($words[$key]);
                }
            }

            foreach ($phrase->suggestions as $suggestion) {
                //get min and max words position in the phrase
                $min = count($words_real);
                $max = 0;
                foreach ($suggestion->words as $word) {
                    if (in_array($word, $words)) {
                        $pos = array_search($word, $words);
                        $min = $pos < $min ? $pos : $min;
                        $max = $pos > $max ? $pos : $max;
                    }
                }

                //get anchors and sentence with anchor
                $anchor = '';
                $sentence_with_anchor = '<span class="wpil_sentence"><span class="wpil_word">' . implode('</span> <span class="wpil_word">', explode(' ', $phrase->sentence_text)) . '</span></span>';
                $sentence_with_anchor = str_replace(['(', ')'], ['</span>(<span class="wpil_word">', '</span>)<span class="wpil_word">'], $sentence_with_anchor);
                $sentence_with_anchor = str_replace(',</span>', '</span>,', $sentence_with_anchor);
                if ($max >= $min) {
                    if ($max == $min) {
                        $anchor = '<span class="wpil_word">' . $words_real[$min] . '</span>';
                        $to = '<a href="%view_link%" target="_blank">' . $anchor . '</a>';
                        $sentence_with_anchor = preg_replace('/'.preg_quote($anchor, '/').'/', $to, $sentence_with_anchor, 1);
                    } else {
                        $anchor = '<span class="wpil_word">' . implode('</span> <span class="wpil_word">', array_slice($words_real, $min, $max - $min + 1)) . '</span>';
                        $from = [
                            '<span class="wpil_word">' . $words_real[$min] . '</span>',
                            '<span class="wpil_word">' . $words_real[$max] . '</span>'
                        ];
                        $to = [
                            '<a href="%view_link%" target="_blank"><span class="wpil_word">' . $words_real[$min] . '</span>',
                            '<span class="wpil_word">' . $words_real[$max] . '</span></a>'
                        ];
                        $sentence_with_anchor = preg_replace('/'.preg_quote($from[0], '/').'/', $to[0], $sentence_with_anchor, 1);
                        $sentence_with_anchor = preg_replace('/'.preg_quote($from[1], '/').'/', $to[1], $sentence_with_anchor, 1);
                    }
                }

                self::setSentenceSrcWithAnchor($suggestion, $phrase->sentence_src, $words_real[$min], $words_real[$max]);

                //add results to suggestion
                $suggestion->anchor = $anchor;

                if (in_array(strip_tags($anchor), $used_anchors)) {
                    unset($phrases[$key_phrase]);
                }

                $suggestion->sentence_with_anchor = $sentence_with_anchor;
            }
        }

        return $phrases;
    }

    /**
     * Add anchor to the sentence source
     *
     * @param $suggestion
     * @param $sentence
     * @param $word_start
     * @param $word_end
     */
    public static function setSentenceSrcWithAnchor(&$suggestion, $sentence, $word_start, $word_end)
    {
        $begin = strpos($sentence, $word_start);
        while($begin && substr($sentence, $begin - 1, 1) !== ' ') {
            $begin--;
        }

        $end = strpos($sentence, $word_end, $begin) + strlen($word_end);
        while($end < strlen($sentence) && substr($sentence, $end, 1) !== ' ') {
            $end++;
        }

        $anchor = substr($sentence, $begin, $end - $begin);
        $replace = '<a href="%view_link%" target="_blank">' . $anchor . '</a>';
        $suggestion->sentence_src_with_anchor = str_replace($anchor, $replace, $sentence);
    }

    /**
     * Get inbound internal links suggestions for the post
     *
     * @param $post
     * @return array|mixed
     */
    public static function getPostInboundSuggestions($post)
    {
        $phrases = [];
        $start = microtime(true);
        $linked_post_ids = Wpil_Post::getLinkedPostIDs($post);
        $keywords = self::getKeywords($post);

        foreach ($keywords as $keyword) {
            $temp_phrases = [];
            foreach(self::getInboundSuggestedPosts($keyword, $linked_post_ids) as $post_for_links) {
                $links_post = new Wpil_Model_Post($post_for_links->ID);

                //get suggestions for post
                if (!empty($_REQUEST['keywords'])) {
                    $suggestions = Wpil_Suggestion::getPostSuggestions($links_post, $post, false,  $keyword);
                } else {
                    $suggestions = Wpil_Suggestion::getPostSuggestions($links_post, $post);
                }

                //skip if no suggestions
                if (!empty($suggestions)) {
                    $temp_phrases = array_merge($temp_phrases, $suggestions);
                }

                if (microtime(true) - $start > 20) {
                    break;
                }
            }

            if (count($temp_phrases)) {
                Wpil_Phrase::TitleKeywordsCheck($temp_phrases, $keyword);
                $phrases = array_merge($phrases, $temp_phrases);
            }
        }

        //add links to phrases
        Wpil_Phrase::InboundSort($phrases);
        $phrases = self::addAnchors($phrases);
        $groups = self::getInboundGroups($phrases);

        return $groups;
    }

    /**
     * Get Inbound internal links page keywords
     *
     * @param $post
     * @return array
     */
    public static function getKeywords($post)
    {
        $keywords = array();
        if(!empty($_POST['keywords'])){
            $keywords = explode(";", sanitize_text_field($_POST['keywords']));
        }elseif (!empty($_GET['keywords'])){
            $keywords = explode(";", sanitize_text_field($_GET['keywords']));
        }
        
        $keywords = array_filter($keywords);
        
        if(empty($keywords)){
            $keywords = array(implode(' ', Wpil_Word::cleanIgnoreWords(explode(' ', Wpil_Word::strtolower($post->title)))));
        }

        return $keywords;
    }

    /**
     * Search posts with common words in the content and return an array of all found post ids
     *
     * @param $keyword
     * @param $exluded_posts
     * @return array
     */
    public static function getInboundSuggestedPosts($keyword, $exluded_posts)
    {
        global $wpdb;

        $post_types = implode("','", Wpil_Settings::getPostTypes());

        $category = '';
        if (!empty($_POST['same_category']) || !empty($_GET['same_category'])) {
            $post = Wpil_Base::getPost();
            if (get_post_type($post->id) == 'post') {
                $categories = wp_get_post_categories($post->id);
                $categories = count($categories) ? implode(',', $categories) : "''";
                $category .= " AND ID in (select object_id from {$wpdb->prefix}term_relationships where term_taxonomy_id in ($categories)) ";
            } elseif (get_post_type($post->id) == 'product') {
                $categories = [];
                $categories_query = $wpdb->get_results("SELECT r.term_taxonomy_id FROM wp_term_relationships r INNER JOIN wp_term_taxonomy t ON r.term_taxonomy_id = t.term_taxonomy_id WHERE r.object_id = {$post->id} and t.taxonomy = 'product_cat'");
                foreach ($categories_query as $cat) {
                    $categories[] = $cat->term_taxonomy_id;
                }
                $categories = count($categories) ? implode(',', $categories) : "''";
                $category .= " AND ID in (select object_id from {$wpdb->prefix}term_relationships where term_taxonomy_id in ($categories)) ";
            }
        }

        //get all posts contains words from post title
        $post_content = self::getInboundPostContent($keyword);

        $included_posts = '';
        $custom_fields = self::getInboundCustomFields($keyword);
        if (!empty($custom_fields)) {
            $posts = self::getInboundCustomFields($keyword);
            $excluded = explode(',', $exluded_posts);
            foreach ($posts as $key => $included_post) {
                if (in_array($included_post, $excluded)) {
                    unset($posts[$key]);
                }
            }

            if (!empty($posts)) {
                $included_posts = " OR ID IN (" . implode(', ', $posts) . ") ";
            }
        }

        //WPML
        $include = "";
        $post = Wpil_Base::getPost();
        if ($post->type == 'post') {
            if (Wpil_Settings::wpml_enabled()) {
                $ids = Wpil_Post::getSameLanguagePosts($post->id);

                if (!empty($ids)) {
                    $include = " AND ID IN (" . implode(', ', $ids) . ") ";
                } else {
                    $include = " AND ID IS NULL ";
                }
            }
        }

        $posts = $wpdb->get_results("SELECT `ID` FROM {$wpdb->prefix}posts WHERE (ID NOT IN ({$exluded_posts}) $category AND post_type IN ('{$post_types}') AND post_status = 'publish' AND ({$post_content})) $included_posts $include ORDER BY ID DESC");

        return $posts;
    }

    public static function getInboundPostContent($keyword)
    {
        //get unique words from post title
        $words = Wpil_Word::getWords($keyword);
        $words = Wpil_Word::cleanIgnoreWords(array_unique($words));
        $words = array_filter($words);

        if (empty($words)) {
            return [];
        }

        $post_content = "post_content LIKE '%" . implode("%' OR post_content LIKE '%", $words) . "%'";

        return $post_content;
    }

    public static function getKeywordsUrl()
    {
        $url = '';
        if (!empty($_POST['keywords'])) {
            $url = '&keywords=' . str_replace("\n", ";", $_POST['keywords']);
        }

        return $url;
    }

    /**
     * Delete phrases with sugeestion point < 3
     *
     * @param $phrases
     * @return array
     */
    public static function deleteWeakPhrases($phrases)
    {
        if (count($phrases) <= 10) {
            return $phrases;
        }

        $three_and_more = 0;
        foreach ($phrases as $phrase) {
            if ($phrase->suggestions[0]->post_score >=3) {
                $three_and_more++;
            }
        }

        if ($three_and_more < 10) {
            foreach ($phrases as $key => $phrase) {
                if ($phrase->suggestions[0]->post_score < 3) {
                    if ($three_and_more < 10) {
                        $three_and_more++;
                    } else {
                        unset($phrases[$key]);
                    }
                }
            }
        } else {
            foreach ($phrases as $key => $phrase) {
                if ($phrase->suggestions[0]->post_score < 3) {
                    unset($phrases[$key]);
                }
            }
        }

        return $phrases;
    }

    /**
     * Get post IDs from inbound custom fields
     *
     * @param $keyword
     * @return array
     */
    public static function getInboundCustomFields($keyword)
    {
        $posts = [];
        $fields = Wpil_Post::getAdvancedCustomFieldsList();

        if (count($fields)) {
            global $wpdb;

            $post_content = str_replace('post_content', 'm.meta_value', self::getInboundPostContent($keyword));
            $fields = "'" . implode("', '", $fields) . "'";
            $posts_query = $wpdb->get_results("SELECT m.post_id FROM {$wpdb->prefix}postmeta m INNER JOIN {$wpdb->prefix}posts p ON m.post_id = p.ID WHERE m.meta_key in ($fields) AND ($post_content) AND p.post_status = 'publish'");
            foreach ($posts_query as $post) {
                $posts[] = $post->post_id;
            }
        }

        return $posts;
    }

    /**
     * Group inbound suggestions by post ID
     *
     * @param $phrases
     * @return array
     */
    public static function getInboundGroups($phrases)
    {
        $groups = [];
        foreach ($phrases as $phrase) {
            $post_id = $phrase->suggestions[0]->post->id;
            $post_score = $phrase->suggestions[0]->post_score;
            if (empty($groups[$post_id])) {
                $groups[$post_id] = [$phrase];
            } else {
                $groups[$post_id][] = $phrase;
            }
        }

        return $groups;
    }

    /**
     * Remove empty sentences from the list
     *
     * @param $sentences
     */
    public static function removeEmptySentences(&$sentences)
    {
        foreach ($sentences as $key => $sentence)
        {
            if (empty(trim(strip_tags($sentence)))) {
                unset($sentences[$key]);
            }
        }
    }

    /**
     * Generate subquery to search posts or products only with same categories
     *
     * @param $post
     * @return string
     */
    public static function getTitleQueryExclude($post)
    {
        global $wpdb;

        $exclude = "";
        if ($post->type == 'post') {
            $exclude .= " AND ID != {$post->id} ";
        }

        if (!empty($_POST['same_category']) || !empty($_GET['same_category'])) {
            if (get_post_type($post->id) == 'post') {
                $categories = wp_get_post_categories($post->id);
                $categories = count($categories) ? implode(',', $categories) : "''";
                $exclude .= " AND ID in (select object_id from {$wpdb->prefix}term_relationships where term_taxonomy_id in ($categories))";
            } elseif (get_post_type($post->id) == 'product') {
                $categories = [];
                $categories_query = $wpdb->get_results("SELECT r.term_taxonomy_id FROM wp_term_relationships r INNER JOIN wp_term_taxonomy t ON r.term_taxonomy_id = t.term_taxonomy_id WHERE r.object_id = {$post->id} and t.taxonomy = 'product_cat'");
                foreach ($categories_query as $category) {
                    $categories[] = $category->term_taxonomy_id;
                }
                $categories = count($categories) ? implode(',', $categories) : "''";
                $exclude .= " AND ID in (select object_id from {$wpdb->prefix}term_relationships where term_taxonomy_id in ($categories))";
            }
        }

        return $exclude;
    }

}
