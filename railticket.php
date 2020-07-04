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
require_once('editlib.php');

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
        case 'tickets':
            $result = $ticketbuilder->get_tickets();
            break;
        case 'capacity':
            $result = $ticketbuilder->get_capacity();
            break;
        case 'purchase':
            $result = $ticketbuilder->do_purchase();
            break;
    }

    wp_send_json_success($result);
}

 function railticket_custom_price_to_cart_item( $cart_object ) {  
    if( !WC()->session->__isset( "reload_checkout" )) {
        foreach ( $cart_object->cart_contents as $key => $value ) {
            if( isset( $value["custom_price"] ) ) {
                //for woocommerce version lower than 3
                //$value['data']->price = $value["custom_price"];
                //for woocommerce version +3
                $value['data']->set_price($value["custom_price"]);
            }
        }  
    }  
}

function railticket_remove_quantity_fields( $return, $product ) {
    switch ( $product->product_type ) :
        case "railticket":
            return true;
            break;
        default: 
            return false;
            break;
    endswitch;
}
add_filter( 'woocommerce_is_sold_individually', 'railticket_remove_quantity_fields', 10, 2 );

function railticket_remove_price_fields( $price, $product ) {
    switch ( $product->product_type ) :
        case "railticket":
            return "";
            break;
        default: 
            return $price;
            break;
    endswitch;
}
add_filter( 'woocommerce_get_price_html', 'railticket_remove_price_fields', 10, 2 );

function railticket_cart_item_custom_meta_data($item_data, $cart_item) {
    global $wpdb;
    if ( isset($cart_item['ticketselections']) && isset($cart_item['ticketsallocated']) && isset($cart_item['tickettimes'])) {
        $item_data[] = array(
            'key'       => "Date of Travel",
            'value'     => railticket_product_format_date($cart_item['tickettimes']['dateoftravel'])
        );
        $item_data[] = array(
            'key'       => "Journey Type",
            'value'     => ucfirst($cart_item['tickettimes']['journeytype'])
        );

        $fstation = railticket_product_get_station_name($cart_item['tickettimes']['fromstation']);
        $item_data[] = array(
            'key'       => "Outbound",
            'value'     => $fstation.", ".railticket_product_format_time($cart_item['tickettimes']['outtime'])
        );
        if ($cart_item['tickettimes']['journeytype'] == 'return') {
            $tstation = railticket_product_get_station_name($cart_item['tickettimes']['tostation']);
            $item_data[] = array(
                'key'       => "Return",
                'value'     => $tstation.", ".railticket_product_format_time($cart_item['tickettimes']['rettime'])
            );
        }

        foreach ($cart_item['ticketsallocated'] as $ttype => $qty) {
            $ticketdata = railticket_product_ticketsallocated_display($ttype);
            $item_data[] = array(
                'key'       => $qty." x ".$ticketdata->name,
                'value'     => $ticketdata->description
            );
        }

        $item_data[] = array(
            'key'       => "Total Seats",
            'value'     => $cart_item['tickettimes']['totalseats']
        );

        $item_data[] = array(
            'key'       => "Outboud Seating bays",
            'value'     => $cart_item['tickettimes']['outbays']
        );

        $item_data[] = array(
            'key'       => "Return Seating bays",
            'value'     => $cart_item['tickettimes']['retbays']
        );

        if ($cart_item['tickettimes']['pricesupplement'] > 0) {
            $item_data[] = array(
                'key'       => "Minimum Price Supplement",
                'value'     => "£".$cart_item['tickettimes']['pricesupplement']
            );
        }
    }

    return $item_data;
}

function railticket_product_format_date($date) {
    // Wordpress is failing to set the timezone, so force it here.
    //date_default_timezone_set(get_option('timezone_string'));
    $railticket_timezone = new DateTimeZone(get_option('timezone_string'));
    $jdate = DateTime::createFromFormat('Y-m-d', $date);
    return strftime(get_option('railtimetable_date_format'), $jdate->getTimeStamp());
}

function railticket_product_format_time($time) {
    // Wordpress is failing to set the timezone, so force it here.
    //date_default_timezone_set(get_option('timezone_string'));
    $railticket_timezone = new DateTimeZone(get_option('timezone_string'));
    $dtime = DateTime::createFromFormat('H.i', $time);
    return strftime(get_option('railtimetable_time_format'), $dtime->getTimeStamp());
}

