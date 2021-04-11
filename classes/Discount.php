<?php

namespace wc_railticket;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class Discount {
    public function __construct($data) {
        $this->data = $data;

        $this->railticket_timezone = new \DateTimeZone(get_option('timezone_string'));
        $this->today = new \DateTime();
        $this->today->setTimezone($this->railticket_timezone);
        $this->today->setTime(0,0,0);
    }

    public static function get_discount($code) {
        global $wpdb;

        $data = $wpdb->get_row("SELECT discounts.*, codes.code, codes.start, codes.end, codes.single, codes.disabled ".
            "FROM {$wpdb->prefix}wc_railticket_discountcodes codes ".
            "INNER JOIN {$wpdb->prefix}wc_railticket_discounts discounts ON discounts.shortname = codes.shortname ".
            "WHERE codes.code = '".$code."'");

        if (!$data) {
            return false;
        }

        return new Discount($data);
    }

    public function get_shortname() {
        return $this->data->shortname;
    }

    public function get_code() {
        return $this->data->code;
    }

    public function get_name() {
        return $this->data->name;
    }

    public function get_baseprice_field() {
        return $this->data->basefare;
    }

    public function check_price_field($prefered) {
        if ($this->data->basefare == 'auto') {
            return $prefered;
        }

        return $this->data->basefare;
    }

    public function is_valid() {

        if ($this->data->start != null) {
            $startdate = DateTime::createFromFormat('Y-m-d', $this->data->start);
            $startdate->setTimezone($this->railticket_timezone);
            if ($today < $startdate) {
                return false;
            }
        }

        if ($this->data->end != null) {
            $enddate = DateTime::createFromFormat('Y-m-d', $this->data->end);
            $enddate->setTimezone($this->railticket_timezone);
            if ($today > $enddate) {
                return false;
            }
        }

        return !$this->data->disabled;
    }

    public function use() {
        if (!$this->data->single) {
            return;
        }

        $this->disable_discount();
    }

    public function disable() {
        global $wpdb;

        $wpdb->update("{$wpdb->prefix}wc_railticket_discountcodes",
            array('disabled' => 1), array('code' => $this->data->code));
    }
}
