<h2 class="nav-tab-wrapper" style="margin-bottom:1em;">
    <a class="nav-tab <?=empty($_GET['type'])?'nav-tab-active':''?>" id="general-tab" href="<?=admin_url('admin.php?page=link_whisper')?>"><?php  _e( "Dashboard", 'wpil' )?></a>
    <a class="nav-tab <?=(!empty($_GET['type']) && $_GET['type']=='links')?'nav-tab-active':''?>" id="home-tab" href="<?=admin_url('admin.php?page=link_whisper&type=links')?>"><?php  _e( "Links report", 'wpil' )?> </a>
    <a class="nav-tab <?=(!empty($_GET['type']) && $_GET['type']=='domains')?'nav-tab-active':''?>" id="home-tab" href="<?=admin_url('admin.php?page=link_whisper&type=domains')?>"><?php  _e( "Domains report", 'wpil' )?> </a>
    <!-- a class="nav-tab <?=(!empty($_GET['type']) && $_GET['type']=='error')?'nav-tab-active':''?>" id="post_types-tab" href="<?=admin_url('admin.php?page=link_whisper&type=error')?>"><?php  _e( "Error Report", 'wpil' )?> </a -->

    <form action='' method="post" id="wpil_report_reset_data_form">
        <input type="hidden" name="reset" value="1">
        <input type="hidden" name="reset_data_nonce" value="<?php echo wp_create_nonce($user->ID . 'wpil_reset_report_data'); ?>">
        <button type="submit" class="button-primary">Re-run Reports</button>
    </form>
</h2>