<?php

if ( ! defined( 'ABSPATH' ) ) {
    return;
}

add_action('admin_footer', 'railticket_custom_product_admin_custom_js');
add_shortcode('railticket_selector', 'railticket_selector');
add_shortcode('railticket_special', 'railticket_get_special_button');
add_shortcode('railticket_review', 'railticket_review_order');
add_shortcode('railticket_survey', 'railticket_survey');
add_action( 'wp_ajax_nopriv_railticket_ajax', 'railticket_ajax_request');
add_action( 'wp_ajax_railticket_ajax', 'railticket_ajax_request');

add_filter( 'cron_schedules', 'railticket_add_every_two_minutes' );
add_action( 'railticket_add_every_two_minutes', 'railticket_every_two_minutes_event_func' );


function railticket_create_db() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    include('sqlimport.php');
    foreach ($sql as $s) {
        dbDelta($s);
    }
}

/*
* Work around method for WordPress's very inconsistent handling of timezones.
**/

function railticket_timefunc($fmt, $time) {
    $tz = date_default_timezone_get();
    date_default_timezone_set(get_option('timezone_string'));
    $result = strftime($fmt, $time);
    date_default_timezone_set($tz);
    return $result;
}

function railticket_selector() {
    $ticketbuilder = railticket_getticketbuilder();
    return $ticketbuilder->render();
}

function railticket_ajax_request() {
    try {
        $ticketbuilder = railticket_getticketbuilder();
        $function = railticket_getpostfield('function');
        $result = array();
        switch ($function) {
            case 'bookable_stations':
                $result = $ticketbuilder->get_bookable_stations();
                break;
            case 'journey_opts':
                $result = $ticketbuilder->get_journey_options();
                break;
            case 'bookable_trains':
                $result = $ticketbuilder->get_bookable_trains();
                break;
            case 'ticket_data':
                $result = $ticketbuilder->get_ticket_data();
                break;
            case 'validate_discount':
                $result = $ticketbuilder->get_validate_discount();
                break;
            case 'capacity':
                $result = $ticketbuilder->get_capacity();
                break;
            case 'purchase':
                $result = $ticketbuilder->do_purchase();
                break;
        }
        wp_send_json_success($result);
    } catch (\wc_railticket\TicketException $e) {
        $result = array('fatalerror' => $e->getMessage());
        wp_send_json_success($result);
    }
}

// Add a new interval of 120 seconds
// See http://codex.wordpress.org/Plugin_API/Filter_Reference/cron_schedules

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

// Hook into that action that'll fire every two minutes

function railticket_every_two_minutes_event_func() {
    global $wpdb;
    $expiretime = time() - intval(get_option('wc_product_railticket_reservetime'))*60;
    $releasetime = $expiretime - intval(get_option('wc_product_railticket_releaseinventory'))*60;

    $sql = "SELECT * FROM {$wpdb->prefix}wc_railticket_bookings WHERE woocartitem != '' AND expiring = 1 AND created < ".$releasetime;
    $bookings = $wpdb->get_results($sql);
    foreach ($bookings as $booking) {
        $wpdb->delete("{$wpdb->prefix}wc_railticket_booking_bays", array('bookingid' => $booking->id));
        $wpdb->delete("{$wpdb->prefix}wc_railticket_bookings", array('id' => $booking->id));
        unset($booking->id);
        $wpdb->insert("{$wpdb->prefix}wc_railticket_bookings_expired", (array) $booking);
        if (strpos($booking->time, "s:") === 0) {
            \wc_railticket\survey\Surveys::expire($booking->woocartitem);
        }
    }

    $sql = "SELECT id FROM {$wpdb->prefix}wc_railticket_bookings WHERE woocartitem != '' AND expiring = 0 AND created < ".$expiretime;
    $bookingids = $wpdb->get_results($sql);
    foreach ($bookingids as $bookingid) {
        $wpdb->update("{$wpdb->prefix}wc_railticket_bookings", array('expiring' => 1), array('id' => $bookingid->id));
    }
}


