<?php

if ( ! defined( 'ABSPATH' ) ) {
    return;
}

add_action('admin_footer', 'railticket_custom_product_admin_custom_js');
add_shortcode('railticket_selector', 'railticket_selector');
add_shortcode('railticket_special', 'railticket_get_special_button');
add_action( 'wp_ajax_nopriv_railticket_ajax', 'railticket_ajax_request');
add_action( 'wp_ajax_railticket_ajax', 'railticket_ajax_request');

add_filter( 'cron_schedules', 'railticket_add_every_two_minutes' );
add_action( 'railticket_add_every_two_minutes', 'railticket_every_two_minutes_event_func' );

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

// Hook into that action that'll fire every three minutes

function railticket_every_two_minutes_event_func() {
    global $wpdb;
    $expiretime = time() - intval(get_option('wc_product_railticket_reservetime'))*60;
    $releasetime = $expiretime - intval(get_option('wc_product_railticket_releaseinventory'))*60;

    $sql = "SELECT id FROM {$wpdb->prefix}wc_railticket_bookings WHERE woocartitem != '' AND expiring = 1 AND created < ".$releasetime;
    $bookingids = $wpdb->get_results($sql);
    foreach ($bookingids as $bookingid) {
        $wpdb->delete("{$wpdb->prefix}wc_railticket_booking_bays", array('bookingid' => $bookingid->id));
        $wpdb->delete("{$wpdb->prefix}wc_railticket_bookings", array('id' => $bookingid->id));
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
         $show, $localprice, $manual, $discountcode);
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

