<?php

/**
 * Work with links
 */
class Wpil_Link
{
    /**
     * Register services
     */
    public function register()
    {
        add_action('wp_ajax_wpil_save_linking_references', [$this, 'addLinks']);
        add_action('wp_ajax_wpil_get_link_title', ['Wpil_Link', 'getLinkTitle']);
    }

    /**
     * Update post links
     */
    function addLinks()
    {
        $err_msg = false;

        //check if request has needed data
        if (empty($_POST['data'])) {
            $err_msg = "No links selected";
        } elseif (empty($_POST['id']) || empty($_POST['type']) || empty($_POST['page'])){
            $err_msg = "Broken links data";
        } else {
            $page = $_POST['page'];

            foreach ($_POST['data'] as $item) {
                $id = !empty($item['id']) ? (int)$item['id'] : (int)$_POST['id'];
                $type = !empty($item['type']) ? $item['type'] : $_POST['type'];

                $links = $item['links'];
                //trim sentences
                foreach ($links as $key => $link) {
                    if ($page == 'inbound') {
                        $link['id'] = (int)$_POST['id'];
                        $link['type'] = sanitize_text_field($_POST['type']);
                    }


                    if (!empty($link['custom_link'])) {
                        $view_link = $link['custom_link'];
                    } elseif ($link['type'] == 'term') {
                        $view_link = get_term_link((int)$link['id']);
                    } else {
                        $view_link = get_the_permalink((int)$link['id']);
                    }

                    $links[$key]['sentence'] = trim(base64_decode($link['sentence']));
                    $links[$key]['sentence_with_anchor'] = trim(str_replace('%view_link%', $view_link, $link['sentence_with_anchor']));

                    if (!empty($link['custom_sentence'])) {
                        $links[$key]['custom_sentence'] = trim(str_replace('|href="([^"]+)"|', $view_link, base64_decode($link['custom_sentence'])));

                        if (!empty($link['custom_link'])) {
                            $links[$key]['custom_sentence'] = preg_replace('|href="([^"]+)"|', 'href="'.$link['custom_link'].'"', $links[$key]['custom_sentence']);
                        }
                    }

                    update_post_meta($link['id'], 'wpil_sync_report3', 0);
                }

                if ($type == 'term') {
                    update_term_meta($id, 'wpil_links', $links);
                } else {
                    update_post_meta($id, 'wpil_links', $links);

                    if ($page == 'outbound') {
                        //create DB record with success flag
                        update_post_meta($id, 'wpil_is_outbound_links_added', '1');

                        //create DB record to refresh page after post update if Gutenberg is active
                        if (!empty($_POST['gutenberg']) && $_POST['gutenberg'] == 'true') {
                            update_post_meta($id, 'wpil_gutenberg_restart', '1');
                        }
                    }
                }
            }

            //add links to content
            if ($page == 'inbound') {
                foreach ($_POST['data'] as $item) {
                    if ($item['type'] == 'term') {
                        Wpil_Term::addLinksToTerm($item['id']);
                    } else {
                        ob_start();
                        Wpil_Post::addLinksToContent(null, ['ID' => $item['id']]);
                        ob_end_clean();
                    }
                }

                if ($item['type'] == 'term') {
                    update_term_meta((int)$_POST['id'], 'wpil_is_inbound_links_added', '1');
                } else {
                    update_post_meta((int)$_POST['id'], 'wpil_is_inbound_links_added', '1');
                }
            }
        }

        //return response
        header("Content-type: application/json");
        echo json_encode(['err_msg' => $err_msg]);

        exit;
    }

    /**
     * Delete link from post
     */
    public static function delete()
    {
        $post_id = !empty($_POST['post_id']) ? $_POST['post_id'] : null;
        $post_type = !empty($_POST['post_type']) ? $_POST['post_type'] : null;
        $url = !empty($_POST['url']) ? $_POST['url'] : null;
        $anchor = !empty($_POST['anchor']) ? base64_decode($_POST['anchor']) : null;
        $link_id = !empty($_POST['link_id']) ? $_POST['link_id'] : null;

        if ($post_id && $post_type && $url) {
            $post = new Wpil_Model_Post($post_id, $post_type);
            $content = $post->getContent();

            //delete link from post content
            if (empty($anchor)) {
                $content = preg_replace('#<a [^>]+(\'|\")' . $url . '(\'|\")[^>]*>([^<]+)</a>#i', '$3',  $content);
            } else {
                $content = preg_replace('#<a [^>]+(\'|\")' . $url . '(\'|\")[^>]*>' . $anchor . '</a>#i', $anchor,  $content);
            }

            if ($post_type == 'post') {
                wp_update_post([
                    'ID' => $post_id,
                    'post_content' => $content
                ]);

                //delete link from editors
                Wpil_Editor_Elementor::deleteLink($post_id, $url, $anchor);
                Wpil_Editor_Beaver::deleteLink($post_id, $url, $anchor);
                Wpil_Editor_Thrive::deleteLink($post_id, $url, $anchor);
                Wpil_Editor_Origin::deleteLink($post_id, $url, $anchor);
            }

            //delete link record from wpil_broken links table
            if ($link_id) {
                Wpil_Error::deleteLink($link_id);
            }
        }

        die;
    }

