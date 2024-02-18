<?php

/**
 * Model for posts and terms
 *
 * Class Wpil_Model_Post
 */
class Wpil_Model_Post
{
    public $id;
    public $title;
    public $type;
    public $content;
    public $links;
    public $slug = null;

    public function __construct($id, $type = 'post')
    {
        $this->id = $id;
        $this->type = $type;
        $this->links = (object)[
            'view' => '',
            'edit' => '',
            'export' => '',
            'refresh' => '',
        ];

        if ($type == 'post') {
            //fill post properties
            $item = get_post($id);
            if (!empty($item)) {
                $this->title = $item->post_title;
                $this->links = (object)[
                    'view' => get_the_permalink($id),
                    'edit' => get_edit_post_link($id),
                    'export' => esc_url(admin_url("post.php?area=wpil_export&post_id=" . $id)),
                    'refresh' => esc_url(admin_url("admin.php?page=link_whisper&type=post_links_count_update&post_id=" . $id)),
                ];
            }
        } elseif ($type == 'term') {
            //fill term properties

            $item = get_term($id);
            if (!empty($item)) {
                $this->title = $item->name;
                $this->links = (object)[
                    'view' => get_term_link($item->term_id),
                    'edit' => esc_url(admin_url('term.php?taxonomy=' . $item->taxonomy . '&post_type=post&tag_ID=' . $id)),
                    'export' => esc_url(admin_url("post.php?area=wpil_export&term_id=" . $id)),
                    'refresh' => esc_url(admin_url("admin.php?page=link_whisper&type=post_links_count_update&term_id=" . $id))
                ];
            }
        }
    }

    /**
     * Update post content
     *
     * @param $content
     * @return $this
     */
    function setContent($content)
    {
        $this->content = $content;

        return $this;
    }

    /**
     * Get post content depends on post type
     *
     * @return string
     */
    function getContent()
    {
        if (empty($this->content)) {
            if ($this->type == 'term') {
                $content = term_description($this->id);
            } else {
                $item = get_post($this->id);
                $content = $item->post_content;
                $content .= $this->getAdvancedCustomFields();
            }

            $content = preg_replace('#(?<=<!--WPRM Recipe)(.*?)(?=<!--End WPRM Recipe-->)#ms', '', $content);

            $this->content = $content;
        }

        return $this->content;
    }

    /**
     * Get post slug depends on post type
     *
     * @return string|null
     */
    function getSlug()
    {
        if (empty($this->slug)) {
            if ($this->type == 'term') {
                $term = get_term($this->id);
                $this->slug = $term->slug;
            } else {
                $post = get_post($this->id);
                $this->slug = $post->post_name;
            }
        }

        return $this->slug;
    }

    /**
     * Get post content from advanced custom fields
     *
     * @return string
     */
    function getAdvancedCustomFields()
    {
        $content = '';
        foreach (Wpil_Post::getAdvancedCustomFieldsList() as $field) {
            if ($c = get_post_meta($this->id, $field, true)) {
                $content .= "\n" . $c;
            }
        }

        return $content;
    }
}
