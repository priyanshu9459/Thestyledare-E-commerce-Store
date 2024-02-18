<div class="wrap wpil-report-page wpil_styles">
    <?=Wpil_Base::showVersion()?>
    <?php $user = wp_get_current_user(); ?>
    <h1 class="wp-heading-inline">Internal links report</h1>
    <hr class="wp-header-end">
    <div id="poststuff">
        <div id="post-body" class="metabox-holder">
            <div id="post-body-content" style="position: relative;">
                <?php include_once 'report_tabs.php'; ?>
                <div class="tbl-link-reports">
                    <form>
                        <input type="hidden" name="page" value="link_whisper" />
                        <input type="hidden" name="type" value="links" />
                        <?php $tbl->search_box('Search', 'search_posts'); ?>
                        <?php $tbl->display(); ?>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
