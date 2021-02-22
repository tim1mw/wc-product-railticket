<?php

/**
 * Plugin Name: WooCommerce Ticket Product Type
 * Description: Works with railtimetable
 */

if ( ! defined( 'ABSPATH' ) ) {
    return;
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
    $tostation = railticket_getpostfield('tostation');
    $outtime = railticket_getpostfield('outtime');
    $rettime = railticket_getpostfield('rettime');
    $journeytype = railticket_getpostfield('journeytype');
    $overridevalid = railticket_getpostfield('overridevalid');
    $disabledrequest = railticket_getpostfield('disabledrequest');
    $notes = railticket_getpostfield('notes');
    $nominimum = railticket_getpostfield('nominimum');
    $show = railticket_getpostfield('show');

    $ticketselections = null;
    if (array_key_exists('ticketselections', $_REQUEST)) {
        $ticketselections = json_decode(stripslashes($_REQUEST['ticketselections']));
    }
    $ticketsallocated = null;
    if (array_key_exists('ticketselections', $_REQUEST)) {
        $ticketsallocated = json_decode(stripslashes($_REQUEST['ticketallocated']));
    }
    $tickets = array();

    return new \wc_railticket\TicketBuilder($dateoftravel, $fromstation, $tostation, $outtime, $rettime,
        $journeytype, $ticketselections, $ticketsallocated, $overridevalid, $disabledrequest, $notes, $nominimum, $show);
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

    $evtdate = Datetime::createFromFormat('Y-m-d', $attr['date']);
    $date = strftime(get_option('wc_railticket_date_format'), $evtdate->getTimestamp());

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

add_action('admin_footer', 'railticket_custom_product_admin_custom_js');
add_shortcode('railticket_selector', 'railticket_selector');
add_shortcode('railticket_special', 'railticket_get_special_button');
add_action( 'wp_ajax_nopriv_railticket_ajax', 'railticket_ajax_request');
add_action( 'wp_ajax_railticket_ajax', 'railticket_ajax_request');

add_filter( 'cron_schedules', 'railticket_add_every_two_minutes' );
add_action( 'railticket_add_every_two_minutes', 'railticket_every_two_minutes_event_func' );
