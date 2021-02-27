<?php

namespace wc_railticket;
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class Timetable {

    private $data;
    private $cache;

    protected function __construct($data, $date = false) {
        global $wpdb;
        $this->data = $data;
        $this->date = $date;
        $this->data->colsmeta = json_decode($data->colsmeta);

        if ($date) {
            $specials = \wc_railticket\Special::get_specials($date);
            if ($specials) {
                $c = $specials[0]->get_colour();
                if (strlen($c) > 0) {
                    $this->data->colour = $c;
                }
                $b = $specials[0]->get_background();
                if (strlen($b) > 0) {
                    $this->data->background = $b;
                }
                $this->specials = true;
            }
        } else {
            $this->specials = false;
        }
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

    public function get_up_deps(Station $station, $format = false) {
        return $this->get_times($station, 'up', 'deps', $format);
    }

    public function get_down_deps(Station $station, $format = false) {
        return $this->get_times($station, 'down', 'deps', $format);
    }

    public function get_up_arrs(Station $station, $format = false) {
        return $this->get_times($station, 'up', 'arrs', $format);
    }

    public function get_down_arrs(Station $station, $format = false) {
        return $this->get_times($station, 'down', 'arrs', $format);
    }

    public function get_stations($dataonly = false) {
        return Station::get_stations($this->data->revision, $dataonly);
    }

    public function get_terminal($direction) {
        global $wpdb;
        if ($direction == 'up') {
            $sort = "ASC";
        } else {
            $sort = "DESC";
        }

        $data = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}wc_railticket_stations WHERE revision = ".
            $this->data->revision." AND closed = 0 ORDER BY SEQUENCE ".$sort." LIMIT 1");

        $s = new Station($data);
        return $s;
    }

    public function get_last_train() {
        // TODO This is quite an expensive method to call and the value won't change, so add a column to the DB and cache the result on import

        $stations = Station::get_stations($this->data->revision);
        $lastdepdt = 0;
        $lastdep = false;
        foreach ($stations as $station) {
            $updeps = $this->get_up_deps($station);
            foreach ($updeps as $updep) {
                $updepdt = (intval($updep->hour)*60) + intval($updep->min);
                if ($updepdt > $lastdepdt) {
                    $lastdepdt = $updepdt;
                    $lastdep = $updep;
                }
            }

            $downdeps = $this->get_down_deps($station);
            foreach ($downdeps as $downdep) {
                $downdepdt = (intval($downdep->hour)*60) + intval($downdep->min);
                if ($downdepdt > $lastdepdt) {
                    $lastdepdt = $downdepdt;
                    $lastdep = $downdep;
                }
            }
        }

        return $lastdep;
    }

    public function next_train_from(Station $from) {
        $alldeps = array_merge($this->get_down_deps($from), $this->get_up_deps($from));
        $now = new \DateTime();
        $nowtime = ($now->format('G')*60) + $now->format('i');
        foreach ($alldeps as $dep) {
            $time = (intval($dep->hour)*60) + intval($dep->min) + intval(get_option("wc_product_railticket_bookinggrace"));
            if ($time > $nowtime) {
                return $dep;
            }
        }
        return false;
    }

    public function has_specials() {
        return $this->specials;
    }
}
