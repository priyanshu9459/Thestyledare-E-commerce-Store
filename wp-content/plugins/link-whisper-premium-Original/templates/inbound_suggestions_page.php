<div class="wrap wpil-report-page wpil_styles" id="inbound_suggestions_page" data-id="<?=$post->id?>" data-type="<?=$post->type?>">
    <?=Wpil_Base::showVersion()?>
    <h1 class="wp-heading-inline"><?php _e("Inbound links suggestions", "wpil"); ?></h1>
    <a href="<?=esc_url($return_url)?>" class="page-title-action"><?php _e('Return to Report','wpil'); ?></a>
    <h2><?=esc_attr($post->title);?></h2>
    <div id="keywords">
        <form action="" method="post">
            <label for="keywords_field">Search by Keyword</label>
            <textarea name="keywords" id="keywords_field"><?=!empty($_POST['keywords'])?sanitize_textarea_field($_POST['keywords']):''?></textarea>
            <button type="submit" class="button-primary">Search</button>
        </form>
    </div>
    <hr class="wp-header-end">
    <div id="poststuff">
        <div id="post-body" class="metabox-holder">
            <div id="post-body-content" style="position: relative;">

                <?php if (!empty($message_error)) : ?>
                    <div class="notice notice-error is-dismissible"><?=$message_error?></div>
                <?php endif; ?>

                <?php if (!empty($message_success)) : ?>
                    <div class="notice notice-success is-dismissible"><?=$message_success?></div>
                <?php endif; ?>

                <div class="tbl-link-reports">
                    <?php $user = wp_get_current_user(); ?>
                    <?php if (!empty($_GET['wpil_no_preload'])) : ?>
                        <form method="post" action="">
                            <div data-wpil-ajax-container data-wpil-ajax-container-url="<?=esc_url(admin_url('admin.php?page=link_whisper&type=inbound_suggestions_page_container&'.($post->type=='term'?'term_id=':'post_id=').$post->id.(!empty($user->ID) ? '&nonce='.wp_create_nonce('wpil_suggestion_nonce_'.$user->ID) : '')).Wpil_Suggestion::getKeywordsUrl().'&wpil_no_preload=1')?>">
                                <div style="margin-bottom: 15px;"><input style="margin-bottom: -5px;" type="checkbox" name="same_category" id="field_same_category_page" <?=(isset($same_category) && !empty($same_category)) ? 'checked' : ''?>> <label for="field_same_category_page">Only Show Link Suggestions in the Same Category as This Post</label> <br></div>
                                <button id="inbound_suggestions_button" class="sync_linking_keywords_list button-primary" data-id="<?=esc_attr($post->id)?>" data-type="<?=esc_attr($post->type)?>" data-page="inbound">Add links</button>
                            </div>
                        </form>
                    <?php else : ?>
                    <div data-wpil-ajax-container data-wpil-ajax-container-url="<?=esc_url(admin_url('admin.php?page=link_whisper&type=inbound_suggestions_page_container&'.($post->type=='term'?'term_id=':'post_id=').$post->id.(!empty($user->ID) ? '&nonce='.wp_create_nonce('wpil_suggestion_nonce_'.$user->ID) : '')).Wpil_Suggestion::getKeywordsUrl())?>">
                        <div class='progress_panel loader'>
                            <div class='progress_count' style='width: 100%'></div>
                        </div>
                    </div>
                    <p>
                        <a href="<?=esc_url($_SERVER['REQUEST_URI'] . '&wpil_no_preload=1')?>">Load without animation</a>
                    </p>
                    <?php endif; ?>
                    <div data-wpil-page-inbound-links=1> </div>
                </div>
            </div>
        </div>
    </div>
</div>
