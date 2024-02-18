<form method="post" action="">
    <div style="margin-bottom: 15px;"><input style="margin-bottom: -5px;" type="checkbox" name="same_category" id="field_same_category" <?=(isset($same_category) && !empty($same_category)) ? 'checked' : ''?>> <label for="field_same_category">Only Show Link Suggestions in the Same Category as This Post</label> <br></div>
    <button id="inbound_suggestions_button" class="sync_linking_keywords_list button-primary" data-id="<?=esc_attr($post->id)?>" data-type="<?=esc_attr($post->type)?>" data-page="inbound">Add links</button>
    <?php require WP_INTERNAL_LINKING_PLUGIN_DIR . 'templates/table_inbound_suggestions.php'?>
</form>