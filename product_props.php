<?php
/*
Plugin Name: WooCommerce Product Props
Plugin URI: http://www.clickandgarden.it/
Description: WooCommerce custom product properties
Author: Turcu Laurentiu
Author URI: http://
Version: 1.0.0

	Copyright: © 2012 Acme Corp (email : turculaurentiu91@gmail.com)
	License: GNU General Public License v3.0
	License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/


//code to deal with admin form display
//the form will be displaied in the post edit for product posts

//creates the box container at the end of the page
function props_add_admin_box() {
    add_meta_box( 'props_admin_form', "Product Properties" , 'props_admin_form_display', 'product' );
}
add_action( 'add_meta_boxes', 'props_add_admin_box' );

//handles the display of the admin form
function props_admin_form_display( $post ) {
    //get the properties number for the current post
    $props = get_post_meta($post->ID, "props", true);
    $prefabs = get_post_meta($post->ID, "prefabs", true);

    //if props is empty, we deal with a new product or with a product whout props
    echo '<script>';
    if( !empty($props)){
      echo 'var props = ' . $props . "; ";
    }

    if (!empty($prefabs)){
      echo ' var prefabs = ' . $prefabs . "; ";
    }
    echo 'var product_id = ' . $post->ID . "; ";
    echo '</script>';
    include "admin_form.html";
}

//handles the save of props
function props_save($post_id){
  $is_autosave = wp_is_post_autosave( $post_id );
  $is_revision = wp_is_post_revision( $post_id );

  if ($is_autosave || $is_revision){
    return;
  }

  if (isset($_POST['props_json'])){
    update_post_meta($post_id, "props", $_POST['props_json']);
    update_post_meta($post_id, "prefabs", $_POST['prefabs_json']);
  }
}

add_action('save_post', 'props_save');

//handle the display of table, when shortcode is requested
function props_product_display($attributes){
  extract(shortcode_atts(array('post_id' => -1,),$attributes));
  if ($post_id == -1){
    return "Sorry! this prouct is inextistent.";
  }
  $props = get_post_meta($post_id, "props", true);
  $prefabs = get_post_meta($post_id, "prefabs", true);
  $price = get_post_meta($post_id, "_regular_price", true);

if (empty($props)){
  $props = "null";
}

if (empty($prefabs)){
  $prefabs = "null";
}

  $json_values = "<script>
                  var props = " . $props . ";
                  var prefabs = " . $prefabs . ";
                  var product_id = " . $post_id . ";
                  var regular_price = " . $price . ";
                  </script>";
  $table_file = file_get_contents( plugin_dir_path(__FILE__) . "table.html");

  return $json_values . $table_file;
}

//handles all the shorcodes registry (we have only one but...)
function props_register_shortcodes(){
  add_shortcode("product-table", "props_product_display" );
}

add_action( 'init', 'props_register_shortcodes');

//handles ajax request to add to chart

function props_add_to_cart(WP_REST_Request $request){

  session_start();

  //$_SESSION["req_product_id"] = $request["product_id"];
  $_SESSION["req_props"] = $request["props"];
  $_SESSION["req_total"] = $request["total"];

  $return_val = "product id :" . $_SESSION["req_product_id"] . " | ";
  $return_val = $return_val . $_SESSION["req_total"] . " | ";

  for ($i = 0; $i<count($_SESSION["req_props"]); $i++){
    $return_val = $return_val . $_SESSION["req_props"][$i] . " | ";
  }

  return $return_val;
}

//function to add custom data to WooCommerce session
add_filter('woocommerce_add_cart_item_data','props_data_to_wc_session',1,2);

function props_data_to_wc_session($cart_item_data,$product_id){
  session_start();
  global $Woocommerce;

  $new_value = array();
  $new_value['total'] = $_SESSION["req_total"];
  $new_value["props"] = $_SESSION["req_props"];

  unset($_SESSION["req_total"] );
  unset($_SESSION["req_props"]);

  if (empty($cart_item_data)){
    return $new_value;
  } else {
    return array_merge($cart_item_data, $new_value);
  }
}

//extract props form wc session and add it to the cart vuew
add_filter('woocommerce_get_cart_item_from_session', 'get_props_from_session', 1, 3 );

function get_props_from_session($item,$values,$key){
  if (array_key_exists( 'props', $values ) ){
        $item['props'] = $values['props'];
  }
  if (array_key_exists("total", $values)){
    $item["total"] = $values["total"];
  }
        return $item;
}


//add_filter('woocommerce_checkout_cart_item_quantity','props_from_session_into_cart',1,3);
//add_filter('woocommerce_cart_item_price','props_from_session_into_cart',1,3);
add_filter('woocommerce_cart_item_name','props_from_session_into_cart',1,3);

function props_from_session_into_cart($product_name, $values, $cart_item_key){

  /*code to add custom data on Cart & checkout Page*/
  $return_string = $product_name . '<ul>';// . "<br />" . print_r($values['_custom_options']);

  for ($i=0; $i<count($values["props"]); $i++){
    $return_string = $return_string . "<li>" . $values["props"][$i] . "</li>";
  }

  $return_string = $return_string . "</ul>";
  return $return_string;
}

add_action( 'woocommerce_before_calculate_totals', 'props_update_custom_price', 1, 1 );
function props_update_custom_price( $cart_object ) {
    foreach ( $cart_object->cart_contents as $cart_item_key => $value ) {
        // Version 2.x
        //$value['data']->price = $value['_custom_options']['custom_price'];
        // Version 3.x / 4.x
        $value['data']->set_price($value['total']); // + $value['data']->get_price());
    }
}

//add values to order meta data to be seen in order posts and confirmation mail
add_action('woocommerce_add_order_item_meta','props_add_values_to_order_item_meta',1,2);
function props_add_values_to_order_item_meta($item_id, $values) {
    global $woocommerce,$wpdb;

    for ($i=0; $i<count($values["props"]); $i++){
      wc_add_order_item_meta($item_id, $values["props"][$i],$values['props'][$i]);
    }
}

add_action('woocommerce_before_cart_item_quantity_zero','props_remove_user_custom_data_options_from_cart',1,1);
if(!function_exists('props_remove_user_custom_data_options_from_cart'))
{
    function props_remove_user_custom_data_options_from_cart($cart_item_key)
    {
        global $woocommerce;
        // Get cart
        $cart = $woocommerce->cart->get_cart();
        // For each item in cart, if item is upsell of deleted product, delete it
        foreach( $cart as $key => $values)
        {
        if ( $values['props'] == $cart_item_key ){
            unset( $woocommerce->cart->cart_contents[ $key ] );
        }
        if ( $values['total'] == $cart_item_key ){
            unset( $woocommerce->cart->cart_contents[ $key ] );
        }
    }
  }
}

//registers tha api route
add_action( 'rest_api_init', function () {
  register_rest_route( 'props', '/addCart//', array(
    'methods' => 'POST',
    'callback' => 'props_add_to_cart',
  ) );
} );
?>
