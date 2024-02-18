<div class="wrap wpil-report-page wpil_styles">
    <?=Wpil_Base::showVersion()?>
    <h1 class="wp-heading-inline">Error report</h1>
    <hr class="wp-header-end">
    <div id="poststuff">
        <div id="post-body" class="metabox-holder">
            <div id="post-body-content" style="position: relative;">
                <?php include_once 'report_tabs.php'; ?>
                <div id="report_error">
                    <form action='' method="post" style="float: right; margin-bottom: 10px;" id="wpil_error_reset_data_form">
                        <input type="hidden" name="reset" value="1">
                        <input type="hidden" name="nonce" value="<?php echo wp_create_nonce($user->ID . 'wpil_error_reset_data'); ?>">
                        <button type="submit" class="button-primary">Reset Data</button>
                    </form>
                    <br clear="all">
                    <?php $table->display(); ?>
                </div>
            </div>
        </div>
    </div>
</div>