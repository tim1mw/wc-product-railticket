<?php

namespace wc_railticket;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class Discount extends DiscountType {
    public function __construct($data) {
        $this->data = $data;
        $this->data->rules = json_decode($this->data->rules);

        $this->railticket_timezone = new \DateTimeZone(get_option('timezone_string'));
        $this->today = new \DateTime();
        $this->today->setTimezone($this->railticket_timezone);
        $this->today->setTime(0,0,0);

        $this->valid = !$this->data->disabled;

        if ($this->data->start != null) {
            $startdate = DateTime::createFromFormat('Y-m-d', $this->data->start);
            $startdate->setTimezone($this->railticket_timezone);
            if ($today < $startdate) {
                $this->valid;
            }
        }

        if ($this->data->end != null) {
            $enddate = DateTime::createFromFormat('Y-m-d', $this->data->end);
            $enddate->setTimezone($this->railticket_timezone);
            if ($today > $enddate) {
                $this->valid;
            }
        }

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

    public function apply_stations(Station $from, Station $to) {
        // Check the station rules!
    }

    public function get_code() {
        return $this->data->code;
    }

    public function ticket_has_discount($tickettype) {
        if (!$this->valid) {
            return false;
        }

        return parent::ticket_has_discount($tickettype);
    }

    public function is_valid() {
        return $this->valid;
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