function railticket_getticketbuilder() {
    $dateoftravel = railticket_getpostfield('dateoftravel');
    $fromstation = railticket_getpostfield('fromstation');
    $journeychoice = railticket_getpostfield('journeychoice');
    $overridevalid = railticket_gettfpostfield('overridevalid');
    $disabledrequest = railticket_gettfpostfield('disabledrequest');
    $notes = railticket_getpostfield('notes');
    $nominimum = railticket_gettfpostfield('nominimum');
    $show = railticket_getpostfield('show');
    // Invert this one so the visible option can be false by default
    $localprice = !railticket_gettfpostfield('onlineprice');
    $manual = railticket_gettfpostfield('manual');
    $discountcode = railticket_getpostfield('discountcode');
    $discountnote = railticket_getpostfield('discountnote');

    // If any of the pre-sets exist, go straight to the ticket builder.
    if (array_key_exists('a_dateofjourney', $_REQUEST) ||
        array_key_exists('a_deptime', $_REQUEST) ||
        array_key_exists('a_station', $_REQUEST) ||
        array_key_exists('a_journeychoice', $_REQUEST) ||
        array_key_exists('a_discountcode', $_REQUEST)
       ) {
        $show = true;
    }

    $times = null;
    if (array_key_exists('times', $_REQUEST)) {
        $times = json_decode(stripslashes($_REQUEST['times']));
    }
    $ticketselections = null;
    if (array_key_exists('ticketselections', $_REQUEST)) {
        $ticketselections = json_decode(stripslashes($_REQUEST['ticketselections']));
    }
    $ticketsallocated = null;
    if (array_key_exists('ticketselections', $_REQUEST)) {
        $ticketsallocated = json_decode(stripslashes($_REQUEST['ticketallocated']));
    }
    $tickets = array();

    // TODO Sanitize JSON?

    return new \wc_railticket\TicketBuilder($dateoftravel, $fromstation, $journeychoice, $times,
         $ticketselections, $ticketsallocated, $overridevalid, $disabledrequest, $notes, $nominimum,
         $show, $localprice, $manual, $discountcode, $discountnote);
}

function railticket_gettfpostfield($field) {
    if (array_key_exists($field, $_REQUEST)) {
        $t = sanitize_text_field($_REQUEST[$field]);
        if ($t == 'true' || $t === true || $t == 1) {
            return true;
        }
    }
    return false;
}

function railticket_getpostfield($field) {
    if (array_key_exists($field, $_REQUEST)) {
        return sanitize_text_field($_REQUEST[$field]);
    }
    return false;
}

function railticket_get_special_button($attr) {
    global $wpdb;

    $specials = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_railticket_specials".
        " WHERE date = '".$attr['date']."' AND id = ".$attr['id']);

    if (!$specials || count($specials) == 0) {
        echo "Event not found";
        return;
    }
    $special = $specials[0];
    $railticket_timezone = new \DateTimeZone(get_option('timezone_string'));
    $evtdate = Datetime::createFromFormat('Y-m-d', $attr['date'], $railticket_timezone);
    $date = railticket_timefunc(get_option('wc_railticket_date_format'), $evtdate->getTimestamp());

    if ($special->onsale == 0) {
        echo "Sorry, the \"".$special->name."\" on ".$evtdate." is not currently on sale";
    }

    echo "<form action='/book/' method='post'>".
        "<input type='submit' value='Click or Tap to buy tickets for the ".$special->name." on ".$date."' />".
        "<input type='hidden' name='a_dateofjourney' value='".$attr['date']."' />".
        "<input type='hidden' name='a_deptime' value='s:".$attr['id']."' />".
        "<input type='hidden' name='a_station' value='".$special->fromstation."' />".
        "<input type='hidden' name='a_destination' value='".$special->tostation."' />".
        "<input type='hidden' name='a_direction' value='' />".
        "<input type='hidden' name='show' value='1' />".
        "</form><br />";
}

function railticket_review_order() {
    $ref = railticket_getpostfield('ref');

    if (!$ref) {
        return "<p>No order reference code specified. You need to use the link in your booking email to review your orders.</p>";
    }

    $ref = urldecode($ref);

    $bookingorder = \wc_railticket\BookingOrder::get_booking_order_byrefcode($ref);
    if (!$bookingorder) {
        return "<p>Sorry, invalid reference code. The order could not be found.</p>";
    }

    global $rtmustache;
    wp_register_style('railticket_style', plugins_url('wc-product-railticket/revieworder.css'));
    wp_enqueue_style('railticket_style');

    $alldata = railticket_get_customer_booking_order_data($bookingorder);
    $template = $rtmustache->loadTemplate('showordercustomer');
    $retstring = $template->render($alldata);

    $dctickets = $bookingorder->get_discountcode_ticket_codes();
    $exclude = false;
    if (!$dctickets) {
        // See if this order could have been used as a discount code.
        $dcode = $bookingorder->get_discount_code();
        if (strlen($dcode) == 0) {
            return $retstring;
        }
        $order = \wc_railticket\BookingOrder::get_booking_order($dcode);
        if (!$order) {
            return $retstring;
        }
        $retstring .= "<h3>".__("The order is part of a multi-trip booking:</h3>");
        $alldata = railticket_get_customer_booking_order_data($order);
        $retstring .= $template->render($alldata);
        $exclude = $bookingorder->get_order_id();
        $bookingorder = $order;
    }


    // This order number can be used as a discount code, so find all the linked orders and display.

    $orders = \wc_railticket\BookingOrder::get_booking_orders_by_discountcode($bookingorder->get_order_id());
    if (count($orders) == 0) {
        return $retstring;
    }
    $retstring .= "<h3>".__("The following orders are part of you multi-trip booking:</h3><br />");

    foreach ($orders as $order) {
        if ($order->get_order_id() == $exclude) {
            continue;
        }

        $alldata = railticket_get_customer_booking_order_data($order);
        $retstring .= $template->render($alldata);
    }

    return $retstring;
}