    /**
     * Check if link is internal
     *
     * @param $url
     * @return bool
     */
    public static function isInternal($url)
    {
        $localhost = parse_url(get_site_url(), PHP_URL_HOST);
        $host = parse_url($url, PHP_URL_HOST);

        if (!empty($localhost) && !empty($host) && $localhost == $host) {
            return true;
        }

        return false;
    }

    /**
     * Check if link is broken
     *
     * @param $url
     * @return bool|int
     */
    public static function notValid($url) {
        ini_set('user_agent', $_SERVER['HTTP_USER_AGENT']);
        $headers = @get_headers($url);
        $headers = (is_array($headers)) ? implode( "\n ", $headers) : $headers;
        if (!(bool)preg_match('#^HTTP/.*\s+[(200|301|302)]+\s#i', $headers)) {
            preg_match('#^HTTP/.*\s+([\d]+)\s+#i', $headers, $matches);

            // if the headers were empty and the url was formatted correctly, set up for a curl call
            $look_further = false;
            if(empty($headers) && filter_var($url, FILTER_VALIDATE_URL) !== false){
                $look_further = true;
            }

            if (!empty($matches[1]) || $look_further) {
                if ((int)$matches[1] == 403 || $look_further) {
                    return self::notValidCurl($url);
                }
                return (int)$matches[1];
            }

            return 404;
        }

        return false;
    }

    public static function notValidCurl($url) {
        $c = curl_init($url);
        curl_setopt($c, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
        curl_setopt($c, CURLOPT_FILETIME, true);
        curl_setopt($c, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($c, CURLOPT_NOBODY, true);
        curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($c, CURLOPT_HEADER, true);
        $headers = curl_exec($c);

        if (!(bool)preg_match('#^HTTP/.*\s+[(200|301|302)]+\s#i', $headers)) {
            preg_match('#^HTTP/.*\s+([\d]+)\s+#i', $headers, $matches);

            if (!empty($matches[1])) {
                return (int)$matches[1];
            }

            return 404;
        }

        return false;
    }

    /**
     * Get link title by URL
     */
    public static function getLinkTitle()
    {
        $link = !empty($_POST['link']) ? $_POST['link'] : '';
        $title = '';
        $id = '';
        $type = '';

        if ($link) {
            if (self::isInternal($link)) {
                $post_id = url_to_postid($link);
                if ($post_id) {
                    $post = get_post($post_id);
                    $title = $post->post_title;
                    $link = '/' . $post->post_name;
                    $id = $post_id;
                    $type = 'post';
                } else {
                    $slugs = array_filter(explode('/', $link));
                    $term = Wpil_Term::getTermBySlug(end($slugs));
                    if (!empty($term)) {
                        $title = $term->name;
                        $link = end($slugs);
                        $id = $term->term_id;
                        $type = 'term';
                    }
                }
            }

            //get title if link is not post or term
            if (!$title) {
                $str = file_get_contents($link);
                if(strlen($str)>0){
                    $str = trim(preg_replace('/\s+/', ' ', $str)); // supports line breaks inside <title>
                    preg_match("/\<title\>(.*)\<\/title\>/i",$str,$title); // ignore case
                    $title = $title[1];
                }
            }

            echo json_encode([
                'title' => $title,
                'link' => $link,
                'id' => $id,
                'type' => $type
            ]);
        }

        die;
    }

    /**
     * Remove class "wpil_internal_link" from links
     */
    public static function removeLinkClass()
    {
        global $wpdb;

        $wpdb->get_results("UPDATE {$wpdb->posts} SET post_content = REPLACE(post_content, 'wpil_internal_link', '') WHERE post_content LIKE '%wpil_internal_link%'");
    }
}
