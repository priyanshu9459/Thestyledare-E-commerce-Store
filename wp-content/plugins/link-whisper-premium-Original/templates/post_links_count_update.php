<div class="wrap wpil-report-page wpil_styles wpil-lists wpil_post_links_count_update_page">
    <br>
    <a href="<?=admin_url("admin.php?page=link_whisper")?>" class="page-title-action">Return to Report</a>
    <h1 class='wp-heading-inline'>Updating links stats for <?=$post->type?> #<?=$post->id?>, `<?=$post->title?>`</h1>
    <p>
        <a href="<?=$post->links->edit?>" target="_blank">[edit]</a>
        <a href="<?=$post->links->view?>" target="_blank">[view]</a>
        <a href="<?=$post->links->export?>" target="_blank">[export]</a>
    </p>
    <h2>Previous data:</h2>
    <p>Date of previous analysis: <?=!empty($prev_t) ? $prev_t : '- not set -'?></p>
    <ul>
        <li>
            <b>Outbound internal links:</b> <?=$prev_count['outbound_internal']?>
        </li>
        <li>
            <b>Inbound internal links:</b> <?=$prev_count['inbound_internal']?>
        </li>
        <li>
            <b>Outbound external links:</b> <?=$prev_count['outbound_external']?>
        </li>
    </ul>

    <h2>New data:</h2>
    <p>Date of analysis: <?=$new_time?></p>
    <p>Time spent: <?=number_format($time, 3)?> seconds</p>
    <ul>
        <li>
            <b>Outbound internal links:</b> <?=$count['outbound_internal']?> (difference: <?=$count['outbound_internal'] - $prev_count['outbound_internal']?>)
        </li>
        <li>
            <b>Inbound internal links:</b> <?=$count['inbound_internal']?> (difference: <?=$count['inbound_internal'] - $prev_count['inbound_internal']?>)
        </li>
        <li>
            <b>Outbound external links:</b> <?=$count['outbound_external']?> (difference: <?=$count['outbound_external'] - $prev_count['outbound_external']?>)
        </li>
    </ul>

    <h3>Outbound internal links (links count: <?=$count['outbound_internal']?>)</h3>
    <ul>
        <?php foreach ($links_data['outbound_internal'] as $link) : ?>
            <li>
                <a href="<?=esc_url($link->url)?>" target="_blank" style="text-decoration: underline">
                    <?=esc_url($link->url)?><br> <b>[<?=esc_attr($link->anchor)?>]</b>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>

    <h3>Inbound internal links (links count: <?=$count['inbound_internal']?>)</h3>
    <ul>
        <?php foreach ($links_data['inbound_internal'] as $link) : ?>
            <li>
                [<?=$link->post->id?>] <?=$link->post->title?> <b>[<?=esc_attr($link->anchor)?>]</b>
                <br>
                <a href="<?=$link->post->links->edit?>" target="_blank">[edit]</a>
                <a href="<?=$link->post->links->view?>" target="_blank">[view]</a>
                <br>
                <br>
            </li>
        <?php endforeach; ?>
    </ul>

    <h3>Outbound external links (links count: <?=$count['outbound_external']?>)</h3>
    <ul>
        <?php foreach ($links_data['outbound_external'] as $link) : ?>
            <li>
                <a href="<?=esc_url($link->url)?>" target="_blank" style="text-decoration: underline">
                    <?=esc_url($link->url)?>
                    <br>
                    <b>[<?=esc_attr($link->anchor)?>]</b>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</div>