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

        $this->railticket_timezone = new \DateTimeZone(get_option('timezone_string'));
        $this->today = new \DateTime();
        $this->today->setTimezone($this->railticket_timezone);
        $this->today->setTime(0,0,0);

        $this->fromstation = $fromstation;
        $this->tostation = $tostation;
        $this->journeytype = $journeytype;

        $this->valid = !$this->data->disabled;

        if ($this->data->start != null) {
            $startdate = DateTime::createFromFormat('Y-m-d', $this->data->start);
            $startdate->setTimezone($this->railticket_timezone);
            if ($today < $startdate) {
                $this->valid = false;
            }
        }

        if ($this->data->end != null) {
            $enddate = DateTime::createFromFormat('Y-m-d', $this->data->end);
            $enddate->setTimezone($this->railticket_timezone);
            if ($today > $enddate) {
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
