"use strict";

(function ($)
{

	/////////// preloading
	$('[data-wpil-ajax-container]').each(function(k, el)
	{
		var $el = $(el);
		var url = $el.attr('data-wpil-ajax-container-url');
		var count = 0;
		var urlParams = parseURLParams(url);

		if(urlParams.type && 'outbound_suggestions_ajax' === urlParams.type[0]){
			ajaxGetSuggestionsOutbound($el, url, count);
		}else if(urlParams.type && 'inbound_suggestions_page_container' === urlParams.type[0]){
			ajaxGetSuggestionsInbound($el, url, count);
		}
	});

	function ajaxGetSuggestionsInbound($el, url, count, lastPost = 0, processedPostCount = 0, key = null)
	{
		var urlParams = parseURLParams(url);
		var post_id = (urlParams.post_id) ? urlParams.post_id[0] : null;
		var term_id = (urlParams.term_id) ? urlParams.term_id[0] : null;
		var keywords = (urlParams.keywords) ? urlParams.keywords[0] : '';
		var sameCategory = (urlParams.same_category) ? urlParams.same_category[0] : '';
        var nonce = (urlParams.nonce) ? urlParams.nonce[0]: '';

        if(!nonce){
            return;
        }

        // if there isn't a key set, make one
        if(!key){
            while(true){
                key = Math.round(Math.random() * 1000000000);
                if(key > 999999){break;}
            }
        }

		jQuery.ajax({
			type: 'POST',
			url: ajaxurl,
			data: {
				action: 'get_post_suggestions',
                nonce: nonce,
				count: count,
				post_id: post_id,
                term_id: term_id,
				type: 'inbound_suggestions',
				keywords: keywords,
				same_category: sameCategory,
				last_post: lastPost,
                completed_processing_count: processedPostCount,
                key: key,
			},
			success: function(response){
                // if there was an error
                if(response.error){
                    // output the error message
                    swal(response.error.title, response.error.text, 'error');
                    // and exit
                    return;
                }

				count = parseInt(count) + 1;

				if(false && !response.completed){
					ajaxGetSuggestionsInbound($el, url, count, response.last_post, response.completed_processing_count, key);
				}else{
					return updateSuggestionDisplay(post_id, term_id, nonce, $el, 'inbound_suggestions', sameCategory, key);
				}
			}
		});
	}

	function ajaxGetSuggestionsOutbound($el, url, count, key = null)
	{
        // if there isn't a key set, make one
        if(!key){
            while(true){
                key = Math.round(Math.random() * 1000000000);
                if(key > 999999){break;}
            }
        }

		var urlParams = parseURLParams(url);
		var post_id = (urlParams.post_id) ? urlParams.post_id[0] : null;
		var term_id = (urlParams.term_id) ? urlParams.term_id[0] : null;
		var sameCategory = (urlParams.same_category) ? urlParams.same_category[0] : '';
        var nonce = (urlParams.nonce) ? urlParams.nonce[0]: '';

        if(!nonce){
            return;
        }

		jQuery.ajax({
			type: 'POST',
			url: ajaxurl,
			data: {
				action: 'get_post_suggestions',
                nonce: nonce,
				count: count,
				post_id: post_id,
                term_id: term_id,
				same_category: sameCategory,
				type: 'outbound_suggestions',
                key: key,
			},
			success: function(response){
                // if there was an error
                if(response.error){
                    // output the error message
                    swal(response.error.title, response.error.text, 'error');
                    // and exit
                    return;
                }

				count = parseInt(count) + 1;

				if((count * response.batch_size) < response.post_count){
					ajaxGetSuggestionsOutbound($el, url, count, key);
				}else{
					return updateSuggestionDisplay(post_id, term_id, nonce, $el, 'outbound_suggestions', sameCategory, key);
				}
			},
            error: function(jqXHR, textStatus, errorThrown){
                console.log({jqXHR, textStatus, errorThrown});
            }
		});
	}

	function updateSuggestionDisplay(postId, termId, nonce, $el, type = 'outbound_suggestions', sameCategory = '', key = null){
		jQuery.ajax({
			type: 'POST',
			url: ajaxurl,
			data: {
				action: 'update_suggestion_display',
                nonce: nonce,
				post_id: postId,
                term_id: termId,
                key: key,
				type: type,
				same_category: sameCategory,
			},
			success: function(response){
                // if there was an error
                if(response.error){
                    // output the error message
                    swal(response.error.title, response.error.text, 'error');
                    // and exit
                    return;
                }

                // update the suggestion report
				$el.html(response);
			}
		});
	}

    /**
     * Helper function that parses urls to get their query vars.
     **/
	function parseURLParams(url) {
		var queryStart = url.indexOf("?") + 1,
			queryEnd   = url.indexOf("#") + 1 || url.length + 1,
			query = url.slice(queryStart, queryEnd - 1),
			pairs = query.replace(/\+/g, " ").split("&"),
			parms = {}, i, n, v, nv;
	
		if (query === url || query === "") return;
	
		for (i = 0; i < pairs.length; i++) {
			nv = pairs[i].split("=", 2);
			n = decodeURIComponent(nv[0]);
			v = decodeURIComponent(nv[1]);
	
			if (!parms.hasOwnProperty(n)) parms[n] = [];
			parms[n].push(nv.length === 2 ? v : null);
		}
		return parms;
	}

	function wpilImplodeEls(sep, els)
	{
		var res = [];
		$(els).each(function(k, el) {
			res.push(el.outerHTML);
		});

		return res.join(sep);
	}

	function wpilImplodeText(sep, els)
	{
		var res = [];
		$(els).each(function(k, el) {
			var $el = $(el);
			res.push($el.text());
		});

		return res.join(sep);
	}

	function wpilPushFix($ex)
	{
		var $div = $("<div/>");
		$div.append($ex);
		return $div.html();
	}

	$(document).on('click', '.wpil_sentence a', function (e) {
		e.preventDefault();
	});

	$(document).on('click', '[class*=wpil_word]', function (e) {
		e.preventDefault();

		var $el = $(this);
		var $cont = $el.closest('.wpil_sentence');

		$cont.find('.wpil_a_first').removeClass('wpil_a_first');
		$cont.find('.wpil_a_last').removeClass('wpil_a_last');
		$cont.find('.wpil_link_init').removeClass('wpil_link_init');
		$cont.find('a span.wpil_word').addClass('wpil_link_init');
		$cont.find('.wpil_click').removeClass('wpil_click');

		$el.addClass('wpil_click');

		var $a = $cont.find('a');
		var $a_prev = $a.clone();
		$a_prev.html('');

		$a.find('span:first').addClass('wpil_a_first');
		$a.find('span:last').addClass('wpil_a_last');

		var $st = $cont.clone();
		var $st_a = $st.find('a');

		$st_a.contents().unwrap();

		var c = {};
		c['prev'] = [];
		c['link'] = [];
		c['next'] = [];

		var mode = 'prev';
		var link_started = false;
		var click_handled = false;

		var $words = $st.find('span.wpil_word');

		var word_id = 0;
		var word_id_attr = 'wpil-word-id';
		$words.each(function(i, el) {
			word_id++;
			var $el = $(el);
			$el.attr(word_id_attr, word_id);
		});

		$words.each(function(i, el) {
			var $el = $(el);
			var word_id = $el.attr(word_id_attr);
			var sel = "[" + word_id_attr + '=' + word_id + ']';
			$el = $st.find(sel);

			function wpilPushCurrent()
			{
				var p = wpilPushFix($el);
				var $px = $("<div/>");
				$px.append(p);
				c[mode].push(p);
			}

			if ($el.hasClass('wpil_click')) {
				click_handled = true;

				if ('prev' == mode) {
					if ($el.hasClass('wpil_link_init')) {
						if ($el.next().hasClass('wpil_link_init')) {
							wpilPushCurrent();
							mode = 'link';
						} else {
							mode = 'link';
							wpilPushCurrent();
							mode = 'next';
						}
					} else {
						mode = 'link';
						wpilPushCurrent();
						var $next = $el.next();
					}
				} else {
					if ('link' == mode && $el.hasClass('wpil_link_init') && c['link'].length > 0) {
						mode = 'next';
						wpilPushCurrent();
					} else {
						if (!$el.hasClass('wpil_link_init')) {
							wpilPushCurrent();
						} else {
							wpilPushCurrent();
						}
						mode = 'next';
					}
				}
			} else if ($el.hasClass('wpil_a_first')) {
				mode = 'link';
				wpilPushCurrent();
				if ($el.hasClass('wpil_a_last') && click_handled) {
					mode = 'next';
				}
			} else if ($el.hasClass('wpil_a_last')) {
				wpilPushCurrent();

				if (click_handled) {
					mode = 'next';
				}
			} else {
				wpilPushCurrent();
			}
		});

		var $st_new = $("<div />");
		var $a_new = $a_prev.clone();

		$a_new.append(c['link'].join(' '));

		$st_new.append(c['prev'].join(' '));
		$st_new.append(" ");
		$st_new.append(wpilImplodeEls(" ", $a_new));
		$st_new.append(" ");
		$st_new.append(c['next'].join(' '));

		$cont.html($st_new.html());
		custom_sentence_refresh($cont);
	});

	var same_category_loading = false;

	$(document).on('change', '#field_same_category', function(){
		if (!same_category_loading) {
			same_category_loading = true;
			var container = $(this).closest('[data-wpil-ajax-container]');
			var url = container.attr('data-wpil-ajax-container-url');
			var urlParams = parseURLParams(url);
			var sameCategory = $(this).prop('checked');
			var checked = '';
			var post_id = (urlParams.post_id) ? urlParams.post_id[0] : 0;

			if (sameCategory) {
				url += "&same_category=true";
				checked = 'checked="checked"';
			}

			if(urlParams.wpil_no_preload && '1' === urlParams.wpil_no_preload[0]){
				var checkAndButton = '<div style="margin-bottom: 15px;"><input style="margin-bottom: -5px;" type="checkbox" name="same_category" id="field_same_category_page" ' + checked + '><label for="field_same_category_page">Only Show Link Suggestions in the Same Category as This Post</label> <br></div><button id="inbound_suggestions_button" class="sync_linking_keywords_list button-primary" data-id="' + post_id + '" data-type="inbound_suggestions_page_container" data-page="inbound">Custom links</button>';
				container.html(checkAndButton);
			}else{
				container.html('<div class="progress_panel loader"><div class="progress_count" style="width: 100%"></div></div>');
			}

			if(urlParams.type && 'outbound_suggestions_ajax' === urlParams.type[0]){
				ajaxGetSuggestionsOutbound(container, url, 0);
			}else if(urlParams.type && 'inbound_suggestions_page_container' === urlParams.type[0]){
				ajaxGetSuggestionsInbound(container, url, 0);
			}

			same_category_loading = false;
		}
	});

	$(document).on('change', '#field_same_category_page', function(){
		var url = document.URL;
		if ($(this).prop('checked')) {
			url += "&same_category=true";
		} else {
			url = url.replace('&same_category=true', '');
		}

		location.href = url;
	});

	$(document).on('click', '.sync_linking_keywords_list', function (e) {
		e.preventDefault();

		var page = $(this).data('page');
		var links = [];
		var data = [];
		$(this).closest('div').find('[wpil-link-new][type=checkbox]:checked').each(function() {
			if (page == 'inbound') {
				var item = {};
				item.id = $(this).closest('tr').find('.sentence').data('id');
				item.type = $(this).closest('tr').find('.sentence').data('type');
				item.links = [{
					'sentence': $(this).closest('tr').find('.sentence').find('[name="sentence"]').val(),
					'sentence_with_anchor': $(this).closest('tr').find('.sentence').find('.wpil_sentence')[0].outerHTML,
					'custom_sentence': $(this).closest('tr').find('input[name="custom_sentence"]').val()
				}];

				data.push(item);
			} else {
				if ($(this).closest('tr').find('input[type="radio"]:checked').length) {
					var id =  $(this).closest('tr').find('input[type="radio"]:checked').data('id');
					var type = $(this).closest('tr').find('input[type="radio"]:checked').data('type');
					var custom_link = $(this).closest('tr').find('input[type="radio"]:checked').data('custom');
				} else {
					var id =  $(this).closest('tr').find('.suggestion').data('id');
					var type =  $(this).closest('tr').find('.suggestion').data('type');
					var custom_link =  $(this).closest('tr').find('.suggestion').data('custom');
				}

				links.push({
					id: id,
					type: type,
					custom_link: custom_link,
					sentence: $(this).closest('div').find('[name="sentence"]').val(),
					sentence_with_anchor: $(this).closest('div').find('.wpil_sentence')[0].outerHTML,
					custom_sentence: $(this).closest('.sentence').find('input[name="custom_sentence"]').val()
				});
			}
		});

		if (page == 'outbound') {
			data.push({'links': links});
		}

		$('.wpil_keywords_list').addClass('ajax_loader');

		var data_post = {
			"id": $(this).data('id'),
			"type": $(this).data('type'),
			"page": $(this).data('page'),
			"action": 'wpil_save_linking_references',
			'data': data,
			'gutenberg' : $('.block-editor-page').length ? true : false
    	};

		$.ajax({
			url: wpil_ajax.ajax_url,
			dataType: 'json',
			data: data_post,
			method: 'post',
			error: function (jqXHR, textStatus, errorThrown) {
				$('.wpil_keywords_list').removeClass('ajax_loader');
				var msg = textStatus;
				msg += "\n\n";
				msg += jqXHR.responseText;
				swal('Error', msg, 'error');
			},
			success: function (data) {
				$('.wpil_keywords_list').removeClass('ajax_loader');

				if (data.err_msg) {
					swal('Error', data.err_msg, 'error');
				} else {
					if (page == 'outbound') {
						if ($('.editor-post-save-draft').length) {
							$('.editor-post-save-draft').click();
						} else if ($('#save-post').length) {
							$('#save-post').click();
						} else if ($('.editor-post-publish-button').length) {
							$('.editor-post-publish-button').click();
						} else if ($('#publish').length) {
							$('#publish').click();
						} else if ($('.edit-tag-actions').length) {
							$('.edit-tag-actions input[type="submit"]').click();
						}
					} else {
						location.reload();
					}
				}
			}
		})
	});

	function stristr(haystack, needle, bool)
	{
		// http://jsphp.co/jsphp/fn/view/stristr
		// +   original by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
		// +   bugfxied by: Onno Marsman
		// *     example 1: stristr('Kevin van Zonneveld', 'Van');
		// *     returns 1: 'van Zonneveld'
		// *     example 2: stristr('Kevin van Zonneveld', 'VAN', true);
		// *     returns 2: 'Kevin '
		var pos = 0;

		haystack += '';
		pos = haystack.toLowerCase().indexOf((needle + '').toLowerCase());

		if (pos == -1) {
			return false;
		} else {
			if (bool) {
				return haystack.substr(0, pos);
			} else {
				return haystack.slice(pos);
			}
		}
	}

	function wpil_handle_errors(resp)
	{
		if (stristr(resp, "520") && stristr(resp, "unknown error") && stristr(resp, "Cloudflare")) {
			swal('Error', "It seems you are using CloudFlare and CloudFlare is hiding some error message. Please temporary disable CloudFlare, open reporting page again, look if it has any new errors and send it to us", 'error')
				.then(wpil_report_next_step);
			return true;
		}

		if (stristr(resp, "504") && stristr(resp, "gateway")) {
			swal('Error', "504 error: Gateway timeout - please ask your hosting support about this error", 'error')
				.then(wpil_report_next_step);
			return true;
		}

		return false;
	}

	function wpil_report_next_step()
	{
		location.reload();
	}

    /**
     * Makes the call to reset the report data when the user clicks on the "Reset Data" button.
     **/
    function resetReportData(e){
        e.preventDefault();
        var form = $(this);
        var reset = form.find('[name="reset"]').val();
        var nonce = form.find('[name="reset_data_nonce"]').val();
       
        if(!reset || !nonce || form.attr('disabled')){
            return;
        }
        
        // disable the reset button
        form.attr('disabled', true);
        // add a color change to the button indicate it's disabled
        form.find('.button-primary').addClass('wpil_button_is_active');
        processReportReset(nonce, 0, true);
    }


    var timeList = [];    
    function processReportReset(nonce = null, loopCount = 0, clearData = false){
        
        if(!nonce){
            return;
        }

        jQuery.ajax({
			type: 'POST',
			url: ajaxurl,
			data: {
				action: 'reset_report_data',
                nonce: nonce,
                loop_count: loopCount,
                clear_data: clearData,
			},
            error: function (jqXHR, textStatus, errorThrown) {
				var resp = jqXHR.responseText;

				if (wpil_handle_errors(resp)) {
					wpil_report_next_step();
					return;
				}

				var msg = '';

				if (textStatus != 'parsererror') {
					msg += textStatus;
					msg += "\n\n";
				}

				msg += resp;

				swal('Error', msg, 'error')
					.then(wpil_report_next_step);
			},
			success: function(response){
                // if there was an error
                if(response.error){
                    // output the error message
                    swal(response.error.title, response.error.text, 'error');
                    // and exit
                    return;
                }
                
                // if we've been around a couple times without processing links, there must have been an error
                if(!response.links_to_process_count && response.loop_count > 5){
                    // output the error message
                    swal('Data Reset Error', 'Link Whisper has tried a number of times to reset the report data, and it hasn\'t been able to complete the action.', 'error');
                    // and exit
                    return;
                }

                // if the data has been successfully reset
                if(response.data_setup_complete){
                    // do completion
                    // set the loading screen now that the data setup is complete
                    if(response.loading_screen){
                        $('#wpbody-content').html(response.loading_screen);
                    }
                    // set the time
                    timeList.push(response.time);
                    // and call the data processing function to handle the data
                    processReportData(response.nonce, 0, 0, 0);
                }else{
                    // if we're not done processing links, go around again
                    processReportReset(response.nonce, (response.loop_count + 1), true);
                }
			}
		});
    }

    // listen for clicks on the "Reset Data" button
    $('#wpil_report_reset_data_form').on('submit', resetReportData);

    /**
     * Process runner that handles the report data generation process.
     * Loops around until all the site's links are inserted into the LW link table
     **/
    function processReportData(nonce = null, loopCount = 0, linkPostsToProcessCount = 0, linkPostsProcessed = 0, metaFilled = false, linksFilled = false){
        if(!nonce){
            return;
        }

        // initialize the stage clock. // The clock is useful for debugging
        if(loopCount < 1){
            if(timeList.length > 0){
                var lastTime = timeList.pop();
                timeList = [lastTime];
            }else{
                timeList = [];
            }
        }

        jQuery.ajax({
			type: 'POST',
			url: ajaxurl,
			data: {
				action: 'process_report_data',
                nonce: nonce,
                loop_count: loopCount,
                link_posts_to_process_count: linkPostsToProcessCount,
                link_posts_processed: linkPostsProcessed,
                meta_filled: metaFilled,
                links_filled: linksFilled
			},
            error: function (jqXHR, textStatus, errorThrown) {
				var resp = jqXHR.responseText;

				if (wpil_handle_errors(resp)) {
					wpil_report_next_step();
					return;
				}

				var msg = '';

				if (textStatus != 'parsererror') {
					msg += textStatus;
					msg += "\n\n";
				}

				msg += resp;

				swal('Error', msg, 'error')
					.then(wpil_report_next_step);
			},
			success: function(response){
                console.log(response);

                // if there was an error
                if(response.error){
                    // output the error message
                    swal(response.error.title, response.error.text, 'error');
                    // and exit
                    return;
                }

                // log the time
                timeList.push(response.time);

                // if the meta has been successfully processed
                if(response.meta_processing_complete){
                    // show the link processing loading screen
                    if(response.loading_screen){
                        $('#wpbody-content').html(response.loading_screen);
                    }
                    // console.log the time if available
                    if(timeList > 1){
                        console.log('The meta processing took: ' + (timeList[(timeList.length - 1)] - timeList[0]) + ' seconds.');
                    }
                    
                    // and recall the function to begin the link processing (loading the site's links into the link table)
                    processReportData(  response.nonce,                         // nonce
                                        0,                                      // loop count
                                        response.link_posts_to_process_count,   // posts/cats to process count
                                        0,                                      // how many have been processed so far
                                        response.meta_filled,                   // if the meta processing is complete
                                        response.links_filled);                 // if the link processing is complete
                }else if(response.link_processing_complete){
                // if we've finished loading links into the link table
                    // show the post processing loading page
                    if(response.loading_screen){
                        $('#wpbody-content').html(response.loading_screen);
                    }
                    
                    // console.log the time if available
                    if(timeList > 1){
                        console.log('The link processing took: ' + (timeList[(timeList.length - 1)] - timeList[0]) + ' seconds.');
                    }
                    
                    // re-call the function for the final round of processing
                    processReportData(  response.nonce, 
                                        0, 
                                        response.link_posts_to_process_count, 
                                        0,
                                        response.meta_filled,
                                        response.links_filled);
                    
                }else if(response.processing_complete){
                // if the processing is complete
                    // console.log the time if available
                    if(timeList > 1){
                        console.log('The post processing took: ' + (timeList[(timeList.length - 1)] - timeList[0]) + ' seconds.');
                    }

                    // update the loading bar one more time
                    animateTheReportLoadingBar(response);
                    
                    // and show the user a success message!
                    swal('Success!', 'Synchronization has been completed.', 'success').then(wpil_report_next_step);
                    
                }else{
                    // if we're not done processing, go around again
                    processReportData(  response.nonce, 
                                        (response.loop_count + 1), 
                                        response.link_posts_to_process_count, 
                                        response.link_posts_processed,
                                        response.meta_filled,
                                        response.links_filled);
                    
                    // if the meta has been processed
                    if(response.meta_filled){
                        // update the loading bar
                        animateTheReportLoadingBar(response);
                    }
                }
			}
		});
    }

    /**
     * Updates the loading bar length and the displayed completion status.
     * 
     * A possible improvement might be to progressively update the loading bar so its more interesting.
     * As it is now, the bar jumps every 60s, so it might be a bit dull and the user might wonder if it's working.
     **/
    function animateTheReportLoadingBar(response){
        // get the loading display
        var loadingDisplay = $('#wpbody-content .wpil-loading-screen');
        // create some variable to update the display with
        var percentCompleted = Math.floor((response.link_posts_processed/response.link_posts_to_process_count) * 100);
        var displayedStatus = percentCompleted + '%' + ((response.links_filled) ? (', ' + response.link_posts_processed + '/' + response.link_posts_to_process_count) : '') + ' ' + wpil_ajax.completed;
//        var oldPercent = parseInt(loadingDisplay.find('.progress_count').css('width'));

        // update the display with the new info
        loadingDisplay.find('.wpil-loading-status').text(displayedStatus);
        loadingDisplay.find('.progress_count').css({'width': percentCompleted + '%'});
    }

	$(document).on('click', '.wpil-collapsible', function (e) {
		if ($(this).hasClass('wpil-no-action') || $(e.target).hasClass('wpil_word') || $(e.target).hasClass('add_custom_link_button') || $(e.target).hasClass('add_custom_link') || $(e.target).parents('.add_custom_link').length) {
			return;
		}

		e.preventDefault();

		var $el = $(this);
		var $content = $el.closest('.wpil-collapsible-wrapper').find('.wpil-content');
		var cl_active = 'wpil-active';

		if ($el.hasClass(cl_active)) {
			$el.removeClass(cl_active);
			$content.hide();
		} else {
			$el.addClass(cl_active);
			$content.show();
		}
	});

	$(document).ready(function(){
		var saving = false;

		if (typeof wp.data != 'undefined' && typeof wp.data.select('core/editor') != 'undefined') {
			wp.data.subscribe(function () {

				if (document.body.classList.contains( 'block-editor-page' ) && !saving) {
					saving = true;
					setTimeout(function(){
						$.post( ajaxurl, {action: 'wpil_editor_reload', post_id: $('#post_ID').val()}, function(data) {
							if (data == 'reload') {
								location.reload();
							}

							saving = false;
						});
					}, 1000);
				}
			});
		}

		if ($('#post_ID').length) {
			$.post( ajaxurl, {action: 'wpil_is_outbound_links_added', id: $('#post_ID').val(), type: 'post'}, function(data) {
				if (data == 'success') {
					swal('Success', 'Links have been added successfully', 'success');
				}
			});
		}

		if ($('#inbound_suggestions_page').length) {
			var id  = $('#inbound_suggestions_page').data('id');
			var type  = $('#inbound_suggestions_page').data('type');

			$.post( ajaxurl, {action: 'wpil_is_inbound_links_added', id: id, type: type}, function(data) {
				if (data == 'success') {
					swal('Success', 'Links have been added successfully', 'success');
				}
			});
		}

		$(document).on('click', '#select_all', function () {
			if ($(this).prop('checked')) {
				$(this).closest('table').find('input[type="checkbox"]').prop('checked', true);
			} else {
				$(this).closest('table').find('input[type="checkbox"]').prop('checked', false);
			}
		});

		$(document).on('click', '.best_keywords.outbound .wpil-collapsible-wrapper input[type="radio"]', function () {
			var data = $(this).closest('li').find('.data').html();
			var id = $(this).data('id');
			var type = $(this).data('type');
			var suggestion = $(this).data('suggestion');
			$(this).closest('ul').find('input').prop('checked', false);

			$(this).prop('checked', true);
			$(this).closest('.wpil-collapsible-wrapper').find('.wpil-collapsible-static').html(data + '<span class="add_custom_link_button"> | <a href="javascript:void(0)">Custom Link</a></span>');
			$(this).closest('tr').find('input[type="checkbox"]').prop('checked', false);
			$(this).closest('tr').find('input[type="checkbox"]').val(suggestion + ',' + id);

			if (!$(this).closest('tr').find('input[data-wpil-custom-anchor]').length && $(this).closest('tr').find('.sentence[data-id="'+id+'"][data-type="'+type+'"]').length) {
				$(this).closest('tr').find('.sentences > div').hide();
				$(this).closest('tr').find('.sentence[data-id="'+id+'"][data-type="'+type+'"]').show();
			}
		});

		$(document).on('click', '.best_keywords.inbound .wpil-collapsible-wrapper input[type="radio"]', function () {
			var data = $(this).closest('li').find('.data').html();
			$(this).closest('ul').find('input').prop('checked', false);
			$(this).prop('checked', true);
			$(this).closest('.wpil-collapsible-wrapper').find('.sentence').html(data + '<span class="wpil_edit_sentence"><i class="dashicons dashicons-edit"></i> Edit sentence</span>');
			$(this).closest('tr').find('input[type="checkbox"]').prop('checked', false);
		});

		//delete link from post content
		$(document).on('click', '.wpil_link_delete', function () {
			if (confirm("Are you sure you want to delete this link?")) {
				var el = $(this);
				var data = {
					url: el.data('url'),
					anchor: el.data('anchor'),
					post_id: el.data('post_id'),
					post_type: el.data('post_type'),
					link_id: typeof el.data('link_id') !== 'undefined' ? el.data('link_id') : ''
				};

				$.post('admin.php?page=link_whisper&type=delete_link', data, function(){
					if (el.hasClass('broken_link')) {
						el.closest('tr').fadeOut(300);
					} else {
						el.closest('li').fadeOut(300);
					}
				});
			}
		});

		//show links chart in dashboard
		if ($('#wpil_links_chart').length) {
			var internal = $('input[name="internal_links_count"]').val();
			var external = $('input[name="total_links_count"]').val() - $('input[name="internal_links_count"]').val();

			$('#wpil_links_chart').jqChart({
				title: { text: '' },
				legend: {
					title: '',
					font: '15px sans-serif',
					location: 'top',
					border: {visible: false}
				},
				border: { visible: false },
				animation: { duration: 1 },
				shadows: {
					enabled: true
				},
				series: [
					{
						type: 'pie',
						fillStyles: ['#418CF0', '#FCB441'],
						labels: {
							stringFormat: '%d',
							valueType: 'dataValue',
							font: 'bold 15px sans-serif',
							fillStyle: 'white',
							fontWeight: 'bold'
						},
						explodedRadius: 8,
						explodedSlices: [1],
						data: [['Internal', internal], ['External', external]],
						labelsPosition: 'inside', // inside, outside
						labelsAlign: 'circle', // circle, column
						labelsExtend: 20,
						leaderLineWidth: 1,
						leaderLineStrokeStyle: 'black'
					}
				]
			});
		}
	});

	//send request to proceed broken links search
	function wpil_error_process(link = '')
	{
		$.post(ajaxurl, {
			action: 'wpil_error_process',
			link: link
		}, function(response){
			$('.progress_count:first').css('width', response.percents + '%');
			$('.wpil-loading-status:first').text(response.status);

			if (response.finish) {
				swal('Success!', 'Synchronization has been completed.', 'success').then(function(){
					location.reload();
				});
			} else {
				wpil_error_process(response.link);
			}
		});
	}

	//send request to reset data about broken links
	$('#wpil_error_reset_data_form').on('submit', function(e){
		e.preventDefault();
		var nonce = $(this).find('input[name="nonce"]').val();

		$.post(ajaxurl, {
			action: 'wpil_error_reset_data',
			nonce: nonce
		}, function(response){
			if (typeof response.error != 'undefined') {
				swal(response.error.title, response.error.text, 'error');
				return;
			} else if (typeof response.template != 'undefined') {
				$('#wpbody-content').html(response.template);
				wpil_error_process();
			}
		}, 'json');
	});

	//show progress bar and send search request if user interrupted the search
	if (typeof error_reset_run != 'undefined' && error_reset_run) {
		$.post(ajaxurl, {
			action: 'wpil_error_process',
			link: '',
			get_status: 1
		}, function(response){
			$('.progress_count:first').css('width', response.percents + '%');
			$('.wpil-loading-status:first').text(response.status);
			wpil_error_process();
		});
	}

	$(document).on('click', '.add_custom_link_button', function(e){
		$(this).closest('div').append('<div class="add_custom_link">' +
			'<input type="text" placeholder="Custom Link">' +
			'<span class="button-primary">' +
			'<i class="mce-ico mce-i-dashicon dashicons-editor-break"></i>' +
			'</span>' +
			'</div>');
	});

	$(document).on('click', '.add_custom_link span', function(){
		var el = $(this);
		var link = el.parent().find('input').val();
		if (link) {
			$.post(ajaxurl, {link: link, action: 'wpil_get_link_title'}, function (response) {
				response = $.parseJSON(response);
				if (!el.parents('.wpil-collapsible-wrapper').length) {
					var suggestion = el.closest('.suggestion');
					suggestion.html(response.title + '<br><a class="post-slug" target="_blank" href="'+link+'">'+response.link+'</a>' +
						'<span class="add_custom_link_button"> | <a href="javascript:void(0)">Custom Link</a></span>');
					suggestion.data('id', response.id);
					suggestion.data('type', response.type);
					suggestion.data('custom', response.link);
				} else {
					var wrapper = el.closest('.wpil-collapsible-wrapper');
					wrapper.find('input[type="radio"]').prop('checked', false);
					wrapper.find('.wpil-content ul').prepend('<li>' +
						'<div>' +
						'<input type="radio" checked="" data-id="'+response.id+'" data-type="'+response.type+'" data-suggestion="-1" data-custom="'+link+'">' +
						'<span class="data">' +
						'<span style="opacity:1">'+response.title+'</span><br>' +
						'<a class="post-slug" target="_blank" href="'+link+'">'+response.link+'</a>\n' +
						'</span>' +
						'</div>' +
						'</li>');
					wrapper.find('input[type="radio"]')[0].click();
					wrapper.find('.wpil-collapsible').addClass('wpil-active');
					wrapper.find('.wpil-content').show();
				}
			});
		} else {
			alert("The link is empty!");
		}
	});

	//show edit sentence form
	$(document).on('click', '.wpil_edit_sentence', function(){
		var block = $(this).closest('.sentence');
		var form = block.find('.wpil_edit_sentence_form');
		var id = 'wpil_editor' + block.data('id');
		var sentence = form.find('.wpil_content').html();

		if (typeof inbound_internal_link !== 'undefined') {
			var link = inbound_internal_link;
		} else {
			var link = $(this).closest('tr').find('.post-slug:first').attr('href');
		}

		sentence = sentence.replace('%view_link%', link);
		form.find('.wpil_content').attr('id', id).html(sentence).show();
		form.show();
		var textarea_height = form.find('.wpil_content').height() + 100;
		form.find('.wpil_content').height(textarea_height);
		wp.editor.initialize(id, {
			tinymce: true,
			quicktags: true,
		});

		block.find('input[type="checkbox"], .wpil_sentence, .wpil_edit_sentence').hide();
		form.find('.wpil_content').hide();
		form.show();
	});

	//Cancel button pressed
	$(document).on('click', '.wpil_edit_sentence_form .button-secondary', function(){
		var block = $(this).closest('.sentence');
		wpil_editor_remove(block);
	});

	//Save edited sentence
	$(document).on('click', '.wpil_edit_sentence_form .button-primary', function(){
		var block = $(this).closest('.sentence');
		var id = 'wpil_editor' + block.data('id');

		//get content from the editor
		var sentence;
		if ($('#' + id).css('display') == 'none') {
			var editor = tinyMCE.get(id);
			sentence = editor.getContent();
		} else {
			sentence = $('#' + id).val();
		}

		//remove multiple whitespaces and outer P tag
		if (sentence.substr(0,3) == '<p>') {
			sentence = sentence.substr(3);
		}
		if (sentence.substr(-4) == '</p>') {
			sentence = sentence.substr(0, sentence.length - 4);
		}
		var sentence_clear = sentence;

		//put each word to span
		var link = sentence.match(/<a[^>]+>/);
		if (link[0] != null) {
			sentence = sentence.replace(/<a[^>]+\s*>/, ' %link_start% ');
			sentence = sentence.replace(/\s*<\/a>/, ' %link_end% ');
		}

		sentence = sentence.replace(/\s+/g, ' ');
		sentence = sentence.replace(/ /g, '</span> <span class="wpil_word">');
		sentence = '<span class="wpil_word">' + sentence + '</span>';
		if (link[0] != null) {
			sentence = sentence.replace(/<span class="wpil_word">%link_start%<\/span>/g, link[0]);
			sentence = sentence.replace(/<span class="wpil_word">%link_end%<\/span>/g, '</a>');
		}

		//hide form and show sentence
		block.find('.wpil_sentence').html(sentence);
		block.find('input[name="custom_sentence"]').val(btoa(unescape(encodeURIComponent(sentence_clear))));
		wpil_editor_remove(block)
	});

	//Remove WP Editor after sentence editing
	function wpil_editor_remove(block) {
		var form = block.find('.wpil_edit_sentence_form');
		var textarea_height = form.find('.wpil_content').height() - 100;
		form.find('.wpil_content').height(textarea_height);
		form.hide();
		form.find('.wpil_content').attr('id', '').prependTo(form);
		wp.editor.remove('wpil_editor' + block.data('id'));
		form.find('.wp-editor-wrap').remove();
		block.find('input[type="checkbox"], .wpil_sentence, .wpil_edit_sentence').show();
	}

	function custom_sentence_refresh(el) {
		var input = el.closest('.sentence').find('input[name="custom_sentence"]');
		var sentence = el.closest('.wpil_sentence').html();
		sentence = sentence.replace(/<span[^>]+>/g, '');
		sentence = sentence.replace(/<\/span>/g, '');
		el.closest('.sentence').find('.wpil_content').html(sentence);

		if (input.val() !== '') {
			input.val(btoa(unescape(encodeURIComponent(sentence))));
		}
	}
})(jQuery);
