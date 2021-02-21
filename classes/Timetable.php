<?php

namespace wc_railticket;
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class Timetable {

    private $data;
    private $cache;

    protected function __construct($data, $date = false) {
        $this->data = $data;
        $this->date = $date;
        $this->data->colsmeta = json_decode($data->colsmeta);
        $this->cache = array();
    }

    public static function get_timetable($ttid, $revision, $date = false) {
        global $wpdb;
        $data = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}wc_railticket_timetables WHERE revision = ".$revision." AND timetableid = ".$ttid);

        if ($data) {
            return new Timetable($data, $date);
        }

        return false;
    }

    public static function get_timetable_by_date($date) {
        global $wpdb;

        $revision = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}wc_railticket_ttrevisions WHERE ".
            "datefrom <= '".$date."' AND dateto >= '".$date."'");
        $ttid = $wpdb->get_var("SELECT timetableid FROM {$wpdb->prefix}wc_railticket_dates WHERE date = '".$date."'");

        if (!$revision || !$ttid) {
            return false;
        }

        return self::get_timetable($ttid, $revision, $date);
    }

    public static function get_all_revisions($format = false) {
        global $wpdb;

        $revisions = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_railticket_ttrevisions");
        if (!$format) {
            return $revisions;
        }

        $railticket_timezone = new \DateTimeZone(get_option('timezone_string'));

        foreach ($revisions as $rev) {
            $jdate = \DateTime::createFromFormat('Y-m-d', $rev->datefrom, $railticket_timezone);
            $rev->datefromformat = strftime(get_option('wc_railticket_date_format'), $jdate->getTimeStamp());
            $jdate = \DateTime::createFromFormat('Y-m-d', $rev->dateto, $railticket_timezone);
            $rev->datetoformat = strftime(get_option('wc_railticket_date_format'), $jdate->getTimeStamp());
        }

        return $revisions;
    }

    public function get_revision() {
        return $this->data->revision;
    }

    public function get_revision_name() {
        global $wpdb;
        return $wpdb->get_var("SELECT name FROM {$wpdb->prefix}wc_railticket_ttrevisions WHERE id = ".$this->data->revision);
    }

    public function get_name() {
        return ucfirst($this->data->timetable);
    }

    public function get_key_name() {
        return $this->data->timetable;
    }

    public function get_colour() {
        return $this->data->colour;
    }

    public function get_background() {
        return $this->data->background;
    }

    public function get_timetableid() {
        return $this->data->timetableid;
    }

    public function get_date($format = false) {
        if (!$format || !$this->date) {
            return $this->date;
        }

        $railticket_timezone = new \DateTimeZone(get_option('timezone_string'));
        $jdate = \DateTime::createFromFormat('Y-m-d', $this->date, $railticket_timezone);
        return strftime(get_option('wc_railticket_date_format'), $jdate->getTimeStamp());
    }

    public function get_times(Station $station, $direction, $type, $format) {
        global $wpdb;

        if (!$this->date) {
            throw new TicketException("This timetable instance has no date set, you must set a date to get station times.");
        }

        $cachekey = $station->get_stnid()."_".$direction."_".$type;
        if (array_key_exists($cachekey, $this->cache)) {
            return $cache[$cachekey];
        }

        $dtimes = $wpdb->get_var("SELECT ".$direction."_".$type." FROM {$wpdb->prefix}wc_railticket_stntimes WHERE revision = ".
            $this->data->revision." AND timetableid = ".$this->data->timetableid." AND station = ".$station->get_stnid());
        $railticket_timezone = new \DateTimeZone(get_option('timezone_string'));
        $date = \DateTime::createFromFormat("Y-m-d", $this->date, $railticket_timezone);
        $times = $this->apply_rules($dtimes, $this->data->colsmeta, $date);

        if ($format) {
            $fmt = get_option('wc_railticket_time_format');
            $ftimes = array();
            foreach ($times as $time) {
                $dtime = \DateTime::createFromFormat("H:i", $time->hour.":".$time->min, $railticket_timezone);
                $time->key = $time->hour.".".$time->min;
                $time->formatted = strftime($fmt, $dtime->getTimeStamp());
            }

        }
        return $times;
    }

    private function apply_rules($times) {

        $filtered = array();
        $times = json_decode($times);
        $count = 0;

        foreach ($times as $time) {
            $rules = $this->data->colsmeta[$count]->rules;
            $count++;
            foreach ($rules as $rule) {

                switch ($rule->code) {
                    case '*':
                        if ($this->isruleforday($rule->str, $date)) {
                            $filtered[] = $time;  
                            continue 3;
                        }
                        break;
                    case '!':
                        if ($this->isruleforday($rule->str, $date)) {  
                            continue 3;
                        }
                        break;
                }
            }
            $filtered[] = $time;
        }
        return $filtered;
    }

    private function isruleforday($str, $date) {
        $len = strlen($str);
        switch ($len) {
            case 1:
                if ($date->format("N") == $str) {
                    return true;
                }
                break;
            case 8:
                $tdate = DateTime::createFromFormat("Ymd", $str);
                if ($tdate == $date) {
                    return true;
                }
                break;
        }
        return false;
    }

    public function get_up_deps(Station $station, $format) {
        return $this->get_times($station, 'up', 'deps', $format);
    }

    public function get_down_deps(Station $station, $format) {
        return $this->get_times($station, 'down', 'deps', $format);
    }

    public function get_up_arrs(Station $station, $format) {
        return $this->get_times($station, 'up', 'arrs', $format);
    }

    public function get_down_arrs(Station $station, $format) {
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

        $data = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}wc_railticket_stations WHERE revision = ".
            $this->data->revision." ORDER BY SEQUENCE ".$sort." LIMIT 1");

        return new Station($data);
    }
}
