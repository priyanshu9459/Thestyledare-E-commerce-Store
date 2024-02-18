<?php

/**
 * Class Wpil_Dashboard
 */
class Wpil_Dashboard
{
    /**
     * Get posts count with selected types
     *
     * @return string|null
     */
    public static function getPostCount()
    {
        global $wpdb;
        $post_types = implode("','", Wpil_Settings::getPostTypes());
        $count = $wpdb->get_var("SELECT count(*) FROM {$wpdb->prefix}posts WHERE post_type IN ('$post_types') AND post_status = 'publish'");
        $taxonomies = Wpil_Settings::getTermTypes();
        if (!empty($taxonomies)) {
            $count += $wpdb->get_var("SELECT count(*) FROM {$wpdb->prefix}term_taxonomy WHERE taxonomy IN ('" . implode("', '", $taxonomies) . "')");
        }

        return $count;
    }

    /**
     * Get all links count
     *
     * @return string|null
     */
    public static function getLinksCount()
    {
        global $wpdb;
        return $wpdb->get_var("SELECT count(*) FROM {$wpdb->prefix}wpil_report_links");
    }

    /**
     * Get internal links count
     *
     * @return string|null
     */
    public static function getInternalLinksCount()
    {
        global $wpdb;
        return $wpdb->get_var("SELECT count(*) FROM {$wpdb->prefix}wpil_report_links WHERE internal = 1");
    }

    /**
     * Get posts count without inbound internal links
     *
     * @return string|null
     */
    public static function getOrphanedPostsCount()
    {
        global $wpdb;
        $count = $wpdb->get_var("SELECT count(DISTINCT m.post_id) FROM {$wpdb->postmeta} m INNER JOIN {$wpdb->posts} p ON m.post_id = p.ID WHERE p.post_status = 'publish' AND m.meta_key = 'wpil_links_inbound_internal_count' AND m.meta_value = 0");
        if (!empty(Wpil_Settings::getTermTypes())) {
            $count += $wpdb->get_var("SELECT count(DISTINCT term_id) FROM {$wpdb->prefix}termmeta WHERE meta_key = 'wpil_links_inbound_internal_count' AND meta_value = 0");
        }

        return $count;
    }

    /**
     * Get 10 most used domains from external links
     *
     * @return array
     */
    public static function getTopDomains()
    {
        global $wpdb;
        $result = $wpdb->get_results("SELECT host, count(*) as `cnt` FROM {$wpdb->prefix}wpil_report_links WHERE host IS NOT NULL GROUP BY host ORDER BY count(*) DESC LIMIT 10");

        return $result;
    }

    /**
     * Get broken external links count
     *
     * @return string|null
     */
    public static function getBrokenLinksCount()
    {
        global $wpdb;
        return $wpdb->get_var("SELECT count(*) FROM {$wpdb->prefix}wpil_broken_links WHERE internal = 1");
    }

    /**
     * Get broken internal links count
     *
     * @return string
     */
    public static function get404LinksCount()
    {
        global $wpdb;
        return $wpdb->get_var("SELECT count(*) FROM {$wpdb->prefix}wpil_broken_links WHERE internal = 0");
    }

    /**
     * Get data for domains table
     *
     * @param $per_page
     * @param $page
     * @param $search
     * @return array
     */
    public static function getDomainsData($per_page, $page, $search)
    {
        global $wpdb;
        $domains = [];
        $search = !empty($search) ? " AND host LIKE '%$search%'" : '';
        $result = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wpil_report_links WHERE host IS NOT NULL $search");
        foreach ($result as $link) {
            $host = $link->host;
            $id = $link->post_id;
            $type = $link->post_type;
            $p = new Wpil_Model_Post($id, $type);

            if (empty($domains[$host])) {
                $domains[$host] = ['host' => $host, 'posts' => [], 'links' => []];
            }

            if (empty($domains[$host]['posts'][$id])) {
                $domains[$host]['posts'][$id] = $p;
            }

            $domains[$host]['links'][] = new Wpil_Model_Link([
                'url' => $link->raw_url,
                'anchor' => strip_tags($link->anchor),
                'post' => $p
            ]);
        }

        usort($domains, function($a, $b){
            if (count($a['links']) == count($b['links'])) {
                return 0;
            }

            return (count($a['links']) < count($b['links'])) ? 1 : -1;
        });

        return [
            'total' => count($domains),
            'domains' => array_slice($domains, ($page - 1) * $per_page, $per_page)
        ];
    }
}