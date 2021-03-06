<?php

namespace wc_railticket;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class BookingOrder {

    private $bookings, $manual, $orderid;
    public $bookableday;

    private function __construct($bookings, $orderid, $manual, $cart_item = false) {
        global $wpdb;
        $this->orderid = $orderid;
        $this->manual = $manual;
        $this->incart = false;

        if ($cart_item) {
            $this->tickets = $cart_item['ticketsallocated'];
            $this->travellers = $cart_item['ticketselections'];
            $this->ticketprices = $cart_item['ticketprices'];
            $this->price = $cart_item['custom_price'];
            $this->supplement = $cart_item['supplement'];
            $this->notes = '';
            $this->customername = '';
            $this->customerpostcode = '';
            $this->paid = false;
            $this->incart = true;
        } elseif ($manual) {
            $mb = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}wc_railticket_manualbook WHERE id = ".$orderid);
            $this->tickets = json_decode($mb->tickets);
            $this->travellers = json_decode($mb->travellers);
            $this->ticketprices = json_decode($mb->ticketprices);
            $this->price = $mb->price;
            $this->supplement = $mb->supplement;
            $this->notes = $mb->notes;
            $this->customername = '';
            $this->customerpostcode = '';
            $this->paid = true;
        } else {
            $order = wc_get_order($orderid);
            $wooorderitem = $bookings[0]->wooorderitem;
            $this->tickets = $this->get_woo_meta('ticketsallocated-', $wooorderitem);
            $this->travellers = $this->get_woo_meta('ticketselections-', $wooorderitem);
            $this->ticketprices = $this->get_woo_meta('ticketprices-', $wooorderitem);
            $this->price = $wpdb->get_var("SELECT meta_value FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE ".
                " meta_key='_line_total' AND order_item_id = ".$wooorderitem."");
            $this->supplement = $wpdb->get_var("SELECT meta_value FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE ".
                " meta_key='supplement' AND order_item_id = ".$wooorderitem."");
            $this->notes = $order->get_customer_note();
            $this->customername = $order->get_formatted_billing_full_name();
            $this->customerpostcode = $order->get_billing_postcode();
            $this->paid = $order->is_paid();
        }

        // Bookings for a single order are always for the same day, so we can shortcut this safely
        $this->bookableday = BookableDay::get_bookable_day($bookings[0]->date);

        $this->bookings = array();
        foreach ($bookings as $booking) {
            $this->bookings[] = new Booking($booking, $this->bookableday);
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

        if (count($bookings) == 0) {
            return false;
        }

        return new BookingOrder($bookings, $orderid, $manual);
    }

    public static function get_booking_order_cart($cart_item) {
        global $wpdb;

        $bookings = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_railticket_bookings WHERE woocartitem = '".$cart_item['key']."'");
        if (count($bookings) == 0) {
            return false;
        }

        return new BookingOrder($bookings, $cart_item['key'], false, $cart_item);
    }

    public static function get_booking_order_itemid($itemid) {
        global $wpdb;

        $bookings = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_railticket_bookings WHERE wooorderitem = '".$itemid."'");
        if (count($bookings) == 0) {
            return false;
        }

        return new BookingOrder($bookings, $bookings[0]->wooorderid, false);    
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

    public function other_items() {
        if ($this->manual) {
            return false;
        }

        $order = wc_get_order($this->orderid);
        $orderitems = array();
        foreach($order->get_items() as $item_id => $item) {
            if ($this->bookings[0]->get_order_item_id() == $item_id) {
                continue;
            }
            $c = new \stdclass();
            $c->name = $item->get_name();
            $c->qty = $item->get_quantity();
            $orderitems[] = $c;
        }

        return $orderitems;
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
        global $wpdb;
        if ($format) {
            $ta = (array) $this->tickets;
            $fmt = array();
            foreach ($ta as $ticket => $num) {
                 $fmt[] = $num."x ".$wpdb->get_var("SELECT name FROM {$wpdb->prefix}wc_railticket_tickettypes WHERE code = '".$ticket."'");
            }
            return implode(', ', $fmt);
        }
        return $this->tickets;
    }

    public function get_travellers() {
        return $this->travellers;
    }

    public function get_notes() {
        return $this->notes;
    }

    public function get_price($format = false) {
        if (!$format) {
            return $this->price;
        }

        return "£".number_format($this->price, 2);
    }

    public function get_supplement($format = false) {
        if (!$format) {
            return $this->supplement;
        }

        return "£".number_format($this->supplement, 2);
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
        foreach ($this->bookings as $booking) {
            $booking->delete();
        }

        if ($this->manual) {
            $wpdb->get_results("DELETE FROM {$wpdb->prefix}wc_railticket_manualbook WHERE id = ".$this->orderid);
        }  
    }

}