function railticket_get_customer_booking_order_data(\wc_railticket\BookingOrder $bookingorder) {
    $discount = $bookingorder->get_discount_type();

    $orderdata = array();
    if ($discount) {
        $orderdata[] = array('item' => __('Discount', 'wc_railticket'), 'value' => $discount->get_name(), 'style' => 'color:blue');
        if ($discount->show_notes()) {
            $orderdata[] = array('item' => $discount->get_note_type(),
                'value' => '<div style="text-align:center;">'.
                    '<span style="font-weight:bold;color:red;font-size:x-large;">'.$bookingorder->get_discount_note().'</span></div>');
        }
    }
    if ($bookingorder->get_price() > 0) {
        $orderdata[] = array('item' => __('Total Price', 'wc_railticket'), 'value' => $bookingorder->get_price(true));
    
        $orderdata[] = array('item' => __('Price Breakdown', 'wc_railticket'), 'value' => $bookingorder->get_ticket_prices(true));
        if ($bookingorder->get_supplement() > 0) {
            $orderdata[] = array('item' => __('Supplement', 'wc_railticket'), 'value' => $bookingorder->get_supplement(true));
        }
    }
    $orderdata[] = array('item' => __('Tickets'), 'value' => $bookingorder->get_tickets(true));
    $orderdata[] = array('item' => __('Date'), 'value' => $bookingorder->get_date(true));
    if ($bookingorder->is_special()) {
        $orderdata[] = array('item' => __('Special'), 'value' => $bookingorder->get_special()->get_name());
    } else {
        $orderdata[] = array('item' => __('Journey Type'), 'value' => $bookingorder->get_journeytype(true));
    }
    $orderdata[] = array('item' => __('Wheelchair space requested'), 'value' => $bookingorder->priority_requested(true));

    $alldata = array(
        'dateofjourney' => $bookingorder->get_date(),
        'details' => $orderdata,
        'timestr' => __('Departure Time', 'wc_railticket'),
        'tripstr' => __('Trip', 'wc_railticket'),
        'baystr' => __('Bays', 'wc_railticket'),
        'otheritemsstr' => __('Shop Items to Collect', 'wc_railticket'),
        'collectedstr' => __('Collected', 'wc_railticket'),
        'orderid' => $bookingorder->get_order_id(),
        'otheritems' => $bookingorder->other_items()
    );

    if (!$bookingorder->is_special()) {
        $alldata['bookings'] = railticket_get_booking_render_data($bookingorder);
    }

    if ($alldata['otheritems'] == false || count($alldata['otheritems']) == 0) {
        $alldata['otheritemsstyle'] = 'display:none';
    }

    $booking = $bookingorder->get_bookings()[0];
    $discounts = \wc_railticket\DiscountByOrder::get_discount($bookingorder->get_order_id(), $booking->get_from_station(), $booking->get_to_station(), $bookingorder->get_journeytype(), $bookingorder->get_date(), true);
    if (!$discounts) {
        return $alldata;
    }

    $alldata['discounts'] = array();
    $valid = false;
    foreach ($discounts as $discount) {
        $d = new \stdclass();
        $d->name = $discount->get_name();
        $d->message = $discount->get_usage();
        $alldata['discounts'][] = $d;
        if ($discount->is_valid()) {
            $valid = true;
        }
    }

    if ($valid) {
        $alldata['bookurl'] = site_url().'/book';
    }
    return $alldata;
}

function railticket_survey() {
    $item = railticket_cart_item();

    if (!$item) {
        wp_redirect(get_option('wc_product_railticket_finishurl'));
        return;
    }

    $bookingorder = \wc_railticket\BookingOrder::get_booking_order_cart($item);

    if (!$bookingorder) {
        wp_redirect(get_option('wc_product_railticket_finishurl'));
        return;
    }

    if (!$bookingorder->is_special()) {
        wp_redirect(get_option('wc_product_railticket_finishurl'));
        return;
    }

    $survey = $bookingorder->get_special()->get_survey();
    if (!$survey || $survey->completed($bookingorder)) {
        wp_redirect(get_option('wc_product_railticket_finishurl'));
        return;
    }

    $message = $survey->do_survey($bookingorder);
    if ($survey->is_processed()) {
       $fps = FollowUpProduct::get_follow_ups_bookingorder($bookingorder);
       if (count($fps) > 0) {
           wp_redirect(reset($fps)->get_url());
           return;
       }
    }

    return $message."<h5>Please continue to the <a href='/checkout'>checkout</a> to complete your purchase.</h5>";;
}
