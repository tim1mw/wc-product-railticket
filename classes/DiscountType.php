<?php

namespace wc_railticket;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class DiscountType {

    public function __construct($data) {
        $this->data = $data;
        $this->data->rules = json_decode($this->data->rules);
        $this->data->customtype = (bool) $this->data->customtype;
        $this->data->triptype = $this->data->triptype;
        $this->data->shownotes = (bool) $this->data->shownotes;
    }

    public static function get_discount_type($shortname, $dataonly = false) {
        global $wpdb;

        $data = $wpdb->get_row("SELECT * FROM ".
            "{$wpdb->prefix}wc_railticket_discounts ".
            "WHERE shortname = '".$shortname."'");

        if (!$data) {
            return false;
        }

        if ($dataonly) {
            self::format_data($data);
            return $data;
        }

        return new DiscountType($data);
    }

    public static function get_all_discount_type_mindata() {
        global $wpdb;

        return $wpdb->get_results("SELECT id, name, shortname FROM ".
            "{$wpdb->prefix}wc_railticket_discounts ORDER BY name");
    }

    public static function get_all_discount_types($dataonly = false) {
        global $wpdb;

        $dts = $wpdb->get_results("SELECT * FROM ".
            "{$wpdb->prefix}wc_railticket_discounts ORDER BY name");

        if ($dataonly) {
            foreach ($dts as $dt) {
                self::format_data($dt);
            }
            return $dts;
        }

        $alldt = array();
        foreach ($dts as $dt) {
            $alldt = new DiscountType($dt);
        }
        return $alldt;
    }

    private static function format_data(&$dt) {
        switch ($dt->basefare) {
            case 'auto': $dt->basefarefmt = 'Automatic'; break;
            case 'price': $dt->basefarefmt = 'Always Online'; break;
            case 'localprice': $dt->basefarefmt = 'Always Guard'; break;
        }
        if ($dt->customtype == 1) {
            $dt->customtypefmt = 'Yes';
        } else {
            $dt->customtypefmt = 'No';
        }
        switch ($dt->triptype) {
            case 'full': $dt->triptypefmt = 'Full Line Return'; break;
            case 'fullsgl': $dt->triptypefmt = 'Full Line Single'; break;
            case 'any': $dt->triptypefmt = 'Any Trip'; break;
        }
    }

    public function get_shortname() {
        return $this->data->shortname;
    }

    public function get_name() {
        return $this->data->name;
    }

    public function get_comment() {
        return $this->data->comment;
    }

    public function show_notes() {
        return $this->data->shownotes;
    }

    public function get_note_instructions() {
        return $this->data->noteinstructions;
    }

    public function get_note_type() {
        return $this->data->notetype;
    }

    public function get_pattern() {
        return $this->data->pattern;
    }

    public function get_max_seats() {
        return $this->data->maxseats;
    }

    public function get_baseprice_field() {
        return $this->data->basefare;
    }

    public function get_triptype_field() {
        return $this->data->triptype;
    }

    public function not_guard() {
        return $this->data->notguard;
    }

    public function get_rules_data() {
        return $this->data->rules;
    }

    public function check_price_field($prefered) {
        if ($this->data->basefare == 'auto') {
            return $prefered;
        }

        return $this->data->basefare;
    }

    public function apply_price_rule($tickettype, $price, $pfield) {
        $tparts = explode('/', $tickettype);
        if (!property_exists($this->data->rules->discounts, $tparts[0])) {
            return $price;
        }

        // We should be given the full ticket type code here, if it has the discount string on the end
        // Then we should apply the discount id this is a customtype.
        $tc = count($tparts);
        if ($tc == 1 && $this->data->customtype == true) {
            return $price;
        }

        // Sanity check, this ticketype is for this discount!
        if ($tc == 2 && $tparts[1] != $this->data->shortname) {
            return $price;
        }

        $k = $tparts[0];

        $rule = $this->data->rules->discounts->$k;

        switch ($rule->type) {
            case 'percent': 
                $price = $price - (($price/100) * $rule->value);
                break;
            case 'fixed':
                $price = $price - $rule->value;
                break;
            case 'price':
                if (is_object($rule->value)) {
                    $price = $rule->value->$pfield;
                } else {
                    $price = $rule->value;
                }
                break;
        }

        return $price;
    }

    public function get_ticket_max_travellers($tickettype) {
        return $this->data->rules->discounts->$tickettype->max;
    }

    public function use_custom_type() {
        return $this->data->customtype;
    }

    public function inherit_deps() {
        return $this->data->inheritdeps;
    }

    public function ticket_has_discount($tickettype) {
        if (property_exists($this->data->rules->discounts, $tickettype)) {
            return true;
        }

        return false;
    }

    public function has_excludes() {
        if (count($this->data->rules->excludes) > 0) {
            return true;
        }

        return false;
    }

    public function get_excludes() {
        return $this->data->rules->excludes;
    }

    public function update($name, $basefare, $customtype, $inheritdeps, $maxseats, $triptype, $rules, $comment, $shownotes,
        $noteinstructions, $notetype, $pattern, $notguard) {
        global $wpdb;
        $this->data->name = $name;
        $this->data->basefare = $basefare;
        $this->data->customtype = $customtype;
        $this->data->inheritdeps = $inheritdeps;
        $this->data->maxseats = $maxseats;
        $this->data->triptype = $triptype;
        $this->data->rules = json_decode($rules);
        $this->data->comment = $comment;
        $this->data->shownotes = $shownotes;
        $this->data->noteinstructions = $noteinstructions;
        $this->data->notetype = $notetype;
        $this->data->pattern = $pattern;
        $this->data->notguard = $notguard;
        $this->update_record();
    }

    private function update_record() {
        global $wpdb;

        $data = get_object_vars($this->data);
        $data['rules'] = json_encode($data['rules']);

        $wpdb->update($wpdb->prefix.'wc_railticket_discounts',
            $data,
            array('id' => $this->data->id));
    }
}