function railticket_product_get_station_name($id) {
    global $wpdb;
    return $wpdb->get_var("SELECT name FROM {$wpdb->prefix}railtimetable_stations WHERE id = ".$id);
}

function railticket_product_ticketsallocated_display($ttype) {
    global $wpdb;
    $sql = "SELECT {$wpdb->prefix}wc_railticket_prices.id, ".
        "{$wpdb->prefix}wc_railticket_prices.tickettype, ".
        "{$wpdb->prefix}wc_railticket_prices.price, ".
        "{$wpdb->prefix}wc_railticket_tickettypes.name, ".
        "{$wpdb->prefix}wc_railticket_tickettypes.description ".
        "FROM {$wpdb->prefix}wc_railticket_prices ".
        "INNER JOIN {$wpdb->prefix}wc_railticket_tickettypes ON ".
        "{$wpdb->prefix}wc_railticket_tickettypes.code = {$wpdb->prefix}wc_railticket_prices.tickettype ".
        "WHERE tickettype = '".$ttype."'";
    return $wpdb->get_results($sql, OBJECT)[0];
}

add_filter( 'woocommerce_get_item_data', 'railticket_cart_item_custom_meta_data', 10, 2 );


function railticket_product_add_on_order_item_meta($item_id, $values) {
    railticket_product_add_on_order_item_meta2($item_id, $values, 'ticketselections');
    railticket_product_add_on_order_item_meta2($item_id, $values, 'ticketsallocated');
    railticket_product_add_on_order_item_meta2($item_id, $values, 'tickettimes');
}

function railticket_product_add_on_order_item_meta2($item_id, $values, $key) {
    if ( ! empty( $values[$key] ) ) {
        foreach ($values[$key] as $a => $b) {
            wc_add_order_item_meta($item_id, $key."-".$a, $b);
        }
    }
}

add_action( 'woocommerce_add_order_item_meta', 'railticket_product_add_on_order_item_meta', 10, 2 );
add_filter( 'woocommerce_order_item_get_formatted_meta_data', 'railticket_order_item_get_formatted_meta_data', 10, 1 );

function railticket_order_item_get_formatted_meta_data($formatted_meta) {
    $retmeta = array();
    $tickets = "";
    $tkey = false;
    $outstationindex = false;
    $tostationindex = false;
    foreach ($formatted_meta as $index => $fm) {
        $fmparts = explode("-", $fm->key);
        switch ($fmparts[0]) {
            case 'ticketselections':
                // Hide this
                break;
            case 'ticketsallocated':
                if ($tkey == false) {
                    $tkey = $index;
                    $retmeta[$index] = $fm;
                }

                $ticket = railticket_product_ticketsallocated_display($fmparts[1]);
                $tickets .= $fm->value.'x&nbsp;'.$ticket->name." (".$ticket->description.'), ';
                break;
            case 'tickettimes':
                switch ($fmparts[1]) {
                    case 'fromstation':
                        $fm->display_key = 'Outbound';
                        $fm->display_value = '<p>'.railticket_product_get_station_name($fm->value);
                        $outstationindex = $index;
                        $retmeta[$index] = $fm;
                        break;
                    case 'tostation':
                        $fm->display_key = 'Return';
                        $fm->display_value = '<p>'.railticket_product_get_station_name($fm->value);
                        $tostationindex = $index;
                        $retmeta[$index] = $fm;
                        break;
                    case 'outtime':
                        $retmeta[$outstationindex]->display_value .= ", ".railticket_product_format_time($fm->value).'</p>';
                        break;
                    case 'rettime':
                        $retmeta[$tostationindex]->display_value .= ", ".railticket_product_format_time($fm->value).'</p>';
                        break;
                    case 'dateoftravel':
                        $fm->display_key = 'Date of travel';
                        $fm->display_value = '<p>'.railticket_product_format_date($fm->value).'</p>';
                        $retmeta[$index] = $fm;
                        break;
                    case 'journeytype':
                        $fm->display_key = 'Journey Type';
                        if ($fm->value == 'single') {
                            $fm->display_value = '<p>Single</p>';
                        } else {
                            $fm->display_value = '<p>Return</p>';
                        }
                        $retmeta[$index] = $fm;
                        break;
                    case 'totalseats':
                        $fm->display_key = 'Total Seats';
                        $fm->display_value = '<p>'.$fm->value.'</p>';
                        $tostationindex = $index;
                        $retmeta[$index] = $fm;
                        break;
                    case 'pricesupplement':
                        if ($fm->value > 0) {
                            $fm->display_key = 'Minimum Price Supplement';
                            $fm->display_value = '<p>£'.$fm->value.'</p>';
                            $tostationindex = $index;
                            $retmeta[$index] = $fm;
                        }
                        break;
                    case 'outbays':
                        $fm->display_key = 'Outboud Seating bays';
                        $fm->display_value = '<p>'.$fm->value.'</p>';
                        $retmeta[$index] = $fm;
                        break;
                    case 'retbays':
                        $fm->display_key = 'Return Seating bays';
                        $fm->display_value = '<p>'.$fm->value.'</p>';
                        $retmeta[$index] = $fm;
                        break;
                }
                break;
        }
    }
    $retmeta[$tkey]->display_key = "Tickets";
    $retmeta[$tkey]->display_value = '<p>'.substr($tickets, 0, strlen($tickets)-2).'</p>';

    return $retmeta;
}

