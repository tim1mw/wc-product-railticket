<?php

namespace wc_railticket;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class Discount extends DiscountType {
    public function __construct($data, $fromstation, $tostation, $journeytype) {
        $this->data = $data;
        $this->data->rules = json_decode($this->data->rules);
        $this->data->customtype = (bool) $this->data->customtype;
        $this->data->triptype = (bool) $this->data->triptype;
        $this->data->shownotes = (bool) $this->data->shownotes;
        $this->data->single = (bool) $this->data->single;
        $this->data->disabled = (bool) $this->data->disabled;

        $this->railticket_timezone = new \DateTimeZone(get_option('timezone_string'));
        $this->today = new \DateTime();
        $this->today->setTimezone($this->railticket_timezone);
        $this->today->setTime(0,0,0);

        $this->fromstation = $fromstation;
        $this->tostation = $tostation;
        $this->journeytype = $journeytype;

        $this->valid = !$this->data->disabled;

        if ($this->data->start != null) {
            $startdate = \DateTime::createFromFormat('Y-m-d', $this->data->start);
            $startdate->setTimezone($this->railticket_timezone);
            if ($this->today < $startdate) {
                $this->valid = false;
            }
        }

        if ($this->data->end != null) {
            $enddate = \DateTime::createFromFormat('Y-m-d', $this->data->end);
            $enddate->setTimezone($this->railticket_timezone);
            if ($this->today > $enddate) {
                $this->valid = false;
            }
        }

        switch ($this->data->triptype) {
            case 'full': 
                if ($this->fromstation==false || $this->tostation==false || $this->journeytype==false || $this->journeytype == 'single') {
                    $this->valid = false;
                    break;
                }

                if ( $this->journeytype == 'round') {
                    break;
                }

                if (!$this->fromstation->is_principal() || !$this->tostation->is_principal()) {
                    $this->valid = false;
                }
                    
                break;
            case 'any':
                break;
        }
    }

    public static function get_discount($code, $fromstation, $tostation, $journeytype) {
        global $wpdb;

        $data = $wpdb->get_row("SELECT discounts.*, codes.code, codes.start, codes.end, codes.single, codes.disabled ".
            "FROM {$wpdb->prefix}wc_railticket_discountcodes codes ".
            "INNER JOIN {$wpdb->prefix}wc_railticket_discounts discounts ON discounts.shortname = codes.shortname ".
            "WHERE codes.code = '".strtolower($code)."'");

        if (!$data) {
            return false;
        }

        return new Discount($data, $fromstation, $tostation, $journeytype);
    }

    public static function get_all_discount_data() {
        global $wpdb;

        return $wpdb->get_results("SELECT discounts.name, codes.* ".
            "FROM {$wpdb->prefix}wc_railticket_discountcodes codes ".
            "INNER JOIN {$wpdb->prefix}wc_railticket_discounts discounts ON discounts.shortname = codes.shortname ".
            "ORDER BY codes.shortname, codes.code");
    }

    public static function get_all_guard_discounts() {
        global $wpdb;

        $railticket_timezone = new \DateTimeZone(get_option('timezone_string'));
        $today = new \DateTime();
        $today->setTimezone($railticket_timezone);
        $today->setTime(0,0,0);  
        $today = $today->format('Y-m-d');

        return $wpdb->get_results("SELECT DISTINCT codes.shortname, discounts.name, codes.code ".
            "FROM {$wpdb->prefix}wc_railticket_discountcodes codes ".
            "INNER JOIN {$wpdb->prefix}wc_railticket_discounts discounts ON discounts.shortname = codes.shortname ".
            "WHERE codes.disabled = 0 AND codes.single = 0 AND discounts.notguard = 0 ".
            "AND (codes.start IS NULL OR codes.start <= '".$today."') ".
            "AND (codes.end IS NULL OR codes.end >= '".$today."') ".
            "ORDER BY codes.shortname, codes.code");
    }

    public static function delete_discount_code($id) {
        global $wpdb;
        $wpdb->delete("{$wpdb->prefix}wc_railticket_discountcodes", array('id' => $id));
    }

    public static function clean_discount_codes($id) {
        global $wpdb;
        $wpdb->delete("{$wpdb->prefix}wc_railticket_discountcodes", array('single' => 1, 'disabled' => 1));
    }

    public static function add_discount_code($shortname, $code, $start, $end, $single, $disabled, $notes) {
        global $wpdb;

        if (strlen($end) == 0) {
            $end = null;
        }

        if (strlen($start) == 0) {
            $start = null;
        }

        $check = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}wc_railticket_discountcodes WHERE code='".$code."'");
        if ($check) {
            return false;
        }

        $wpdb->insert("{$wpdb->prefix}wc_railticket_discountcodes",
            array('shortname' => $shortname, 'code' => $code, 'start' => $start, 'end' => $end,
            'single' => $single, 'disabled' => $disabled, 'notes' => $notes));
        return true;
    }

    public static function update_discount_code($id, $code, $start, $end, $single, $disabled, $notes) {
        global $wpdb;

        if (strlen($end) == 0) {
            $end = null;
        }

        if (strlen($start) == 0) {
            $start = null;
        }

        $check = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}wc_railticket_discountcodes WHERE code='".$code."' AND id != ".$id);
        if ($check) {
            $wpdb->update("{$wpdb->prefix}wc_railticket_discountcodes",
                array('start' => $start, 'end' => $end,
                'single' => $single, 'disabled' => $disabled, 'notes' => $notes),
                array('id' => $id));
            return false;
        }

        $wpdb->update("{$wpdb->prefix}wc_railticket_discountcodes",
            array('code' => $code, 'start' => $start, 'end' => $end,
            'single' => $single, 'disabled' => $disabled, 'notes' => $notes),
            array('id' => $id));
        return true;
    }

    public function get_code() {
        return $this->data->code;
    }

    public function get_message() {
        if ($this->valid) {
            return __('Discount Validated', 'wc_railticket').": ".$this->get_name();
        }
        return $this->get_name()." ".__('discount is not valid for you selection', 'wc_railticket');
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

    public function is_disabled() {
        return $this->data->disabled;
    }

    public function use() {
        if (!$this->data->single) {
            return;
        }

        $this->disable();
    }

    public static function unuse($code) {
        global $wpdb;
        $wpdb->update("{$wpdb->prefix}wc_railticket_discountcodes",
            array('disabled' => 0), array('code' => $code, 'single' => 1));
    }

    public function disable() {
        global $wpdb;

        $wpdb->update("{$wpdb->prefix}wc_railticket_discountcodes",
            array('disabled' => 1), array('code' => $this->data->code));
    }

}
