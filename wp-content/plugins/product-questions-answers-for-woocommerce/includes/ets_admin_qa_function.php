<?php
if ( ! defined( 'ABSPATH' ) ) exit;
// admin controller
class ETS_WOO_PRODUCT_ADMIN_QUESTION_ANSWER 
{ 	
	public function __construct() { 
		//initalizing admin features.
		add_action('init', array($this, 'checkIsAdmin')); 
	}

	public function checkIsAdmin(){
		if(current_user_can('shop_manager') || current_user_can('administrator')){ 
			
			// Save Product data in the admin Tab.
			add_action( 'woocommerce_process_product_meta', array($this, 'admin_qa_save') , 10, 1 );

			//Add new Theam option in the admin Painel.
			add_action('admin_menu', array($this, 'admin_menu_product_qa'));
			 
			//Add CSS file.
			add_action( 'admin_enqueue_scripts',array($this, 'admin_style'));  
			// add new Tab. 
			add_filter('woocommerce_product_data_tabs', array($this, 'product_tab_admin_qa'));
			
			// Add the script file in the drop & drag Question And Answer Listing.
			add_action( 'admin_enqueue_scripts', array($this, 'product_panels_scripts_ui' ));
			
			// Create the admin Url in Script Variable.	
			add_action( 'admin_enqueue_scripts', array($this, 'admin_woo_qa_script' ));
			
			// Tab content.
			add_action( 'woocommerce_product_data_panels', array($this, 'product_panels'));
 
			// Save question order in DB.
			add_action('wp_ajax_ets_qa_save_order', array($this, 'qa_order_save'));
			
			// Add new Question And Answer.
			add_action('wp_ajax_ets_add_new_qusetion_answer', array($this, 'add_new_qa_inputs'));

			// Delete the Question And Answer.
			add_action('wp_ajax_etsdelete_qusetion_answer', array($this, 'delete_qa'));
		}		 
	}

	/**
	 * Add new Theam option in the admin Panel
	 */ 
	public function admin_menu_product_qa(){
		add_menu_page(__('Products Q & A','ets_q_n_a'), __('Products Q & A','ets_q_n_a'), 'manage_options', 'theme-options', array($this, 'etsLoadMoreQa'), 'dashicons-info ',59);
	}