function railticket_cart_updated($cart_item_key, $cart) {
    global $wpdb;
    $bookingids = $wpdb->get_results("SELECT id FROM {$wpdb->prefix}wc_railticket_bookings WHERE woocartitem = '".$cart_item_key."'");
    foreach ($bookingids as $bookingid) {
        $wpdb->delete("{$wpdb->prefix}wc_railticket_booking_bays", array('bookingid' => $bookingid->id));
        $wpdb->delete("{$wpdb->prefix}wc_railticket_bookings", array('id' => $bookingid->id));
    }
}
add_action( 'woocommerce_remove_cart_item', 'railticket_cart_updated', 10, 2 );

add_action('woocommerce_checkout_create_order_line_item', 'railticket_cart_order_item_metadata', 10, 4 );
function railticket_cart_order_item_metadata( $item, $cart_item_key, $values, $order ) {
    // Save the cart item key as hidden order item meta data
    $item->update_meta_data( '_cart_item_key', $cart_item_key );
}

add_action('woocommerce_thankyou', 'railticket_cart_complete', 10, 1);
function railticket_cart_complete($order_id) {
    global $wpdb;
    if ( ! $order_id )
        return;

    // Allow code execution only once 
    if( ! get_post_meta( $order_id, '_railticket_thankyou_action_done', true ) ) {

        // Get an instance of the WC_Order object
        $order = wc_get_order( $order_id );

        // Loop through order items
        foreach ( $order->get_items() as $item_id => $item ) {
            // Get the product object
            $product = $item->get_product();

            // Get the product Id
            $product_id = $product->get_id();
            if ($product_id == get_option('wc_product_railticket_woocommerce_product')) {
                $key = $item->get_meta( '_cart_item_key' );
                $wpdb->update("{$wpdb->prefix}wc_railticket_bookings",
                    array('wooorderid' => $order_id, 'woocartitem' => '', 'wooorderitem' => $item_id),
                    array('woocartitem' => $key));
            }
        }
        $order->update_meta_data( '_railticket_thankyou_action_done', true );
        $order->save();
    }

}

// Add a new interval of 120 seconds
// See http://codex.wordpress.org/Plugin_API/Filter_Reference/cron_schedules
add_filter( 'cron_schedules', 'railticket_add_every_two_minutes' );
function railticket_add_every_two_minutes( $schedules ) {
    $schedules['every_two_minutes'] = array(
            'interval'  => 120,
            'display'   => __( 'Every 2 Minutes', 'textdomain' )
    );
    return $schedules;
}

// Schedule an action if it's not already scheduled
if ( ! wp_next_scheduled( 'railticket_add_every_two_minutes' ) ) {
    wp_schedule_event( time(), 'every_two_minutes', 'railticket_add_every_two_minutes' );
}

