<?php
/*
Plugin Name: Ralawise Scraping Sid Techno
Plugin URI: #
Description: Developed by Sid Techno.
Version: 1.0.0
Author: Sidtehcno
Author URI: https://sidtechno.com
License: GPLv2 or later
Text Domain: Ralawise Scraping Sid Techno
*/
include('simple_html_dom.php');

function on_activate()
{
    global $wpdb;
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    $create_table_query = "
		CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}ralawise_sid_brands` (
		  `id` int(11) NOT NULL auto_increment,
		  `brand_name` text NOT NULL,
		  `brand_img` text NULL,
		  `brand_count` int(11) NOT NULL DEFAULT 0,
		  `brand_term_id` int(11) NOT NULL DEFAULT 0,
           PRIMARY KEY  (`id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
	";

    dbDelta($create_table_query);


    $create_table_query = "
		CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}ralawise_sid_products` (
		  `id` int(11) NOT NULL auto_increment,
		  `name` text NULL,
		  `sizerange` text NULL,
		  `productgroup` text NULL,
		  `picture` text NULL,
		  `detailpageurl` text NULL,
		  `brandpicture` text NULL,
		  `brandname` text NULL,
		  `brandid` text NULL,
		  `sename` text NULL,
		  `fromprice` text NULL,
		  `vatable` text NULL,
		  `mainimage` text NULL,
		  `images` text NULL,
           PRIMARY KEY  (`id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
	";
    dbDelta($create_table_query);

    $create_table_query = "
		CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}rala_console_log` (
			`id` int(11) NOT NULL auto_increment,
			`message` text NULL,
			`datetime` timestamp NOT NULL DEFAULT current_timestamp(),
           PRIMARY KEY  (`id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
	";
    dbDelta($create_table_query);
}
register_activation_hook(__FILE__, 'on_activate');

function on_deactive()
{
    global $wpdb;

	$tableArray = [   
	  $wpdb->prefix . "ralawise_sid_brands",
	  $wpdb->prefix . "ralawise_sid_products	",
	];

	foreach ($tableArray as $tablename) {
		$wpdb->query("DROP TABLE IF EXISTS $tablename");
	}
}
register_deactivation_hook(__FILE__, 'on_deactive');

