<?php
	/**
	 * @package Custom_Taxonomy
	 * @version 1.0
	 */
	/*
	Plugin Name: Custom Taxonomy
	Description: This plugin do the following:
	             - Register custom taxonomy for website
				 - Unregister built in taxonomy.
				 - Configuration: Custom taxonomy API source URL, Cron job time
	Author: Dinesh Kumar
	Version: 1.0
	*/
	
	
	function custom_taxonomy_init() {
		$labels = array(
		'name' => _x( 'Custom Categories', 'taxonomy general name' ),
		'singular_name' => _x( 'Custom Category', 'taxonomy singular name' ),
		'search_items' =>  __( 'Search Custom categories' ),
		'all_items' => __( 'All Custom categories' ),
		'parent_item' => __( 'Parent Custom Category' ),
		'parent_item_colon' => __( 'Parent Custom Category:' ),
		'edit_item' => __( 'Edit Custom Category' ), 
		'update_item' => __( 'Update Custom Category' ),
		'add_new_item' => __( 'Add New Custom Category' ),
		'new_item_name' => __( 'New Custom Category Name' ),
		'menu_name' => __( 'Custom categories' ),
	  );
	  
		// create a new taxonomy
		register_taxonomy('custom-categories',array('post'), array(
		'hierarchical' => true,
		'labels' => $labels,
		'show_ui' => true,
		'show_admin_column' => true,
		'query_var' => true,
		'rewrite' => array( 'slug' => 'custom-categorie' ),
	  ));
	  
	  register_taxonomy('category', array());
	}
	add_action( 'init', 'custom_taxonomy_init' );
	
	
	
 
	function custom_category_admin_menu(){
			add_menu_page( 'Custom Taxonomy Page', 'Custom Taxonomy', 'manage_options','custom_categorie', 'custom_categorie_options' );
	}
	add_action('admin_menu', 'custom_category_admin_menu');
	
	function custom_categorie_options(){
        custom_categorie_post();
		$api_url = get_option( '_api_url' );
		$cron_job_time = get_option( '_cron_job_time' );
		?>
			<h1>Custom Taxonomy settings</h1>
			<form  method="post" >
					<label>API URL: </label><input type='text' id='_api_url' name='_api_url' value="<?php echo $api_url; ?>"/><br />
					<label>Cron job time in seconds: </label><input type='text' id='_cron_job_time' name='_cron_job_time' value="<?php echo $cron_job_time; ?>"/>
					<?php submit_button('Save') ?>&nbsp;
					Import categories from API:<br />
					<?php submit_button('Import Categories') ?>
			</form>
		<?php
	}
	
	function custom_categorie_post(){				
		if(isset($_POST['submit']) && $_POST['submit'] == 'Save'){
			if(isset($_POST['_api_url']) && $_POST['_api_url'] != ""){
				update_option( '_api_url', $_POST['_api_url'] );
			}
			
			if(isset($_POST['_cron_job_time']) && $_POST['_cron_job_time'] != ""){
				update_option( '_cron_job_time', $_POST['_cron_job_time'] );
			}
		}
		
		if(isset($_POST['submit']) && $_POST['submit'] == 'Import Categories'){
			importCategories();
		}
		
	}
	
	function importCategories(){
		global $wpdb;
			$parentCategoryUrl = get_option( '_api_url' )."?";
			$apiResponse = wp_remote_get( $parentCategoryUrl);
			$customCategories = json_decode($apiResponse['body']);
			
			if($apiResponse['response']['code'] != 200){
				echo "Application encountered problem. Please check the API resource.";
				
			}else{	
				uasort($customCategories, 'sort_by_parent');
				
				foreach ($customCategories as $category) {				
					//Check category exist or not
					$termsExist = get_terms('custom-categories', array(
						'meta_key' => 'custom_category_id',
						'meta_value' => $category->id,
						'hide_empty' => false,
					));
				
				
					// Check Parent Category
					if($category->parent_id !== 0 && $category->parent_id !== null){
						$parentCategory = get_terms('custom-categories', array(
							'meta_key' => 'custom_category_id',
							'meta_value' => $category->parent_id,
							'hide_empty' => false,
						));
						if($parentCategory){
							$category->parent_id = $parentCategory[0]->term_id;
						} else {
							$category->parent_id = 0;
						}
					} else {
						$category->parent_id = 0;
					}

					if($termsExist) {

						$cat_defaults = array(
							'cat_ID' => $termsExist[0]->term_id,
							'cat_name' => $category->name ,
							'category_description' => $category->name ,
							'category_nicename' => $category->name ,
							'category_parent' => $category->parent_id ,
							'taxonomy' => 'custom-categories'
						);

						$term_id = wp_insert_category( $cat_defaults,true);

					} else {

						$cat_defaults = array(
							'cat_name' => $category->name ,
							'category_description' => $category->name ,
							'category_nicename' => $category->name ,
							'category_parent' => $category->parent_id ,
							'taxonomy' => 'custom-categories'
						);
						$term_id = wp_insert_category( $cat_defaults);
						add_term_meta ($term_id, 'custom_category_id', $category->id,true);
					}
				}				
			}
	}
	add_action ('getCategories', 'importCategories');
	
	function sort_by_parent($a, $b)
	{
		return $a->parent_id - $b->parent_id;
	}
	
	function getcrontimeFn($schedules){
		$crontime = get_option( '_cron_job_time' );
		$schedules['getcrontime'] = array(
			'interval'  => $crontime,
			'display' => __( 'According to time configg' )
		);
		return $schedules;
	}
	add_filter( 'cron_schedules', 'getcrontimeFn' );
	
	function cronjob_activation() {
		if( !wp_next_scheduled( 'getCategories' ) ) {  
		   wp_schedule_event( time(), 'getcrontime', 'getCategories' );  
		}
	}
	register_activation_hook(__FILE__, 'cronjob_activation');
	add_action('wp', 'cronjob_activation');
	
	
	function cronjob_deactivate() {
		// find out when the last event was scheduled
		$timestamp = wp_next_scheduled ('getCategories');
		// unschedule previous event if any
		wp_unschedule_event ($timestamp, 'getCategories');
	}
	register_deactivation_hook(__FILE__, 'cronjob_deactivate');



?>