<?php

/**
* Note run this after upgrade!
* UPDATE `wp_woocommerce_order_itemmeta` SET `meta_key`='supplement' WHERE meta_key='tickettimes-pricesupplement' 
**/

if ( ! defined( 'ABSPATH' ) ) {
    return;
}

add_action('init', 'register_railticket_product_type');
add_filter('product_type_selector', 'add_railticket_product');

add_action('woocommerce_before_calculate_totals', 'railticket_custom_price_to_cart_item', 99 );
add_filter('woocommerce_prevent_admin_access', '__return_false' );
add_filter('woocommerce_disable_admin_bar', '__return_false' );
add_filter('woocommerce_is_sold_individually', 'railticket_remove_quantity_fields', 10, 2 );
add_filter('woocommerce_get_price_html', 'railticket_remove_price_fields', 10, 2 );
add_filter('woocommerce_get_item_data', 'railticket_cart_item_custom_meta_data', 10, 2 );
add_filter('woocommerce_order_item_get_formatted_meta_data', 'railticket_order_item_get_formatted_meta_data', 10, 1 );
add_action('woocommerce_remove_cart_item', 'railticket_cart_updated', 10, 2 );
add_action('woocommerce_checkout_create_order_line_item', 'railticket_cart_order_item_metadata', 10, 4 );
add_action('woocommerce_before_thankyou', 'railticket_cart_complete', 10, 1);
add_action('woocommerce_before_checkout_form', 'railticket_cart_check_cart');
add_action('woocommerce_before_cart', 'railticket_cart_check_cart');
add_action('woocommerce_new_order', 'railticket_cart_check_cart_at_checkout');
add_action('woocommerce_order_status_refunded', 'railticket_order_cancel_refund');
add_action('woocommerce_order_status_cancelled', 'railticket_order_cancel_refund');
//add_action('woocommerce_after_single_product_summary', 'railticket_product_front');

// General options refuse to show without an advanced element we don't need....
add_action( 'woocommerce_product_options_general_product_data', function(){
    echo '<div class="options_group show_if_advanced clear"></div>';
} );

/**
* Show information on the product page
**/

/*
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

function railticket_get_ticket_data() {
    return json_decode(file_get_contents(plugin_dir_path(__FILE__).'ticket-types.js'));
}
*/

/**
 * Register the custom product type after init
 */

function register_railticket_product_type() {
    require_once(plugin_dir_path(__FILE__).'includes/class-wc-product-railticket.php');
}

