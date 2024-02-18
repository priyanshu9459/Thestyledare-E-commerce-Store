<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e("Internal links report","wpil"); ?></h1>
    <hr class="wp-header-end">
    <div id="poststuff">
        <div id="post-body" class="metabox-holder">
            <div id="post-body-content" style="position: relative;">
                <p><?php _e("Prepare data, completed","wpil"); ?>: <span class=wpil-loading-status><?=$st['status']?></span></p>
                <div class="syns_div wpil_report_need_prepare">
                    <h4 class="progress_panel_msg hide"><?php _e('Synchronizing your data..','wpil'); ?></h4>
                    <div class="progress_panel" data-total="<?php echo $st['remained']?>">
                        <div class="progress_count" style='width: <?=$st['w']?>%'><span class=wpil-loading-status><?=$st['status']?></span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>