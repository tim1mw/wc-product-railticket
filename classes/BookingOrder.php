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
            $this->tickets =(array) $cart_item['ticketsallocated'];
            $this->travellers =  (array) $cart_item['ticketselections'];
            $this->ticketprices = (array) $cart_item['ticketprices'];
            $this->price = $cart_item['custom_price'];
            $this->supplement = $cart_item['supplement'];
            $this->notes = '';
            $this->customername = '';
            $this->customerpostcode = '';
            $this->customeremail = '';
            $this->customerphone = '';
            $this->paid = false;
            $this->incart = true;
            $this->createdby = 0;
        } elseif ($manual) {
            $mb = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}wc_railticket_manualbook WHERE id = ".$orderid);
            $this->tickets = (array) json_decode($mb->tickets);
            $this->travellers = (array) json_decode($mb->travellers);
            if (property_exists($mb, 'ticketprices')) {
                $this->ticketprices = (array) json_decode($mb->ticketprices);
            } else {
                $this->ticketprices = array();
            }
            $this->price = $mb->price;
            $this->supplement = $mb->supplement;
            $this->notes = $mb->notes;
            $this->customername = '';
            $this->customerpostcode = '';
            $this->customeremail = '';
            $this->customerphone = '';
            $this->createdby = $mb->createdby;
            $this->paid = true;
        } else {
            $wooorderitem = $bookings[0]->wooorderitem;
            $this->tickets = (array) $this->get_woo_meta('ticketsallocated-', $wooorderitem);
            $this->travellers = (array) $this->get_woo_meta('ticketselections-', $wooorderitem);
            $this->ticketprices = (array) $this->get_woo_meta('ticketprices-', $wooorderitem);
            $this->price = $wpdb->get_var("SELECT meta_value FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE ".
                " meta_key='_line_total' AND order_item_id = ".$wooorderitem."");
            $this->supplement = $wpdb->get_var("SELECT meta_value FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE ".
                " meta_key='supplement' AND order_item_id = ".$wooorderitem."");
            $this->createdby = 0;

            // This object might get created prior to the order being fully setup in Woocommerce, so skip this if there is no order
            $order = wc_get_order($orderid);
            if ($order) {
                $this->notes = $order->get_customer_note();
                $this->customername = $order->get_formatted_billing_full_name();
                $this->customerpostcode = $order->get_billing_postcode();
                $this->customeremail = $order->get_billing_email();
                $this->customerphone = $order->get_billing_phone();
                $this->paid = $order->is_paid();
            } else {
                $this->customername = '';
                $this->customerpostcode = '';
                $this->customeremail = '';
                $this->customerphone = '';
                $this->paid = false;
                $this->incart = false;
            }
        }

        // Bookings for a single order are always for the same day, so we can shortcut this safely
        $this->bookableday = BookableDay::get_bookable_day($bookings[0]->date);

        $this->bookings = array();
        foreach ($bookings as $booking) {
            $this->bookings[] = new Booking($booking, $this->bookableday);
        }
        if ($this->ticketprices && array_key_exists('__discounttype', $this->ticketprices)) {
            $this->discount = DiscountType::get_discount_type($this->ticketprices['__discounttype']);
        }
    }

    public static function get_booking_order($orderid, $manual = false) {
        global $wpdb;

        if ($manual === false && strpos(strtoupper($orderid), 'M') === 0) {
            $manual = true;
            $orderid = substr($orderid, 1);
        }

        if (!is_numeric($orderid)) {
            return false;
        }

        if ($manual) {
            $bookings = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_railticket_bookings WHERE manual = '".$orderid."' ORDER BY time ASC");
        } else {
            $bookings = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_railticket_bookings WHERE wooorderid = '".$orderid."' ORDER BY time ASC");
        }

        if (count($bookings) == 0) {
            return false;
        }

        return new BookingOrder($bookings, $orderid, $manual);
    }

    public static function get_booking_order_cart($cart_item) {
        global $wpdb;

        $bookings = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_railticket_bookings WHERE woocartitem = '".$cart_item['key']."' ORDER BY time ASC");
        if (count($bookings) == 0) {
            return false;
        }

        return new BookingOrder($bookings, $cart_item['key'], false, $cart_item);
    }

    public static function get_booking_order_bycartkey($key) {
        global $wpdb;

        $bookings = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_railticket_bookings WHERE woocartitem = '".$key."' ORDER BY time ASC");
        if (count($bookings) == 0) {
            return false;
        }

        return new BookingOrder($bookings, $key, false, false);
    }

    public static function get_booking_order_itemid($itemid) {
        global $wpdb;

        $bookings = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_railticket_bookings WHERE wooorderitem = '".$itemid."' ORDER BY time ASC");
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

    public function notify() {
        if ($this->bookings[0]->is_manual()) {
            return;
        }
        $order = wc_get_order($this->bookings[0]->get_order_id());
        $wc_emails = WC()->mailer()->get_emails();

        if (empty($wc_emails)) {
            return;
        }

        if ($order->has_status( 'on-hold' )) {
            $email_id = 'customer_on_hold_order';
        } elseif ($order->has_status( 'processing' )) {
            $email_id = 'customer_processing_order';
        } elseif ($order->has_status( 'completed' )) {
            $email_id = 'customer_completed_order';
        } else {
            $email_id = "nothing";
        }

        foreach ($wc_emails as $wc_mail) {
            if ($wc_mail->id == $email_id) {
                $wc_mail->trigger($order->get_id());
            }
        }
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

    public function get_order_id() {
        return $this->bookings[0]->get_order_id();
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

    public function get_phone() {
       return $this->customerphone;
    }

    public function get_email() {
       return $this->customeremail;
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
            $fmt = array();
            foreach ($this->tickets as $ticket => $num) {
                $fmt[] = $num."x ".$this->bookableday->fares->get_ticket_name($ticket, $this->discount);
            }
            return implode(', ', $fmt);
        }
        return $this->tickets;
    }

    public function total_tickets() {
        $count = 0;
        foreach ($this->tickets as $ticket => $num) {
            $count += $num;
        }
        return $count;
    }

    public function get_travellers() {
        return $this->travellers;
    }

    public function get_notes() {
        return $this->notes;
    }

    public function get_created_by($format = false) {
        if ($format) {
            if (!$this->manual) {
                return __('Online Booking', 'wc_railticket');
            }

            if ($this->createdby > 0) {
                $u = get_userdata($this->createdby);
                return $u->first_name." ".$u->last_name;
            }
            return __('Unknown User', 'wc_railticket')." (id = ".$this->createdby.")";
        }

        return $this->createdby;
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

    public function get_ticket_prices($format = false) {
        if (!$this->ticketprices) {
            return array();
        }
        $filtered = array();

        if ($format) {
            $str = '';
            foreach ($this->ticketprices as $tpcode => $tpprice) {
                if (strpos($tpcode, '__') === 0) {
                    continue;
                }
                $name = $this->bookableday->fares->get_ticket_name($tpcode, $this->discount);

                // TODO Sort out currency symbols properly....
                $filtered[] = $name.' £'.number_format($tpprice, 2).' '.__('each', 'wc_railticket');
            }
            return implode(', ', $filtered);
        }

        foreach ($this->ticketprices as $tpcode => $tpprice) {
            if (strpos($tpcode, '__') === 0) {
                continue;
            }
            $filtered[$tpcode] = $tpprice;
        }

        return $filtered;
    }

    public function is_guard_price() {
        if ($this->ticketprices && array_key_exists('__pfield', $this->ticketprices)) {
            if ($this->ticketprices['__pfield'] == 'localprice') {
                return true;
            }
        }

        return false;
    }

    public function get_discount_type() {
        return $this->discount;
    }

    public function get_discount() {
        if ($this->ticketprices && array_key_exists('__discounttotal', $this->ticketprices)) {
            return $this->ticketprices['discounttotal'];
        }

        return 0;
    }

    public function get_date($format = false, $nottoday = false) {
        // Bookings for a single order are always for the same day, so we can shortcut this safely
        return $this->bookings[0]->get_date($format, $nottoday);
    }


    public function is_special() {
        // Bookings for a single order are always for the same day, so we can shortcut this safely
        return $this->bookings[0]->is_special();
    }

    public function get_seats() {
        // Bookings for a single order are always for the same day, so we can shortcut this safely
        return $this->bookings[0]->get_seats();
    }

    public function priority_requested($format = false) {
        return $this->bookings[0]->get_priority($format);
    }

    public function get_journeys() {
        $j = 0;
        foreach ($this->bookings as $bk) {
            $j += $bk->get_seats();
        }
        return $j;
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

        // Specials are booked as singles underneath, but shown as specials when the formatted.
        if ($this->bookings[0]->is_special()) {
            return __('Special', 'wc_railticket');
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

    public function set_date(BookableDay $bk) {
        $this->bookableday = $bk;

        foreach ($this->bookings as $booking) {
            $booking->set_date($bk);
        }
        return true;
    }

}
