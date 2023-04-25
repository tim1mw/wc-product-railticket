<?php

namespace wc_railticket;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class Discount extends DiscountType {
    public function __construct($data, $fromstation, $tostation, $journeytype, $dateoftravel) {
        $this->data = $data;
        $this->data->rules = json_decode($this->data->rules);
        $this->data->customtype = (bool) $this->data->customtype;
        $this->data->triptype = $this->data->triptype;
        $this->data->shownotes = (bool) $this->data->shownotes;
        $this->data->single = (bool) $this->data->single;
        $this->data->disabled = (bool) $this->data->disabled;

        $this->railticket_timezone = new \DateTimeZone(get_option('timezone_string'));
        $this->dateoftravel = \DateTime::createFromFormat('Y-m-d', $dateoftravel);
        $this->dateoftravel->setTimezone($this->railticket_timezone);
        $this->fromstation = $fromstation;
        $this->tostation = $tostation;
        $this->journeytype = $journeytype;

        $this->valid = !$this->data->disabled;

        if ($this->data->start != null) {
            $startdate = \DateTime::createFromFormat('Y-m-d', $this->data->start);
            $startdate->setTimezone($this->railticket_timezone);
            if ($this->dateoftravel < $startdate) {
                $this->valid = false;
            }
        }

        if ($this->data->end != null) {
            $enddate = \DateTime::createFromFormat('Y-m-d', $this->data->end);
            $enddate->setTimezone($this->railticket_timezone);
            if ($this->dateoftravel > $enddate) {
                $this->valid = false;
            }
        }

        if ($this->valid) {
            switch ($this->data->triptype) {
                case 'fullsgl':
                    if (!$this->fromstation->is_principal() || !$this->tostation->is_principal() || $this->journeytype != 'single') {
                        $this->valid = false;
                    }
                    break;
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
                    $this->valid = true;
                    break;
            }
        }

    }

    public static function get_discount($code, $fromstation, $tostation, $journeytype, $dateoftravel) {
        global $wpdb;

        $data = $wpdb->get_row("SELECT discounts.*, codes.code, codes.start, codes.end, codes.single, codes.disabled ".
            "FROM {$wpdb->prefix}wc_railticket_discountcodes codes ".
            "INNER JOIN {$wpdb->prefix}wc_railticket_discounts discounts ON discounts.shortname = codes.shortname ".
            "WHERE codes.code = '".strtolower($code)."'");

        if (!$data) {
            // This might be a multi-trip discount which uses the order number as the discount code, so look for an order
            // and see if it contains a valid purchase
            $d = DiscountByOrder::get_discount($code, $fromstation, $tostation, $journeytype, $dateoftravel);
            if ($d) {
                return $d;
            }
            return false;
        }

        $bk = BookableDay::get_bookable_day($dateoftravel);
        if (in_array($data->shortname, $bk->get_discount_exclude())) {
            return false;
        }

        return new Discount($data, $fromstation, $tostation, $journeytype, $dateoftravel);
    }


    public static function get_all_discount_data($singleuse = false) {
        global $wpdb;
        if ($singleuse) {
            $singleuse = 1;
        } else {
            $singleuse = 0;
        }
        $sql = "SELECT discounts.name, codes.* ".
            "FROM {$wpdb->prefix}wc_railticket_discountcodes codes ".
            "INNER JOIN {$wpdb->prefix}wc_railticket_discounts discounts ON discounts.shortname = codes.shortname ".
            "WHERE codes.single = ".$singleuse." ".
            "ORDER BY codes.shortname, codes.code";

        return $wpdb->get_results($sql);
    }

    public static function get_all_guard_discounts($dateoftravel) {
        global $wpdb;

        return $wpdb->get_results("SELECT DISTINCT codes.shortname, discounts.name, codes.code ".
            "FROM {$wpdb->prefix}wc_railticket_discountcodes codes ".
            "INNER JOIN {$wpdb->prefix}wc_railticket_discounts discounts ON discounts.shortname = codes.shortname ".
            "WHERE codes.disabled = 0 AND codes.single = 0 AND discounts.notguard = 0 ".
            //"AND (codes.start IS NULL OR codes.start <= '".$dateoftravel."') ".
            //"AND (codes.end IS NULL OR codes.end >= '".$dateoftravel."') ".
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

    public static function add_discount_code_batch($shortname, $code, $start, $end, $single, $disabled, $notes, $batch) {
        global $wpdb;

        if (strlen($end) == 0) {
            $end = null;
        }

        if (strlen($start) == 0) {
            $start = null;
        }

        $length = strlen($code);
        $allcodes = array();

        for ($loop = 0; $loop < $batch; $loop++) {
            $ncode = $code.self::generateRandomString($length);

            $check = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}wc_railticket_discountcodes WHERE code='".$code."'");
            if ($check) {
                continue;
            }

            $allcodes[] = $ncode;

            $wpdb->insert("{$wpdb->prefix}wc_railticket_discountcodes",
                array('shortname' => $shortname, 'code' => $ncode, 'start' => $start, 'end' => $end,
                'single' => $single, 'disabled' => $disabled, 'notes' => $notes));
        }

        return $allcodes;
    }

    private static function generateRandomString($length) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyz';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, $charactersLength - 1)];
        }
        return $randomString;
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

    public function get_travellers() {
        return array();
    }

    public function lock_travellers() {
        return false;
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

    public function use($ticketselections) {
        if (!$this->data->single) {
            return;
        }

        if ($this->data->customtype) {
            $ticketselections = (array) $ticketselections;
            $found = false;
            foreach ($ticketselections as $tsk => $tsv) {
                if (strpos($tsk, "/".$this->data->shortname) !== false && $tsv > 0) {
                    $found = true;
                }
            }

            // The code was entered, but nothing claimed, so don't disable.
            if (!$found) {
                return;
            }

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
