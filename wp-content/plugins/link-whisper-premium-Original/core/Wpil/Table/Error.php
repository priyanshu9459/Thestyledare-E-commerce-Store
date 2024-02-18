<?php

if (!class_exists('WP_List_Table')) {
    require_once ( ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

/**
 * Class Wpil_Table_Error
 */
class Wpil_Table_Error extends WP_List_Table
{
    function get_columns()
    {
        return [
            'post' => __('Post', 'wpil'),
            'url' => __('Broken URL', 'wpil'),
            'type' => __('Type', 'wpil'),
            'code' => __('HTTP Code', 'wpil'),
            'created' => __('Discovered', 'wpil'),
            'actions' => '',
        ];
    }

    function prepare_items()
    {
        //pagination
        $options = get_user_meta(get_current_user_id(), 'report_options', true);
        $per_page = !empty($options['per_page']) ? $options['per_page'] : 20;
        $page = isset($_REQUEST['paged']) ? (int)$_REQUEST['paged'] : 1;
        $orderby = isset($_REQUEST['orderby']) ? $_REQUEST['orderby'] : '';
        $order = isset($_REQUEST['order']) ? $_REQUEST['order'] : '';

        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = [$columns, $hidden, $sortable];
        $data = Wpil_Error::getData($per_page, $page, $orderby, $order);
        $this->items = $data['links'];

        $this->set_pagination_args(array(
            'total_items' => $data['total'],
            'per_page' => $per_page,
            'total_pages' => ceil($data['total'] / $per_page)
        ));
    }

    function column_default($item, $column_name)
    {
        switch($column_name) {
            case 'url':
                return '<a href="'.$item->$column_name.'" target="_blank">'.$item->$column_name.'</a>';
            case 'created':
                return date('d M Y (H:i)', strtotime($item->created));
            case 'type':
                return $item->internal ? 'internal' : 'external';
            case 'actions':
                return $item->delete_icon;
            default:
                return $item->{$column_name};
        }
    }

    function get_sortable_columns()
    {
        return [
            'post' => ['post', false],
            'type' => ['internal', false],
            'code' => ['code', false],
            'created' => ['created', false],
        ];
    }
}