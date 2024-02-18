jQuery(document).ready(function(){	 
	jQuery('#sortable').sortable({
	    update: function (event, ui) {
	        var sortedData = jQuery(this).sortable('serialize');
	        var submit = jQuery ("#ets-qus-form").serialize(); 
	        console.log(submit);
	        jQuery.ajax({
	            url: etsWooQaParams.admin_ajax,
	            type: 'POST',   
				data: 'action=ets_qa_save_order&product_id=' + etsWooQaParams.currentProdcutId + '&' + sortedData + "&changeOrderQa=" + etsWooQaParams.changeOrderQa,
	        });
	    }
	});

 	jQuery('.ets-del-qa').click(function(e){
		e.preventDefault();
		var conformation = confirm("Are you sure to delete permanently?");
    	if(conformation == true) { 
			let questionNumber = jQuery(this).data('questionkey');
			let LiData = jQuery("li.ets-qa-item").serialize(); 
			let prdId = etsWooQaParams.currentProdcutId; 
			jQuery.ajax({ 
				url: etsWooQaParams.admin_ajax,
				type: 'POST',  
				data:'action=etsdelete_qusetion_answer&questionIndex=' + questionNumber +'&prdId='+ prdId + '&deleteQaNonce=' + etsWooQaParams.deleteQaNonce,
				success: function(date) {  
	            	jQuery("#ets-qa-item-" + questionNumber).remove();
	            }
	        });
		}
	});

	jQuery('.ets-add-new-qa').click(function(e){
		e.preventDefault();
		let key = e.offsetX; 
		if(key != 0){
			let count = jQuery('#ets-new-question-Answer-count').val();   
			if ( typeof count == 'undefined' ) 
				count = 0 ;  
			 
			jQuery.ajax({ 
				url: etsWooQaParams.admin_ajax,
				type: 'POST',  
				dataType: "JSON",  
				data:'action=ets_add_new_qusetion_answer&count=' + count + '&addNewQaNonce=' + etsWooQaParams.addNewQaNonce,
				success: function(res) {  
					jQuery('.ets-new-qa-field').append(res.htmlData);  
					count = res.count; 
					jQuery('#ets-new-question-Answer-count').val(count);       	 
	            }
	        });
	    }
	});

});