add_action( 'init', 'run_cron' );
function run_cron() {
	global $wpdb;
	error_reporting(E_ALL);
	ini_set("log_errors", 1);
	ini_set("error_log", plugin_dir_path( __FILE__ )."/error.log");

	if( isset( $_GET['run_cron'] ) ) {
		// EXIT();
		sid_console_log("Run Cron Start");
		$get_variant_json = $wpdb->get_row("SELECT * FROM  $wpdb->postmeta WHERE meta_key = 'product_variant_rala' LIMIT 1");
		if(isset($get_variant_json->meta_value)) {
			sid_console_log("Product Variant Trigger");
			if(count(json_decode($get_variant_json->meta_value)) > 0) {
				sid_console_log("Product Variant Insert");

				$get_variant_json_decode = json_decode($get_variant_json->meta_value);
				$count_product = 0;
				$product_id = $get_variant_json->post_id;

				foreach ($get_variant_json_decode as $get_variant_key => $get_variant_value) {
					// insert_or_update_product($get_variant_value, $get_variant_json->term_id);
					$variant_get_from_web = insert_or_update_variant($product_id, $get_variant_value);
					unset($get_variant_json_decode[$get_variant_key]);
					if($count_product == 5) {
						break;
					}
					$count_product++;
				}

				$count_array = 0;
				$variant_new_array = array();
				foreach ($get_variant_json_decode as $get_variant_json_decode_key => $get_variant_json_decode_value) {
					$variant_new_array[$count_array] = $get_variant_json_decode_value;
					$count_array++;
				}

				$wpdb->query($wpdb->prepare("UPDATE `".$wpdb->prefix."postmeta` SET `meta_value` = '%s' WHERE `meta_key` = 'product_variant_rala' AND meta_id = '%s';", json_encode($variant_new_array), $get_variant_json->meta_id));
			} else {
				$wpdb->query($wpdb->prepare("DELETE FROM $wpdb->postmeta WHERE meta_id = '%s' ",$get_variant_json->meta_id));
			}
		}

		$rala_check_sub_category = get_option( 'rala_check_sub_category' );
		if($rala_check_sub_category != 1) {
			sid_console_log("Product Category Trigger");
			$get_categories_id = $wpdb->get_results("
			    SELECT * 
			    FROM  $wpdb->termmeta
			        WHERE meta_key = 'sub_cate_update' AND meta_value = '0'
			");
			if(count($get_categories_id) == 0) {
				update_option("rala_check_sub_category", 1);
			}

			$count = 0;
			foreach ($get_categories_id as $cat_key => $cat_value) {
				fetch_and_insert_category($cat_value->term_id, 0);
				if($count == 10) {
					break;
				}
				$count++;
			}
		}

		$get_products_json = $wpdb->get_row("SELECT * FROM  $wpdb->termmeta WHERE meta_key = 'products_json' LIMIT 1");
		if(isset($get_products_json->meta_value)) {
			sid_console_log("Product Trigger");

			$get_products_json_decode = json_decode($get_products_json->meta_value, true);
			if(count($get_products_json_decode) > 0) {
				$count_product = 0;

				foreach ($get_products_json_decode as $get_products_key => $get_products_value) {
					sid_console_log("Product Insert");
					insert_or_update_product($get_products_value, $get_products_json->term_id);
					unset($get_products_json_decode[$get_products_key]);
					if($count_product == 2) {
						break;
					}
					$count_product++;
				}

				$count_array = 0;
				$product_new_array = array();

				foreach ($get_products_json_decode as $get_products_json_decode_key => $get_products_json_decode_value) {
					$product_new_array[$count_array] = $get_products_json_decode_value;
					$count_array++;
				}

				update_term_meta($get_products_json->term_id, 'products_json', json_encode($product_new_array));
			} else {
				delete_term_meta($get_products_json->term_id,'products_json');
			}
		}

		$rala_run_cron = get_option('rala_run_cron');
		$schedule_24_hour = time() - 604800;
		update_option("rala_check_sub_category", 0);
		if(empty($rala_run_cron) || $rala_run_cron <= $schedule_24_hour) {
			sid_console_log("Main Cron Trigger");
			$data_resp = get_curl_page(501, 0);

			sid_console_log("Total Category ".count($data_resp['CategoryFilter']));
			sid_console_log("Total Brand ".count($data_resp['BrandFilter']));

			insert_or_update_cat($data_resp['CategoryFilter'],0);
			update_option("rala_check_sub_category", 0);
			insert_or_update_brand($data_resp['BrandFilter']);

			$TotalRecords = $data_resp['TotalRecords'];
			$PageNumber = $data_resp['PageNumber'];

			update_option("rala_run_cron", time());
		}

		exit();
	}
}
function fetch_and_insert_category($cat_term_id, $CurrentPageNumber = 0) {
	sid_console_log("Product Category Insert Page#".$CurrentPageNumber);
	$rala_wise_ID = get_term_meta($cat_term_id,'ralawise_id',true);
	$data_resp = get_curl_page($rala_wise_ID, $CurrentPageNumber);

	$TotalRecords = $data_resp['TotalRecords'];
	$PageNumber = $data_resp['PageNumber'];

	$totalRecordsFetch = 1020;
	if($PageNumber != 0) {
		$totalRecordsFetch = 1020 * $PageNumber;
	}
	insert_or_update_cat($data_resp['CategoryFilter'], $cat_term_id);

    update_term_meta($cat_term_id, 'products_json', json_encode($data_resp['Products'],JSON_HEX_QUOT));
    update_term_meta($cat_term_id, 'sub_cate_update', 1);
	if($totalRecordsFetch <= $TotalRecords) {
		fetch_and_insert_category($cat_term_id, $CurrentPageNumber+1); 
	}
}

function insert_or_update_product($product, $category_id){
	global $wpdb;
	if(is_array($product)) {
		$DetailPageUrl = $product['DetailPageUrl'];
		$BrandPicture = $product['BrandPicture'];
		$ProductGroup = $product['ProductGroup'];
		$productName = $product['Name'];
		$productMainImage = $product['MainImage'];
		$productImages = $product['Images'];
		$productBrandName = $product['BrandName'];
		$productBrandId = $product['BrandId'];
	} else {
		$DetailPageUrl = $product->DetailPageUrl;
		$BrandPicture = $product->BrandPicture;
		$ProductGroup = $product->ProductGroup;
		$productName = $product->Name;
		$productMainImage = $product->MainImage;
		$productImages = $product->Images;
		$productBrandName = $product->BrandName;
		$productBrandId = $product->BrandId;
	}
	$pageurl = 'https://funtasticuniform.yourwebshop.com'.$DetailPageUrl;	

	$product_data = scrap_product_html($pageurl, $BrandPicture);
	$check_product_exist = $wpdb->get_row("
	    SELECT * 
	    FROM  ".$wpdb->prefix."postmeta
	        WHERE meta_value = '".$ProductGroup."'
	");
	if(!empty($check_product_exist)) {
		$get_product = $wpdb->get_row("
		    SELECT * 
		    FROM  ".$wpdb->prefix."ralawise_sid_products
		        WHERE productgroup = '".$ProductGroup."'
		");
		$data_array = array();
	
		wp_set_object_terms( $check_product_exist->post_id, 'variable', 'product_type');
	    update_post_meta($check_product_exist->post_id, "product_meta_group", $ProductGroup);

		if($get_product->name != $productName) {
			$data_array['post_title'] = $productName;
		} 
		if($get_product->MainImage != $productMainImage) {
			$main_img_attachment_id = sid_upload_from_url("https://funtasticuniform.yourwebshop.com/".$productMainImage);
			$wpdb->query($wpdb->prepare("UPDATE `".$wpdb->prefix."postmeta` SET `meta_value` = '%s' WHERE `meta_key` = '_thumbnail_id' AND post_id = '%s';",$main_img_attachment_id, $check_product_exist->post_id));
		} 
		if($get_product->Images != json_encode($productImages)) {
			$gallery_image = '';
			foreach ($productImages as $Images_key => $Images_value) {
				if($gallery_image == '') {
					$gallery_image .= sid_upload_from_url("https://funtasticuniform.yourwebshop.com/".$Images_value);
				} else {
					$gallery_image .= ',';
					$gallery_image .= sid_upload_from_url("https://funtasticuniform.yourwebshop.com/".$Images_value);
				}
			}
			if(!empty($gallery_image)) {
				$wpdb->query($wpdb->prepare("UPDATE `".$wpdb->prefix."postmeta` SET `meta_value` = '%s' WHERE `meta_key` = '_product_image_gallery' AND post_id = '%s';", $gallery_image, $check_product_exist->post_id));
			}
		} 
		if($get_product->prod_decritpion != $product_data['prod_decritpion']) {
	        $data_array['post_content'] = $product_data['prod_decritpion'];
		} 
		if($get_product->variant_products != $product_data['variant_array_sku']) {
		    update_post_meta($check_product_exist->post_id, "product_variant_rala", $product_data['variant_array_sku']);
		}
		if(count($data_array) > 0) {
			$data_array['ID'] = $check_product_exist->post_id;
			wp_update_post( $data_array );
		}
		if($category_id != $get_product->category_id) {
			$check_if_terms_exist = $wpdb->get_row("SELECT * FROM  `".$wpdb->prefix."term_relationships` WHERE object_id = '".$check_product_exist->post_id."' AND term_taxonomy_id = '".$category_id."' LIMIT 1");
			if(empty($check_if_terms_exist)) {
				$wpdb->query($wpdb->prepare("INSERT INTO `".$wpdb->prefix."term_relationships` (`object_id`, `term_taxonomy_id`) VALUES ('%s', '%s');",$check_product_exist->post_id, $category_id));
			}
		}

		$wpdb->query($wpdb->prepare("UPDATE `".$wpdb->prefix."ralawise_sid_products` SET `name`='%s', `productgroup`='%s', `MainImage`='%s', `Images`='%s', `BrandPicture`='%s', `BrandName`='%s', `BrandId`='%s', `brand_term_id`='%s', `prod_decritpion`='%s', `variant_products`='%s', `category_id`='%s' WHERE productgroup='%s';", $productName, $ProductGroup, $productMainImage, json_encode($productImages), $BrandPicture, $productBrandName, $productBrandId, $product_data['brand_term_id'], $product_data['prod_decritpion'], $product_data['variant_array_sku'], $category_id, $ProductGroup));
	} else {
		$wpdb->query($wpdb->prepare("INSERT INTO `".$wpdb->prefix."ralawise_sid_products` (`name`, `productgroup`, `MainImage`, `Images`, `BrandPicture`, `BrandName`, `BrandId`, `brand_term_id`, `prod_decritpion`, `variant_products`, `category_id`) VALUES ('%s','%s','%s','%s','%s','%s','%s','%s', '%s', '%s','%s');", $productName, $ProductGroup,$productMainImage,json_encode($productImages),$BrandPicture,$productBrandName,$productBrandId,$product_data['brand_term_id'],$product_data['prod_decritpion'],$product_data['variant_array_sku'],$category_id));

	    $post_id = wp_insert_post(array(
	        'post_title' => $productName,
	        'post_type' => 'product',
	        'post_status' => 'publish',
	        'post_content' => $product_data['prod_decritpion'],
	    ));

		wp_set_object_terms( $post_id, 'variable', 'product_type' );
	    update_post_meta($post_id, "product_meta_group", $ProductGroup);
	    update_post_meta($post_id, "product_variant_rala", $product_data['variant_array_sku']);
	    
	    $wc_product = wc_get_product( $post_id );
	    $wc_product->set_sku($ProductGroup);

		$wpdb->query($wpdb->prepare("INSERT INTO `".$wpdb->prefix."term_relationships` (`object_id`, `term_taxonomy_id`) VALUES ('%s', '%s');",$post_id, $category_id));
		$wpdb->query($wpdb->prepare("INSERT INTO `".$wpdb->prefix."term_relationships` (`object_id`, `term_taxonomy_id`) VALUES ('%s', '%s');",$post_id,$product_data['brand_term_id']));

		// Upload main Image
		$main_img_attachment_id = sid_upload_from_url("https://funtasticuniform.yourwebshop.com/".$productMainImage);
		$wpdb->query($wpdb->prepare("INSERT INTO `".$wpdb->prefix."postmeta` (`post_id`, `meta_key`, `meta_value`) VALUES ('%s','_thumbnail_id', '%s');",$post_id,$main_img_attachment_id));

		$gallery_image = '';
		foreach ($productImages as $Images_key => $Images_value) {
			if($gallery_image == '') {
				$gallery_image .= sid_upload_from_url("https://funtasticuniform.yourwebshop.com/".$Images_value);
			} else {
				$gallery_image .= ',';
				$gallery_image .= sid_upload_from_url("https://funtasticuniform.yourwebshop.com/".$Images_value);
			}
		}
		if(!empty($gallery_image)) {
			$wpdb->query($wpdb->prepare("INSERT INTO `".$wpdb->prefix."postmeta` (`post_id`, `meta_key`, `meta_value`) VALUES ('%s','_product_image_gallery', '%s');",$post_id, $gallery_image));
		}
	}

}
function scrap_product_html($pageurl, $brand_img) {
	$product = array();
	$html = sid_file_get_html($pageurl);
	$brand_name = $html->find('.brandLogoWrapper img', 0)->getAttribute('alt');
	$product['brand_term_id'] = get_brand_term_id($brand_name, $brand_img);
	$product['prod_decritpion'] = '';
	$product['prod_decritpion'] .= $html->find('.marB10.lh22', 0);
	$product['prod_decritpion'] .= $html->find('.marB5.lh22', 0);
	$product['prod_decritpion'] .= $html->find('.marB10.lh22', 0);
	$attr_array = array();
	foreach($html->find('ul#colourOptions') as $ul) {
	    foreach($ul->find('li') as $li) {
	    	$attr = $li->getAttribute('data-bind');
	    	$attr = str_replace("click : function() { colourClick('","",$attr);
	    	$attr = str_replace("') }","",$attr);
	    	$attr_array[] = $attr;
	    }
	}

	$product['variant_array_sku'] = json_encode($attr_array);

	return $product;
}

function get_brand_term_id($brand_name, $brand_img) {
	global $wpdb;
	$result = $wpdb->get_row("
	    SELECT * 
	    FROM  ".$wpdb->prefix."ralawise_sid_brands
	        WHERE brand_name = '".esc_html($brand_name)."'
	");

	if(empty($result->brand_img) || $result->brand_img == '') {
		$attachment_id = sid_upload_from_url("https://funtasticuniform.yourwebshop.com/".$brand_img);

		$wpdb->query($wpdb->prepare("UPDATE `".$wpdb->prefix."ralawise_sid_brands` SET `brand_img` = '%s' WHERE `".$wpdb->prefix."ralawise_sid_brands`.`id` = '%s';",$brand_img, $result->id));
    	update_term_meta($result->id, 'thumbnail_id', $attachment_id);
	}
	return $result->brand_term_id;

}

function insert_or_update_brand($brand_array) {
	global $wpdb;
	foreach ($brand_array as $cat_key => $cat_value) {
		$result = $wpdb->get_row("
		    SELECT * 
		    FROM  $wpdb->termmeta
		        WHERE meta_key = 'ralawise_brand_id' AND meta_value = '".$cat_value['Id']."'
		");
		if(!empty($result)) {
			$cid = sid_wp_update_term( $result->term_id, 'ts_product_brand', array(
				'name' => $cat_value['Name'],
				'slug' => $cat_value['Name']
			));
			$wpdb->query($wpdb->prepare("UPDATE `".$wpdb->prefix."ralawise_sid_brands` SET `brand_term_id` = '%s' WHERE `".$wpdb->prefix."ralawise_sid_brands`.`id` = '%s';",$cat_value['Name'], $cat_id));
		} else {
			$cid = sid_wp_insert_term( $cat_value['Name'], 'ts_product_brand', array("name"=>$cat_value['Name']));
			if ( ! is_wp_error( $cid ) )
			{
			    $cat_id = isset( $cid['term_id'] ) ? $cid['term_id'] : 0;
			    update_term_meta($cat_id, 'ralawise_brand_id', $cat_value['Id']);
				$wpdb->query($wpdb->prepare("INSERT INTO `".$wpdb->prefix."ralawise_sid_brands` (`brand_name`, `brand_term_id`) VALUES ('%s', '%s');",esc_html(str_replace("®","&#174;",$cat_value['Name'])), $cat_id));
			}
		}
	}
}

function insert_or_update_cat($cat_array,$parent_id) {
	global $wpdb;
	foreach ($cat_array as $cat_key => $cat_value) {
		$result = $wpdb->get_row("
		    SELECT * 
		    FROM  $wpdb->termmeta
		        WHERE meta_key = 'ralawise_id' AND meta_value = '".$cat_value['Id']."'
		");
		if(!empty($result)) {
			if($parent_id != $cat_value['Id']) {
				wp_update_term( $result->term_id, 'product_cat', array(
					'name' => $cat_value['Name'],
					'slug' => $cat_value['Name']
				), array('parent'=>$parent_id));
			    update_term_meta($result->term_id, 'sub_cate_update', 0);
			}
		} else {
			if($parent_id != $cat_value['Id']) {
				$cid = wp_insert_term( $cat_value['Name'], 'product_cat', array('parent'=>$parent_id));
				if ( ! is_wp_error( $cid ) )
				{
				    $cat_id = isset( $cid['term_id'] ) ? $cid['term_id'] : 0;
				    update_term_meta($cat_id, 'ralawise_id', $cat_value['Id']);
				    update_term_meta($cat_id, 'sub_cate_update', 0);
				}
			}
		}
	}
}


add_action( 'delete_term_taxonomy', function($tt_id) {
	global $wpdb;
	$wpdb->query( 
		$wpdb->prepare( 
			"DELETE FROM $wpdb->termmeta WHERE term_id = %d",
		    $tt_id 
		)
	);
}, 9, 1);



function get_curl_page($cat_id, $page_number) {
	$url = "https://funtasticuniform.yourwebshop.com/(S-05e51156-f01e-48db-92f3-1f1680a4b1b6)/Category/Filter";

	$curl = curl_init($url);
	curl_setopt($curl, CURLOPT_URL, $url);
	curl_setopt($curl, CURLOPT_POST, true);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

	$headers = array(
		"Content-Type: application/json",
	);
	curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

	$data = <<<DATA
	{"Categories":[],"Brands":[],"PrimaryColours":[],"PageSize":1020,"PageNumber":$page_number,"ParentCategoryId":$cat_id,"EmbeddedCartId":"(S-05e51156-f01e-48db-92f3-1f1680a4b1b6)"}
	DATA;

	curl_setopt($curl, CURLOPT_POSTFIELDS, $data);

	//for debug only!
	curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

	$resp = curl_exec($curl);
	curl_close($curl);
	return json_decode($resp, true);
}

function insert_or_update_variant($product_id, $variant_sku) {
	$json_data = get_variant_stock_price($variant_sku);
	$json_decode_variant = $json_data;

	$attributes = array();


	$attributes['pa_color'] = array(
	        'name' => 'pa_color',
	        'value' => '',
	        'is_visible' => '1',
	        'is_variation' => '1',
	        'is_taxonomy' => '1'
	    );
	$attributes['pa_quantity'] = array(
	        'name' => 'pa_quantity',
	        'value' => '',
	        'is_visible' => '1',
	        'is_variation' => '1',
	        'is_taxonomy' => '1'
	    );

	$insert_color_term = wp_insert_term( $json_decode_variant['ColourName'], 'pa_color', array("slug"=>strtolower($json_decode_variant['ColourName']).'_'.$product_id));
	if(isset($insert_color_term->errors)) {
	    $insert_color_term = get_term_by( 'slug', str_replace(" ", "-", strtolower($json_decode_variant['ColourName'])).'_'.$product_id, 'pa_color' );
		$color_term_id = $insert_color_term->term_id;
	} else {
		$color_term_id = $insert_color_term['term_id'];
	}

	$varaint_color_img_attachment_id = sid_upload_from_url("https://funtasticuniform.yourwebshop.com/".$json_decode_variant['ImagePath']);

	$image_serial_array = array(
	    'ts_color_color' => '#ffffff',
	    'ts_color_image' => $varaint_color_img_attachment_id
	);
	$serialized_data = serialize($image_serial_array);
    update_term_meta($color_term_id, 'ts_product_color_config', $serialized_data);

	$insert_term = wp_insert_term( 'Single', 'pa_quantity', array("slug"=>'single'));
	$insert_term = wp_insert_term( 'Pack', 'pa_quantity', array("slug"=>'pack'));
	$insert_term = wp_insert_term( 'Carton', 'pa_quantity', array("slug"=>'carton'));


	update_post_meta( $product_id, 'pa_color', array('pa_color' => array('name' => 'pa_color','value' => $json_decode_variant['ColourName'],'is_visible' => '1','is_variation' => '1','is_taxonomy'  => '1')));
	update_post_meta( $product_id, 'pa_quantity', array('pa_quantity' => array('name' => 'pa_quantity','value' => 'Single','is_visible' => '1','is_variation' => '1','is_taxonomy'  => '1')));
	update_post_meta( $product_id, 'pa_quantity', array('pa_quantity' => array('name' => 'pa_quantity','value' => 'Pack','is_visible' => '1','is_variation' => '1','is_taxonomy'  => '1')));
	update_post_meta( $product_id, 'pa_quantity', array('pa_quantity' => array('name' => 'pa_quantity','value' => 'Carton','is_visible' => '1','is_variation' => '1','is_taxonomy'  => '1')));

	$pa_size_array = array();
	$pa_color_array = array();
	foreach ($json_decode_variant['Sizes'] as $Sizes_key => $Sizes_value) {
		if(!empty($Sizes_value)) {
			$insert_term = wp_insert_term( $Sizes_value, 'pa_size', array("slug"=>str_replace(" ", "-", strtolower($Sizes_value))));
			update_post_meta( $product_id, 'pa_size', array('pa_size' => array('name' => 'pa_size','value' => $Sizes_value,'is_visible' => '1','is_variation' => '1','is_taxonomy'  => '1')));
			$pa_size_array[] = $Sizes_value;
		}
	}

	$old_size_values = wp_get_object_terms($product_id, 'pa_size');
	$old_color_values = wp_get_object_terms($product_id, 'pa_color');
	foreach ($old_size_values as $old_size_values_key => $old_size_values_value) {
		$pa_size_array[] = $old_size_values_value->name;
	}
	foreach ($old_color_values as $old_color_values_key => $old_color_values_value) {
		$pa_color_array[] = $old_color_values_value->name;
	}
	$pa_color_array[] = $json_decode_variant['ColourName'];

	if(count($pa_size_array) > 0) {
		$attributes['pa_size'] = array(
		        'name' => 'pa_size',
		        'value' => '',
		        'is_visible' => '1',
		        'is_variation' => '1',
		        'is_taxonomy' => '1'
		);
	}
	update_post_meta( $product_id, '_product_attributes', $attributes );
	if(count($pa_size_array) > 0) { wp_set_object_terms( $product_id, $pa_size_array, 'pa_size' ); }
	wp_set_object_terms( $product_id, $pa_color_array, 'pa_color' );
	wp_set_object_terms( $product_id, array('Single','Pack','Carton'), 'pa_quantity' );
	wp_set_object_terms( $product_id, array('Single','Pack','Carton'), 'pa_quantity' );

	foreach ($json_decode_variant['LinePrices'] as $LinePrices_key => $LinePrices_value) {
		// Create the variation post
		// if($LinePrices_key == 0){
			// $variation = array(
			//     'post_title' => 'My Product Variation',
			//     'post_content' => '',
			//     'post_status' => 'publish',
			//     'post_parent' => $product_id,
			//     'post_type' => 'product_variation'
			// );
			// $variation_id = wp_insert_post( $variation );

			// // Set the attribute values for the variation
			// wp_set_object_terms( $variation_id, $json_decode_variant['ColourName'], 'color', true );
			// wp_set_object_terms( $variation_id, 'Single', 'quantity', true );

			// // Set the variation's price
			// update_post_meta( $variation_id, '_price', '20' );

			// // Set the variation's stock
			// update_post_meta( $variation_id, '_stock', '10' );
			// update_post_meta( $variation_id, '_manage_stock', 'yes' );		
		// }

		// $variation = new WC_Product_Variation();
		// $variation->set_parent_id($product_id);
		// $variation->set_attributes(array(
		//     'color' => $json_decode_variant['ColourName'],
		//     'quantity' => 'Single',
		// ));
		// $variation->set_regular_price(19.99);
		// $variation->set_stock_quantity(10);
		// $variation->set_manage_stock(true);
		// $variation->save();	
		$variation_1_data = array(
		    'post_title'    => 'Variation #'.$LinePrices_key+1,
		    'post_content'  => '',
		    'post_status'   => 'publish',
		    'post_parent'   => $product_id,
		    'post_type'     => 'product_variation'
		);
		$variation_1_id = wp_insert_post( $variation_1_data );

		update_post_meta( $variation_1_id, 'attribute_pa_color', str_replace(" ", "-", strtolower($json_decode_variant['ColourName'])).'_'.$product_id );
		wp_set_object_terms($variation_1_id, $json_decode_variant['ColourName'], 'color', true);
		if(!empty($LinePrices_value['Size'])) {
			wp_set_object_terms($variation_1_id, $LinePrices_value['Size'], 'Size', true);
			update_post_meta( $variation_1_id, 'attribute_pa_size', str_replace(" ", "-", strtolower($LinePrices_value['Size'])) );
		}
		wp_set_object_terms($variation_1_id, 'Single', 'quantity', true);
		update_post_meta( $variation_1_id, 'attribute_pa_quantity', 'single');
		update_post_meta( $variation_1_id, '_price', $LinePrices_value['Single']['Price'] );
		update_post_meta( $variation_1_id, '_regular_price', $LinePrices_value['Single']['Price'] );
		update_post_meta( $variation_1_id, '_manage_stock', 'yes' );



		wc_update_product_stock( $variation_1_id, $LinePrices_value['Stock'] );

		$pack_quantity = $LinePrices_value['Stock'] / $LinePrices_value['Pack']['Quantity'];
		$variation_2_data = array(
		    'post_title'    => 'Variation #'.$LinePrices_key+1,
		    'post_content'  => '',
		    'post_status'   => 'publish',
		    'post_parent'   => $product_id,
		    'post_type'     => 'product_variation'
		);
		$variation_2_id = wp_insert_post( $variation_2_data );

		update_post_meta( $variation_2_id, 'attribute_pa_color', str_replace(" ", "-", strtolower($json_decode_variant['ColourName'])).'_'.$product_id );
		wp_set_object_terms($variation_2_id, $json_decode_variant['ColourName'], 'color', true);
		if(!empty($LinePrices_value['Size'])) {
			wp_set_object_terms($variation_2_id, $LinePrices_value['Size'], 'Size', true);
			update_post_meta( $variation_2_id, 'attribute_pa_size', str_replace(" ", "-", strtolower($LinePrices_value['Size'])) );
		}
		wp_set_object_terms($variation_2_id, 'Pack', 'quantity', true);
		update_post_meta( $variation_2_id, 'attribute_pa_quantity', 'pack');
		update_post_meta( $variation_2_id, '_price', $LinePrices_value['Pack']['Price'] );
		update_post_meta( $variation_2_id, '_regular_price', $LinePrices_value['Pack']['Price'] );
		update_post_meta( $variation_2_id, '_manage_stock', 'yes' );
		wc_update_product_stock( $variation_2_id, floor($pack_quantity) );


		$carton_quantity = $LinePrices_value['Stock'] / $LinePrices_value['Carton']['Quantity'];
		$variation_3_data = array(
		    'post_title'    => 'Variation #'.$LinePrices_key+1,
		    'post_content'  => '',
		    'post_status'   => 'publish',
		    'post_parent'   => $product_id,
		    'post_type'     => 'product_variation'
		);
		$variation_3_id = wp_insert_post( $variation_3_data );

		update_post_meta( $variation_3_id, 'attribute_pa_color', str_replace(" ", "-", strtolower($json_decode_variant['ColourName'])).'_'.$product_id );
		wp_set_object_terms($variation_3_id, $json_decode_variant['ColourName'], 'color', true);
		if(!empty($LinePrices_value['Size'])) {
			wp_set_object_terms($variation_3_id, $LinePrices_value['Size'], 'Size', true);
			update_post_meta( $variation_3_id, 'attribute_pa_size', str_replace(" ", "-", strtolower($LinePrices_value['Size'])) );
		}
		wp_set_object_terms($variation_3_id, 'Carton', 'quantity', true);

		update_post_meta( $variation_3_id, 'attribute_pa_quantity', 'carton');
		update_post_meta( $variation_3_id, '_price', $LinePrices_value['Carton']['Price'] );
		update_post_meta( $variation_3_id, '_regular_price', $LinePrices_value['Carton']['Price'] );
		update_post_meta( $variation_3_id, '_manage_stock', 'yes' );
		wc_update_product_stock( $variation_3_id, floor($carton_quantity) );
	}
	update_post_meta( $product_id, '_product_attributes', $attributes );
}
function insert_update_attributes_n_value($product_id) {
	$attributes_data = array(
	    array('name'=>'Size',  'options'=>array('S', 'L', 'XL', 'XXL'), 'visible' => 1, 'variation' => 1 ),
	    array('name'=>'Color', 'options'=>array('Red', 'Blue', 'Black', 'White'), 'visible' => 1, 'variation' => 1 )
	);

	if( sizeof($attributes_data) > 0 ){
	    $attributes = array(); // Initializing

	    // Loop through defined attribute data
	    foreach( $attributes_data as $key => $attribute_array ) {
	        if( isset($attribute_array['name']) && isset($attribute_array['options']) ){
	            // Clean attribute name to get the taxonomy
	            $taxonomy = 'pa_' . wc_sanitize_taxonomy_name( $attribute_array['name'] );

	            $option_term_ids = array(); // Initializing

	            // Loop through defined attribute data options (terms values)
	            foreach( $attribute_array['options'] as $option ){
	                if( term_exists( $option, $taxonomy ) ){
	                    // Save the possible option value for the attribute which will be used for variation later
	                    wp_set_object_terms( $product_id, $option, $taxonomy, true );
	                    // Get the term ID
	                    $option_term_ids[] = get_term_by( 'name', $option, $taxonomy )->term_id;
	                }
	            }
	        }
	        // Loop through defined attribute data
	        $attributes[$taxonomy] = array(
	            'name'          => $taxonomy,
	            'value'         => $option_term_ids, // Need to be term IDs
	            'position'      => $key + 1,
	            'is_visible'    => $attribute_array['visible'],
	            'is_variation'  => $attribute_array['variation'],
	            'is_taxonomy'   => '1'
	        );
	    }
	    // Save the meta entry for product attributes
	    update_post_meta( $product_id, '_product_attributes', $attributes );
	}
}
function get_variant_stock_price($variant) {
	$url = "https://funtasticuniform.yourwebshop.com/(S-05e51156-f01e-48db-92f3-1f1680a4b1b6)/Product/GetStockAndPrices/".$variant."?_=".time();

	$curl = curl_init($url);
	curl_setopt($curl, CURLOPT_URL, $url);
	curl_setopt($curl, CURLOPT_POST, false);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

	$headers = array(
		"Content-Type: application/json",
	);
	curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

	// curl_setopt($curl, CURLOPT_POSTFIELDS, $data);

	//for debug only!
	curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

	$resp = curl_exec($curl);
	curl_close($curl);
	return json_decode($resp, true);
}



function sid_wp_insert_term( $term, $taxonomy, $args = array() ) {
	global $wpdb;

	// if ( ! taxonomy_exists( $taxonomy ) ) {
	// 	return new WP_Error( 'invalid_taxonomy', __( 'Invalid taxonomy.' ) );
	// }

	/**
	 * Filters a term before it is sanitized and inserted into the database.
	 *
	 * @since 3.0.0
	 * @since 6.1.0 The `$args` parameter was added.
	 *
	 * @param string|WP_Error $term     The term name to add, or a WP_Error object if there's an error.
	 * @param string          $taxonomy Taxonomy slug.
	 * @param array|string    $args     Array or query string of arguments passed to wp_insert_term().
	 */
	$term = apply_filters( 'pre_insert_term', $term, $taxonomy, $args );

	if ( is_wp_error( $term ) ) {
		return $term;
	}

	if ( is_int( $term ) && 0 === $term ) {
		return new WP_Error( 'invalid_term_id', __( 'Invalid term ID.' ) );
	}

	if ( '' === trim( $term ) ) {
		return new WP_Error( 'empty_term_name', __( 'A name is required for this term.' ) );
	}

	$defaults = array(
		'alias_of'    => '',
		'description' => '',
		'parent'      => 0,
		'slug'        => '',
	);
	$args     = wp_parse_args( $args, $defaults );

	if ( (int) $args['parent'] > 0 && ! term_exists( (int) $args['parent'] ) ) {
		return new WP_Error( 'missing_parent', __( 'Parent term does not exist.' ) );
	}

	$args['name']     = $term;
	$args['taxonomy'] = $taxonomy;

	// Coerce null description to strings, to avoid database errors.
	$args['description'] = (string) $args['description'];

	$args = sanitize_term( $args, $taxonomy, 'db' );

	// expected_slashed ($name)
	$name        = wp_unslash( $args['name'] );
	$description = wp_unslash( $args['description'] );
	$parent      = (int) $args['parent'];

	$slug_provided = ! empty( $args['slug'] );
	if ( ! $slug_provided ) {
		$slug = sanitize_title( $name );
	} else {
		$slug = $args['slug'];
	}

	$term_group = 0;
	if ( $args['alias_of'] ) {
		$alias = get_term_by( 'slug', $args['alias_of'], $taxonomy );
		if ( ! empty( $alias->term_group ) ) {
			// The alias we want is already in a group, so let's use that one.
			$term_group = $alias->term_group;
		} elseif ( ! empty( $alias->term_id ) ) {
			/*
			 * The alias is not in a group, so we create a new one
			 * and add the alias to it.
			 */
			$term_group = $wpdb->get_var( "SELECT MAX(term_group) FROM $wpdb->terms" ) + 1;

			wp_update_term(
				$alias->term_id,
				$taxonomy,
				array(
					'term_group' => $term_group,
				)
			);
		}
	}

	/*
	 * Prevent the creation of terms with duplicate names at the same level of a taxonomy hierarchy,
	 * unless a unique slug has been explicitly provided.
	 */
	$name_matches = get_terms(
		array(
			'taxonomy'               => $taxonomy,
			'name'                   => $name,
			'hide_empty'             => false,
			'parent'                 => $args['parent'],
			'update_term_meta_cache' => false,
		)
	);

	/*
	 * The `name` match in `get_terms()` doesn't differentiate accented characters,
	 * so we do a stricter comparison here.
	 */
	$name_match = null;
	if ( $name_matches ) {
		foreach ( $name_matches as $_match ) {
			if ( strtolower( $name ) === strtolower( $_match->name ) ) {
				$name_match = $_match;
				break;
			}
		}
	}

	if ( $name_match ) {
		$slug_match = get_term_by( 'slug', $slug, $taxonomy );
		if ( ! $slug_provided || $name_match->slug === $slug || $slug_match ) {
			if ( is_taxonomy_hierarchical( $taxonomy ) ) {
				$siblings = get_terms(
					array(
						'taxonomy'               => $taxonomy,
						'get'                    => 'all',
						'parent'                 => $parent,
						'update_term_meta_cache' => false,
					)
				);

				$existing_term = null;
				$sibling_names = wp_list_pluck( $siblings, 'name' );
				$sibling_slugs = wp_list_pluck( $siblings, 'slug' );

				if ( ( ! $slug_provided || $name_match->slug === $slug ) && in_array( $name, $sibling_names, true ) ) {
					$existing_term = $name_match;
				} elseif ( $slug_match && in_array( $slug, $sibling_slugs, true ) ) {
					$existing_term = $slug_match;
				}

				if ( $existing_term ) {
					return new WP_Error( 'term_exists', __( 'A term with the name provided already exists with this parent.' ), $existing_term->term_id );
				}
			} else {
				return new WP_Error( 'term_exists', __( 'A term with the name provided already exists in this taxonomy.' ), $name_match->term_id );
			}
		}
	}

	$slug = wp_unique_term_slug( $slug, (object) $args );

	$data = compact( 'name', 'slug', 'term_group' );

	/**
	 * Filters term data before it is inserted into the database.
	 *
	 * @since 4.7.0
	 *
	 * @param array  $data     Term data to be inserted.
	 * @param string $taxonomy Taxonomy slug.
	 * @param array  $args     Arguments passed to wp_insert_term().
	 */
	$data = apply_filters( 'wp_insert_term_data', $data, $taxonomy, $args );

	if ( false === $wpdb->insert( $wpdb->terms, $data ) ) {
		return new WP_Error( 'db_insert_error', __( 'Could not insert term into the database.' ), $wpdb->last_error );
	}

	$term_id = (int) $wpdb->insert_id;

	// Seems unreachable. However, is used in the case that a term name is provided, which sanitizes to an empty string.
	if ( empty( $slug ) ) {
		$slug = sanitize_title( $slug, $term_id );

		/** This action is documented in wp-includes/taxonomy.php */
		do_action( 'edit_terms', $term_id, $taxonomy );
		$wpdb->update( $wpdb->terms, compact( 'slug' ), compact( 'term_id' ) );

		/** This action is documented in wp-includes/taxonomy.php */
		do_action( 'edited_terms', $term_id, $taxonomy );
	}

	$tt_id = $wpdb->get_var( $wpdb->prepare( "SELECT tt.term_taxonomy_id FROM $wpdb->term_taxonomy AS tt INNER JOIN $wpdb->terms AS t ON tt.term_id = t.term_id WHERE tt.taxonomy = %s AND t.term_id = %d", $taxonomy, $term_id ) );

	if ( ! empty( $tt_id ) ) {
		return array(
			'term_id'          => $term_id,
			'term_taxonomy_id' => $tt_id,
		);
	}

	if ( false === $wpdb->insert( $wpdb->term_taxonomy, compact( 'term_id', 'taxonomy', 'description', 'parent' ) + array( 'count' => 0 ) ) ) {
		return new WP_Error( 'db_insert_error', __( 'Could not insert term taxonomy into the database.' ), $wpdb->last_error );
	}

	$tt_id = (int) $wpdb->insert_id;

	/*
	 * Sanity check: if we just created a term with the same parent + taxonomy + slug but a higher term_id than
	 * an existing term, then we have unwittingly created a duplicate term. Delete the dupe, and use the term_id
	 * and term_taxonomy_id of the older term instead. Then return out of the function so that the "create" hooks
	 * are not fired.
	 */
	$duplicate_term = $wpdb->get_row( $wpdb->prepare( "SELECT t.term_id, t.slug, tt.term_taxonomy_id, tt.taxonomy FROM $wpdb->terms AS t INNER JOIN $wpdb->term_taxonomy AS tt ON ( tt.term_id = t.term_id ) WHERE t.slug = %s AND tt.parent = %d AND tt.taxonomy = %s AND t.term_id < %d AND tt.term_taxonomy_id != %d", $slug, $parent, $taxonomy, $term_id, $tt_id ) );

	/**
	 * Filters the duplicate term check that takes place during term creation.
	 *
	 * Term parent + taxonomy + slug combinations are meant to be unique, and wp_insert_term()
	 * performs a last-minute confirmation of this uniqueness before allowing a new term
	 * to be created. Plugins with different uniqueness requirements may use this filter
	 * to bypass or modify the duplicate-term check.
	 *
	 * @since 5.1.0
	 *
	 * @param object $duplicate_term Duplicate term row from terms table, if found.
	 * @param string $term           Term being inserted.
	 * @param string $taxonomy       Taxonomy name.
	 * @param array  $args           Arguments passed to wp_insert_term().
	 * @param int    $tt_id          term_taxonomy_id for the newly created term.
	 */
	$duplicate_term = apply_filters( 'wp_insert_term_duplicate_term_check', $duplicate_term, $term, $taxonomy, $args, $tt_id );

	if ( $duplicate_term ) {
		$wpdb->delete( $wpdb->terms, array( 'term_id' => $term_id ) );
		$wpdb->delete( $wpdb->term_taxonomy, array( 'term_taxonomy_id' => $tt_id ) );

		$term_id = (int) $duplicate_term->term_id;
		$tt_id   = (int) $duplicate_term->term_taxonomy_id;

		clean_term_cache( $term_id, $taxonomy );
		return array(
			'term_id'          => $term_id,
			'term_taxonomy_id' => $tt_id,
		);
	}

	/**
	 * Fires immediately after a new term is created, before the term cache is cleaned.
	 *
	 * The {@see 'create_$taxonomy'} hook is also available for targeting a specific
	 * taxonomy.
	 *
	 * @since 2.3.0
	 * @since 6.1.0 The `$args` parameter was added.
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy slug.
	 * @param array  $args     Arguments passed to wp_insert_term().
	 */
	do_action( 'create_term', $term_id, $tt_id, $taxonomy, $args );

	/**
	 * Fires after a new term is created for a specific taxonomy.
	 *
	 * The dynamic portion of the hook name, `$taxonomy`, refers
	 * to the slug of the taxonomy the term was created for.
	 *
	 * Possible hook names include:
	 *
	 *  - `create_category`
	 *  - `create_post_tag`
	 *
	 * @since 2.3.0
	 * @since 6.1.0 The `$args` parameter was added.
	 *
	 * @param int   $term_id Term ID.
	 * @param int   $tt_id   Term taxonomy ID.
	 * @param array $args    Arguments passed to wp_insert_term().
	 */
	do_action( "create_{$taxonomy}", $term_id, $tt_id, $args );

	/**
	 * Filters the term ID after a new term is created.
	 *
	 * @since 2.3.0
	 * @since 6.1.0 The `$args` parameter was added.
	 *
	 * @param int   $term_id Term ID.
	 * @param int   $tt_id   Term taxonomy ID.
	 * @param array $args    Arguments passed to wp_insert_term().
	 */
	$term_id = apply_filters( 'term_id_filter', $term_id, $tt_id, $args );

	clean_term_cache( $term_id, $taxonomy );

	/**
	 * Fires after a new term is created, and after the term cache has been cleaned.
	 *
	 * The {@see 'created_$taxonomy'} hook is also available for targeting a specific
	 * taxonomy.
	 *
	 * @since 2.3.0
	 * @since 6.1.0 The `$args` parameter was added.
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy slug.
	 * @param array  $args     Arguments passed to wp_insert_term().
	 */
	do_action( 'created_term', $term_id, $tt_id, $taxonomy, $args );

	/**
	 * Fires after a new term in a specific taxonomy is created, and after the term
	 * cache has been cleaned.
	 *
	 * The dynamic portion of the hook name, `$taxonomy`, refers to the taxonomy slug.
	 *
	 * Possible hook names include:
	 *
	 *  - `created_category`
	 *  - `created_post_tag`
	 *
	 * @since 2.3.0
	 * @since 6.1.0 The `$args` parameter was added.
	 *
	 * @param int   $term_id Term ID.
	 * @param int   $tt_id   Term taxonomy ID.
	 * @param array $args    Arguments passed to wp_insert_term().
	 */
	do_action( "created_{$taxonomy}", $term_id, $tt_id, $args );

	/**
	 * Fires after a term has been saved, and the term cache has been cleared.
	 *
	 * The {@see 'saved_$taxonomy'} hook is also available for targeting a specific
	 * taxonomy.
	 *
	 * @since 5.5.0
	 * @since 6.1.0 The `$args` parameter was added.
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy slug.
	 * @param bool   $update   Whether this is an existing term being updated.
	 * @param array  $args     Arguments passed to wp_insert_term().
	 */
	do_action( 'saved_term', $term_id, $tt_id, $taxonomy, false, $args );

	/**
	 * Fires after a term in a specific taxonomy has been saved, and the term
	 * cache has been cleared.
	 *
	 * The dynamic portion of the hook name, `$taxonomy`, refers to the taxonomy slug.
	 *
	 * Possible hook names include:
	 *
	 *  - `saved_category`
	 *  - `saved_post_tag`
	 *
	 * @since 5.5.0
	 * @since 6.1.0 The `$args` parameter was added.
	 *
	 * @param int   $term_id Term ID.
	 * @param int   $tt_id   Term taxonomy ID.
	 * @param bool  $update  Whether this is an existing term being updated.
	 * @param array $args    Arguments passed to wp_insert_term().
	 */
	do_action( "saved_{$taxonomy}", $term_id, $tt_id, false, $args );

	return array(
		'term_id'          => $term_id,
		'term_taxonomy_id' => $tt_id,
	);
}

function sid_wp_update_term( $term_id, $taxonomy, $args = array() ) {
	global $wpdb;

	// if ( ! taxonomy_exists( $taxonomy ) ) {
	// 	return new WP_Error( 'invalid_taxonomy', __( 'Invalid taxonomy.' ) );
	// }

	$term_id = (int) $term_id;

	// First, get all of the original args.
	$term = sid_get_term( $term_id, $taxonomy );

	if ( is_wp_error( $term ) ) {
		return $term;
	}

	if ( ! $term ) {
		return new WP_Error( 'invalid_term', __( 'Empty Term.' ) );
	}

	$term = (array) $term->data;

	// Escape data pulled from DB.
	$term = wp_slash( $term );

	// Merge old and new args with new args overwriting old ones.
	$args = array_merge( $term, $args );

	$defaults    = array(
		'alias_of'    => '',
		'description' => '',
		'parent'      => 0,
		'slug'        => '',
	);
	$args        = wp_parse_args( $args, $defaults );
	$args        = sanitize_term( $args, $taxonomy, 'db' );
	$parsed_args = $args;

	// expected_slashed ($name)
	$name        = wp_unslash( $args['name'] );
	$description = wp_unslash( $args['description'] );

	$parsed_args['name']        = $name;
	$parsed_args['description'] = $description;

	if ( '' === trim( $name ) ) {
		return new WP_Error( 'empty_term_name', __( 'A name is required for this term.' ) );
	}

	if ( (int) $parsed_args['parent'] > 0 && ! term_exists( (int) $parsed_args['parent'] ) ) {
		return new WP_Error( 'missing_parent', __( 'Parent term does not exist.' ) );
	}

	$empty_slug = false;
	if ( empty( $args['slug'] ) ) {
		$empty_slug = true;
		$slug       = sanitize_title( $name );
	} else {
		$slug = $args['slug'];
	}

	$parsed_args['slug'] = $slug;

	$term_group = isset( $parsed_args['term_group'] ) ? $parsed_args['term_group'] : 0;
	if ( $args['alias_of'] ) {
		$alias = get_term_by( 'slug', $args['alias_of'], $taxonomy );
		if ( ! empty( $alias->term_group ) ) {
			// The alias we want is already in a group, so let's use that one.
			$term_group = $alias->term_group;
		} elseif ( ! empty( $alias->term_id ) ) {
			/*
			 * The alias is not in a group, so we create a new one
			 * and add the alias to it.
			 */
			$term_group = $wpdb->get_var( "SELECT MAX(term_group) FROM $wpdb->terms" ) + 1;

			sid_wp_update_term(
				$alias->term_id,
				$taxonomy,
				array(
					'term_group' => $term_group,
				)
			);
		}

		$parsed_args['term_group'] = $term_group;
	}

	/**
	 * Filters the term parent.
	 *
	 * Hook to this filter to see if it will cause a hierarchy loop.
	 *
	 * @since 3.1.0
	 *
	 * @param int    $parent      ID of the parent term.
	 * @param int    $term_id     Term ID.
	 * @param string $taxonomy    Taxonomy slug.
	 * @param array  $parsed_args An array of potentially altered update arguments for the given term.
	 * @param array  $args        Arguments passed to wp_update_term().
	 */
	$parent = (int) apply_filters( 'wp_update_term_parent', $args['parent'], $term_id, $taxonomy, $parsed_args, $args );

	// Check for duplicate slug.
	$duplicate = get_term_by( 'slug', $slug, $taxonomy );
	if ( $duplicate && $duplicate->term_id !== $term_id ) {
		// If an empty slug was passed or the parent changed, reset the slug to something unique.
		// Otherwise, bail.
		if ( $empty_slug || ( $parent !== (int) $term['parent'] ) ) {
			$slug = wp_unique_term_slug( $slug, (object) $args );
		} else {
			/* translators: %s: Taxonomy term slug. */
			return new WP_Error( 'duplicate_term_slug', sprintf( __( 'The slug &#8220;%s&#8221; is already in use by another term.' ), $slug ) );
		}
	}

	$tt_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT tt.term_taxonomy_id FROM $wpdb->term_taxonomy AS tt INNER JOIN $wpdb->terms AS t ON tt.term_id = t.term_id WHERE tt.taxonomy = %s AND t.term_id = %d", $taxonomy, $term_id ) );

	// Check whether this is a shared term that needs splitting.
	$_term_id = _split_shared_term( $term_id, $tt_id );
	if ( ! is_wp_error( $_term_id ) ) {
		$term_id = $_term_id;
	}
	/**
	 * Fires immediately before the given terms are edited.
	 *
	 * @since 2.9.0
	 * @since 6.1.0 The `$args` parameter was added.
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy slug.
	 * @param array  $args     Arguments passed to wp_update_term().
	 */
	do_action( 'edit_terms', $term_id, $taxonomy, $args );

	$data = compact( 'name', 'slug', 'term_group' );

	/**
	 * Filters term data before it is updated in the database.
	 *
	 * @since 4.7.0
	 *
	 * @param array  $data     Term data to be updated.
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy slug.
	 * @param array  $args     Arguments passed to wp_update_term().
	 */
	$data = apply_filters( 'wp_update_term_data', $data, $term_id, $taxonomy, $args );

	$wpdb->update( $wpdb->terms, $data, compact( 'term_id' ) );

	if ( empty( $slug ) ) {
		$slug = sanitize_title( $name, $term_id );
		$wpdb->update( $wpdb->terms, compact( 'slug' ), compact( 'term_id' ) );
	}

	/**
	 * Fires immediately after a term is updated in the database, but before its
	 * term-taxonomy relationship is updated.
	 *
	 * @since 2.9.0
	 * @since 6.1.0 The `$args` parameter was added.
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy slug.
	 * @param array  $args     Arguments passed to wp_update_term().
	 */
	do_action( 'edited_terms', $term_id, $taxonomy, $args );

	/**
	 * Fires immediate before a term-taxonomy relationship is updated.
	 *
	 * @since 2.9.0
	 * @since 6.1.0 The `$args` parameter was added.
	 *
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy slug.
	 * @param array  $args     Arguments passed to wp_update_term().
	 */
	do_action( 'edit_term_taxonomy', $tt_id, $taxonomy, $args );

	$wpdb->update( $wpdb->term_taxonomy, compact( 'term_id', 'taxonomy', 'description', 'parent' ), array( 'term_taxonomy_id' => $tt_id ) );

	/**
	 * Fires immediately after a term-taxonomy relationship is updated.
	 *
	 * @since 2.9.0
	 * @since 6.1.0 The `$args` parameter was added.
	 *
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy slug.
	 * @param array  $args     Arguments passed to wp_update_term().
	 */
	do_action( 'edited_term_taxonomy', $tt_id, $taxonomy, $args );

	/**
	 * Fires after a term has been updated, but before the term cache has been cleaned.
	 *
	 * The {@see 'edit_$taxonomy'} hook is also available for targeting a specific
	 * taxonomy.
	 *
	 * @since 2.3.0
	 * @since 6.1.0 The `$args` parameter was added.
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy slug.
	 * @param array  $args     Arguments passed to wp_update_term().
	 */
	do_action( 'edit_term', $term_id, $tt_id, $taxonomy, $args );

	/**
	 * Fires after a term in a specific taxonomy has been updated, but before the term
	 * cache has been cleaned.
	 *
	 * The dynamic portion of the hook name, `$taxonomy`, refers to the taxonomy slug.
	 *
	 * Possible hook names include:
	 *
	 *  - `edit_category`
	 *  - `edit_post_tag`
	 *
	 * @since 2.3.0
	 * @since 6.1.0 The `$args` parameter was added.
	 *
	 * @param int   $term_id Term ID.
	 * @param int   $tt_id   Term taxonomy ID.
	 * @param array $args    Arguments passed to wp_update_term().
	 */
	do_action( "edit_{$taxonomy}", $term_id, $tt_id, $args );

	/** This filter is documented in wp-includes/taxonomy.php */
	$term_id = apply_filters( 'term_id_filter', $term_id, $tt_id );

	clean_term_cache( $term_id, $taxonomy );

	/**
	 * Fires after a term has been updated, and the term cache has been cleaned.
	 *
	 * The {@see 'edited_$taxonomy'} hook is also available for targeting a specific
	 * taxonomy.
	 *
	 * @since 2.3.0
	 * @since 6.1.0 The `$args` parameter was added.
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy slug.
	 * @param array  $args     Arguments passed to wp_update_term().
	 */
	do_action( 'edited_term', $term_id, $tt_id, $taxonomy, $args );

	/**
	 * Fires after a term for a specific taxonomy has been updated, and the term
	 * cache has been cleaned.
	 *
	 * The dynamic portion of the hook name, `$taxonomy`, refers to the taxonomy slug.
	 *
	 * Possible hook names include:
	 *
	 *  - `edited_category`
	 *  - `edited_post_tag`
	 *
	 * @since 2.3.0
	 * @since 6.1.0 The `$args` parameter was added.
	 *
	 * @param int   $term_id Term ID.
	 * @param int   $tt_id   Term taxonomy ID.
	 * @param array $args    Arguments passed to wp_update_term().
	 */
	do_action( "edited_{$taxonomy}", $term_id, $tt_id, $args );

	/** This action is documented in wp-includes/taxonomy.php */
	do_action( 'saved_term', $term_id, $tt_id, $taxonomy, true, $args );

	/** This action is documented in wp-includes/taxonomy.php */
	do_action( "saved_{$taxonomy}", $term_id, $tt_id, true, $args );

	return array(
		'term_id'          => $term_id,
		'term_taxonomy_id' => $tt_id,
	);
}



function sid_get_term( $term, $taxonomy = '', $output = OBJECT, $filter = 'raw' ) {
	if ( empty( $term ) ) {
		return new WP_Error( 'invalid_term', __( 'Empty Term.' ) );
	}

	// if ( $taxonomy && ! taxonomy_exists( $taxonomy ) ) {
	// 	return new WP_Error( 'invalid_taxonomy', __( 'Invalid taxonomy.' ) );
	// }

	if ( $term instanceof WP_Term ) {
		$_term = $term;
	} elseif ( is_object( $term ) ) {
		if ( empty( $term->filter ) || 'raw' === $term->filter ) {
			$_term = sanitize_term( $term, $taxonomy, 'raw' );
			$_term = new WP_Term( $_term );
		} else {
			$_term = WP_Term::get_instance( $term->term_id );
		}
	} else {
		$_term = WP_Term::get_instance( $term, $taxonomy );
	}

	if ( is_wp_error( $_term ) ) {
		return $_term;
	} elseif ( ! $_term ) {
		return null;
	}

	// Ensure for filters that this is not empty.
	$taxonomy = $_term->taxonomy;

	/**
	 * Filters a taxonomy term object.
	 *
	 * The {@see 'get_$taxonomy'} hook is also available for targeting a specific
	 * taxonomy.
	 *
	 * @since 2.3.0
	 * @since 4.4.0 `$_term` is now a `WP_Term` object.
	 *
	 * @param WP_Term $_term    Term object.
	 * @param string  $taxonomy The taxonomy slug.
	 */
	$_term = apply_filters( 'get_term', $_term, $taxonomy );

	/**
	 * Filters a taxonomy term object.
	 *
	 * The dynamic portion of the hook name, `$taxonomy`, refers
	 * to the slug of the term's taxonomy.
	 *
	 * Possible hook names include:
	 *
	 *  - `get_category`
	 *  - `get_post_tag`
	 *
	 * @since 2.3.0
	 * @since 4.4.0 `$_term` is now a `WP_Term` object.
	 *
	 * @param WP_Term $_term    Term object.
	 * @param string  $taxonomy The taxonomy slug.
	 */
	$_term = apply_filters( "get_{$taxonomy}", $_term, $taxonomy );

	// Bail if a filter callback has changed the type of the `$_term` object.
	if ( ! ( $_term instanceof WP_Term ) ) {
		return $_term;
	}

	// Sanitize term, according to the specified filter.
	$_term->filter( $filter );

	if ( ARRAY_A === $output ) {
		return $_term->to_array();
	} elseif ( ARRAY_N === $output ) {
		return array_values( $_term->to_array() );
	}

	return $_term;
}

function sid_upload_from_url( $url, $title = null ) {
	require_once( ABSPATH . "/wp-load.php");
	require_once( ABSPATH . "/wp-admin/includes/image.php");
	require_once( ABSPATH . "/wp-admin/includes/file.php");
	require_once( ABSPATH . "/wp-admin/includes/media.php");
	
	// Download url to a temp file
	$tmp = download_url( $url );
	if ( is_wp_error( $tmp ) ) return false;
	
	// Get the filename and extension ("photo.png" => "photo", "png")
	$filename = pathinfo($url, PATHINFO_FILENAME);
	$extension = pathinfo($url, PATHINFO_EXTENSION);
	
	// An extension is required or else WordPress will reject the upload
	if ( ! $extension ) {
		// Look up mime type, example: "/photo.png" -> "image/png"
		$mime = mime_content_type( $tmp );
		$mime = is_string($mime) ? sanitize_mime_type( $mime ) : false;
		
		// Only allow certain mime types because mime types do not always end in a valid extension (see the .doc example below)
		$mime_extensions = array(
			// mime_type         => extension (no period)
			'text/plain'         => 'txt',
			'text/csv'           => 'csv',
			'application/msword' => 'doc',
			'image/jpg'          => 'jpg',
			'image/jpeg'         => 'jpeg',
			'image/gif'          => 'gif',
			'image/png'          => 'png',
			'video/mp4'          => 'mp4',
		);
		
		if ( isset( $mime_extensions[$mime] ) ) {
			// Use the mapped extension
			$extension = $mime_extensions[$mime];
		}else{
			// Could not identify extension
			@unlink($tmp);
			return false;
		}
	}
	
	
	
	// Upload by "sideloading": "the same way as an uploaded file is handled by media_handle_upload"
	$args = array(
		'name' => "$filename.$extension",
		'tmp_name' => $tmp,
	);
	
	// Do the upload
	$attachment_id = media_handle_sideload( $args, 0, $title);
	
	// Cleanup temp file
	@unlink($tmp);
	
	// Error uploading
	if ( is_wp_error($attachment_id) ) return false;
	
	// Success, return attachment ID (int)
	return (int) $attachment_id;
}



function sid_console_log($message) {
	global $wpdb;
	$wpdb->query($wpdb->prepare("INSERT INTO `".$wpdb->prefix."rala_console_log` (`message`) VALUES ('%s');",$message));
}