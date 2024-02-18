<?php

if (!class_exists('WP_List_Table')) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class Wpil_Table_Report extends WP_List_Table
{

    function __construct()
    {
        parent::__construct(array(
            'singular' => __('Linking Stats', 'wpil'),
            'plural' => __('Linking Stats', 'wpil'),
            'ajax' => false
        ));

        $this->prepare_items();
    }

    function column_default($item, $column_name)
    {
        if (!array_key_exists($column_name, $item)) {
            return "<i>(not set)</i>";
        }

        $v = $item[$column_name];
        if (!$v) {
            $v = 0;
        }

        $v_num = $v;

        $post_id = $item['post']->id;
        if (in_array($column_name, Wpil_Report::$meta_keys)) {
            $opts = [];
            $opts['target'] = '_blank';
            $opts['style'] = 'text-decoration: underline';

            $opts['data-wpil-report-post-id'] = $post_id;
            $opts['data-wpil-report-type'] = $column_name;
            $opts['data-wpil-report'] = 1;

            $v = "<span class='wpil_ul'>$v</span>";

            switch ($column_name) {
                case WPIL_LINKS_INBOUND_INTERNAL_COUNT:
                    $v = "&#x2799;$v";
                    break;

                case WPIL_LINKS_OUTBOUND_EXTERNAL_COUNT:
                    $v .= "&#x279A;";
                    break;

                case WPIL_LINKS_OUTBOUND_INTERNAL_COUNT:
                    $v .= "&#x2799;";
                    break;
            }


            if ($v_num > 0 || WPIL_LINKS_INBOUND_INTERNAL_COUNT == $column_name) {

            } else {
                $v = "<div style='margin:0px; text-align: center; padding: 5px'>$v</div>";
            }

            if ($v_num > 0 || WPIL_LINKS_INBOUND_INTERNAL_COUNT == $column_name) {
                if ($item['post']->type == 'term') {
                    $links_data = get_term_meta($post_id, $column_name . '_data', $single = true);
                } else {
                    $links_data = get_post_meta($post_id, $column_name . '_data', $single = true);
                }


                $rep = '';

                if (is_array($links_data)) {
                    $rep .= '<ul class="report_links">';

                    switch ($column_name) {
                        case 'wpil_links_inbound_internal_count':
                            foreach ($links_data as $link) {
                                $rep .= '<li><i class="wpil_link_delete dashicons dashicons-no-alt" data-post_id="'.$link->post->id.'" data-post_type="'.$link->post->type.'" data-anchor="'.base64_encode($link->anchor).'" data-url="'.$link->url.'"></i><div>' . $link->post->title . ' <b>[' . strip_tags($link->anchor) . ']</b><br><br><a href="' . admin_url('post.php?post=' . $link->post->id . '&action=edit') . '" target="_blank">[edit]</a> <a href="' . $link->post->links->view . '" target="_blank">[view]</a><br><br></div></li>';
                            }
                            break;
                        case 'wpil_links_outbound_internal_count':
                        case 'wpil_links_outbound_external_count':
                            foreach ($links_data as $link) {
                                $rep .= '<li><i class="wpil_link_delete dashicons dashicons-no-alt" data-post_id="'.$item['post']->id.'" data-post_type="'.$item['post']->type.'" data-anchor="'.base64_encode($link->anchor).'" data-url="'.$link->url.'"></i><div><a href="' . $link->url . '" target="_blank" style="text-decoration: underline">' . $link->url . ' <b>[' . strip_tags($link->anchor) . ']</b></a></div></li>';
                            }
                            break;
                    }

                    $rep .= '</ul>';
                }

                $e_rt = esc_attr($column_name);
                $e_p_id = esc_attr($post_id);

                $v = "<div class='wpil-collapsible-wrapper'>
  			            <div class='wpil-collapsible wpil-collapsible-static wpil-links-count' data-wpil-report-type='$e_rt' data-wpil-report-post-id='$e_p_id' >$v</div>
  				        <div class='wpil-content'>
          			        $rep
  				        </div>
  				    </div>";
            }

            if (WPIL_LINKS_INBOUND_INTERNAL_COUNT == $column_name) {
                $v .= '<br><div class="wpil-collapsible wpil-no-sign wpil-no-action"><a href="'.$item['links_inbound_page_url'].'" target="_blank" style="text-decoration: underline" data-wpil-report-post-id="1" data-wpil-report-type="wpil_links_inbound_internal_count" data-wpil-report="1">Add</a></div>';
            }
        }

        return $v;
    }

    function get_columns()
    {
        $columns = [
            'post_title' => __('Title', 'wpil'),
            'date' => __('Published', 'wpil'),
            WPIL_LINKS_INBOUND_INTERNAL_COUNT => __('Inbound internal links', 'wpil'),
            WPIL_LINKS_OUTBOUND_INTERNAL_COUNT => __('Outbound internal links', 'wpil'),
            WPIL_LINKS_OUTBOUND_EXTERNAL_COUNT => __('Outbound external links', 'wpil'),
        ];

        return $columns;
    }

    function column_post_title($item)
    {
        $post = $item['post'];

        $actions = [];

        $title = '<a href="' . $post->links->edit . '" class="row-title">' . esc_attr($post->title) . '</a>';
        $actions['view'] = '<a target=_blank  href="' . $post->links->view . '">View</a>';
        $actions['edit'] = '<a target=_blank href="' . $post->links->edit . '">Edit / Add outbound links</a>';
        $actions['export'] = '<a target=_blank href="' . $post->links->export . '">Export data</a>';
        $actions['refresh'] = '<a href="' . $post->links->refresh . '">Refresh links count</a>';

        return $title . $this->row_actions($actions);
    }


    function get_sortable_columns()
    {
        $cols = $this->get_columns();

        $sortable_columns = [];

        foreach ($cols as $col_k => $col_name) {
            $sortable_columns[$col_k] = [$col_k, false];
        }

        return $sortable_columns;
    }

    function prepare_items()
    {
        $options = get_user_meta(get_current_user_id(), 'report_options', true);
        $per_page = !empty($options['per_page']) ? $options['per_page'] : 20;

        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = array($columns, $hidden, $sortable);

        $start = isset($_REQUEST['paged']) ? (int)$_REQUEST['paged'] : 0;
        $orderby = (isset($_REQUEST['orderby']) && !empty($_REQUEST['orderby'])) ? sanitize_text_field($_REQUEST['orderby']) : 'date';
        $order = (!empty($_REQUEST['order'])) ? sanitize_text_field($_REQUEST['order']) : 'DESC';
        $search = (!empty($_REQUEST['s'])) ? sanitize_text_field($_REQUEST['s']) : '';

        $data = Wpil_Report::getData($start, $orderby, $order, $search, $limit = $per_page);

        $total_items = $data['total_items'];
        $data = $data['data'];

        $this->items = $data;

        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ));
    }

    /**
     * Displays the search box.
     *
     * @param string $text     The 'submit' button label.
     * @param string $input_id ID attribute value for the search input field.
     */
    public function search_box( $text, $input_id ) {
        if ( empty( $_REQUEST['s'] ) && ! $this->has_items() ) {
            return;
        }

        $input_id = $input_id . '-search-input';

        if ( ! empty( $_REQUEST['orderby'] ) ) {
            echo '<input type="hidden" name="orderby" value="' . esc_attr( $_REQUEST['orderby'] ) . '" />';
        }
        if ( ! empty( $_REQUEST['order'] ) ) {
            echo '<input type="hidden" name="order" value="' . esc_attr( $_REQUEST['order'] ) . '" />';
        }
        if ( ! empty( $_REQUEST['post_mime_type'] ) ) {
            echo '<input type="hidden" name="post_mime_type" value="' . esc_attr( $_REQUEST['post_mime_type'] ) . '" />';
        }
        if ( ! empty( $_REQUEST['detached'] ) ) {
            echo '<input type="hidden" name="detached" value="' . esc_attr( $_REQUEST['detached'] ) . '" />';
        }
        ?>
        <p class="search-box">
            <label class="screen-reader-text" for="<?php echo esc_attr( $input_id ); ?>"><?php echo $text; ?>:</label>
            <input type="search" id="<?php echo esc_attr( $input_id ); ?>" name="s" value="<?php _admin_search_query(); ?>" placeholder="Keyword or URL" />
            <?php submit_button( $text, '', '', false, array( 'id' => 'search-submit' ) ); ?>
        </p>
        <?php
    }

}
