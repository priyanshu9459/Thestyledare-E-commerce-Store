<table class="wp-list-table widefat fixed striped posts tbl_keywords_x js-table wpil-outbound-links best_keywords inbound" id="tbl_keywords">
    <?php if (!empty($groups)) : ?>
        <thead>
        <tr>
            <th style="width: 25px;"><input type="checkbox" id="select_all"></th>
            <th><b>Phrase</b></th>
            <th><b>Post</b></th>
            <th><b>Date Published</b></th>
        </tr>
        </thead>
        <tbody id="the-list">
        <?php foreach ($groups as $post_id => $group) : $phrase = $group[0]; ?>
            <tr data-wpil-sentence-id="<?=esc_attr($post_id)?>">
                <td>
                    <input type="checkbox" name="link_keywords[]" class="chk-keywords" wpil-link-new="">
                </td>
                <td class="sentences">
                    <?php if (count($group) > 1) : ?>
                        <div class="wpil-collapsible-wrapper">
                            <div class="wpil-collapsible wpil-collapsible-static wpil-links-count">
                                <div class="sentence" data-id="<?=esc_attr($post_id)?>" data-type="<?=esc_attr($phrase->suggestions[0]->post->type)?>">
                                    <div class="wpil_edit_sentence_form">
                                        <textarea class="wpil_content"><?=$phrase->suggestions[0]->sentence_src_with_anchor?></textarea>
                                        <span class="button-primary">Save</span>
                                        <span class="button-secondary">Cancel</span>
                                    </div>
                                    <?=$phrase->suggestions[0]->sentence_with_anchor?>
                                    <span class="wpil_edit_sentence">| <a href="javascript:void(0)">Edit Sentence</a></span>
                                    <?=!empty(Wpil_Suggestion::$undeletable)?' ('.esc_attr($phrase->suggestions[0]->anchor_score).')':''?>
                                    <input type="hidden" name="sentence" value="<?=base64_encode($phrase->sentence_src)?>">
                                    <input type="hidden" name="custom_sentence" value="">
                                </div>
                            </div>
                            <div class="wpil-content" style="display: none;">
                                <ul>
                                    <?php foreach ($group as $key_phrase => $phrase) : ?>
                                        <li>
                                            <div>
                                                <input type="radio" <?=!$key_phrase?'checked':''?>>
                                                <div class="data">
                                                    <div class="wpil_edit_sentence_form">
                                                        <textarea class="wpil_content"><?=$phrase->suggestions[0]->sentence_src_with_anchor?></textarea>
                                                        <span class="button-primary">Save</span>
                                                        <span class="button-secondary">Cancel</span>
                                                    </div>
                                                    <?=$phrase->suggestions[0]->sentence_with_anchor?>
                                                    <?=!empty(Wpil_Suggestion::$undeletable)?' ('.esc_attr($phrase->suggestions[0]->anchor_score).')':''?>
                                                    <input type="hidden" name="sentence" value="<?=base64_encode($phrase->sentence_src)?>">
                                                    <input type="hidden" name="custom_sentence" value="">
                                                </div>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    <?php else : ?>
                        <div class="sentence" data-id="<?=esc_attr($post_id)?>" data-type="<?=esc_attr($phrase->suggestions[0]->post->type)?>">
                            <div class="wpil_edit_sentence_form">
                                <textarea class="wpil_content"><?=$phrase->suggestions[0]->sentence_src_with_anchor?></textarea>
                                <span class="button-primary">Save</span>
                                <span class="button-secondary">Cancel</span>
                            </div>
                            <?=$phrase->suggestions[0]->sentence_with_anchor?>
                            <span class="wpil_edit_sentence">| <a href="javascript:void(0)">Edit Sentence</a></span>
                            <?=!empty(Wpil_Suggestion::$undeletable)?' ('.esc_attr($phrase->suggestions[0]->anchor_score).')':''?>
                            <input type="hidden" name="sentence" value="<?=base64_encode($phrase->sentence_src)?>">
                            <input type="hidden" name="custom_sentence" value="">
                        </div>
                    <?php endif; ?>
                </td>
                <td>
                    <div style="opacity:<?=$phrase->suggestions[0]->opacity?>" class="suggestion">
                        <?=esc_attr($phrase->suggestions[0]->post->title)?>
                        <?=!empty(Wpil_Suggestion::$undeletable)?' ('.esc_attr($phrase->suggestions[0]->post_score).')':''?>
                        <br>
                        <a class="post-slug" target="_blank" href="<?=$phrase->suggestions[0]->post->links->view?>">
                            /<?=$phrase->suggestions[0]->post->getSlug()?>
                        </a>
                    </div>
                </td>
                <td>
                    <?=($phrase->suggestions[0]->post->type=='post'?get_the_date('', $phrase->suggestions[0]->post->id):'not set')?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    <?php else : ?>
        <tr>
            <td>No suggestions found</td>
        </tr>
    <?php endif; ?>
</table>
<script>
    var inbound_internal_link = '<?=$post->links->view?>';
</script>
