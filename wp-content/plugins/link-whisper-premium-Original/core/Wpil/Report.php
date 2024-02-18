<?php

/**
 * Report controller
 */
class Wpil_Report
{
    static $all_post_ids = array();
    static $all_post_count;
    static $memory_break_point;
    
    public static $meta_keys = [
        'wpil_links_outbound_internal_count',
        'wpil_links_inbound_internal_count',
        'wpil_links_outbound_external_count'
    ];
    /**
     * Register services
     */
    public function register()
    {
        add_action('wp_ajax_reset_report_data', [$this, 'ajax_reset_report_data']);
        add_action('wp_ajax_process_report_data', [$this, 'ajax_process_report_data']);
        add_filter('screen_settings', [ $this, 'showScreenOptions' ], 10, 2);
        add_filter('set-screen-option', [$this, 'saveOptions'], 11, 3);
    }

    /**
     * Reports init function
     */
    public static function init()
    {
        global $wpdb;

        //exit if user role lower than editor
        $user = wp_get_current_user();
        if ($user->roles[0] != 'administrator' && $user->roles[0] != 'editor') {
            exit;
        }

        //activate debug mode if it enabled
        if (get_option(WPIL_OPTION_DEBUG_MODE, false)) {
            error_reporting(E_ALL ^ E_DEPRECATED & ~E_NOTICE ^ E_WARNING);
            ini_set('display_errors', 1);
            ini_set('error_log', WP_INTERNAL_LINKING_PLUGIN_DIR . 'error.log');
            ini_set("memory_limit", "-1");
            ini_set("max_execution_time", 600);

            //set error handler
            set_error_handler([Wpil_Base::class, 'handleError']);
        }

        $type = !empty($_GET['type']) ? $_GET['type'] : '';
        //post links count update page
        if ($type == 'post_links_count_update') {
            self::postLinksCountUpdate();
            return;
        } elseif ($type == 'delete_link') {
            Wpil_Link::delete();
            return;
        }

        switch($type) {
            case 'inbound_suggestions_page':
                self::inboundSuggestionsPage();
                break;
            case 'links':
                $tbl = new Wpil_Table_Report();
                $page = isset($_REQUEST['page']) ? sanitize_text_field($_REQUEST['page']) : 'link_whisper';
                include WP_INTERNAL_LINKING_PLUGIN_DIR . '/templates/link_report_v2.php';
                break;
            case 'domains':
                $table = new Wpil_Table_Domain();
                $table->prepare_items();
                include WP_INTERNAL_LINKING_PLUGIN_DIR . '/templates/report_domains.php';
                break;
            case 'error':
                /*$error_reset_run = get_option('wpil_error_reset_run', 0);
                if ($error_reset_run) {
                    include WP_INTERNAL_LINKING_PLUGIN_DIR . '/templates/error_process_posts.php';
                } else {
                    $table = new Wpil_Table_Error();
                    $table->prepare_items();
                    include WP_INTERNAL_LINKING_PLUGIN_DIR . '/templates/report_error.php';
                }*/
                echo "Under construction!";
                break;
            default:
                $domains = Wpil_Dashboard::getTopDomains();
                $top_domain = !empty($domains[0]->cnt) ? $domains[0]->cnt : 0;
                wp_register_script('wpil_chart_js', WP_INTERNAL_LINKING_PLUGIN_URL . 'js/jquery.jqChart.min.js', array('jquery'), false, false);
                wp_enqueue_script('wpil_chart_js');
                wp_register_style('wpil_chart_css', WP_INTERNAL_LINKING_PLUGIN_URL . 'css/jquery.jqChart.css', $deps=[], false);
                wp_enqueue_style('wpil_chart_css');
                include WP_INTERNAL_LINKING_PLUGIN_DIR . '/templates/report_dashboard.php';
                break;
        }
    }

