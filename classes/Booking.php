<?php

namespace wc_railticket;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class Booking {

    private $data, $bays;
    public $special, $bookableday;

    public function __construct($data, BookableDay $bkd) {
        global $wpdb;
        $this->data = $data;
        $this->bookableday = $bkd;
        $this->bays = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_railticket_booking_bays WHERE bookingid = ".$this->data->id);
        if ($this->data->collected == 1) {
            $this->data->collected = true;
        } else {
            $this->data->collected = false;
        }

        if (strpos($this->data->time, "s:") === 0) {
            $this->special = Special::get_special($this->data->time);
        } else {
            $this->special = false;
        }
    }

    /**
    * Make this static so we don't have to set up the entire object tree just to mark this as collected
    * @param $id The ID of the booking
    * @param $val The collected value
    **/

    public static function set_collected($id, $val) {
        global $wpdb;

        $wpdb->update("{$wpdb->prefix}wc_railticket_bookings",
            array('collected' => $val),
            array('id' => $id));
    }

    public static function delete_booking_order_cart($cart_item_key) {
        global $wpdb;
        $bookingids = $wpdb->get_results("SELECT id FROM {$wpdb->prefix}wc_railticket_bookings WHERE woocartitem = '".$cart_item_key."'");
        foreach ($bookingids as $bookingid) {
            $wpdb->delete("{$wpdb->prefix}wc_railticket_booking_bays", array('bookingid' => $bookingid->id));
            $wpdb->delete("{$wpdb->prefix}wc_railticket_bookings", array('id' => $bookingid->id));
        }
    }

    public static function set_cart_itemid($item_id, $key) {
        global $wpdb;
        $wpdb->update("{$wpdb->prefix}wc_railticket_bookings",
            array('wooorderitem' => $item_id),
            array('woocartitem' => $key));
    }

    public static function cart_purchased($order_id, $item_id, $key) {
        global $wpdb;
        $wpdb->update("{$wpdb->prefix}wc_railticket_bookings",
            array('wooorderid' => $order_id, 'woocartitem' => '', 'wooorderitem' => $item_id, 'expiring' => 0),
            array('woocartitem' => $key));
    }

    public function get_date($format = false, $nottoday = false) {
        if ($format) {
            $railticket_timezone = new \DateTimeZone(get_option('timezone_string'));
            $jdate = \DateTime::createFromFormat('Y-m-d', $this->data->date, $railticket_timezone);
            $now = new \DateTime();
            if ($nottoday && $now != $jdate) {
                return strftime(get_option('wc_railticket_date_format'), $jdate->getTimeStamp()).
                    " <span style='color:red;font-weight:bold;font-size:small;'>(".__("Booking not for Today", "wc_railticket").")</span>";
            }

            return strftime(get_option('wc_railticket_date_format'), $jdate->getTimeStamp());
        }

        return $this->data->date;
    }

    public function get_dep_time($format = false) {
        global $wpdb;
        if ($this->special) {
            if ($format) {
                return $this->special->get_name();
            } else {
                return $this->special->get_dep_id();
            }
        }

        if ($format) {
            $railticket_timezone = new \DateTimeZone(get_option('timezone_string'));
            $dtime = \DateTime::createFromFormat("H.i", $this->data->time, $railticket_timezone);
            if ($dtime) {
                // Despite the config option not having a space, this is contriving to put in a leading space I don't want. Trim it!
                return trim(strftime(get_option('wc_railticket_time_format'), $dtime->getTimeStamp()));
            }
        }
        return $this->data->time;
    }

    public function get_id() {
        return $this->data->id;
    }

    public function get_from_station() {
        return Station::get_station($this->data->fromstation, $this->bookableday->timetable->get_revision());
    }

    public function get_to_station() {
        return Station::get_station($this->data->tostation, $this->bookableday->timetable->get_revision());
    }

    public function get_direction() {
        return $this->data->direction;
    }

    public function get_bays($format = false) {
        if (!$format) {
            return $this->bays;
        }

        return CoachManager::format_booking_bays($this->bays);
    }

    public function get_seats() {
        return $this->data->seats;
    }

    public function is_collected() {
        return $this->data->collected;
    }

    public function is_manual() {
        if ($this->data->manual > 0) {
            return true;
        }

        return false;
    }

    public function is_special() {
        return $this->special;
    }

    public function get_order_id() {
        if ($this->data->manual > 0) {
            return "M".$this->data->manual;
        }

        return $this->data->wooorderid;
    }

    public function in_cart() {
        if (strlen($this->data->woocartitem) > 0) {
            return true;
        }

        return false;
    }

    public function get_order_name() {
        if ($this->data->manual > 0 || $this->data->wooorderid == 0 ) {
            return '';
        }

        $order = wc_get_order($this->data->wooorderid);
        return $order->get_formatted_billing_full_name();
    }

    public function get_order_item_id() {
        return $this->data->wooorderitem;
    }

    public function delete() {
        global $wpdb;
        $wpdb->get_results("DELETE FROM {$wpdb->prefix}wc_railticket_booking_bays WHERE bookingid = ".$this->data->id);
        $wpdb->get_results("DELETE FROM {$wpdb->prefix}wc_railticket_bookings WHERE id = ".$this->data->id);
    }

    public function set_date(BookableDay $bk) {
        $this->bookableday = $bk;
        $this->data->date = $bk->get_date();
        $this->update_record();
    }

    public function set_dep(Station $from, Station $to, $time) {
        $this->data->fromstation = $from->get_stnid();
        $this->data->tostation = $to->get_stnid();
        $this->data->time = $time;
        $this->update_record();
    }

    private function update_record() {
        global $wpdb;
        $wpdb->update($wpdb->prefix.'wc_railticket_bookings',
            get_object_vars($this->data),
            array('id' => $this->data->id));
    }
}
