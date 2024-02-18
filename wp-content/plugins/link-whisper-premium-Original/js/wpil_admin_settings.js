"use strict";

(function ($)
{
    
    var hasChangedLanguage = false;



    $('.debug-settings-carrot').on('click', openCloseDebugSettings);
    function openCloseDebugSettings(){
        var $setting = $(this),
            active = $setting.hasClass('active');
        if(active){
            $setting.removeClass('active');
            $('.debug-setting-control').css({'height': '0px', 'overflow': 'hidden'});
        }else{
            $setting.addClass('active');
            $('.debug-setting-control').css('height', 'initial');
        }
    }
    
    $('#wpil-selected-language').on('change', updateDisplayedIgnoreWordList);
    function updateDisplayedIgnoreWordList(){

        var wordLists = $('#wpil-available-language-word-lists').val(),
            selectedLang = $('#wpil-selected-language').val();
        if(!wordLists){
            return;
        }

        if(!hasChangedLanguage){
            var str1 = $('#wpil-currently-selected-language-confirm-text-1').val();
            var str2 = $('#wpil-currently-selected-language-confirm-text-2').val();
            var text = (str1 + '\n\n' + str2);

            swal({
                title: 'Notice:',
                text: (text) ? text: 'Changing Link Whisper\'s language will replace the current Words to be Ignored with a new list of words. \n\n If you\'ve added any words to the Words to be Ignored area, this will erase them.',
                icon: 'info',
                buttons: {
                    cancel: true,
                    confirm: true,
                },
                }).then((replace) => {
                  if (replace) {
                        wordLists = JSON.parse(wordLists);
                        if(wordLists[selectedLang]){
                            $('#ignore_words').val(wordLists[selectedLang].join('\n'));
                            $('#wpil-currently-selected-language').val(selectedLang);
                            hasChangedLanguage = true;
                        }
                  } else {
                    $('#wpil-selected-language').val($('#wpil-currently-selected-language').val());
                  }
                });
        }else{
            wordLists = JSON.parse(wordLists);
            if(wordLists[selectedLang]){
                $('#ignore_words').val(wordLists[selectedLang].join('\n'));
                $('#wpil-currently-selected-language').val(selectedLang);
            }
        }
    }
    
    
})(jQuery);