function add_railticket_product($types){
    // Key should be exactly the same as in the class product_type parameter
    $types[ 'railticket' ] = __( 'Rail Ticket', 'railticket_product' );
    return $types;
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

function railticket_cart_item_custom_meta_data($item_data, $cart_item) {
    if (!isset($cart_item['ticketselections']) || !isset($cart_item['ticketsallocated'])) {
        return $item_data;
    }

    $bookingorder = \wc_railticket\BookingOrder::get_booking_order_cart($cart_item);
    if (!$bookingorder) {
        return $item_data;
    }

    $item_data[] = array(
        'key'       => __("Date of Travel", "wc_railticket"),
        'value'     => $bookingorder->get_date(true)
    );

    $bookings = $bookingorder->get_bookings();

    if ($bookings[0]->is_special()) {
        $item_data[] = array(
            'key'       => __("Special", "wc_railticket"),
            'value'     => $bookings[0]->get_dep_time(true)
       );

        $item_data[] = array(
            'key'       => __("Departing from ", "wc_railticket"),
            'value'     => $bookings[0]->get_from_station()->get_name()
       );

        $item_data[] = array(
            'key'       => __("Seating Bays", "wc_railticket"),
            'value'     => $bookingorder->get_seats()
       );
    } else {
        $item_data[] = array(
            'key'       => __("Journey", "wc_railticket"),
            'value'     => $bookingorder->get_journeytype(true)." ".__("from", "wc_railticket")." ".
                       $bookings[0]->get_from_station()->get_name()." ".__("to", "wc_railticket")." ".
                       $bookings[0]->get_to_station()->get_name()
       );

        // TODO Hide bays for seat only allocation
        foreach ($bookings as $booking) {
            $item_data[] = array(
                'key'       => $booking->get_dep_time(true)." ".__("departure from ", "wc_railticket").
                               $booking->get_from_station()->get_name(),
                'value'     => $booking->get_bays(true)
            ); 
        }
    }

    $item_data[] = array(
        'key'       => __("Tickets", "wc_railticket"),
        'value'     => $bookingorder->get_tickets(true)
    );   

    $item_data[] = array(
        'key'       => __("Total Seats", "wc_railticket"),
        'value'     => $bookingorder->get_seats()
    );

    if ($bookingorder->get_supplement() > 0) {
        $item_data[] = array(
            'key'       => __("Minimum Price Supplement", "wc_railticket"),
            'value'     => $bookingorder->get_supplement(true)
        );
    }

    $discount = $bookingorder->get_discount_type();
    if ($discount) {
        $item_data[] = array(
            'key'       => __("Discount Type", "wc_railticket"),
            'value'     => $discount->get_name()
        ); 
        $item_data[] = array(
            'key'       => __("Discount Savings", "wc_railticket"),
            'value'     => $bookingorder->get_discount(true)
        ); 
    }

    return $item_data;
}

function railticket_order_item_get_formatted_meta_data($formatted_meta) {
    global $wpdb;
    // Woocommerce doesn't tell use what the order or order item id here 
    // The itemid is being put into the order metadata as a value so we get it here as a value
    $itemid = false;

    foreach ($formatted_meta as $item) {
        if ($item->key == 'itemid') {
            $itemid = $item->value;
            break;
        }
    }

    // Older orders are missing the itemid param.
    // We can get the cart item id by using the key from a meta data and get the order item
    // ID from it's DB entry. Sometimes the keys here in the formatted data are invalid, so loop till we get a good one.
    if ($itemid == 0 || strlen($itemid) == 0) {
        foreach (array_keys($formatted_meta) as $key) {
            $itemid = $wpdb->get_var("SELECT order_item_id FROM {$wpdb->prefix}woocommerce_order_itemmeta ".
                "WHERE meta_id = ".$key);

            if ($itemid != 0) {
                break;
            }
        }
    }

    $bookingorder = \wc_railticket\BookingOrder::get_booking_order_itemid($itemid);

    if (!$bookingorder) {
        // This is getting silly.... Sometimes this is called with the DB in an inconsistent state, so try the cart item
        foreach ($formatted_meta as $item) {
            if ($item->key == 'cart_item_key') {
                $cartid = $item->value;
                $bookingorder = \wc_railticket\BookingOrder::get_booking_order_bycartkey($cartid);
                break;
            }
        }
    }

    if (!$bookingorder) {
        $retmeta = array();

        $found = false;
        foreach ($formatted_meta as $index => $item) {
            $fmparts = explode("-", $item->key);
            switch ($fmparts[0]) {
                case 'cart_item_key':
                case 'itemid':
                    break;
                // If any of these fields are present and we don't have a ticket ID by now, this booking is broken....
                // There has to be a better way to deal with this....
                case 'supplement':
                case 'ticketsallocated':
                case 'ticketselections':
                case 'ticketprices':
                    if ($found) {
                        break;
                    }
                    $item->display_key = __("Order Problem", "wc_railticket");
                    $item->display_value = __("Your booking data is missing, this is very unusual, your tickets may have expired in the basket while the payment process was being completed. Please contact the railway ASAP with your booking ID to have this corrected.", "wc_railticket");
                    $retmeta[$index] = $item;
                    $found = true;
                    break;
                default:
                    $retmeta[$index] = $item;
            }
        }

        return $retmeta;
    }
    $bookings = $bookingorder->get_bookings();

    // This is a massive fudge because woocommerce won't let me insert arbitrary data to display here, some of the
    // the data in the order items we don't want to show, but we have data from the underlying bookings we do want to show.
    // So the stuff shown and the key used doesn't fully match up...

    $retmeta = array();
    $tkey = false;
    $skey = false;
    $pkey = false;
    $dkey = false;

    foreach ($formatted_meta as $index => $fm) {
        $fmparts = explode("-", $fm->key);
        switch ($fmparts[0]) {
            case 'supplement':
                $fm->display_key = __("Supplement", "wc_railticket");
                $fm->display_value = $bookingorder->get_supplement(true);
                $retmeta[$index] = $fm;
                break;
            case 'ticketsallocated':
                if ($tkey == false) {
                    $tkey = $index;
                    $fm->display_key = __("Tickets", "wc_railticket");
                    $fm->display_value = $bookingorder->get_tickets(true);
                    $retmeta[$index] = $fm;
                }
                break;
            case 'ticketselections':
                if ($skey == false) {
                    $skey = $index;
                    $fm->display_key = __("Journey", "wc_railticket");
                    $d = "<br />".__("Date of Travel", "wc_railticket").": ".$bookingorder->get_date(true)."<br />";
                    if ($bookings[0]->is_special()) {
                        $d .= __("Total Seats", "wc_railticket").": ".$bookingorder->get_seats()."<br />".
                            $bookings[0]->get_dep_time(true)."<br />".
                            __("Departing from ", "wc_railticket").$bookings[0]->get_from_station()->get_name().
                            "<br />".$bookings[0]->get_bays(true)."<br />";
                    } else {
                        $d .= $bookingorder->get_journeytype(true)." ".__("from", "wc_railticket")." ".
                        $bookings[0]->get_from_station()->get_name()." ".__("to", "wc_railticket")." ".
                        $bookings[0]->get_to_station()->get_name()."<br />";
                        $d .= __("Total Seats", "wc_railticket").": ".$bookingorder->get_seats()."<br />";

                        // TODO Hide bays for seat based capacity
                        foreach ($bookings as $booking) {
                           $d .= $booking->get_dep_time(true)." ".__("departure from ", "wc_railticket").$booking->get_from_station()->get_name().
                                 ": ".$booking->get_bays(true)."<br />";

                        }
                    }

                    $fm->display_value = $d;
                    $retmeta[$index] = $fm;
                }
                break;
            case 'ticketprices':
                if ($dkey == false) {
                    $discount = $bookingorder->get_discount_type();
                    if ($discount) {
                        $fm->display_key = __("Discount Type", "wc_railticket");
                        $fm->display_value = $discount->get_name().", ".__("saving", "wc_railticket")." ".$bookingorder->get_discount(true);
                        $retmeta[$index] = $fm;
                    }
                    $dkey = true;
                }
                break;
        }
    }
    
    return $retmeta;
}

function railticket_cart_updated($cart_item_key, $cart) {
    \wc_railticket\Booking::delete_booking_order_cart($cart_item_key);
}

function railticket_cart_order_item_metadata($item, $cart_item_key, $values, $order ) {
    railticket_product_add_new_order_line_item($item, $values, 'ticketselections');
    railticket_product_add_new_order_line_item($item, $values, 'ticketsallocated');
    railticket_product_add_new_order_line_item($item, $values, 'ticketprices');
    if (array_key_exists('supplement', $values)) {
        $item->update_meta_data('supplement', $values['supplement']);
    }
    $item->update_meta_data('itemid', $item->get_id());
    $item->update_meta_data('cart_item_key', $cart_item_key);

    \wc_railticket\Booking::set_cart_itemid($item->get_id(), $values['key']);

    // Save the cart item key as hidden order item meta data
    //$item->update_meta_data( '_cart_item_key', $cart_item_key );
}

function railticket_product_add_new_order_line_item($item, $values, $key) {
    if ( ! empty( $values[$key] ) ) {
        foreach ($values[$key] as $a => $b) {
            $item->update_meta_data($key."-".$a, $b);
        }
    }
}

function railticket_cart_complete($order_id) {
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
                $key = $item->get_meta( 'cart_item_key' );
                \wc_railticket\Booking::cart_purchased($order_id, $item_id, $key);

                $pn = get_option('wc_product_railticket_prioritynotify');
                // Sanity check just in case somebody managed to get past all the expiry checks and the order
                // took so long the booking expired....
                $bo = \wc_railticket\BookingOrder::get_booking_order($order_id);
                if ($bo == false) {
                    railticket_send_broken_order($order_id, $pn, $key);
                    continue;
                }

                if (strlen($pn) > 0) {
                    if ($bo->priority_requested() > 0) {
                        railticket_send_priority_notify($bo, $pn);
                    }
                }
            } 
        }
        $order->update_meta_data( '_railticket_thankyou_action_done', true );
        $order->save();
    }
}