    /**
     * Resets all the stored link data in both the meta and the LW link table, on ajax call.
     **/
    public static function ajax_reset_report_data(){
        global $wpdb;
        $links_table = $wpdb->prefix . "wpil_report_links";

        // verify the nonce
        $user = wp_get_current_user();
        if(!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], $user->ID . 'wpil_reset_report_data')){
            // output the error message if the nonce doesn't check out
            wp_send_json(array(
                            'error' => array(
                                'title' => __('Data Error', 'wpil'),
                                'text'  => __('There was an error in processing the data, please reload the page and try again.', 'wpil'),
                            )
                        ));
        }

        // validate the data and set the default values
        $status = array(
            'nonce'                     => $_POST['nonce'],
            'loop_count'                => isset($_POST['loop_count'])  ? (int)$_POST['loop_count'] : 0,
            'clear_data'                => (isset($_POST['clear_data']) && 'true' === $_POST['clear_data'])  ? true : false,
            'data_setup_complete'       => false,
            'time' => microtime(true),
        );

        // if we're clearing data
        if(true === $status['clear_data']){
            // create a list of the meta keys we store link data in
            $meta_keys = array( 'wpil_links_outbound_internal_count',
                                'wpil_links_inbound_internal_count',
                                'wpil_links_outbound_external_count',
                                'wpil_links_outbound_internal_count_data',
                                'wpil_links_inbound_internal_count_data',
                                'wpil_links_outbound_external_count_data',
                                'wpil_sync_report3',
                                'wpil_sync_report2_time');
            
            // clear any stored meta data
            foreach($meta_keys as $key) {
                $wpdb->delete($wpdb->prefix.'postmeta', ['meta_key' => $key]);
                $wpdb->delete($wpdb->prefix.'termmeta', ['meta_key' => $key]);
            }
            
            // clear the link table
            self::setupWpilLinkTable();
            
            // check to see that the link table was successfully created
            $table = $wpdb->get_results("SELECT `post_id` FROM {$links_table} LIMIT 1");
            if(!empty($wpdb->last_error)){
                // if there was an error, let the user know about it
                wp_send_json(array(
                    'error' => array(
                        'title' => __('Database Error', 'wpil'),
                        'text'  => sprintf(__('There was an error in creating the links database table. The error message was: %s', 'wpil'), $wpdb->last_error),
                    )
                ));
            }
            
            // set the clear data flag to false now that we're done clearing the data
            $status['clear_data'] = false;
            // signal that the data setup is complete
            $status['data_setup_complete'] = true;
            // get the meta processing screen to show the user on the next leg of processing
            $status['loading_screen'] = self::get_loading_screen('meta-loading-screen');
            // and send back the notice
            wp_send_json($status);
        }

        // if we made it this far without a break, there must have been data missing
        wp_send_json(array(
                'error' => array(
                    'title' => __('Data Error', 'wpil'),
                    'text'  => __('There was some data missing from the reset attempt, please refresh the page and try again.', 'wpil'),
                )
        ));
    }

    /**
     * Inserts the data needed to generate the report in the meta and the link table, on ajax call.
     **/
    public static function ajax_process_report_data(){
        global $wpdb;
        $links_table = $wpdb->prefix . "wpil_report_links";

        // verify the nonce
        $user = wp_get_current_user();
        if(!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], $user->ID . 'wpil_reset_report_data')){
            wp_send_json(array(
                'error' => array(
                    'title' => __('Data Error', 'wpil'),
                    'text'  => __('There was an error in processing the data, please reload the page and try again.', 'wpil'),
                )
            ));
        }

        // validate the data and set the default return values
        $status = array(
            'nonce'                         => $_POST['nonce'],
            'loop_count'                    => isset($_POST['loop_count'])             ? (int)$_POST['loop_count'] : 0,
            'link_posts_to_process_count'   => isset($_POST['link_posts_to_process_count']) ? (int)$_POST['link_posts_to_process_count'] : 0,
            'link_posts_processed'          => isset($_POST['link_posts_processed'])   ? (int)$_POST['link_posts_processed'] : 0,
            'meta_filled'                   => (isset($_POST['meta_filled']) && 'true' === $_POST['meta_filled']) ? true : false,
            'links_filled'                  => (isset($_POST['links_filled']) && 'true' === $_POST['links_filled']) ? true : false,
            'meta_processing_complete'      => false,
            'link_processing_complete'      => false,
            'report_processing_complete'    => false,
            'time' => microtime(true),
        );

        // if the total post count hasn't been obtained yet
        if(0 === $status['link_posts_to_process_count']){
            $status['link_posts_to_process_count'] = self::get_total_post_count();
        }
        
        // if the meta flags haven't been set
        if(false === $status['meta_filled']){
            // tag all posts with a meta flag for processing
            $meta_filled = self::fillMeta();
            // set if the meta is filled out or not
            $status['meta_filled'] = $meta_filled;
            // set if the meta is done being processed
            $status['meta_processing_complete'] = $meta_filled;
            // if the meta is finished processing
            if($meta_filled){
                // get the link processing loading screen
                $status['loading_screen'] = self::get_loading_screen('link-loading-screen');
            }
            // send back the current status data
            wp_send_json($status);
        }

        // if the links in the table haven't been filled
        if(false === $status['links_filled']){
            // check to see if there's already some links processed
            if(0 === $status['link_posts_processed']){
                $processed_count = $wpdb->get_results("SELECT COUNT(DISTINCT {$links_table}.post_id) AS count FROM {$links_table} WHERE 1=1");
                $status['link_posts_processed'] = $processed_count[0]->count;
                // clear any existing stored ids
                delete_transient('wpil_stored_unprocessed_link_ids');
            }
            // begin filling the link table with link references
            $link_processing = self::fillWpilLinkTable();
            // add the number of processed posts to the total count
            $status['link_posts_processed'] += $link_processing['inserted_posts'];
            // say if we're done processing links or not
            $status['links_filled'] = $link_processing['completed'];
            // and signal if the pre processing is complete
            $status['link_processing_complete'] = $link_processing['completed'];
            // if the links have all been processed
            if($link_processing['completed']){
                // get the post processing loading screen
                $status['loading_screen'] = self::get_loading_screen('post-loading-screen');
            }
            // send back the current status data
            wp_send_json($status);
        }

        // refresh the posts inbound/outbound link stats
        $refresh = self::refreshAllStat(true);

        // note how many posts have been refreshed
        $status['link_posts_processed'] = $refresh['loaded'];
        // and if we're done yet
        $status['processing_complete']  = $refresh['finished'];

        wp_send_json($status);
    }

    /**
     * Refresh posts statistics
     *
     * @return array
     */
    public static function refreshAllStat($report_building = false)
    {
        global $wpdb;
        $post_table  = $wpdb->prefix . "posts";
        $meta_table  = $wpdb->prefix . "postmeta";
        $post_types = Wpil_Settings::getPostTypesWithoutTerms();
        $process_terms = !empty(Wpil_Settings::getTermTypes());

        //get all posts count
        $all = self::get_total_post_count();

        $args = array();
        $post_type_replace_string = '';
        $type_count = (count($post_types) - 1);
        foreach($post_types as $key => $post_type){
            if(empty($post_type_replace_string)){
                $post_type_replace_string = ' AND ' . $post_table . '.post_type IN (';
            }

            $args[] = $post_type;
            if($key < $type_count){
                $post_type_replace_string .= '%s, ';
            }else{
                $post_type_replace_string .= '%s)';
            }
        }

        $updated = 0;
        if($post_types){
            // get the total number of posts that have been updated
            $updated += $wpdb->get_results($wpdb->prepare("SELECT COUNT({$post_table}.ID) AS 'posts_found' FROM {$post_table} LEFT JOIN {$meta_table} ON ({$post_table}.ID = {$meta_table}.post_id ) WHERE 1=1 AND ( {$meta_table}.meta_key = 'wpil_sync_report3' AND {$meta_table}.meta_value = 1 ) {$post_type_replace_string} AND (({$post_table}.post_status = 'publish'))", $args))[0]->posts_found;
        }
        // if categories are a selected type
        if($process_terms){
            // add the total number of categories that have been updated
            $updated += $wpdb->get_results("SELECT COUNT(`term_id`) AS 'cats_found' FROM {$wpdb->prefix}termmeta WHERE meta_key = 'wpil_sync_report3' AND meta_value = '1'")[0]->cats_found;
        }
        // and subtract them from the total post count to get the number that have yet to be updated
        $not_updated_count = ($all - $updated);
        
        // get the post processing limit and add it to the query variables
        $limit = (Wpil_Settings::getProcessingBatchSize()/10);
        $args[] = $limit;

        $start = microtime(true);
        $time_limit = ($report_building) ? 20: 5;
        $memory_break_point = self::get_mem_break_point();
        $processed_link_count = 0;
        while(true){
            // get the posts that haven't been updated, subject to the proccessing limit
            $posts_not_updated = $wpdb->get_results($wpdb->prepare("SELECT {$post_table}.ID FROM {$post_table} LEFT JOIN {$meta_table} ON ({$post_table}.ID = {$meta_table}.post_id AND {$meta_table}.meta_key = 'wpil_sync_report3' ) WHERE 1=1 AND ( {$meta_table}.meta_value != 1 ) {$post_type_replace_string} AND (({$post_table}.post_status = 'publish')) GROUP BY {$post_table}.ID ORDER BY {$post_table}.post_date DESC LIMIT %d", $args));
            
            if($process_terms){
                $terms_not_updated = $wpdb->get_results("SELECT `term_id` FROM {$wpdb->prefix}termmeta WHERE meta_key = 'wpil_sync_report3' AND meta_value = '0'");
            }else{
                $terms_not_updated = 0;
            }

            // break if there's no posts/cats to update, or the loop is out of time.
            if( (empty($posts_not_updated) && empty($terms_not_updated)) || microtime(true) - $start > $time_limit){
                break;
            }

            if(!empty($terms_not_updated)){
                $not_updated_count += count($terms_not_updated);
            }

            //update posts statistics
            if (!empty($posts_not_updated)) {
                foreach($posts_not_updated as $post){
                    if (microtime(true) - $start > $time_limit) {
                        break;
                    }
                    
                    // if there is a memory limit and we've passed the safe limit
                    if('disabled' !== $memory_break_point && memory_get_usage() > $memory_break_point){
                        // update the last updated date
                        update_option('wpil_2_report_last_updated', date('c'));
                        // exit this loop and the WHILE loop that wraps it
                        break 2;
                    }
                    
                    $post_obj = new Wpil_Model_Post($post->ID);
                    self::statUpdate($post_obj, $report_building);
                    $processed_link_count++;
                }
            }

            //update term statistics
            if (!empty($terms_not_updated)) {
                foreach($terms_not_updated as $cat){
                    if (microtime(true) - $start > $time_limit) {
                        break;
                    }

                    // if there is a memory limit and we've passed the safe limit
                    if('disabled' !== $memory_break_point && memory_get_usage() > $memory_break_point){
                        // update the last updated date
                        update_option('wpil_2_report_last_updated', date('c'));
                        // exit this loop and the WHILE loop that wraps it
                        break 2;
                    }

                    $post_obj = new Wpil_Model_Post($cat->term_id, 'term');
                    self::statUpdate($post_obj, $report_building);
                    $processed_link_count++;
                }
            }

            update_option('wpil_2_report_last_updated', date('c'));
        }

        $not_updated_count -= $processed_link_count;

        //create array with results
        $r = ['time'=> microtime(true),
            'success' => true,
            'all' => $all,
            'remained' => ($not_updated_count - $processed_link_count),
            'loaded' => ($all - $not_updated_count),
            'finished' => ($not_updated_count <= 0) ? true : false,
            'processed' => $processed_link_count,
            'w' => $all ? round((($all - $not_updated_count) / $all) * 100) : 100,
        ];
        $r['status'] = "$r[w]%, $r[loaded] / $r[all]";

        return $r;
    }

    /**
     * Create meta records for new posts
     */
    public static function fillMeta()
    {
        global $wpdb;
        $post_table  = $wpdb->prefix . "posts";
        $meta_table  = $wpdb->prefix . "postmeta";
        
        $start = microtime(true);

        $args = array();
        $post_type_replace_string = '';
        $post_types = Wpil_Settings::getPostTypesWithoutTerms();
        $process_terms = !empty(Wpil_Settings::getTermTypes());
        $type_count = (count($post_types) - 1);
        foreach($post_types as $key => $post_type){
            if(empty($post_type_replace_string)){
                $post_type_replace_string = ' AND ' . $post_table . '.post_type IN (';
            }
            
            $args[] = $post_type;
            if($key < $type_count){
                $post_type_replace_string .= '%s, ';
            }else{
                $post_type_replace_string .= '%s)';
            }
        }

        $limit = Wpil_Settings::getProcessingBatchSize();
        $args[] = $limit;
        while(true){
            // select a batch of posts that haven't had their link meta updated yet
            $posts = self::get_untagged_posts();

            if(microtime(true) - $start > 20 || empty($posts)){
                break;
            }

            $count = 0;
            $insert_query = "INSERT INTO {$meta_table} (post_id, meta_key, meta_value) VALUES ";
            $links_data = array ();
            $place_holders = array ();
            foreach ($posts as $post_id) {
                array_push(
                    $links_data, 
                    $post_id,
                    'wpil_sync_report3',
                    '0'
                );
                $place_holders [] = "('%d', '%s', '%s')";

                // if we've hit the limit, stop adding posts to process
                if($count > $limit){
                    break;
                }
                $count++;
            }

            if (count($place_holders) > 0) {
                $insert_query .= implode(', ', $place_holders);
                $insert_query = $wpdb->prepare($insert_query, $links_data);
                $insert_count = $wpdb->query($insert_query);
            }

            if(microtime(true) - $start > 20){
                break;
            }
        }

        // if categories are a selected type
        if($process_terms){
            //create or update meta value for categories
            $taxonomies = Wpil_Settings::getTermTypes();
            $terms = $wpdb->get_results("SELECT term_id FROM {$wpdb->term_taxonomy} WHERE taxonomy IN ('" . implode("', '", $taxonomies) . "')");
            foreach($terms as $term){
                if (!get_term_meta($term->term_id, 'wpil_sync_report3', true)) {
                    update_term_meta($term->term_id, 'wpil_sync_report3', 0);
                }
            }
        }
        
        $meta_filled = empty($posts);
        return $meta_filled;
    }

    /**
     * Update post links stats
     *
     * @param integer $post_id
     * @param bool $processing_for_report (Are we pulling data from the link table, or the meta? TRUE for the link table, FALSE for the meta)
     */
    public static function statUpdate($post, $processing_for_report = false)
    {
        global $wpdb;
        $meta_table = $wpdb->prefix."postmeta";

        //get links
        if($processing_for_report){
            $internal_inbound   = self::getReportInternalInboundLinks($post);
            $outbound_links     = self::getReportOutboundLinks($post);
        }else{
            $internal_inbound   = self::getInternalInboundLinks($post);
            $outbound_links     = self::getOutboundLinks($post);
        }

        if ($post->type == 'term') {
            //update term meta
            update_term_meta($post->id, 'wpil_links_inbound_internal_count', count($internal_inbound));
            update_term_meta($post->id, 'wpil_links_inbound_internal_count_data', $internal_inbound);
            update_term_meta($post->id, 'wpil_links_outbound_internal_count', count($outbound_links['internal']));
            update_term_meta($post->id, 'wpil_links_outbound_internal_count_data', $outbound_links['internal']);
            update_term_meta($post->id, 'wpil_links_outbound_external_count', count($outbound_links['external']));
            update_term_meta($post->id, 'wpil_links_outbound_external_count_data', $outbound_links['external']);
            update_term_meta($post->id, 'wpil_sync_report3', 1);
            update_term_meta($post->id, 'wpil_sync_report2_time', date('c'));
        } else {
            // create our array of meta data
            $assembled_data = array(  
                                'wpil_links_inbound_internal_count'         => count($internal_inbound),
                                'wpil_links_inbound_internal_count_data'    => $internal_inbound,
                                'wpil_links_outbound_internal_count'        => count($outbound_links['internal']),
                                'wpil_links_outbound_internal_count_data'   => $outbound_links['internal'],
                                'wpil_links_outbound_external_count'        => count($outbound_links['external']),
                                'wpil_links_outbound_external_count_data'   => $outbound_links['external'],
                                'wpil_sync_report3'                         => 1,
                                'wpil_sync_report2_time'                    => date('c'));

            // check to see if any meta data already exists
            $search_query = $wpdb->prepare("SELECT `post_id`, `meta_key`, `meta_value` FROM {$meta_table} WHERE post_id = {$post->id} AND (`meta_key` = %s OR `meta_key` = %s OR `meta_key` = %s OR `meta_key` = %s OR `meta_key` = %s OR `meta_key` = %s OR (`meta_key` = %s AND `meta_value` = '1') OR `meta_key` = %s)", array_keys($assembled_data));
            $results = $wpdb->get_results($search_query);

            // if meta data does exist
            if(!empty($results)){
                // go over the meta we want to save
                foreach($assembled_data as $key => $value){
                    // see if there's old meta data for the current post
                    $updated = false;
                    foreach($results as $stored_data){
                        // if there is old meta data for the current post...
                        if($key === $stored_data->meta_key || 'wpil_sync_report3' === $key ){
                            // update the meta
                            $wpdb->update(
                                $meta_table,
                                array('meta_key' => $key, 'meta_value' => maybe_serialize($value)),
                                array('post_id' => $post->id, 'meta_key' => $key)
                            );
                            $updated = true;
                            break;
                        }
                    }
                    // if there isn't old meta data...
                    if(!$updated){
                        // insert the current data
                        $wpdb->insert(
                            $meta_table,
                            array('post_id' => $post->id, 'meta_key' => $key, 'meta_value' => maybe_serialize($value))
                        );
                    }
                }
            }else{
            // if no meta data exists, insert our values
                $meta_table = $wpdb->prefix.'postmeta';
                $insert_query = "INSERT INTO {$meta_table} (post_id, meta_key, meta_value) VALUES ";
                $links_data = array();
                $place_holders = array ();
                foreach($assembled_data as $key => $value){
                    if('wpil_sync_report3' === $key){ // skip the sync flag
                        continue;
                    }
                    
                    array_push (
                        $links_data, 
                        $post->id,
                        $key,
                        maybe_serialize($value)
                    );

                    $place_holders [] = "('%d', '%s', '%s')";		
                }

                if (count($place_holders) > 0) {
                    $insert_query .= implode (', ', $place_holders);		
                    $insert_query = $wpdb->prepare ($insert_query, $links_data);
                    $wpdb->query($insert_query);	
                    $wpdb->update(
                        $meta_table,
                        array('meta_key' => 'wpil_sync_report3', 'meta_value' => 1),
                        array('post_id' => $post->id, 'meta_key' => 'wpil_sync_report3')
                    );	
                }
            }
        }
    }

    public static function getReportInternalInboundLinks($post){
        global $wpdb;
        $links_table = $wpdb->prefix . "wpil_report_links";
        $link_data = array();
        $start = microtime(true);

        //get other internal links
        $url = $post->links->view;
        $cleaned_url = trailingslashit(strtok($url, '?#'));
        $cleaned_url = str_replace(['http://', 'https://'], '://', $cleaned_url);
        $protocol_variant_urls = array( ('https'.$cleaned_url), ('http'.$cleaned_url) );

        // get all the links from the link table that point at this post and are on the current site.
        $results = $wpdb->get_results($string = $wpdb->prepare("SELECT `post_id`, `post_type`, `host`, `anchor` FROM {$links_table} WHERE `clean_url` = '%s' OR `clean_url` = '%s'", $protocol_variant_urls));

        $post_objs = array();
        foreach($results as $data){
            if(empty($data->post_id)){
                continue;
            }
            
            if(!isset($post_objs[$data->post_id])){
                $post_objs[$data->post_id] = new Wpil_Model_Post($data->post_id, $data->post_type);
                $post_objs[$data->post_id]->content = null;
            }

            $link_data[] = new Wpil_Model_Link([
                'url' => $url,
                'host' => $data->host,
                'internal' => true,
                'post' => $post_objs[$data->post_id],
                'anchor' => !empty($data->anchor) ? $data->anchor : '',
            ]);
        }

        return $link_data;
        
    }

    public static function getReportOutboundLinks($post){
        global $wpdb;
        $links_table = $wpdb->prefix . "wpil_report_links";

        //create initial array
        $data = array(
            'internal' => array(),
            'external' => array()
        );

        // query all of the link data that the current post has from the link table
        $links = $wpdb->get_results($wpdb->prepare("SELECT `clean_url`, `raw_url`, `host`, `anchor`, `internal` FROM {$links_table} WHERE `post_id` = '%d' AND `post_type` = %s", array($post->id, $post->type)));

        // create a post obj reference to cut down on the number of post queries
        $post_objs = array(); // keyed to clean_url

        //add links to array from post content
        foreach($links as $link){
            // skip if there's no link
            if(empty($link->clean_url)){
                continue;
            }

            // set up the post variable
            $p = null;

            // if the link is an internal one
            if($link->internal){
                // check to see if we've come across the link before
                if(!isset($post_objs[$link->clean_url])){
                    // if we haven't, get the post/term that the link points at
                    $p = url_to_postid($link->clean_url);
                    if(!$p){
                        $slug = array_filter(explode('/', $link->clean_url));
                        $p = Wpil_Term::getTermBySlug(end($slug));
                        if(!empty($p)){
                            $p = new Wpil_Model_Post($p->term_id, 'term');
                        }
                    }else{
                        $p = new Wpil_Model_Post($p);
                    }

                    // store the post object in an array in case we need it later
                    $post_objs[$link->clean_url] = $p;
                }else{
                    // if the link has been processed previously, set the post obj for the one we stored
                    $p = $post_objs[$link->clean_url];
                }
            }

            $link_obj = new Wpil_Model_Link([
                    'url' => $link->raw_url,
                    'anchor' => $link->anchor,
                    'host' => $link->host,
                    'internal' => ($link->internal) ? true : false,
                    'post' => $p,
            ]);
            
            if ($link->internal) {
                $data['internal'][] = $link_obj;
            } else {
                $data['external'][] = $link_obj;
            }
        }

        return $data;
    }

    /**
     * Collect inbound internal links
     *
     * @param integer $post
     * @return array
     */
    public static function getInternalInboundLinks($post)
    {
        global $wpdb;
        $post_table = $wpdb->prefix . "posts";
        $meta_table = $wpdb->prefix."postmeta";

        $data = [];

        //get other internal links
        $url = $post->links->view;
        $host = parse_url($url, PHP_URL_HOST);

        //get post ids with Thrive enabled
        $thrive_posts = $wpdb->get_results("SELECT DISTINCT post_id FROM {$meta_table} WHERE meta_key = 'tcb_editor_enabled' AND meta_value = '1'");
        $thrive_ids = [];
        foreach ($thrive_posts as $thrive_post) {
            $thrive_ids[] = $thrive_post->post_id;
        }

        $posts = [];

        //create duplicate for HTTP or HTTPS
        $url2 = str_replace(['https://', 'http://'], '://', $url);

        //get content from Thrive posts
        $thrive_ids = !empty($thrive_ids) ? implode(',', $thrive_ids) : "''";
        if (!empty($thrive_ids)) {
            $result = $wpdb->get_results("SELECT * FROM {$meta_table} WHERE post_id IN ({$thrive_ids}) AND meta_key = 'tve_updated_post' AND meta_value LIKE '%{$url2}%' ");
            if ($result) {
                foreach ($result as $thrive) {
                    $posts[] = (new Wpil_Model_Post($thrive->post_id))->setContent($thrive->meta_value);
                }
            }
        }

        //get content from non-Thrive posts
        $result = $wpdb->get_results("SELECT ID FROM {$post_table} WHERE ID NOT IN ({$thrive_ids}) AND post_status = 'publish' AND post_content LIKE '%{$url2}%' ");
        if ($result) {
            foreach ($result as $post) {
                $posts[] = new Wpil_Model_Post($post->ID);
            }
        }

        //get content from categories
        $result = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}term_taxonomy WHERE description LIKE '%{$url2}%' ");
        if ($result) {
            foreach ($result as $term) {
                $posts[] = new Wpil_Model_Post($term->term_id, 'term');
            }
        }

        $posts = array_merge($posts, self::getCustomFieldsInboundLinks($url2));

        //make result array from both post types
        foreach($posts as $p){
            preg_match_all('|<a [^>]+'.$url2.'[\'\"][^>]*>([^<]*)<|i', $p->getContent(), $anchors);
            $p->content = null;

            foreach ($anchors[1] as $key => $anchor) {
                if (empty($anchor) && strpos($anchors[0][$key], 'title=') !== false) {
                    preg_match('/<a\s+(?:[^>]*?\s+)?title=(["\'])(.*?)\1/i', $anchors[0][$key], $title);
                    if (!empty($title[2])) {
                        $anchor = $title[2];
                    }
                }

                $data[] = new Wpil_Model_Link([
                    'url' => $url,
                    'host' => str_replace('www.', '', $host),
                    'internal' => true,
                    'post' => $p,
                    'anchor' => !empty($anchor) ? $anchor : '',
                ]);
            }
        }

        return $data;
    }

    /**
     * Updates the link counts for all posts that the current post is linking to.
     * Link data is from the link table.
     * 
     * @param object $post 
     **/
    public static function updateReportInternallyLinkedPosts($post){
        global $wpdb;
        $links_table = $wpdb->prefix . "wpil_report_links";

        if(empty($post) || !is_object($post)){
            return false;
        }

        // get all the outbound internal links for the current post
        $links = $wpdb->get_results($wpdb->prepare("SELECT `clean_url` FROM {$links_table} WHERE `post_id` = '%d' AND `post_type` = '%s' AND `internal` = 1", array($post->id, $post->type)));

        // exit if there's no links
        if(empty($links)){
            return false;
        }

        // create a list of posts that have already been updated
        $updated = array();

        //add links to array from post content
        foreach($links as $link){
            // skip if there's no link
            if(empty($link->clean_url)){
                continue;
            }

            // set up the post variable
            $p = null;

            // check to see if we've come across the link before
            if(!isset($updated[$link->clean_url])){
                // if we haven't, get the post/term that the link points at
                $p = url_to_postid($link->clean_url);
                if(!$p){
                    $slug = array_filter(explode('/', $link->clean_url));
                    $p = Wpil_Term::getTermBySlug(end($slug));
                    if(!empty($p)){
                        $p = new Wpil_Model_Post($p->term_id, 'term');
                    }
                }else{
                    $p = new Wpil_Model_Post($p);
                }

                // if there is a post/term
                if(null !== $p){
                    // update it's link counts
                    self::statUpdate($p, true);
                }

                // store the post/term url so we don't update the same post multiple times
                $updated[$link->clean_url] = true;
            }
        }

        // if any posts have been updated, return true. Otherwise, false.
        return (!empty($updated)) ? true : false;
    }

    /**
     * Get links from text
     *
     * @param $post
     * @return array
     */
    public static function getContentLinks($post)
    {
        $data = [];
        $my_host = parse_url(get_site_url(), PHP_URL_HOST);
        $post_link = $post->links->view;

        //get all links from content
        preg_match_all('|<a\s[^>]*href=(\"??)([^\" >]*?)\\1[^>]*>(.*)<\/a>|siU', $post->getContent(), $matches);

        //make array with results
        foreach ($matches[0] as $key => $value) {
            if (!empty($matches[2][$key]) && !empty($matches[3][$key]) && !self::isJumpLink($matches[2][$key], $post_link)) {
                $url = $matches[2][$key];
                $host = parse_url($url, PHP_URL_HOST);
                $p = null;

                // if there is no host, but it's not a jump link
                if(empty($host)){
                    // set the host as the current site
                    $host = $my_host;
                    // and update the url
                    $url = (get_site_url() . $url);
                }

                //check if link is internal
                if ($host == $my_host) {
                    $p = url_to_postid($url);
                    if (!$p) {
                        $slug = array_filter(explode('/', $url));
                        $p = Wpil_Term::getTermBySlug(end($slug));
                        if (!empty($p)) {
                            $p = new Wpil_Model_Post($p->term_id, 'term');
                        }
                    } else {
                        $p = new Wpil_Model_Post($p);
                    }
                }

                $internal = false;
                if ($host == $my_host) {
                    $internal = true;
                }

                $anchor = strip_tags($matches[3][$key]);
                if (empty($anchor) && strpos($matches[0][$key], 'title=') !== false) {
                    preg_match('/<a\s+(?:[^>]*?\s+)?title=(["\'])(.*?)\1/i', $matches[0][$key], $title);
                    if (!empty($title[2])) {
                        $anchor = $title[2];
                    }
                }

                $data[] = new Wpil_Model_Link([
                    'url' => $url,
                    'anchor' => $anchor,
                    'host' => str_replace('www.', '', $host),
                    'internal' => $internal,
                    'post' => $p,
                    'added_by_plugin' => false,
                ]);
            }
        }

        return $data;
    }

    public static function isJumpLink($link = '', $post_url){
        $is_jump_link = false;

        // if the first char is a #
        if('#' === substr($link, 0, 1)){
            // this is a jump link
            $is_jump_link = true;
        }elseif(strpos($link, $post_url) !== false){
        // if the link is contained in the post view link, this is a jump link
            $is_jump_link = true;
        }elseif(strpos(strtok($link, '?#'), $post_url) !== false){
        // if the link is in the view link after cleaning it up, this is a jump link
            $is_jump_link = true;
        }else{
            $is_jump_link = false;
        }
        return $is_jump_link;
    }

    /**
     * Get all post outbound links
     *
     * @param $post
     * @return array
     */
    public static function getOutboundLinks($post)
    {
        $my_host = parse_url(get_site_url(), PHP_URL_HOST);

        //create initial array
        $data = [
            'internal' => [],
            'external' => []
        ];

        //add links to array from post content
        foreach (self::getContentLinks($post) as $link) {
            if ($link->internal) {
                $data['internal'][] = $link;
            } else {
                $data['external'][] = $link;
            }
        }

        if ($post->type == 'post') {
            //add links to array from links added by plugin
            $links = get_post_meta($post->id, 'wpil_add_links', true);
            if (!empty($links)) {
                $ids = [];
                foreach ($links as $link) {
                    if (!in_array($link['to_post_id'], $ids)) {
                        $host = parse_url($link['url'], PHP_URL_HOST);
                        $p = new Wpil_Model_Post($link['to_post_id']);

                        $data['internal'][] = new Wpil_Model_Link([
                            'url' => $link['url'],
                            'host' => str_replace('www.', '', $host),
                            'internal' => true,
                            'post' => $p,
                            'anchor' => !empty($link['custom_anchor'])? $link['custom_anchor'] : $link['anchor_rooted'],
                            'added_by_plugin' => true
                        ]);

                        $ids[] = $link['to_post_id'];
                    }
                }
            }
        }

        return $data;
    }

    /**
     * Show inbound suggestions page
     */
    public static function inboundSuggestionsPage()
    {
        //prepage variables for template
        $return_url = !empty($_GET['ret_url']) ? base64_decode($_GET['ret_url']) : admin_url('admin.php?page=link_whisper');

        $post = Wpil_Base::getPost();

        $message_success = !empty($_GET['message_success']) ? $_GET['message_success'] : '';
        $message_error = !empty($_GET['message_error']) ? $_GET['message_error'] : '';
        $page = isset($_REQUEST['page']) ? $_REQUEST['page'] : 1;

        include WP_INTERNAL_LINKING_PLUGIN_DIR . '/templates/inbound_suggestions_page.php';
    }

    /**
     * Show post links count update page
     */
    public static function postLinksCountUpdate()
    {
        //prepare variables
        $post = Wpil_Base::getPost();

        $start = microtime(true);

        if(WPIL_STATUS_LINK_TABLE_EXISTS){
            self::update_post_in_link_table($post);
        }
        self::statUpdate($post);

        $u = admin_url("admin.php?page=link_whisper");
        
        if ($post->type == 'term') {
            $prev_t = get_term_meta($post->id, 'wpil_sync_report2_time', true);

            $prev_count = [
                'inbound_internal' => (int)get_term_meta($post->id, 'wpil_links_inbound_internal_count', true),
                'outbound_internal' => (int)get_term_meta($post->id, 'wpil_links_outbound_internal_count', true),
                'outbound_external' => (int)get_term_meta($post->id, 'wpil_links_outbound_external_count', true)
            ];

            $time = microtime(true) - $start;
            $new_time = get_term_meta($post->id, 'wpil_sync_report2_time', true);

            $count = [
                'inbound_internal' => (int)get_term_meta($post->id, 'wpil_links_inbound_internal_count', true),
                'outbound_internal' => (int)get_term_meta($post->id, 'wpil_links_outbound_internal_count', true),
                'outbound_external' => (int)get_term_meta($post->id, 'wpil_links_outbound_external_count', true)
            ];

            $links_data = [
                'inbound_internal' => get_term_meta($post->id, 'wpil_links_inbound_internal_count_data', true),
                'outbound_internal' => get_term_meta($post->id, 'wpil_links_outbound_internal_count_data', true),
                'outbound_external' => get_term_meta($post->id, 'wpil_links_outbound_external_count_data', true)
            ];
        } else {
            $prev_t = get_post_meta($post->id, 'wpil_sync_report2_time', true);

            $prev_count = [
                'inbound_internal' => (int)get_post_meta($post->id, 'wpil_links_inbound_internal_count', true),
                'outbound_internal' => (int)get_post_meta($post->id, 'wpil_links_outbound_internal_count', true),
                'outbound_external' => (int)get_post_meta($post->id, 'wpil_links_outbound_external_count', true)
            ];

            $time = microtime(true) - $start;
            $new_time = get_post_meta($post->id, 'wpil_sync_report2_time', true);

            $count = [
                'inbound_internal' => (int)get_post_meta($post->id, 'wpil_links_inbound_internal_count', true),
                'outbound_internal' => (int)get_post_meta($post->id, 'wpil_links_outbound_internal_count', true),
                'outbound_external' => (int)get_post_meta($post->id, 'wpil_links_outbound_external_count', true)
            ];

            $links_data = [
                'inbound_internal' => get_post_meta($post->id, 'wpil_links_inbound_internal_count_data', true),
                'outbound_internal' => get_post_meta($post->id, 'wpil_links_outbound_internal_count_data', true),
                'outbound_external' => get_post_meta($post->id, 'wpil_links_outbound_external_count_data', true)
            ];    
        }
        

        include dirname(__DIR__).'/../templates/post_links_count_update.php';
    }

    /**
     * Cron job for calculation prepared reports (one in minute)
     */
    public static function cron()
    {
        //get non updated posts
        $query_not_updated = new WP_Query([
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'post_type' => get_option('wpil_2_post_types', ['post', 'page']),
            'meta_query' => [
                [
                    'key' => 'wpil_sync_report3',
                    'compare' => '!=',
                    'value' => 1
                ]
            ]
        ]);

        //update stats if prepared report exists
        if ($query_not_updated->found_posts) {
            $post = new Wpil_Model_Post($query_not_updated->posts[0]->ID);
            // if the current post has the Thrive builder active, load the Thrive content
            $thrive_active = get_post_meta($post->id, 'tcb_editor_enabled', true);
            if(!empty($thrive_active)){
                $thrive_content = get_post_meta($post->id, 'tve_updated_post', true);
                if($thrive_content){
                    $post->setContent($thrive_content);
                }
            }
            if(WPIL_STATUS_LINK_TABLE_EXISTS){
                self::insert_links_into_link_table($post);
            }
            self::statUpdate($post);
        }
    }

    /**
     * Get report data
     *
     * @param int $start
     * @param string $orderby
     * @param string $order
     * @param string $search
     * @param int $limit
     * @return array
     */
    public static function getData($start = 0, $orderby = '', $order = 'DESC', $search='', $limit=20)
    {
        global $wpdb;

        //check if it need to show categories in the list
        $options = get_user_meta(get_current_user_id(), 'report_options', true);
        $show_categories = (!empty($options['show_categories']) && $options['show_categories'] == 'off') ? false : true;
        $process_terms = !empty(Wpil_Settings::getTermTypes());

        //calculate offset
        $offset = $start > 0 ? (($start - 1) * $limit) : 0;

        $post_types = "'" . implode("','", Wpil_Settings::getPostTypes()) . "'";

        //create search query requests
        $term_search = '';
        $search2 = '';
        if (!empty($search)) {
            if (strpos($search, '/') !== false) {
                $search = array_filter(explode('/', $search));
                $search = end($search);
            }
            $term_search = " AND (t.name LIKE '%$search%' OR tt.description LIKE '%$search%' OR t.slug LIKE '%$search%') ";
            $search2 = " AND (post_title LIKE '%$search%' OR post_content LIKE '%$search%' OR post_name LIKE '%$search%') ";
            $search = " AND (p.post_title LIKE '%$search%' OR p.post_content LIKE '%$search%' OR p.post_name LIKE '%$search%') ";
        }

        if (empty($orderby) || $orderby == 'date') {
            $orderby = 'post_date';
        }

        if ($orderby == 'post_date' || $orderby == 'post_title') {
            //create query for order by title or date
            $query = "SELECT ID, post_title, post_date as `post_date`, 'post' as `type` 
                        FROM {$wpdb->posts}
                        WHERE ID in (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'wpil_sync_report3' AND meta_value = '1') AND post_status = 'publish' AND post_type IN ($post_types) $search2 ";
            if ($show_categories && $process_terms) {
                $taxonomies = Wpil_Settings::getTermTypes();
                $query .= " UNION
                            SELECT tt.term_id as `ID`, t.name as `post_title`, NOW() as `post_date`, 'term' as `type`  
                            FROM {$wpdb->term_taxonomy} tt LEFT JOIN {$wpdb->terms} t ON tt.term_id = t.term_id 
                            WHERE tt.taxonomy IN ('" . implode("', '", $taxonomies) . "') $term_search ";
            }

            $query .= " ORDER BY $orderby $order 
                        LIMIT $offset, $limit";

        } else {
            //create query for other orders
            $query = "SELECT p.ID, p.post_title, p.post_date as `post_date`, m.meta_value, 'post' as `type`  
                        FROM {$wpdb->prefix}posts p RIGHT JOIN {$wpdb->prefix}postmeta m ON p.ID = m.post_id
                        WHERE p.ID in (
                            SELECT post_id FROM {$wpdb->prefix}postmeta WHERE meta_key = 'wpil_sync_report3' AND meta_value = '1'
                        ) AND p.post_status = 'publish' AND p.post_type IN ($post_types) AND m.meta_key LIKE '$orderby' $search";

            if ($show_categories && $process_terms) {
                $query .= " UNION
                            SELECT t.term_id as `ID`, t.name as `post_title`, NOW() as `post_date`, m.meta_value, 'term' as `type`  
                            FROM {$wpdb->prefix}termmeta m INNER JOIN {$wpdb->prefix}terms t ON m.term_id = t.term_id LEFT JOIN {$wpdb->prefix}term_taxonomy tt ON t.term_id = tt.term_id
                            WHERE t.term_id in (
                                SELECT term_id FROM {$wpdb->prefix}termmeta WHERE meta_key = 'wpil_sync_report3' AND meta_value = '1'
                            ) AND m.meta_key LIKE '$orderby' $term_search";
            }

            $query .= "ORDER BY meta_value+0 $order 
                        LIMIT $offset, $limit";
        }

        $result = $wpdb->get_results($query);

        //calculate total count
        $posts_count = $wpdb->get_var("SELECT count(DISTINCT p.ID) 
            FROM {$wpdb->prefix}postmeta m INNER JOIN {$wpdb->prefix}posts p ON m.post_id = p.ID 
            WHERE m.meta_key = 'wpil_sync_report3' AND m.meta_value = '1' AND p.post_status = 'publish' AND p.post_type IN ($post_types) $search");

        $terms_count = $wpdb->get_var("SELECT count(DISTINCT t.term_id) 
            FROM {$wpdb->prefix}termmeta m INNER JOIN {$wpdb->prefix}terms t ON m.term_id = t.term_id LEFT JOIN {$wpdb->prefix}term_taxonomy tt ON t.term_id = tt.term_id 
            WHERE m.meta_key = 'wpil_sync_report3' AND m.meta_value = '1' $term_search");

        $total_items = $posts_count + $terms_count;

        //prepare report data
        $data = [];
        foreach ($result as $key => $post) {
            if ($post->type == 'term') {
                $p = new Wpil_Model_Post($post->ID, 'term');
                $inbound = admin_url("admin.php?term_id={$post->ID}&page=link_whisper&type=inbound_suggestions_page&ret_url=" . base64_encode($_SERVER['REQUEST_URI']));
            } else {
                $p = new Wpil_Model_Post($post->ID);
                $inbound = admin_url("admin.php?post_id={$post->ID}&page=link_whisper&type=inbound_suggestions_page&ret_url=" . base64_encode($_SERVER['REQUEST_URI']));
            }

            $item = [
                'post' => $p,
                'links_inbound_page_url' => $inbound,
                'date' => $post->type == 'post' ? get_the_date('', $post->ID) : 'not set'
            ];

            //get meta data
            if ($post->type == 'term') {
                foreach (self::$meta_keys as $meta_key) {
                    $item[$meta_key] = get_term_meta($post->ID, $meta_key, true);
                }
            } else {
                foreach (self::$meta_keys as $meta_key) {
                    $item[$meta_key] = get_post_meta($post->ID, $meta_key, true);
                }
            }


            $data[$key] = $item;
        }

        return array( 'data' => $data , 'total_items' => $total_items);
    }

    /**
     * Show screen options form
     *
     * @param $status
     * @param $args
     * @return false|string
     */
    public static function showScreenOptions($status, $args)
    {
        //Skip if it is not our screen options
        if ($args->base != Wpil_Base::$report_menu) {
            return $status;
        }

        if (!empty($args->get_option('report_options'))) {
            $options = get_user_meta(get_current_user_id(), 'report_options', true);

            // Check if the screen options have been saved. If so, use the saved value. Otherwise, use the default values.
            if ( $options ) {
                $show_categories = !empty($options['show_categories']) && $options['show_categories'] != 'off';
                $per_page = !empty($options['per_page']) ? $options['per_page'] : 20 ;
            } else {
                $show_categories = true;
                $per_page = 20;
            }

            //get apply button
            $button = get_submit_button( __( 'Apply', 'wp-screen-options-framework' ), 'primary large', 'screen-options-apply', false );

            //show HTML form
            ob_start();
            include WP_INTERNAL_LINKING_PLUGIN_DIR . 'templates/report_options.php';
            return ob_get_clean();
        }

        return '';
    }

    /**
     * Save screen options
     *
     * @param $status
     * @param $option
     * @param $value
     * @return array|mixed
     */
    public static function saveOptions( $status, $option, $value ) {
        if ($option == 'report_options') {
            $value = [];
            if (isset( $_POST['report_options'] ) && is_array( $_POST['report_options'] )) {
                if (!isset($_POST['report_options']['show_categories'])) {
                    $_POST['report_options']['show_categories'] = 'off';
                }
                $value = $_POST['report_options'];
            }

        }

        return $value;
    }

    public static function getCustomFieldsInboundLinks($url)
    {
        $posts = [];

        $fields = Wpil_Post::getAdvancedCustomFieldsList();
        if (count($fields)) {
            global $wpdb;

            $fields = "'" . implode("', '", $fields) . "'";
            $result = $wpdb->get_results("SELECT m.post_id FROM {$wpdb->prefix}postmeta m INNER JOIN {$wpdb->prefix}posts p ON m.post_id = p.ID WHERE m.meta_key in ($fields) AND m.meta_value LIKE '%$url%' AND p.post_status = 'publish'");
            if ($result) {
                foreach ($result as $post) {
                    $posts[] = new Wpil_Model_Post($post->post_id);
                }
            }
        }

        return $posts;
    }

    /**
     * Creates the report links table in the database if it doesn't exist.
     * Clears the link table if it does.
     * Can be set to only create the link table if it doesn't already exist
     * @param bool $only_insert_table
     **/
    public static function setupWpilLinkTable($only_insert_table = false){
        global $wpdb;
        $wpil_links_table = $wpdb->prefix . 'wpil_report_links';

        $wpil_link_table_query = "CREATE TABLE IF NOT EXISTS {$wpil_links_table} (
                                    link_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                    post_id bigint(20) unsigned NOT NULL,
                                    clean_url text,
                                    raw_url text,
                                    host text,
                                    anchor text,
                                    internal tinyint(1) DEFAULT 0,
                                    has_links tinyint(1) NOT NULL DEFAULT 0,
                                    post_type text,
                                    PRIMARY KEY  (link_id),
                                    INDEX (post_id),
                                    INDEX (clean_url(512))
                                )";
        // create DB table if it doesn't exist
        require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($wpil_link_table_query);

        if(self::link_table_is_created()){
            update_option(WPIL_LINK_TABLE_IS_CREATED, true);
        }

        if(!$only_insert_table){
            // and clear any existing data
            $wpdb->query("TRUNCATE TABLE {$wpil_links_table}");
        }
    }

    /**
     * Does a full search of the DB to check for post ids that don't show up in the link table,
     * and then it processes each of those posts to extract the urls from the content to insert in the link table.
     **/
    public static function fillWpilLinkTable(){
        global $wpdb;
        $post_table  = $wpdb->prefix . "posts";
        $meta_table  = $wpdb->prefix . "postmeta";
        $links_table = $wpdb->prefix . "wpil_report_links";
        $count = 0;
        $start = microtime(true);
        $limit = Wpil_Settings::getProcessingBatchSize();
        $memory_break_point = self::get_mem_break_point();



        //get post ids with Thrive enabled
        $thrive_posts = $wpdb->get_results("SELECT DISTINCT {$meta_table}.post_id FROM {$meta_table} WHERE meta_key = 'tcb_editor_enabled' AND meta_value = '1' AND {$meta_table}.post_id != 0");
        $thrive_ids = [];
        foreach ($thrive_posts as $thrive_post) {
            $thrive_ids[] = $thrive_post->post_id;
        }

        // get the ids that haven't been added to the link table yet
        $unprocessed_ids = self::get_all_unprocessed_link_post_ids();
        // if all the posts have been processed
        if(empty($unprocessed_ids)){

            // check to see if categories have been selected for processing
            if(!empty(Wpil_Settings::getTermTypes())){
                // check for categories
                $terms = array();
                $cat_replace_string = '';
                $updated_terms = $wpdb->get_results("SELECT DISTINCT `post_id` FROM {$links_table} WHERE `post_type` = 'term'");
                $term_count = (count($updated_terms) - 1);
                foreach ($updated_terms as $key => $term) {
                    if(empty($term_replace_string)){
                        $term_replace_string = " AND `term_id` NOT IN (";
                    }
                    if($key < $term_count){
                        $term_replace_string .= "%d, ";
                    }else{
                        $term_replace_string .= "%d)";
                    }
                    $terms[] = $term->post_id;
                }
                $terms = $wpdb->get_results($string = $wpdb->prepare("SELECT `term_id` FROM {$wpdb->prefix}term_taxonomy WHERE 1=1 {$term_replace_string}", $terms));

                // if there are categories
                $term_update_count = 0;
                if ($terms) {
                    foreach ($terms as $term) {
                        if((microtime(true) - $start) > 30 || ('disabled' !== $memory_break_point && memory_get_usage() > $memory_break_point)){
                            break;
                        }

                        // insert the term's links into the link table
                        $post = new Wpil_Model_Post($term->term_id, 'term');
                        $term_insert_count = self::insert_links_into_link_table($post);

                        // if the link insert was successful, increase the update count
                        if($term_insert_count > 0){
                            $term_update_count += $term_insert_count;
                        }
                    }
                }

                // if all the found cats have had their links loaded in the database
                if(count($terms) === $term_update_count){
                    // return success
                    return array('completed' => true, 'inserted_posts' => $term_update_count);
                }else{
                    // if not, go around again
                    return array('completed' => false, 'inserted_posts' => $term_update_count);
                }
            }
            
            return array('completed' => true, 'inserted_posts' => 0);
        }

        $updated_unprocessed_ids = array_flip($unprocessed_ids);
        $thrive_ids = array_flip($thrive_ids);
        foreach($unprocessed_ids as $id){
            // exit the loop if we've been at this for 30 seconds or we've passed the memory breakpoint
            if((microtime(true) - $start) > 30 || ('disabled' !== $memory_break_point && memory_get_usage() > $memory_break_point)){
                break; 
            }

            // set up a new post with the current id
            $post = new Wpil_Model_Post($id);

            // if the current post is a Thrive post
            if(isset($thrive_ids[$id])){
                // get the Thrive content from the post meta
                $result = $wpdb->get_results("SELECT `meta_value` FROM {$meta_table} WHERE `post_id` = {$id} AND meta_key = 'tve_updated_post'");
                if(!empty($result)){
                    // and set the post content for the Thrive data
                    $post->setContent($result->meta_value);
                }
            }

            $insert_count = self::insert_links_into_link_table($post);

            if($insert_count > 0){
                $count += $insert_count;
                $updated_unprocessed_ids[$id] = false;
            }
        }
        
        // update the stored list of unprocessed ids
        set_transient('wpil_stored_unprocessed_link_ids', array_values(array_flip(array_filter($updated_unprocessed_ids, 'strlen'))), MINUTE_IN_SECONDS * 5);
        
        return array('completed' => false, 'inserted_posts' => $count);
    }
    
    /**
     * Updates a post's content links by removing the existing link data from the link table and inserting new links from the post content.
     * @param int|object $post 
     * @return bool
     **/
    public static function update_post_in_link_table($post){
        // if we've just been given a post id
        if(is_numeric($post) && !is_object($post)){
            // create a new post object
            $post = new Wpil_Model_Post($post);
        }
        
        $remove = self::remove_post_from_link_table($post);
        $insert = self::insert_links_into_link_table($post);

        return (empty($remove) || empty($insert)) ? false : true;
    }
    
    public static function remove_post_from_link_table($post, $delete_link_refs = false){
        global $wpdb;
        $links_table = $wpdb->prefix . "wpil_report_links";

        // exit if a post id isn't given
        if(empty($post)){
            return 0;
        }

        // delete the rows for this post that are stored in the links table
        $results = $wpdb->delete($links_table, array('post_id' => $post->id, 'post_type' => $post->type));
        $results2 = 0;

        // if we're supposed to remove the links that point to the current post as well
        if($delete_link_refs){
            // get the url
            $url = $post->links->view;
            $cleaned_url = trailingslashit(strtok($url, '?#'));
            // if there is a url
            if(!empty($cleaned_url)){
                // delete the rows that have this post's url in them
                $results2 = $wpdb->delete($links_table, array('clean_url' => $cleaned_url));
            }
        }

        // add together the results of both possible delete operations to get the total rows removed
        return (((int) $results) + ((int) $results2));
    }

    /**
     * Extracts the links from the given post and inserts them into the link table.
     * @param object $post 
     * @return int $count (1 if success, 0 if failure)
     **/
    public static function insert_links_into_link_table($post){
        global $wpdb;
        $links_table = $wpdb->prefix . "wpil_report_links";

        $count = 0;
        $links = self::getContentLinks($post);
        $insert_query = "INSERT INTO {$links_table} (post_id, clean_url, raw_url, host, anchor, internal, has_links, post_type) VALUES ";
        $links_data = array();
        $place_holders = array();
        foreach($links as $link){
            array_push (
                $links_data,
                $post->id,
                trailingslashit(strtok($link->url, '?#')),
                $link->url,
                $link->host,
                $link->anchor,
                $link->internal,
                1,
                $post->type
            );
            
            $place_holders [] = "('%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s')";		
        }

        if (count($place_holders) > 0) {
            $insert_query .= implode (', ', $place_holders);		
            $insert_query = $wpdb->prepare ($insert_query, $links_data);
            $insert = $wpdb->query ($insert_query);

            // if the insert was successful
            if(false !== $insert){
                // increase the insert count
                $count += 1;
            }
        }

        // if there are no links, update the link table with null values to remove it from processing
        if(empty($links)){
            $insert = $wpdb->insert(
                $links_table,
                array(
                    'post_id' => $post->id,
                    'clean_url' => null,
                    'raw_url' => null,
                    'host' => null,
                    'anchor' => null, 
                    'internal' => null, 
                    'has_links' => 0,
                    'post_type' => $post->type
                )
            );

            // if the insert was successful
            if(false !== $insert){
                // increase the insert count
                $count += 1;
            }
        }
        
        return $count;
    }

    /**
     * Gets all post ids from the post table and returns an array of ids.
     * @return array $all_post_ids (an array of all post ids from the post table. Categories aren't included. We're focusing on post ids since they make up the bulk of the ids)
     **/
    public static function get_all_post_ids(){
        global $wpdb;
        if(isset(self::$all_post_ids) && !empty($all_post_ids)){
            $all_post_ids = self::$all_post_ids;
        }else{
            $post_table = $wpdb->prefix . "posts";

            $post_types = Wpil_Settings::getPostTypesWithoutTerms();

            // remove categories from the post types if set
            $process_terms = !empty(Wpil_Settings::getTermTypes());

            $args = array();
            $type_count = (count($post_types) - 1);
            $post_type_replace_string = '';
            foreach($post_types as $key => $post_type){
                if(empty($post_type_replace_string)){
                    $post_type_replace_string = ' AND ' . $post_table . '.post_type IN (';
                }
                
                $args[] = $post_type;
                if($key < $type_count){
                    $post_type_replace_string .= '%s, ';
                }else{
                    $post_type_replace_string .= '%s)';
                }
            }

            $all_post_results = $wpdb->get_results($wpdb->prepare("SELECT {$post_table}.ID FROM {$post_table} WHERE `post_status` = 'publish' {$post_type_replace_string}", $args));

            $all_post_ids = array();
            foreach($all_post_results as $post){
                $all_post_ids[] = $post->ID;
            }
            self::$all_post_ids = $all_post_ids;
        }
        
        return $all_post_ids;
    }

    /**
     * Gets all post ids that aren't listed in the link table.
     * Checks a transient to see if there's a stored list of un updated ids.
     * If there isn't, it checks the database directly
     * @return array $unprocessed_ids (All of the post ids that haven't been listed in the link table yet.)
     **/
    public static function get_all_unprocessed_link_post_ids(){
        global $wpdb;
        $links_table = $wpdb->prefix . "wpil_report_links";
        $start = microtime(true);
        
        $stored_ids = get_transient('wpil_stored_unprocessed_link_ids');
        
        if($stored_ids){
            $unprocessed_ids = $stored_ids;
        }else{
            // first get all the site's post ids
            $all_post_ids = self::get_all_post_ids();
            // then get all the post ids that are processed
            $all_processed_ids = $wpdb->get_results("SELECT DISTINCT {$links_table}.post_id AS ID FROM {$links_table} WHERE 1=1");
            // loop over all the processed posts
            $all_post_ids = array_flip($all_post_ids);
            $posts_to_process = array();
            foreach($all_processed_ids as $processed_post){
                // and tag all the processed post IDs with a false value
                $all_post_ids[$processed_post->ID] = false;
            }

            // strip out all the false values and flip the array so it's in a usable form
            $unprocessed_ids = array_flip(array_filter($all_post_ids, 'strlen'));
            // save the ids to a transient to save time on future runs
            set_transient('wpil_stored_unprocessed_link_ids', $unprocessed_ids, MINUTE_IN_SECONDS * 5);
        }

        // and return the results of our efforts
        return $unprocessed_ids;
    }

    /**
     * Gets the total number of posts that are eligible to include in the link table.
     * This counts all post types selected in the LW settings, including categories.
     * @return int $all_post_count
     **/
    public static function get_total_post_count(){
        global $wpdb;
        $post_table  = $wpdb->prefix . "posts";
        $meta_table  = $wpdb->prefix . "postmeta";
        $term_table  = $wpdb->prefix . "term_taxonomy";

        if(isset(self::$all_post_count) && !empty(self::$all_post_count)){
            return self::$all_post_count;
        }else{
            $post_types = Wpil_Settings::getPostTypesWithoutTerms();
            $process_terms = !empty(Wpil_Settings::getTermTypes());
            $args = array();
            $type_count = (count($post_types) - 1);
            $post_type_replace_string = '';
            foreach($post_types as $key => $post_type){
                if(empty($post_type_replace_string)){
                    $post_type_replace_string = ' AND ' . $post_table . '.post_type IN (';
                }

                $args[] = $post_type;
                if($key < $type_count){
                    $post_type_replace_string .= '%s, ';
                }else{
                    $post_type_replace_string .= '%s)';
                }
            }

            // get all of the site's posts that are in our settings group
            $post_count = $wpdb->get_results($wpdb->prepare("SELECT COUNT({$post_table}.ID) AS count FROM {$post_table} WHERE 1=1 {$post_type_replace_string} AND `post_status` = 'publish'", $args))[0]->count;
            // if term is a selected type
            if($process_terms){
                // get all the site's categories that aren't empty
                $taxonomies = Wpil_Settings::getTermTypes();
                $cat_count = $wpdb->get_results("SELECT COUNT(DISTINCT {$term_table}.term_id) AS count FROM {$term_table} WHERE `taxonomy`IN ('" . implode("', '", $taxonomies) . "')")[0]->count;
            }else{
                $cat_count = 0;
            }

            // add the post count and term count together and return
            self::$all_post_count = ($post_count + $cat_count);
            return self::$all_post_count;
        }
    }

    /**
     * Gets the PHP memory safe usage limit so we know when to quit processing.
     * Currently, the break point is 20mb short of the PHP memory limit.
     **/
    public static function get_mem_break_point(){
        if(isset(self::$memory_break_point) && !empty(self::$memory_break_point)){
            return self::$memory_break_point;
        }else{
            $mem_limit = ini_get('memory_limit');
            
            if(empty($mem_limit) || '-1' == $mem_limit){
                self::$memory_break_point = 'disabled';
                return self::$memory_break_point;
            }

            $mem_size = 0;
            switch(substr($mem_limit, -1)){
                case 'M': 
                case 'm': 
                    $mem_size = (int)$mem_limit * 1048576;
                    break;
                case 'K':
                case 'k':
                    $mem_size = (int)$mem_limit * 1024;
                    break;
                case 'G':
                case 'g':
                    $mem_size = (int)$mem_limit * 1073741824;
                    break;
                default: $mem_size = $mem_limit;
            }

            $mem_break_point = ($mem_size - (20 * 1048576)); // break point == (mem limit - 20mb)
            
            if($mem_break_point < 0){
                self::$memory_break_point = 'disabled';
            }else{
                self::$memory_break_point = $mem_break_point;
            }

            return self::$memory_break_point;
        }
    }

    public static function get_loading_screen($screen = ''){
        switch($screen){
            case 'meta-loading-screen':
                ob_start();
                include WP_INTERNAL_LINKING_PLUGIN_DIR . 'templates/report_prepare_meta_processing.php';
                $return_screen = ob_get_clean();
            break;
            case 'link-loading-screen':
                ob_start();
                include WP_INTERNAL_LINKING_PLUGIN_DIR . 'templates/report_prepare_link_inserting_into_table.php';
                $return_screen = ob_get_clean();
            break;
            case 'post-loading-screen':
                ob_start();
                include WP_INTERNAL_LINKING_PLUGIN_DIR . 'templates/report_prepare_process_links.php';
                $return_screen = ob_get_clean();
            break;
            default:
                $return_screen = '';
        }
        
        return $return_screen;
    }

    /**
     * Checks to see if the link table is created.
     **/
    public static function link_table_is_created(){
        global $wpdb;
        $links_table = $wpdb->prefix . "wpil_report_links";
        // check to see that the link table was successfully created
        $table = $wpdb->get_results("SELECT `post_id` FROM {$links_table} LIMIT 1");
        // if there was an error, assume the table doesn't exist
        if(!empty($wpdb->last_error)){
            return false;
        }else{
            return true;
        }
    }
    
    /**
     * Gets the posts that haven't had their meta filled yet.
     **/
    public static function get_untagged_posts(){
        global $wpdb;
        $post_table  = $wpdb->prefix . "posts";
        $meta_table  = $wpdb->prefix . "postmeta";

        $args = array();
        $post_type_replace_string = '';
        $post_types = Wpil_Settings::getPostTypesWithoutTerms();
        $process_terms = !empty(Wpil_Settings::getTermTypes());
        $type_count = (count($post_types) - 1);
        foreach($post_types as $key => $post_type){
            if(empty($post_type_replace_string)){
                $post_type_replace_string = ' AND ' . $post_table . '.post_type IN (';
            }

            $args[] = $post_type;
            if($key < $type_count){
                $post_type_replace_string .= '%s, ';
            }else{
                $post_type_replace_string .= '%s)';
            }
        }

        // First get all the site's posts
        $all_post_ids = self::get_all_post_ids();
        // Then get the ids of all the posts that have the processing flag
        $posts_with_flag = $wpdb->get_results("SELECT `post_id` FROM {$meta_table} WHERE `meta_key` = 'wpil_sync_report3' ORDER BY `post_id` ASC");

        // create a list of all posts that haven't had their meta filled yet.
        $all_post_ids = array_flip($all_post_ids);
        foreach($posts_with_flag as $flagged_post){
            $all_post_ids[$flagged_post->post_id] = false;
        }

        $unfilled_posts = array_flip(array_filter($all_post_ids, 'strlen'));

        return $unfilled_posts;
    }
}
