<?php
namespace wc_railticket;
defined('ABSPATH') or die('No script kiddies please!');

class OrderSummary extends Report{

    function __construct($date) {
        $this->date = $date;
        $this->bookableday = BookableDay::get_bookable_day($this->date);
    }

    function show_summary($iscsv) {
        global $wpdb, $rtmustache;

        wp_register_style('railticket_style', plugins_url('wc-product-railticket/ticketbuilder.css'));
        wp_enqueue_style('railticket_style');

        $bookingids = $this->bookableday->get_all_order_ids();

        $processed = array();
        $lines = array();

        foreach ($bookingids as $bookingid) {
            $bookingorder = BookingOrder::get_booking_order($bookingid);
            $bookings = $bookingorder->get_bookings();
            $line = array();
            if ($iscsv) {
                $line[] = $bookingid;
            } else {
                $line[] = "<form action='".railticket_get_page_url()."' method='post'>".
                    "<input type='hidden' name='action' value='showorder' />".
                    "<input type='hidden' name='orderid' value='".$bookingid."' />".
                    "<input type='submit' value='".$bookingid."' /></form>";
            }

            $line[] = $bookingorder->get_customer_name();
            $line[] = $bookingorder->get_email();
            $line[] = $bookingorder->get_phone();
            $line[] = $bookings[0]->get_from_station()->get_name();
            if ($bookingorder->get_journeytype() == 'round') {
                $line[] = $bookings[0]->get_from_station()->get_name();
            } else {
                $line[] = $bookings[0]->get_to_station()->get_name();
            }
            $line[] = $bookingorder->get_journeytype(true);
            $line[] = $bookingorder->get_tickets(true);
            $line[] = $bookingorder->get_seats();
            $line[] = $bookingorder->get_supplement();
            $line[] = $bookingorder->get_discount_type();
            $line[] = $bookingorder->get_discount();
            $line[] = $bookingorder->get_price();
            $line[] = $bookingorder->get_notes();
            if ($bookingorder->is_manual()) {
                $key = 'zzzzzzzzzzzz' . $bookingid;
            } else {
                $key = $bookingorder->get_customer_name()." ". wp_generate_uuid4();
            }

            $lines[$key] = $line;
        }

        uksort($lines, function ($a, $b) {
            $a = mb_strtolower($a);
            $b = mb_strtolower($b);
            return strcmp($a, $b);
        });

        $header = array('Order ID', 'Name', 'Email', 'Phone', 'From', 'To', 'Journey', 
            'Tickets', 'Seats', 'Supp.', 'Discount Type', 'Discount', 'Total Price', 'Notes');

        if ($iscsv) {
            header('Content-Type: application/csv');
            header('Content-Disposition: attachment; filename="ordersummary-' . $this->date . '.csv";');
            header('Pragma: no-cache');
            $f = fopen('php://output', 'w');
            fputcsv($f, array('Date', $this->date));
            fputcsv($f, array('', '', '', '', '', ''));
            fputcsv($f, $header);
            foreach ($lines as $line) {
                fputcsv($f, $line);
            }
            fclose($f);
        } else {
            $alldata = new \stdclass();
            $alldata->date = $this->date;
            $alldata->header = $header;
            $alldata->lines = array_values($lines);
            $alldata->url = railticket_get_page_url();

            $template = $rtmustache->loadTemplate('order_summary');
            echo $template->render($alldata);
        }
    }
}