// Hook into that action that'll fire every three minutes
add_action( 'railticket_add_every_two_minutes', 'railticket_every_two_minutes_event_func' );
function railticket_every_two_minutes_event_func() {
    global $wpdb;
    $expiretime = time() - intval(get_option('wc_product_railticket_reservetime'))*60;

    $sql = "SELECT id FROM {$wpdb->prefix}wc_railticket_bookings WHERE woocartitem != '' AND created < ".$expiretime;
    $bookingids = $wpdb->get_results($sql);
    foreach ($bookingids as $bookingid) {
        $wpdb->delete("{$wpdb->prefix}wc_railticket_booking_bays", array('bookingid' => $bookingid->id));
        $wpdb->delete("{$wpdb->prefix}wc_railticket_bookings", array('id' => $bookingid->id));
    }
}

add_action('woocommerce_before_checkout_form', 'railticket_cart_check_cart');
add_action('woocommerce_before_cart', 'railticket_cart_check_cart');

/**
 * Check any rail tickets are still valid
 *
 */
function railticket_cart_check_cart() {
	global $woocommerce, $wpdb;
    $items = $woocommerce->cart->get_cart();
    $ticketid = get_option('wc_product_railticket_woocommerce_product');
    $items = $woocommerce->cart->get_cart();
    foreach($items as $item => $values) { 
        if ($ticketid == $values['data']->get_id()) {
            $sql = "SELECT id FROM {$wpdb->prefix}wc_railticket_bookings WHERE woocartitem = '".$item."'";
            $bookingids = $wpdb->get_results($sql);
            if (count($bookingids) === 0) {
                $woocommerce->cart->remove_cart_item($item);
                echo "<p>Your rail journey tickets have expired and have been removed from the basket. Sorry.</p>";
            }
        }
    } 
}

add_action('woocommerce_order_status_refunded', 'railticket_order_cancel_refund');
add_action('woocommerce_order_status_cancelled', 'railticket_order_cancel_refund');
function railticket_order_cancel_refund($order_id) {
global $wpdb;
    $sql = "SELECT id FROM {$wpdb->prefix}wc_railticket_bookings WHERE wooorderid = ".$order_id;
    $bookingids = $wpdb->get_results($sql);
    foreach ($bookingids as $bookingid) {
        $wpdb->delete("{$wpdb->prefix}wc_railticket_booking_bays", array('bookingid' => $bookingid->id));
        $wpdb->delete("{$wpdb->prefix}wc_railticket_bookings", array('id' => $bookingid->id));
    }
}

function railticket_getticketbuilder() {
    $dateoftravel = railticket_getpostfield('dateoftravel');
    $fromstation = railticket_getpostfield('fromstation');
    $tostation = railticket_getpostfield('tostation');
    $outtime = railticket_getpostfield('outtime');
    $rettime = railticket_getpostfield('rettime');
    $journeytype = railticket_getpostfield('journeytype');
    $overridevalid = railticket_getpostfield('overridevalid');
    $disabledrequest = railticket_getpostfield('disabledrequest');
file_put_contents("/home/httpd/balashoptest.my-place.org.uk/x.txt", $disabledrequest);

    $ticketselections = null;
    if (array_key_exists('ticketselections', $_REQUEST)) {
        $ticketselections = json_decode(stripslashes($_REQUEST['ticketselections']));
    }
    $ticketsallocated = null;
    if (array_key_exists('ticketselections', $_REQUEST)) {
        $ticketsallocated = json_decode(stripslashes($_REQUEST['ticketallocated']));
    }
    $tickets = array();

    return new TicketBuilder($dateoftravel, $fromstation, $tostation, $outtime, $rettime,
        $journeytype, $ticketselections, $ticketsallocated, $overridevalid, $disabledrequest);
}

function railticket_getpostfield($field) {
    if (array_key_exists($field, $_REQUEST)) {
        return sanitize_text_field($_REQUEST[$field]);
    }
    return false;
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
add_action( 'wp_ajax_nopriv_railticket_ajax', 'railticket_ajax_request');
add_action( 'wp_ajax_railticket_ajax', 'railticket_ajax_request');

add_action( 'woocommerce_before_calculate_totals', 'railticket_custom_price_to_cart_item', 99 );
