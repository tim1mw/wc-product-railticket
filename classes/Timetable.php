<?php

namespace wc_railticket;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class Timetable {

    private $data;
    private $cache;

    protected function __construct($data) {
        $this->data = $data;
        $this->data->colsmeta = json_decode($data->colsmeta);
        $this->cache = array();
    }

    public static function get_timetable($ttid, $revision) {
        global $wpdb;
        $data = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}wc_railticket_timetables WHERE revision = ".$revision." AND timetableid = ".$ttid);

        if ($data) {
            return new Timetable($data);
        }

        return false;
    }

    public function get_revision() {
        return $this->data->revision;
    }

    public function get_times($station, $direction, $type, $format) {
        global $wpdb;

        if ($station instanceof Station) {
            $stationid = $station->get_stnid();
        } else {
            $stationid = $station;
        }

        $cachekey = $stationid."_".$direction."_".$type;
        if (array_key_exists($cachekey, $this->cache)) {
            return $cache[$cachekey];
        }

        $times = $wpdb->get_var("SELECT ".$direction."_".$type." FROM {$wpdb->prefix}wc_railticket_stntimes WHERE revision = ".
            $this->data->revision." AND timetableid = ".$this->data->timetableid." AND station = ".$stationid);

        $times = json_decode($times);
        if ($format) {
            $fmt = get_option('wc_railticket_time_format');
            $ftimes = array();
            foreach ($times as $time) {
                $railticket_timezone = new \DateTimeZone(get_option('timezone_string'));
                $dtime = \DateTime::createFromFormat("H:i", $time->hour.":".$time->min);
                $ftimes[] = strftime($fmt, $dtime->getTimeStamp());
            }

            return $ftimes;
        }
        return $times;
    }

    public function get_up_deps($station, $format) {
        return $this->get_times($station, 'up', 'deps', $format);
    }

    public function get_down_deps($station, $format) {
        return $this->get_times($station, 'down', 'deps', $format);
    }

    public function get_up_arrs($station, $format) {
        return $this->get_times($station, 'up', 'arrs', $format);
    }

    public function get_down_arrs($station, $format) {
        return $this->get_times($station, 'down', 'arrs', $format);
    }

    public function get_stations() {
        return Station::get_stations($this->data->revision);
    }

    public function get_terminal($direction) {
        global $wpdb;
        if ($direction == 'up') {
            $sort = "ASC";
        } else {
            $sort = "DESC";
        }

        $data = $wpdb->get_row("SELECT id FROM `wp_wc_railticket_stations` WHERE revision = ".
            $this->data->revision." ORDER BY SEQUENCE ".$sort." LIMIT 1");

        return new Station($data);
    }
}
