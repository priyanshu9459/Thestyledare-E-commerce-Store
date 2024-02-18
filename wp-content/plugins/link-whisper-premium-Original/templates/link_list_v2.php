<div data-wpil-ajax-container="" data-wpil-ajax-container-url="<?=esc_url(admin_url('admin.php?post_id=' . $post_id . '&page=link_whisper&type=outbound_suggestions_ajax'.(!empty($term_id)?'&term_id='.$term_id:'').(!empty($user->ID) ? '&nonce='.wp_create_nonce('wpil_suggestion_nonce_'.$user->ID) : '')))?>" class="wpil_keywords_list wpil_styles">
    <div class="progress_panel loader">
        <div class="progress_count" style="width: 100%"></div>
    </div>
</div>
