<?php

namespace wc_railticket;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class BookingOrder {

    private $bookings, $manual, $orderid;
    public $bookableday;

    private function __construct($bookings, $orderid, $manual) {
        global $wpdb;

        $this->orderid = $orderid;
        $this->manual = $manual;

        if ($manual) {
            $mb = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}wc_railticket_manualbook WHERE id = ".$orderid);
            $this->tickets = json_decode($mb->tickets);
            $this->travellers = json_decode($mb->travellers);
            $this->price = $mb->price;
            $this->notes = $mb->notes;
            $this->customername = '';
            $this->customerpostcode = '';
            $this->paid = true;
        } else {
            $order = wc_get_order($orderid);
            $wooorderitem = $bookings[0]->wooorderitem;
            $this->tickets = $this->get_woo_meta('ticketsallocated-', $wooorderitem);
            $this->travellers = $this->get_woo_meta('ticketselections-', $wooorderitem);
            $this->price = $wpdb->get_var("SELECT meta_value FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE ".
                " meta_key='_line_total' AND order_item_id = ".$wooorderitem."");
            $this->notes = $order->get_customer_note();
            $this->customername = $order->get_formatted_billing_full_name();
            $this->customerpostcode = $order->get_billing_postcode();
            $this->paid = $order->is_paid();
        }

        // Bookings for a single order are always for the same day, so we can shortcut this safely
        $bookableday = BookableDay::get_bookable_day($bookings[0]->date);

        $this->bookings = array();
        foreach ($bookings as $booking) {
            $this->bookings[] = new Booking($booking, $bookableday);
        }
    }

    public static function get_booking_order($orderid, $manual = false) {
        global $wpdb;

        if ($manual === false && strpos(strtoupper($_REQUEST['orderid']), 'M') === 0) {
            $manual = true;
            $orderid = substr($orderid, 1);
        }

        if ($manual) {
            $bookings = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_railticket_bookings WHERE manual = ".$orderid);
        } else {
            $bookings = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_railticket_bookings WHERE wooorderid = ".$orderid);
        }

        if (count($bookings) == false) {
            return false;
        }

        return new BookingOrder($bookings, $orderid, $manual);
    }

    private function get_woo_meta($metakey, $wooorderitem) {
        global $wpdb;
        $woometas = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE ".
            " order_item_id = ".$wooorderitem." AND meta_key LIKE '".$metakey."%'");
        $data = new \stdclass();
        $kl = strlen($metakey);
        foreach ($woometas as $woometa) {
            $key = substr($woometa->meta_key, $kl);
            $data->$key = $woometa->meta_value;
        }
        return $data;
    }

    public function is_manual() {
        return $this->manual;
    }

    public function get_customer_name() {
        return $this->customername;
    }

    public function get_postcode() {
       return $this->customerpostcode;
    }

    public function is_paid($format = false) {
        if (!$format) {
            return $this->paid;
        }

        if ($this->paid) {
           return __('Yes', 'wc_railticket');
        } else {
           return __('No', 'wc_railticket');
        }
    }

    public function get_bookings() {
        return $this->bookings;
    }

    public function get_tickets($format = false) {
        if ($format) {
            $s = '';
            $ta = (array) $this->tickets;
            foreach ($ta as $ticket => $num) {
                 $s .= str_replace('_', ' ', $ticket)." x".$num.", ";
            }
            return $s;
        }
        return $this->tickets;
    }

    public function get_travellers() {
        return $this->travellers;
    }

    public function get_notes() {
        return $this->notes;
    }

    public function get_price() {
        return $this->price;
    }

    public function get_date($format = false, $nottoday = false) {
        // Bookings for a single order are always for the same day, so we can shortcut this safely
        return $this->bookings[0]->get_date($format, $nottoday);
    }

    public function get_seats() {
        // Bookings for a single order are always for the same day, so we can shortcut this safely
        return $this->bookings[0]->get_seats();
    }

    public function get_journeytype($format = false) {
        if (!$format) {
            switch (count($this->bookings)) {
                case 1: return 'single';
                case 2: return 'return';
                case 3: return 'round';
            }

            return false;
        }

        switch (count($this->bookings)) {
            case 1: return __('Single', 'wc_railticket');
            case 2: return __('Return', 'wc_railticket');
            case 3: return __('Round', 'wc_railticket');
        }

        return __('Unknown', 'wc_railticket');
    }

    public function delete() {
        global $wpdb;

        $bookings = $wpdb->get_results("SELECT id, date FROM {$wpdb->prefix}wc_railticket_bookings WHERE manual = ".$this->orderid);
        foreach ($bookings as $booking) {
            $wpdb->get_results("DELETE FROM {$wpdb->prefix}wc_railticket_booking_bays WHERE bookingid = ".$booking->id);
        }

        $wpdb->get_results("DELETE FROM {$wpdb->prefix}wc_railticket_bookings WHERE manual = ".$this->orderid);

        if ($this->manual) {
            $wpdb->get_results("DELETE FROM {$wpdb->prefix}wc_railticket_manualbook WHERE id = ".$this->orderid);
        }
    }
}
