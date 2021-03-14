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

    public static function get_stations($revision, $dataonly = false) {
        global $wpdb;

        $stns = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_railticket_stations WHERE revision = ".
            $revision." ORDER BY sequence ASC", OBJECT);

        if ($dataonly) {
            return $stns;
        }

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

    public function is_principal() {
        return $this->data->principal;
    }

    public function is_closed() {
        return (bool) $this->data->closed;
    }

    public function get_direction(Station $to) {
        if ($this->data->sequence > $to->get_sequence()) {
            return "up";
        }
        if ($this->data->sequence < $to->get_sequence()) {
            return "down";
        }
        return false;
    }

    public function matches(Station $stn) {
        if ($stn->get_stnid() == $this->data->stnid && $stn->get_revision() == $this->data->revision) {
            return true;
        }

        return false;
    }
}
