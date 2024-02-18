<?php

/**
 * Class Wpil_Error
 */
class Wpil_Error
{
    /**
     * Register services
     */
    public function register()
    {
        add_action('wp_ajax_wpil_error_reset_data', [$this, 'ajaxErrorResetData']);
        add_action('wp_ajax_wpil_error_process', [$this, 'ajaxErrorProcess']);
    }

    /**
     * Reset DB fields before search
     */
    public static function ajaxErrorResetData()
    {
        $user = wp_get_current_user();
        if(!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], $user->ID . 'wpil_error_reset_data')){
            wp_send_json([
                'error' => [
                    'title' => __('Data Error', 'wpil'),
                    'text'  => __('There was an error in processing the data, please reload the page and try again.', 'wpil'),
                ]
            ]);
        }

        self::fillPosts();
        self::fillTerms();
        self::prepareTable();
        update_option('wpil_error_reset_run', 1);

        ob_start();
        include WP_INTERNAL_LINKING_PLUGIN_DIR . 'templates/error_process_posts.php';
        $template = ob_get_clean();
        wp_send_json(['template' => $template]);

        die;
    }

    /**
     * Search broken links
     */
    public static function ajaxErrorProcess()
    {
        ini_set('default_socket_timeout', 5);
        $start = microtime(true);
        $total = self::getTotalPostsCount();
        $not_ready = self::getNotReadyPosts();
        $current_link = $_POST['link'];
        $time_limit = 10;
        $proceed = 0;

        //send response with search status to update progress bar
        if (!empty($_POST['get_status'])) {
            self::sendResponse(count($not_ready), $proceed, $total);
        }

        //proceed posts
        foreach ($not_ready as $post) {
            $links = Wpil_Post::getUrls($post);

            foreach ($links as $link) {
                if ($current_link) {
                    if ($current_link == $link) {
                        $current_link = '';
                    }
                    continue;
                }

                if ($code = Wpil_Link::notValid($link)) {
                    self::saveBadLink($link, $post, $code);
                }

                if (microtime(true) - $start > $time_limit) {
                    self::sendResponse(count($not_ready), $proceed, $total, $link);
                }
            }

            $proceed++;
            self::markReady($post);

            if (microtime(true) - $start > $time_limit) {
                break;
            }
        }

        self::sendResponse(count($not_ready), $proceed, $total);

        die;
    }

    /**
     * Send response search status
     *
     * @param $not_ready
     * @param $proceed
     * @param $total
     * @param string $link
     */
    public static function sendResponse($not_ready, $proceed, $total, $link = '')
    {
        $ready = $total - $not_ready + $proceed;
        $percents = ceil($ready / $total * 100);
        $status =  "$percents%, $ready/$total completed";
        $finish = $total == $ready ? true : false;

        if ($finish) {
            update_option('wpil_error_reset_run', 0);
        }

        wp_send_json([
            'finish' => $finish,
            'status' => $status,
            'percents' => $percents,
            'link' => $link,
        ]);
    }

    /**
     * Mark post as processed
     *
     * @param $post
     */
    public static function markReady($post)
    {
        global $wpdb;

        if ($post->type == 'term') {
            $wpdb->update($wpdb->termmeta, ['meta_value' => 1], ['term_id' => $post->id, 'meta_key' => 'wpil_sync_error']);
        } else {
            $wpdb->update($wpdb->postmeta, ['meta_value' => 1], ['post_id' => $post->id, 'meta_key' => 'wpil_sync_error']);
        }
    }

    /**
     * Reset links data about posts
     */
    public static function fillPosts()
    {
        global $wpdb;

        $wpdb->delete($wpdb->postmeta, ['meta_key' => 'wpil_sync_error']);
        $post_types = implode("','", Wpil_Settings::getPostTypes());
        $posts = $wpdb->get_results("SELECT ID FROM {$wpdb->posts} WHERE post_type IN ('$post_types') AND post_status = 'publish'");
        foreach ($posts as $post) {
            $wpdb->insert($wpdb->postmeta, ['post_id' => $post->ID, 'meta_key' => 'wpil_sync_error', 'meta_value' => '0']);
        }
    }

    /**
     * Reset links data about terms
     */
    public static function fillTerms()
    {
        global $wpdb;

        $taxonomies = Wpil_Settings::getTermTypes();

        $wpdb->delete($wpdb->termmeta, ['meta_key' => 'wpil_sync_error']);
        if (!empty($taxonomies)) {
            $terms = $wpdb->get_results("SELECT term_id FROM {$wpdb->term_taxonomy} WHERE taxonomy IN ('" . implode("', '", $taxonomies) . "')");
            foreach ($terms as $term) {
                $wpdb->insert($wpdb->termmeta, ['term_id' => $term->term_id, 'meta_key' => 'wpil_sync_error', 'meta_value' => '0']);
            }
        }
    }

    /**
     * Get total posts count
     *
     * @return string|null
     */
    public static function getTotalPostsCount()
    {
        global $wpdb;

        $count = $wpdb->get_var("SELECT count(*) FROM {$wpdb->postmeta} WHERE meta_key = 'wpil_sync_error'");
        if (!empty(Wpil_Settings::getTermTypes())) {
            $count += $wpdb->get_var("SELECT count(*) FROM {$wpdb->termmeta} WHERE meta_key = 'wpil_sync_error'");
        }

        return $count;
    }

    /**
     * Get posts that should be processed
     *
     * @return array
     */
    public static function getNotReadyPosts()
    {
        global $wpdb;
        $posts = [];

        $result = $wpdb->get_results("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'wpil_sync_error' AND meta_value = 0 ORDER BY post_id ASC");
        foreach ($result as $post) {
            $posts[] = new Wpil_Model_Post($post->post_id);
        }

        if (!empty(Wpil_Settings::getTermTypes())) {
            $result = $wpdb->get_results("SELECT term_id FROM {$wpdb->termmeta} WHERE meta_key = 'wpil_sync_error' AND meta_value = 0 ORDER BY term_id ASC");
            foreach ($result as $post) {
                $posts[] = new Wpil_Model_Post($post->term_id, 'term');
            }
        }

        return $posts;
    }

    /**
     * Create broken links table if it not exists and truncate it
     */
    public static function prepareTable(){
        global $wpdb;

        $wpil_link_table_query = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wpil_broken_links (
                                    id int(10) unsigned NOT NULL AUTO_INCREMENT,
                                    post_id int(10) unsigned NOT NULL,
                                    post_type text,
                                    url text,
                                    internal tinyint(1) DEFAULT 0,
                                    code int(10),
                                    created DATETIME,
                                    PRIMARY KEY  (id)
                                )";

        // create DB table if it doesn't exist
        require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($wpil_link_table_query);

        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}wpil_broken_links");
    }

    /**
     * Save broken link to DB
     *
     * @param $url
     * @param $post
     * @param $code
     */
    public static function saveBadLink($url, $post, $code)
    {
        global $wpdb;

        $internal = Wpil_Link::isInternal($url) ? 1 : 0;
        $wpdb->insert($wpdb->prefix . 'wpil_broken_links', [
            'post_id' => $post->id,
            'post_type' => $post->type,
            'url' => $url,
            'internal' => $internal,
            'code' => $code,
            'created' => current_time('mysql', 1),
        ]);
    }

    /**
     * Get data for Error table
     *
     * @param $per_page
     * @param $page
     * @param string $orderby
     * @param string $order
     * @return array
     */
    public static function getData($per_page, $page, $orderby = '', $order = '')
    {
        global $wpdb;
        $limit = " LIMIT " . (($page - 1) * $per_page) . ',' . $per_page;

        if ($orderby == 'post') {
            $limit = '';
        }

        $sort = " ORDER BY id DESC ";
        if ($orderby && $order && $orderby != 'post') {
            $sort = " ORDER BY $orderby $order ";
        }

        $total = $wpdb->get_var("SELECT count(*) FROM {$wpdb->prefix}wpil_broken_links $sort");
        $result = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wpil_broken_links $sort $limit" );
        foreach ($result as $key => $link) {
            $p = new Wpil_Model_Post($link->post_id, $link->post_type);
            $result[$key]->post = '<a href="' . $p->links->view . '" target="_blank">' . $p->title . '</a><span class="icons"><a target="_blank" href="' . $p->links->edit . '"><i class="dashicons dashicons-edit"></i></a><a target="_blank" href="' . $p->links->view . '"><i class="dashicons dashicons-visibility"></i></a>';
            $result[$key]->post_title = $p->title;
            $result[$key]->delete_icon = '<i data-link_id="' . $link->id . '" data-post_id="'.$p->id.'" data-post_type="'.$p->type.'" data-anchor="" data-url="'.$link->url.'" class="wpil_link_delete broken_link dashicons dashicons-no-alt"></i>';
        }

        if ($orderby == 'post') {
            usort($result, function($a, $b) use($order){
                if ($a->post_title == $b->post_title) {
                    return 0;
                }

                if ($order == 'desc') {
                    return ($a->post_title < $b->post_title) ? 1 : -1;
                } else {
                    return ($a->post_title < $b->post_title) ? -1 : 1;
                }
            });

            $result = array_slice($result, (($page - 1) * $per_page), $per_page);
        }

        return [
            'total' => $total,
            'links' => $result
        ];
    }

    /**
     * Delete link record from DB
     *
     * @param $link_id
     */
    public static function deleteLink($link_id)
    {
        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'wpil_broken_links', ['id' => $link_id]);
    }
}