function railticket_send_priority_notify(\wc_railticket\BookingOrder $bo, $pn) {
    global $rtmustache;
    $alldata = railticket_get_booking_order_data($bo);

    $template = $rtmustache->loadTemplate('priorityemail');
    $message = $template->render($alldata);
    $content_type = function() { return 'text/html'; };
    add_filter( 'wp_mail_content_type', $content_type );
    wp_mail(explode(',', $pn), "Wheelchair booking for ".$bo->get_date(true), $message);
    remove_filter( 'wp_mail_content_type', $content_type );
}

function railticket_send_broken_order($order, $pn) {
    global $rtmustache;
    $alldata = new stdclass();
    $alldata->orderid = $order;
    $template = $rtmustache->loadTemplate('brokenorderemail');
    $message = $template->render($alldata);
    $content_type = function() { return 'text/html'; };
    add_filter( 'wp_mail_content_type', $content_type );
    wp_mail(explode(',', $pn), "Broken order id:".$order, $message);
    remove_filter( 'wp_mail_content_type', $content_type );
}

/**
 * Check any rail tickets are still valid
 *
 */
function railticket_cart_check_cart() {
	global $woocommerce, $wpdb;
    $ticketid = get_option('wc_product_railticket_woocommerce_product');
    $items = $woocommerce->cart->get_cart();
    foreach($items as $item => $values) { 
        if ($ticketid == $values['data']->get_id()) {
            $sql = "SELECT id FROM {$wpdb->prefix}wc_railticket_bookings WHERE woocartitem = '".$item."' AND expiring = 0";
            $bookingids = $wpdb->get_results($sql);
            if (count($bookingids) === 0) {
                $woocommerce->cart->remove_cart_item($item);
                echo "<p style='color:red;font-weight:bold;'>Your rail journey tickets have expired and have been removed from the basket. Sorry.</p>";
            }
        }
    }
}

function railticket_cart_check_cart_at_checkout($callable) {
	global $woocommerce, $wpdb;
    $ticketid = get_option('wc_product_railticket_woocommerce_product');
    $items = $woocommerce->cart->get_cart();
    foreach($items as $item => $values) { 
        if ($ticketid == $values['data']->get_id()) {
            // Allow them through here even if the tickets are expiring.
            $sql = "SELECT id FROM {$wpdb->prefix}wc_railticket_bookings WHERE woocartitem = '".$item."'";
            $bookingids = $wpdb->get_results($sql);
            if (count($bookingids) === 0) {
                $res = new \stdclass();
                $res->result = 'failure';
                $res->messages = "<p style='color:red;font-weight:bold;'>Your rail journey tickets have expired and have been removed from the basket. Sorry.</p>";
                $res->refresh = false;
                $res->reload = true;
                echo json_encode($res);
                exit;
            }

            // If they have gotten this far, they are probably serious about the booking, so lets re-set the clock.
            // This will also give us more accurate time of purchase.
            \wc_railticket\Booking::reset_expire($item);
        }
    }
}

function railticket_order_cancel_refund($order_id) {
    $bookingorder = \wc_railticket\BookingOrder::get_booking_order($order_id);
    $bookingorder->delete();
}
