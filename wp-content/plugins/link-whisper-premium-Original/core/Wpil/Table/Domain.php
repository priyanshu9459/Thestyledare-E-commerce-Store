<?php

if (!class_exists('WP_List_Table')) {
    require_once ( ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

/**
 * Class Wpil_Table_Domain
 */
class Wpil_Table_Domain extends WP_List_Table
{
    function get_columns()
    {
        return [
            'host' => 'Domain',
            'posts' => 'Posts',
            'links' => 'Links',
        ];
    }

    function prepare_items()
    {
        $options = get_user_meta(get_current_user_id(), 'report_options', true);
        $per_page = !empty($options['per_page']) ? $options['per_page'] : 20;
        $page = isset($_REQUEST['paged']) ? (int)$_REQUEST['paged'] : 1;
        $search = !empty($_GET['s']) ? $_GET['s'] : '';

        $columns = $this->get_columns();
        $hidden = [];
        $sortable = [];
        $this->_column_headers = [$columns, $hidden, $sortable];
        $data = Wpil_Dashboard::getDomainsData($per_page, $page, $search);
        $this->items = $data['domains'];

        $this->set_pagination_args(array(
            'total_items' => $data['total'],
            'per_page' => $per_page,
            'total_pages' => ceil($data['total'] / $per_page)
        ));
    }

    function column_default($item, $column_name)
    {
        switch($column_name) {
            case 'host':
                return '<a href="'.$item[$column_name].'" target="_blank">'.$item[$column_name].'</a>';
            case 'posts':
                $posts = $item[$column_name];

                $list = '<ul class="report_links">';
                foreach ($posts as $post) {
                    $list .= '<li>'
                                . $post->title . '<br>
                                <a href="' . admin_url('post.php?post=' . $post->id . '&action=edit') . '" target="_blank">[edit]</a> 
                                <a href="' . $post->links->view . '" target="_blank">[view]</a><br><br>
                              </li>';
                }
                $list .= '</ul>';

                return '<div class="wpil-collapsible-wrapper">
  			                <div class="wpil-collapsible wpil-collapsible-static wpil-links-count">'.count($posts).'</div>
  				            <div class="wpil-content">'.$list.'</div>
  				        </div>';
            case 'links':
                $links = $item[$column_name];

                $list = '<ul class="report_links">';
                foreach ($links as $link) {
                    $list .= '<li><i data-post_id="'.$link->post->id.'" data-post_type="'.$link->post->type.'" data-anchor="" data-url="'.$link->url.'" class="wpil_link_delete dashicons dashicons-no-alt"></i><div><a href="' . $link->url . '" target="_blank">' . $link->url . '</a><br><a href="' . $link->post->links->view . '" target="_blank"><b>[' . $link->anchor . ']</b></a></div></li>';
                }
                $list .= '</ul>';

                return '<div class="wpil-collapsible-wrapper">
  			                <div class="wpil-collapsible wpil-collapsible-static wpil-links-count">'.count($links).'</div>
  				            <div class="wpil-content">'.$list.'</div>
  				        </div>';
            default:
                return print_r($item, true);
        }
    }
}