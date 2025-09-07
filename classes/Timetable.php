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
        $this->railticket_timezone = new \DateTimeZone(get_option('timezone_string'));


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

    public static function get_timetables($revision = false) {
        global $wpdb;

        if ($revision == false) {
            $date = new \DateTime();
            $date = $date->format('Y-m-d');
            $revision = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}wc_railticket_ttrevisions WHERE ".
                "datefrom <= '".$date."' AND dateto >= '".$date."'");
        }
        $alltt = array();
        if ($revision == false) {
           return $alltt;
        }

        $tts = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_railticket_timetables WHERE revision = ".$revision);

        foreach ($tts as $tt) {
            $alltt[] = new Timetable($tt);
        }

        return $alltt;
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

    public static function get_last_timetable($start, $end) {
        global $wpdb;

        $revision = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}wc_railticket_ttrevisions WHERE ".
            "datefrom >= '".$start."' AND dateto <= '".$end."' ORDER BY datefrom DESC LIMIT 1");
        $tt = $wpdb->get_row("SELECT timetableid, date FROM {$wpdb->prefix}wc_railticket_dates WHERE date >= '".$start."' AND date <= '".$end."' ORDER BY date DESC");

        if (!$revision || !$tt) {
            return false;
        }

        return self::get_timetable($tt->timetableid, $revision, $tt->date);
    }

    public static function get_all_revisions($format = false) {
        global $wpdb;

        $revisions = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_railticket_ttrevisions");
        if (!$format) {
            return $revisions;
        }

        $railticket_timezone = new \DateTimeZone(get_option('timezone_string'));

        foreach ($revisions as $rev) {
            $jdate = \DateTime::createFromFormat('Y-m-d', $rev->datefrom, $this->railticket_timezone);
            $rev->datefromformat = railticket_timefunc(get_option('wc_railticket_date_format'), $jdate->getTimeStamp());
            $jdate = \DateTime::createFromFormat('Y-m-d', $rev->dateto, $this->railticket_timezone);
            $rev->datetoformat = railticket_timefunc(get_option('wc_railticket_date_format'), $jdate->getTimeStamp());
        }

        return $revisions;
    }

    public static function get_placeholder($date) {
        global $wpdb;

        $revision = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}wc_railticket_ttrevisions WHERE ".
            "datefrom <= '".$date."' AND dateto >= '".$date."'");

        if ($revision == 0) {
            return false;
        }

        $data = new \stdclass();
        $data->timetableid = 0;
        $data->timetable = 'none';
        $data->revision = $revision;
        $data->background = '000000';
        $data->colour = 'ffffff';
        $data->totaltrains = 0;
        $data->html = '';
        $data->buylink = '';
        $data->hidden = 0;
        
        return new Timetable($data);
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

        $jdate = \DateTime::createFromFormat('Y-m-d', $this->date, $this->railticket_timezone);
        return railticket_timefunc(get_option('wc_railticket_date_format'), $jdate->getTimeStamp());
    }

    /**
    * Given a departure time for a specific station, this will return all the service departure times for other stations
    **/

    public function get_service_by_station(Station $station, $dep, $direction, $stopat) {
        global $wpdb;
        $dtimes = $wpdb->get_var("SELECT ".$direction."_deps FROM {$wpdb->prefix}wc_railticket_stntimes WHERE revision = ".
            $this->data->revision." AND timetableid = ".$this->data->timetableid." AND station = ".$station->get_stnid());

        if (!$dtimes) {
            return false;
        }

        $dep = explode('.', $dep);

        $dtimes = json_decode($dtimes);
        $found = false;
        for ($loop=0; $loop<count($dtimes); $loop++) {
            if ($dtimes[$loop]->hour == $dep[0] && $dtimes[$loop]->min == $dep[1]) {
                $found = $loop;
                break;
            }
        }

        if ($found === false) {
            return false;
        }

        if ($direction == 'up') {
            $order = 'DESC';
            $seq = '>';
        } else {
            $order = 'ASC';
            $seq = '<';
        }
        $alltimes = $wpdb->get_results("SELECT sts.sequence, sts.stnid, times.".$direction."_deps deps ".
            " FROM {$wpdb->prefix}wc_railticket_stntimes times ".
            " INNER JOIN {$wpdb->prefix}wc_railticket_stations sts ON times.station = sts.stnid AND times.revision = sts.revision ".
            " WHERE times.revision = ".
            $this->data->revision." AND times.timetableid = ".$this->data->timetableid." AND sts.sequence ".$seq."= ".$stopat." ".
            "ORDER BY sts.sequence ".$order);
        $service = array();
        foreach ($alltimes as $at) {
            $deps = json_decode($at->deps);
            if (count($deps) == 0) {
                continue;
            }

            $d = new \stdclass();
            $d->time = $deps[$found]->hour.'.'.$deps[$found]->min;
            $d->stnid = $at->stnid;
            $service[$at->sequence] = $d;

            //$service[$at->sequence] = $deps[$found]->hour.'.'.$deps[$found]->min;
        }
        return $service;
    }

    public function get_times(Station $station, $direction, $type, $format, Station $stopsat = null) {
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

        // Set a departure number on each time we get back so we can cross-reference times with those for others
        // stations if we need check the train stops at the destination

        $dtimes = json_decode($dtimes);
        for ($loop = 0; $loop < count($dtimes); $loop++) {
            $dtimes[$loop]->index = $loop;
            $dtimes[$loop]->key = $dtimes[$loop]->hour.".".$dtimes[$loop]->min;
        }

        $date = \DateTime::createFromFormat("Y-m-d", $this->date, $this->railticket_timezone);
        $times = $this->apply_rules($dtimes, $date);

        if ($format) {
            $fmt = get_option('wc_railticket_time_format');
            foreach ($times as $time) {
                $dtime = \DateTime::createFromFormat("H:i", $time->hour.":".$time->min, $this->railticket_timezone);
                $time->formatted = trim(railticket_timefunc($fmt, $dtime->getTimeStamp()));
            }
        }

        // Populate arrivial times for the stopsat station. Don't filter out here so we can still get non-stopping trains at this level
        if ($stopsat != null) {
            $this->service_stops_at($times, $stopsat, $direction, 'arrs', $format);
            $this->service_stops_at($times, $stopsat, $direction, 'deps', $format);
        }

        return $times;
    }

    private function service_stops_at(&$times, Station $stopsat, $direction, $type, $format) {
        $totimes = $this->get_times($stopsat, $direction, $type, $format, null);
        foreach ($times as $time) {
            // If stopsat is already true, then skip this check.
            if (property_exists($time, 'stopsat') && $time->stopsat !== false) {
                continue;
            }
            foreach ($totimes as $totime) {
                if ($time->index == $totime->index) {
                    $time->stopsat = $totime;
                    continue 2;
                }
            }
            $time->stopsat = false;
        }
    }

    private function apply_rules($times, $date) {

        $filtered = array();
        $count = 0;

        // Check for trains that run/don't run today
        foreach ($times as $time) {

            // Filter out trains that don't stop here, aka, no time stated.
            if ($time->hour == '' || $time->min == '') {
                continue;
            }

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
                $tdate = \DateTime::createFromFormat("Ymd", $str, $this->railticket_timezone);
                if ($tdate == $date) {
                    return true;
                }
                break;
        }
        return false;
    }

    public function get_up_deps(Station $station, $format = false, Station $stopsat = null) {
        return $this->get_times($station, 'up', 'deps', $format, $stopsat);
    }

    public function get_down_deps(Station $station, $format = false, Station $stopsat = null) {
        return $this->get_times($station, 'down', 'deps', $format, $stopsat);
    }

    public function get_up_arrs(Station $station, $format = false, Station $stopsat = null) {
        return $this->get_times($station, 'up', 'arrs', $format, $stopsat);
    }

    public function get_down_arrs(Station $station, $format = false, Station $stopsat = null) {
        return $this->get_times($station, 'down', 'arrs', $format, $stopsat);
    }

    public function get_stations($dataonly = false) {
        return Station::get_stations($this->data->revision, $dataonly);
    }

    public function get_station($id) {
        return Station::get_station($id, $this->data->revision);
    }

    public function get_terminal($direction) {
        global $wpdb;
        // TODO: This ought to account for the fact the train we are on might not start from the first open station on the line...

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
        $now->setTimezone($this->railticket_timezone);
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