	/**
	 * Show Error.
	 */
	public function etsErrorNotification() { 
		$class = 'notice notice-error';
		$message = __( 'Access not allowed', 'ets_q_n_a' ).'.';

		printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) ); 
	} 

	/**
	 * Create Sub menu option
	 */
	public function etsLoadMoreQa(){
		$loadButton = get_option( 'ets_load_more_button' ); 
		
		if(empty($loadButton)){  	
			update_option( 'ets_load_more_button','true' );
			update_option( 'ets_product_q_qa_list_length', '10' );
			update_option( 'ets_load_more_button_name', __("Load More",'ets_q_n_a') );
			update_option( 'ets_product_qa_paging_type', "normal" );
			$loadButton = get_option( 'ets_load_more_button' );

		}

		$lengthOfList = get_option( 'ets_product_q_qa_list_length');
		$buttonName = get_option( 'ets_load_more_button_name');
		$pagingType = get_option( 'ets_product_qa_paging_type');

		if (isset($_POST['ets_load_more'])) {
			$adminApprove = isset($_POST['ets_qa_approve']) ? 'yes' : 'no' ;
			update_option( 'ets_qa_approve', $adminApprove );
			
			if(!isset($_POST['ets_load_more_button']) || (!wp_verify_nonce($_POST['ets_load_more_button'] , 'etsLoadMoreQa' ))){
			 	 
			 	$this->etsErrorNotification();
			 	 
			} else {
				$loadButton   =  isset($_POST['ets_load_more_active']) ? intval($_POST['ets_load_more_active']) : 0 ; 
				$lengthOfList = intval($_POST['ets_length_of_list']); 
				$buttonName   = sanitize_text_field($_POST['ets_load_more_button_name']);  
				$pagingType   = sanitize_text_field($_POST['paging_type']);
				
				
				 
				if($loadButton == 1){ 
					if(!empty($lengthOfList)){
						update_option( 'ets_load_more_button', $loadButton );
						update_option( 'ets_product_q_qa_list_length', $lengthOfList );
						update_option( 'ets_product_qa_paging_type', $pagingType );
						// update_option( 'approve', $adminApprove );

						if(!empty($buttonName)){
							update_option( 'ets_load_more_button_name', $buttonName );
						} else {
							$buttonName = __("Load More",'ets_q_n_a');
							update_option( 'ets_load_more_button_name', $buttonName );
						} 
					} else {
						$lengthOfList = 10;
						update_option( 'ets_product_q_qa_list_length', $lengthOfList );
					}
				} else {
					$buttonName = __("Load More",'ets_q_n_a');		
					update_option( 'ets_load_more_button', $loadButton );
					update_option( 'ets_product_q_qa_list_length', $lengthOfList );
					update_option( 'ets_load_more_button_name', $buttonName );
					update_option( 'ets_product_qa_paging_type', $pagingType );
					

				} 
			}			 	 
		} 
		$aprValue = get_option('ets_qa_approve');
		
		
		?><div class="wrap"><div id="icon-options-general" class="icon32"><br></div>
		<h2><?php echo __("Product Q & A Setting",'ets_q_n_a'); ?></h2></div>
		<form method="post" name="load_more_form" action="#"> 
			 	
			<table>
				<tr>
					<td><h4><?php echo __('Load More','ets_q_n_a'); ?>: </h4></td>
					<td> 
						 <?php wp_nonce_field( 'etsLoadMoreQa', 'ets_load_more_button' ); ?>
						<input type="checkbox" <?php if(($loadButton == 1)) { echo $loadButton; ?> checked <?php }?> name="ets_load_more_active" value="1" >
					</td> 	
				</tr> 
				<tr>
					<td><h4><?php echo __('Page Size','ets_q_n_a'); ?>: </h4></td>
					<td><input type="number" name="ets_length_of_list" value="<?php echo isset($lengthOfList) ? $lengthOfList : '';?>"  min="1"  ></td>
				</tr> 
				<tr>
					<td><h4><?php echo __('Paging Button Name','ets_q_n_a'); ?>: </h4></td>
					<td><input type="text" name="ets_load_more_button_name" value="<?php echo isset($buttonName) ? $buttonName : '';?>" width="50px" height="90px"></td>
				</tr>
				<tr>
					<td><h4><?php echo __('Layout','ets_q_n_a'); ?>: </h4></td>
					<td><select name="paging_type">
					    	<option value="normal" <?php if($pagingType == "normal") { ?> selected <?php }?>><?php echo __('Normal','ets_q_n_a');?></option>
					    	<option value="accordion"  <?php if($pagingType == "accordion") { ?> selected <?php }?>><?php echo __('Accordion','ets_q_n_a');?></option>
						</select>
					</td>
				</tr>
				<tr>
					<td><h4><?php echo __('Auto Approve','ets_q_n_a'); ?>: </h4></td>
					<td><input type="checkbox" name="ets_qa_approve" value="yes" <?php if(isset($aprValue) && $aprValue == 'yes'){ echo "checked"; } else { '' ; }?>></td>
				</tr> 
				<tr><td></td>
					<td><button type="submit" name="ets_load_more" class="button button-primary button-large"><?php echo __('Submit',"ets_q_n_a"); ?></button>
				</tr>
			</table>
		</form> 

		<?php 
	} 

 	/**
	 * add menu tab
	 */
 	public function product_tab_admin_qa( $tabs ){
 
		$tabs['admin_answer'] = array(
			'label'    		=> __('Q & A','ets_q_n_a'),
			'target'   		=> 'ets_product_data', 
			'priority' 		=> 100,  
			'id'			=> 'ets_question',
			'content'		=> ''
		);   
		return $tabs; 
	}
	 
	/**
	 * Include Drag and Drop Script jquery-ui
	 */
  	public function product_panels_scripts_ui(){    
		wp_register_script(
			'jquery-ui-sortable', 
			array( 'jquery' ),
			'1.0'
		);
		wp_enqueue_script('jquery-ui-sortable'); 
	}  
	/*
	 * Tab content
	 */
	public function product_panels(){ 
		?>
		<div id="ets_product_data" class="panel woocommerce_options_panel hidden"> 
			<div id="ets_product_detail"> <ul id="sortable">
				<?php
				global $post; 
				$productId = $post->ID; 
				$etsGetQuestion = get_post_meta( $productId,'ets_question_answer', true ); 
				if(!empty($etsGetQuestion)){   
					foreach ($etsGetQuestion as $key => $value) { 
							// to create hidden input field
						?> <li id="ets-qa-item-<?php echo $key;?>" class="ets-qa-item" style="position: relative;"> 
						<?php 
					 	woocommerce_wp_hidden_input( 
							array(  
								'class'		  => "ets_user_name[$key]", 
								'id'		  => "ets_user_name[$key]",
								'name'		  => "ets_user_name[$key]",
								'value'       => $value['user_name'],
							) 

						);
						woocommerce_wp_hidden_input( 
							array(  
								'id'    	  => "ets_user_id[$key]",
								'class'		  => "ets_user_id[$key]",
								'name'		  => "ets_user_id[$key]",
								'value'       => $value['user_id'],  

							) 
						);
						 
						woocommerce_wp_hidden_input( 
							array(  
								'id'    	  => "ets_user_email[$key]",
								'class'		  => "ets_user_email[$key]",
								'name'		  => "ets_user_email[$key]",
							    'value'       => $value['user_email'],  

							) 
						);
						
						woocommerce_wp_hidden_input( 
							array(  
																
								'class'		  => "ets_product_title[$key]",	
								'id'    	  => "ets_product_title[$key]",
								'name'		  => "ets_product_title[$key]",							
								'value'       => $value['product_title'],  

							) 
						);
					
					 	woocommerce_wp_hidden_input( 
							array(  
								'name'		  => "ets_date[$key]",
								'id'    	  => "ets_date[$key]",
								'class'		  => "ets_date[$key]",
								'name'		  => "ets_date[$key]",
								'value'       => $value['date'],   
								'type'		  => 'hidden'
							) 
						);
					 	woocommerce_wp_text_input( 
							array(
								'id'    	  =>  "ets_question[$key]",  
								'name'		  =>  "ets_question[$key]",
								'value'       =>  $value['question'],
								'label'       =>  __('Question','ets_q_n_a').': '  
							) 
						);

						woocommerce_wp_textarea_input( 
							array( 
								'id'		   =>  "ets_answer[$key]",
								'name'		   =>  "ets_answer[$key]",
								'value'        =>  $value['answer'], 
								'label'        =>  __('Answer','ets_q_n_a').': ' 
							) 
						);	

						woocommerce_wp_checkbox( 
							array( 
								'id'    =>  "ets_admin_apv[$key]",
								'class'	=>  "ets_admin_apv[$key] ets_admin_apv",
								'name'  =>  "ets_admin_apv[$key]",
								'value'	=> ((isset($value['approve']) && $value['approve'] =='yes') || !isset($value['approve']) ) ? 'yes' :'no',
								'cbvalue' => 'yes',
								
	 							'label'   =>  __('Approve','ets_q_n_a').': ',
							)
						);
							
						if(empty($value['answer'])){
								$value['empty_text'] = 'empty_text';
								woocommerce_wp_hidden_input( 
								array(  
									'id'    	  => "ets_emp_text_answer[$key]",
									'class'		  => "ets_emp_text_answer[$key]",
									'name'		  => "ets_emp_text_answer[$key]",
									'value'       => $value['empty_text'],   
									'type'		  => 'hidden'
								) 
							);					
						}
						do_action('wc_after_qa_inputs', $productId, $key);
						?>	
					<div class="image-preview">

						<div class="ets-qa-drop">
							<img class="ets-scroll-move" src="<?php echo ETS_WOO_QA_PATH . "asset/images/Cursor-Move.png"; ?>" style="max-width: 15px;">
						</div> 
						<div class="ets-qa-delete">
							<img src="<?php echo ETS_WOO_QA_PATH . "asset/images/delet.png"; ?>" style="max-width: 20px;" data-questionkey="<?php echo $key; ?>" id="ets-delete-qa"  class="ets-del-qa">
							
						</div>
					</div>
					<div class="border"></div>
					</li> 
					<?php 
					}  	
				} else{
					?> 
					<li id="ets-qa-item-new-q"> 
						<?php  // input
							woocommerce_wp_text_input( 
								array(  
									'name'		  => 'ets_first_question',
									'value'       => '',
									'label'       => __('Question','ets_q_n_a').' : ',
									'desc_tip'    => true, 
									'id'		  => 'ets_question_data'	 
								) 
							);
							woocommerce_wp_textarea_input( 
								array( 
									'name'		  => 'ets_first_answer',
									'value'       =>  '',
									'label'       => __('Answer','ets_q_n_a').' : ',
									'desc_tip'    => true, 	
									'id'		  => 'ets_answer_data' 
								) 
							);


							echo '<div class="border"></div>';
							do_action('wc_after_qa_inputs', $productId);
						?>
					</li> 
					<?php 
				}   ?>
		 		<li class="ets-new-qa-field ets-qa-item"></li>  
		 		</ul> 
		 		<input type="hidden" name="ets-new-question-Answer-count" id="ets-new-question-Answer-count" value=""> 
				<a href="#" type="submit" name="ets-add-new-qa" class="ets-add-new-qa ">+<?php 
					echo apply_filters('wc_add_new_qa_label', __('Add New',"ets_q_n_a"));
					?></a>

				
			</div>
		</div> 	
		<?php
	}

	/**
	 * Save Product data in the admin Tab
	 */ 
	public function admin_qa_save( $productId ){ 
	 	 
		$userId = isset($_POST['ets_user_id']) ? (is_array($_POST['ets_user_id']) ? array_map('intval',$_POST['ets_user_id']) : '') : '';  

		$userName = isset($_POST['ets_user_name']) ? (is_array($_POST['ets_user_name']) ? array_map('sanitize_text_field' , $_POST['ets_user_name']) : '') : ''; 
		
		$questions = isset($_POST['ets_question']) ? (is_array($_POST['ets_question']) ? array_map('sanitize_text_field' , $_POST['ets_question']) : '') : '';
		
		$answers = isset($_POST['ets_answer']) ? (is_array($_POST['ets_answer']) ? array_map('sanitize_textarea_field' , $_POST['ets_answer']) : '') : '';  
		
		$date = isset($_POST['ets_date']) ? (is_array($_POST['ets_date']) ? array_map('sanitize_text_field' , $_POST['ets_date']) : '') :'';  
		
		$newQuestion = isset($_POST['ets_new_question']) ? (is_array($_POST['ets_new_question']) ? array_map('sanitize_text_field' , $_POST['ets_new_question']) : '') : '';
		
		$newAnswer = isset($_POST['ets_new_answer']) ? (is_array($_POST['ets_new_answer']) ? array_map('sanitize_textarea_field' , $_POST['ets_new_answer']) : '') : '';	
		
		$productTitle = isset($_POST['ets_product_title']) ? (is_array($_POST['ets_product_title']) ? array_map('sanitize_text_field' , $_POST['ets_product_title']) : '') : '' ;
		
		$empTextAnswer = isset($_POST['ets_emp_text_answer']) ? (is_array($_POST['ets_emp_text_answer']) ? array_map('sanitize_textarea_field' , $_POST['ets_emp_text_answer']) : '') : '';

		$userEmail = isset($_POST['ets_user_email']) && $_POST['ets_user_email'] && is_array($_POST['ets_user_email']) ? array_map('sanitize_email', $_POST['ets_user_email']) : '';

		$admin_approve = isset($_POST['ets_admin_apv']) ? (is_array($_POST['ets_admin_apv']) ? array_map('sanitize_text_field' , $_POST['ets_admin_apv']) : '') : [];
		
		$newDate = date("d-M-Y");
		$user = wp_get_current_user();  
		$newUesrName = $user->user_login;
		$newUserId = $user->ID;
		$newUserEmail = $user->user_email;
		$question = isset($_POST['ets_first_question']) && $_POST['ets_first_question'] ? sanitize_text_field($_POST['ets_first_question']) : '';
		$answer = isset($_POST['ets_first_answer']) && $_POST['ets_first_answer'] ? sanitize_textarea_field($_POST['ets_first_answer']) : '';
		$prdTitle = get_the_title();
		$before_save = get_post_meta( $productId,'ets_question_answer', true );
		do_action('wc_before_qa_save', $productId);
	
		//Insert the first New Question
		if(!empty($question)){ 
			 
			$productFirstQa[] = array(

				"product_title"   =>   $prdTitle,
				"question" 	      =>   $question,
				"answer"	      =>   $answer,
				"date"		      =>   $newDate,
				"user_name"	      =>   $newUesrName,
				"user_email"      =>   $newUserEmail,
				"user_id"	      =>   $newUserId, 
				"approve"		  =>   $admin_approve 
			); 
			update_post_meta( $productId, 'ets_question_answer',  $productFirstQa );
		}  

		$productQas = get_post_meta( $productId, 'ets_question_answer', true );


		//On Click Add new Field New Question
		if(!empty($newQuestion)){ 
			foreach ( $newQuestion as $qkey => $q) { 
				
				$productNewQas[$qkey] = array(   
					"product_title"    =>   $productTitle[$qkey],
					"question" 	       =>   $newQuestion[$qkey],
					"answer"	       =>   $newAnswer[$qkey], 
					"date"		       =>   $newDate ,
					"user_name"	       =>   $newUesrName, 
					"user_email"       =>   $newUserEmail,
					"user_id"	       =>   $newUserId, 
					"approve"		 =>   isset($admin_approve[$qkey]) ? $admin_approve[$qkey] : 'no' 
				);
				
				if(empty($productNewQas[$qkey]['question'])) {
					unset($productNewQas[$qkey]);
				}  
			}
			// update meta with data 
			if(!empty($productQas)){
				$productNewQasList = array_merge( $productQas, $productNewQas);

				 update_post_meta( $productId, 'ets_question_answer', $productNewQasList );
			} else if(!empty($productFirstQa)){
				$productNewQasList = array_merge( $productFirstQa, $productNewQas);  
				 update_post_meta( $productId, 'ets_question_answer', $productNewQasList );
			} else {
				 update_post_meta( $productId, 'ets_question_answer', $productNewQas );
			}	 
		} else { 
			
			//Edit the Question And Answer	  
			foreach ( $questions as $qkey => $q) {  

				$productQas[$qkey] = array(
					"product_title"  =>   $productTitle[$qkey],
					"user_id"	     =>   $userId[$qkey],
					"user_email"     =>   $userEmail[$qkey],
					"user_name"	     =>   $userName[$qkey],
					"question" 	     =>   $q,
					"answer"	     =>   $answers[$qkey], 
					"date"		     =>   $date[$qkey],
					"approve"		 =>   isset($admin_approve[$qkey]) ? $admin_approve[$qkey] : 'no' 
				
				);

				if(empty($productQas[$qkey]['question'])) {
					unset($productQas[$qkey]);
				}  
			} 

			do_action('wc_after_qa_update', $productId, $productQas); 
			// update meta for answer at user question.	   
		 	update_post_meta( $productId, 'ets_question_answer',  $productQas );  

		}
		do_action('wc_after_qa_save', $productId);

		//user mail from admin
		$after_save = get_post_meta( $productId,'ets_question_answer', true );

		if ($after_save) {
			foreach ($after_save as $key => $value) {

				$to = $value['user_email'];
				$userName = $value['user_name'];
				$productTitle = $value['product_title'];
				$answers = $value['answer'];
				$url = get_permalink( $productId);
				$site_url = get_site_url();       
				$site_name = get_bloginfo('name');  				

				// If the answer was changed
				if ( $before_save && !empty(trim($value['answer'])) && !empty(trim($value['user_email'])) && (trim($value['answer']) != trim($before_save[$key]['answer']) && !empty( trim( $before_save[$key]['answer'] )  ) ) )
				{

			 		$subject =apply_filters("wc_qa_answer_updated_mail_subject" ,__("Answer to Your Question was Updated",'ets_q_n_a'). ': ' . get_bloginfo('name'));
			 		$message = "Dear " . $userName . ",<br><br>";
			 		$message .= "<a href='$site_url'>" . $site_name . "</a> updated an answer to your question on the product <a href='$url'> " . $productTitle ."</a>:  <br><div style='background-color: #FFF8DC;border-left: 2px solid #ffeb8e;padding: 10px;margin-top:10px;'>". $answers ."</div>";

			 		$message = apply_filters("wc_qa_answer_updated_mail_message", $message, $productTitle, $answers);

				    $res = wp_mail($to, $subject, $message);
				 
				// First time answer    
				} elseif ( $before_save && empty( trim( $before_save[$key]['answer'] ) ) && !empty( trim( $value['answer'] ) )  && !empty(trim($value['user_email'])) ) {  
					$subject = __("Your Question was Answered",'ets_q_n_a'). ': ' . get_bloginfo('name');
			 		$subject = apply_filters("wc_qa_new_answer_mail_subject", $subject);
			 		$message = "Dear " . $userName . ",<br><br>";
			 		$message .= "<a href='$site_url'>" . $site_name . "</a> added an answer on the product <a href='$url'> " . $productTitle ."</a>:  <br><div style='background-color: #FFF8DC;border-left: 2px solid #ffeb8e;padding: 10px;margin-top:10px;'>". $answers ."</div>";

			 		$message = apply_filters("wc_qa_new_answer_mail_message", $message, $productTitle, $answers);
				    $res = wp_mail($to, $subject, $message);
				}
			}
		}
	} 

	/**
	 * Change Order Q&A 
	 */
	public function qa_order_save() { 
		if(!wp_verify_nonce($_POST['changeOrderQa'],'ets-product-change-order-qa')){
			
			$response = array( 
				'status'		=>     0,
				'error'			=>   __("Access not allowed").'.'
			);
			
			echo json_encode($response);
			die; 
		}

		$newOrderQaList = array(); 
		$productId = intval($_POST['product_id']);  
 		
 		$changedOrderQaList = isset( $_POST['ets-qa-item']) ? (is_array($_POST['ets-qa-item']) ? array_map('intval',$_POST['ets-qa-item']) : '') : '';  

		$productQas = get_post_meta($productId,'ets_question_answer',true);
		 
		foreach($changedOrderQaList as $index) {
			$newOrderQaList[$index] = $productQas[$index];
		} 
		
		update_post_meta( $productId, 'ets_question_answer',  $newOrderQaList );		 
	}

	/**
	 * Secipt File include.
	 */
	public function admin_woo_qa_script() {
		global $pagenow, $post; 
		if ( $pagenow == 'post.php' && $post ) {
			 
			$addNewQaNonce = wp_create_nonce('ets-product-add-new-qa');
			$deleteQa = wp_create_nonce('ets-product-delete-qa');
			$changeOrderQa = wp_create_nonce('ets-product-change-order-qa');
		    wp_register_script(
				'ets_woo_qa_admin_script',
				ETS_WOO_QA_PATH . 'asset/js/ets_woo_qa_admin_script.js',
				array('jquery'), 
				'1.0'
			); 
	        wp_enqueue_script( 'ets_woo_qa_admin_script' );
			
			 	$script_params = array(
					'admin_ajax' 		=>   admin_url('admin-ajax.php'),
					'currentProdcutId' 	=>   $post->ID,
					'addNewQaNonce' 	=>   $addNewQaNonce,
					'deleteQaNonce'		=>   $deleteQa,
					'changeOrderQa'		=>   $changeOrderQa
				);  
		  	wp_localize_script( 'ets_woo_qa_admin_script', 'etsWooQaParams', $script_params ); 
		}
	}

	/**
	 * Include custome style sheet
	 */
	public function admin_style() {
		wp_register_style(
		    'ets_woo_qa_style_css',
		    ETS_WOO_QA_PATH. 'asset/css/ets_woo_qa_style.css' ,
		    [] ,
         	'1.0'
		); 
		wp_enqueue_style( 'ets_woo_qa_style_css');
		 
	}

	/**
	 * Delete Q&A pare.
	 */
	public function delete_qa(){
		if(!wp_verify_nonce($_POST['deleteQaNonce'],'ets-product-delete-qa')){
			
			$response = array( 
				'status'		=>  0,
				'error'			=>  __("Access not allowed").'.'
			);
			
			echo json_encode($response);
			die; 
		}

		$questionIndex = intval($_POST['questionIndex']);
		$productId = intval($_POST['prdId']); 
		$productQas = get_post_meta( $productId, 'ets_question_answer', true );
		unset($productQas[$questionIndex]);
		update_post_meta( $productId, 'ets_question_answer',  $productQas ); 
	}

	/**
	 * Add new Q&A field on click Add new Link
	 */
	public function add_new_qa_inputs(){
		if( !wp_verify_nonce($_POST['addNewQaNonce'],'ets-product-add-new-qa')){
			$response = array( 
				'status'		=>  0,
				'error'			=>  __("Access not allowed",'ets_q_n_a').'.'
			);
			
			echo json_encode($response);
			die; 
		}

		$count = intval($_POST['count']); 
		if(empty($count)){
			$count = 0;
		}
		ob_start();
		woocommerce_wp_text_input( 
			array(  
				'name'		  =>    "ets_new_question[$count]",
				'value'       =>    '',
				'label'       =>     __('Question','ets_q_n_a').": ",
				'desc_tip'    =>    true,  	 
			) 
		);
		
		woocommerce_wp_textarea_input( 
			array( 
				'name'		  =>   "ets_new_answer[$count]",
				'value'       =>    '', 
				'label'       =>     __('Answer','ets_q_n_a').': ',
				'desc_tip'    =>     true,
			) 
		); 

		woocommerce_wp_checkbox( 
			array( 
				'name'		        =>  "ets_admin_apv[$count]",
				'class'				=>   "ets_admin_apv",		
				'label'             =>   __('Approve','ets_q_n_a').': ' ,
				'value'	            => 'yes',
				'cbvalue'           => 'yes',
			)
		);

		do_action('wc_ajax_after_qa_inputs', $count);
		$count = $count + 1;
		
		echo '<div class="border"></div>';
		$htmlData = ob_get_clean();  
		$response = array( 
			'htmlData'		=>   $htmlData,
			'count'			=>   $count,
		);
		echo json_encode($response);
		die;  
	} 
}
$etsWooProductAdminQuestionAnswer = new ETS_WOO_PRODUCT_ADMIN_QUESTION_ANSWER(); 