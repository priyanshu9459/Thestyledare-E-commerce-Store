<div class="wpil_notice" id="wpil_message" style="display: none">
    <p></p>
</div>
<div class="best_keywords outbound">
    <?=Wpil_Base::showVersion()?>
    <p>
        <div style="margin-bottom: 15px;"><input style="margin-bottom: -5px;" type="checkbox" name="same_category" id="field_same_category" <?=(isset($same_category) && !empty($same_category)) ? 'checked' : ''?>> <label for="field_same_category">Only Show Link Suggestions in the Same Category as This Post</label> <br></div>
        <a href="<?=$post->links->export?>" target="_blank">Export data</a>
    </p>
    <button class="sync_linking_keywords_list button-primary" data-id="<?=esc_attr($post->id)?>" data-type="<?=esc_attr($post->type)?>"  data-page="outbound">Update <?=$post->type=='term' ? 'term' : 'post'?></button>
    <?php require WP_INTERNAL_LINKING_PLUGIN_DIR . 'templates/table_suggestions.php'; ?>
</div>
<br>
<button class="sync_linking_keywords_list button-primary" data-id="<?=esc_attr($post->id)?>" data-type="<?=esc_url($post->type)?>"  data-page="outbound">Update <?=$post->type=='term' ? 'term' : 'post'?></button>
<br>
<br>