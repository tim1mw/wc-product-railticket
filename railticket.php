<?php

/**
 * Plugin Name: WooCommerce Ticket Product Type
 * Description: Works with railtimetable
 */

if ( ! defined( 'ABSPATH' ) ) {
    return;
}

define('SINGLE_TICKET', 0);
define('RETURN_TICKET', 1);
define('SPECIAL_TICKET', 2);

require_once('calendar.php');
require_once('bookingclass.php');


// Wordpress is failing to set the timezone, so force it here.
date_default_timezone_set(get_option('timezone_string'));

/**
 * Register the custom product type after init
 */
function register_railticket_product_type() {
    require_once(plugin_dir_path(__FILE__).'includes/class-wc-product-railticket.php');
}

function add_railticket_product( $types ){
    // Key should be exactly the same as in the class product_type parameter
    $types[ 'railticket' ] = __( 'Rail Ticket', 'railticket_product' );
    return $types;
}

function railticket_get_ticket_data() {
    return json_decode(file_get_contents(plugin_dir_path(__FILE__).'ticket-types.js'));
}

/**
* Show information on the product page
**/

function railticket_product_front() {
    global $product;

    if ($product->get_type() != 'railticket') {  	
        return;
    }

    $tickettypes = railticket_get_ticket_data();

    foreach  ($tickettypes->types as $ttype) {

    }

    echo '</li>';

}

/*
* This forces the general options to show so tax class can be set
**/

function railticket_custom_product_admin_custom_js() {
    if ('product' != get_post_type()) :
        return;
    endif;
    ?>
    <script type='text/javascript'>
        jQuery(document).ready(function () {
            // You can't selectively show just the tax classes, which are all I want, so enable the simple ones and then disable price.
            jQuery('.options_group.show_if_simple').addClass('show_if_railticket').show();
        });
    </script>
    <?php
}

function railticket_selector() {
    $ticketbuilder = railticket_getticketbuilder();
    return $ticketbuilder->render();
}


function railticket_script()
{
    wp_register_script('railticket_script', plugins_url('wc-product-railticket/ticket-rules.js'));
    wp_enqueue_script('railticket_script');
}

function railticket_style()
{
    global $wpdb;
    wp_register_style('railticket_style', plugins_url('wc-product-railticket/style.css'));
    wp_enqueue_style('railticket_style');
}


function railticket_ajax_request() {
    $ticketbuilder = railticket_getticketbuilder();
    $function = railticket_getpostfield('function');
    $result = array();
    switch ($function) {
        case 'bookable_stations':
            $result = $ticketbuilder->get_bookable_stations();
            break;
        case 'bookable_trains':
            $result = $ticketbuilder->get_bookable_trains();
            break;
    }

    wp_send_json_success($result);
}

function railticket_getticketbuilder() {
    $dateoftravel = railticket_getpostfield('dateoftravel');
    $fromstation = railticket_getpostfield('fromstation');
    $tostation = railticket_getpostfield('tostation');
    $type = railticket_getpostfield('type');
    $outtime = railticket_getpostfield('outtime');
    $rettime = railticket_getpostfield('rettime');
    $tickets = array();

    return new TicketBuilder($dateoftravel, $fromstation, $tostation, $type, $outtime, $rettime, $tickets);
}

function railticket_getpostfield($field) {
    if (array_key_exists($field, $_REQUEST)) {
        return sanitize_text_field($_REQUEST[$field]);
    }
    return null;
}

add_action('init', 'register_railticket_product_type');
add_filter('product_type_selector', 'add_railticket_product');
add_action('woocommerce_after_single_product_summary', 'railticket_product_front');

// General options refuse to show without an advanced element we don't need....
add_action( 'woocommerce_product_options_general_product_data', function(){
            echo '<div class="options_group show_if_advanced clear"></div>';
} );

add_action('admin_footer', 'railticket_custom_product_admin_custom_js');
add_shortcode('railticket_selector', 'railticket_selector');
add_action( 'wp_enqueue_scripts', 'railticket_style' );
add_action( 'wp_ajax_railticket_ajax', 'railticket_ajax_request');
