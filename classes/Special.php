<?php

namespace wc_railticket;
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class Special {

    private $data, $ttrevision;

    public function __construct($data) {
        global $wpdb;
        $this->data = $data;
        $this->ttrevision = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}wc_railticket_ttrevisions WHERE ".
            "datefrom <= '".$this->data->date."' AND dateto >= '".$this->data->date."'");
    }

    public static function get_specials($date, $dataonly = false) {
        global $wpdb;

        $specials = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_railticket_specials WHERE date = '".$date."'");

        if (count($specials) == 0) {
             return false;   
        }

        if ($dataonly) {
            return $specials;
        }

        $sp = array();
        foreach ($specials as $special) {
            $sp[] = new Special($special);
        }

        return $sp;
    }

    public function get_onsale_data() {
        global $wpdb;

        $data = new \stdclass();
        $data->id = $this->data->id;
        $data->name = $this->data->name;
        $data->description = $this->data->description;

        return $data;
    }

    public static function get_special($id) {
        global $wpdb;

        if (strpos($id, "s:") === 0) {
            $id = substr($id, 2);
        }

        $special = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}wc_railticket_specials WHERE id = ".$id);
        if ($special) {
            return new Special($special);
        }

        return false;
    }

    public function get_id() {
        return $this->data->id;
    }

    public function get_dep_id() {
        return "s:".$this->data->id;
    }

    public function get_name() {
        return $this->data->name;
    }

    public function get_description() {
        return $this->data->description;
    }

    public function get_timetable_revision() {
        return $this->ttrevision;
    }

    public function get_from_station() {
        return Station::get_station($this->data->fromstation, $this->ttrevision);
    }

    public function get_to_station() {
        return Station::get_station($this->data->tostation, $this->ttrevision);
    }

    public function on_sale() {
        return $this->data->onsale;
    }

    public function get_background() {
        return $this->data->background;
    }

    public function get_colour() {
        return $this->data->colour;
    }

    public function get_ticket_types() {
        return json_decode($this->data->tickettypes);
    }
}
