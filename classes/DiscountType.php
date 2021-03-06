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
            return $data;
        }

        return new DiscountType($data);
    }

    public static function get_all_discount_type_mindata() {
        global $wpdb;

        return $wpdb->get_results("SELECT id, name, shortname FROM ".
            "{$wpdb->prefix}wc_railticket_discounts");
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

    public function check_price_field($prefered) {
        if ($this->data->basefare == 'auto') {
            return $prefered;
        }

        return $this->data->basefare;
    }

    public function apply_price_rule($tickettype, $price) {
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
                $price = $rule->value;
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
}
