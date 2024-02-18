<table class="wp-list-table widefat fixed striped posts tbl_keywords_x js-table wpil-outbound-links" id="tbl_keywords">
    <?php if (!empty($phrases)) : ?>
        <thead>
        <tr>
            <th>
                <input type="checkbox" id="select_all">
                <b>Phrase</b>
            </th>
            <th><b>Post</b></th>
        </tr>
        </thead>
        <tbody id="the-list">
        <?php foreach ($phrases as $key_phrase => $phrase) : ?>
            <tr data-wpil-sentence-id="<?=esc_attr($key_phrase)?>">
                <td class="sentences">
                    <?php foreach ($phrase->suggestions as $suggestion) : ?>
                        <div class="sentence" data-id="<?=esc_attr($suggestion->post->id)?>" data-type="<?=esc_attr($suggestion->post->type)?>">
                            <div class="wpil_edit_sentence_form">
                                <textarea class="wpil_content"><?=$suggestion->sentence_src_with_anchor?></textarea>
                                <span class="button-primary">Save</span>
                                <span class="button-secondary">Cancel</span>
                            </div>
                            <input type="checkbox" name="link_keywords[]" class="chk-keywords" wpil-link-new="">
                            <?=$suggestion->sentence_with_anchor?>
                            <span class="wpil_edit_sentence">| <a href="javascript:void(0)">Edit Sentence</a></span>
                            <?=!empty(Wpil_Suggestion::$undeletable)?' ('.esc_attr($suggestion->anchor_score).')':''?>
                            <input type="hidden" name="sentence" value="<?=base64_encode($phrase->sentence_src)?>">
                            <input type="hidden" name="custom_sentence" value="">
                        </div>
                    <?php endforeach; ?>
                </td>
                <td>
                    <?php if (count($phrase->suggestions) > 1) : ?>
                        <div class="wpil-collapsible-wrapper">
                            <div class="wpil-collapsible wpil-collapsible-static wpil-links-count">
                                <div style="opacity:<?=$phrase->suggestions[0]->opacity?>">
                                    <?=esc_attr($phrase->suggestions[0]->post->title)?>
                                    <?=!empty(Wpil_Suggestion::$undeletable)?' ('.esc_attr($phrase->suggestions[0]->post_score).')':''?>
                                    <br>
                                    <a class="post-slug" target="_blank" href="<?=$phrase->suggestions[0]->post->links->view?>">/<?=$phrase->suggestions[0]->post->getSlug()?></a>
                                    <span class="add_custom_link_button"> | <a href="javascript:void(0)">Custom Link</a></span>
                                </div>
                            </div>
                            <div class="wpil-content" style="display: none;">
                                <ul>
                                    <?php foreach ($phrase->suggestions as $key_suggestion => $suggestion) : ?>
                                        <li>
                                            <div>
                                                <input type="radio" <?=!$key_suggestion?'checked':''?> data-id="<?=esc_attr($suggestion->post->id)?>" data-type="<?=esc_attr($suggestion->post->type)?>" data-suggestion="<?=esc_attr($key_suggestion)?>">
                                                <span class="data">
                                                    <span style="opacity:<?=$suggestion->opacity?>"><?=esc_attr($suggestion->post->title)?></span>
                                                    <?=!empty(Wpil_Suggestion::$undeletable)?' ('.esc_attr($suggestion->post_score).')':''?>
                                                    <br>
                                                    <a class="post-slug" target="_blank" href="<?=$suggestion->post->links->view?>">/<?=$suggestion->post->getSlug()?></a>
                                                </span>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    <?php else : ?>
                        <div style="opacity:<?=$phrase->suggestions[0]->opacity?>" class="suggestion" data-id="<?=esc_attr($phrase->suggestions[0]->post->id)?>" data-type="<?=esc_attr($phrase->suggestions[0]->post->type)?>">
                            <?=esc_attr($phrase->suggestions[0]->post->title)?>
                            <?=!empty(Wpil_Suggestion::$undeletable)?' ('.esc_attr($phrase->suggestions[0]->post_score).')':''?>
                            <br>
                            <a class="post-slug" target="_blank" href="<?=$phrase->suggestions[0]->post->links->view?>">
                                /<?=urldecode($phrase->suggestions[0]->post->getSlug())?>
                            </a>
                            <span class="add_custom_link_button"> | <a href="javascript:void(0)">Custom Link</a></span>
                        </div>
                    <?php endif; ?>
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
