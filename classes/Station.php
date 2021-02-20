<?php

namespace wc_railticket;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class Station {
    public function __construct($data) {
        $this->data = $data;
    }

    public static function get_station($stationid, $revision) {
        global $wpdb;
        $stn = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}wc_railticket_stations WHERE revision = ".
            $revision." AND stnid = ".$stationid." ORDER BY sequence ASC", OBJECT);

        return new Station($stn);
    }

    public static function get_stations($revision) {
        global $wpdb;

        $stns = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_railticket_stations WHERE revision = ".
            $revision." ORDER BY sequence ASC", OBJECT);

        $objs = array();
        foreach ($stns as $stn) {
            $objs[$stn->stnid] = new Station($stn);
        }

        return $objs;
    }

    public function get_stnid() {
        return $this->data->stnid;
    }

    public function get_name() {
        return $this->data->name;
    }

    public function get_revision() {
        return $this->data->revision;
    }

    public function get_sequence() {
        return $this->data->sequence;
    }

    public function get_direction(Station $to) {
        if ($this->data->sequence > $to->get_sequence()) {
            return "up";
        } else {
            return "down";
        }
    }
}
