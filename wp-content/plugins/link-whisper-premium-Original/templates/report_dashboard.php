<div class="wrap wpil-report-page wpil_styles">
    <?=Wpil_Base::showVersion()?>
    <h1 class="wp-heading-inline">Dashboard</h1>
    <hr class="wp-header-end">
    <div id="poststuff">
        <div id="post-body" class="metabox-holder">
            <div id="post-body-content" style="position: relative;">
                <?php include_once 'report_tabs.php'; ?>
                <div id="report_dashboard">
                    <div class="box">
                        <div class="title">Links Stats</div>
                        <div class="body" id="report_stats">
                            <div><i class="dashicons dashicons-format-aside"></i><span>Posts Crawled</span><?=Wpil_Dashboard::getPostCount()?></div>
                            <div><i class="dashicons dashicons-admin-links"></i><span>Links Found</span><?=Wpil_Dashboard::getLinksCount()?></div>
                            <div><i class="dashicons dashicons-arrow-left-alt"></i><span>Internal Links</span><?=Wpil_Dashboard::getInternalLinksCount()?></div>
                            <div><i class="dashicons dashicons-dismiss"></i><span>Orphaned Posts</span><?=Wpil_Dashboard::getOrphanedPostsCount()?></div>
                            <!-- div><i class="dashicons dashicons-admin-tools"></i><span>Broken Links</span><?=Wpil_Dashboard::getBrokenLinksCount()?></div>
                            <div><i class="dashicons dashicons-search"></i><span>404 errors</span><?=Wpil_Dashboard::get404LinksCount()?></div -->
                        </div>
                    </div>
                    <div class="box">
                        <div class="title">Most linked to <a href="<?=admin_url('admin.php?page=link_whisper&type=domains')?>">domains</a></div>
                        <div class="body" id="report_dashboard_domains">
                            <?php
                                $i=0;
                                $prev = isset($domains[0]->cnt) ? $domains[0]->cnt : 0;
                            ?>
                            <?php foreach ($domains as $domain) : ?>
                                <?php if ($prev != $domain->cnt) { $i++; $prev = $domain->cnt; } ?>
                                <div>
                                    <div class="count"><?=$domain->cnt?></div>
                                    <div class="host"><?=$domain->host?></div>
                                </div>
                                <div class="line line<?=$i?>"><span style="width: <?=(($domain->cnt/$top_domain)*100)?>%"></span></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="box">
                        <div class="title">Internal vs External links</div>
                        <div class="body">
                            <div id="wpil_links_chart" style="width: 320px;height: 320px;"></div>
                            <input type="hidden" name="total_links_count" value="<?=Wpil_Dashboard::getLinksCount()?>">
                            <input type="hidden" name="internal_links_count" value="<?=Wpil_Dashboard::getInternalLinksCount()?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
