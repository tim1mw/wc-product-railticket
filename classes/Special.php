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

    public static function get_specials_year($year, $dataonly = false) {
        global $wpdb;
        return self::get_specials_sql("SELECT * FROM {$wpdb->prefix}wc_railticket_specials WHERE date >= '".$year."-01-01' AND date <= '".$year."-12-31' ORDER BY date, id ASC", $dataonly);
    }

    public static function get_specials($date, $dataonly = false, $onsaleonly = false) {
        global $wpdb;
        if ($onsaleonly) {
            return self::get_specials_sql("SELECT * FROM {$wpdb->prefix}wc_railticket_specials WHERE onsale = 1 AND date = '".$date."'", $dataonly);
        } else {
            return self::get_specials_sql("SELECT * FROM {$wpdb->prefix}wc_railticket_specials WHERE date = '".$date."'", $dataonly);
        }
    }



    private static function get_specials_sql($sql, $dataonly = false) {
        global $wpdb;

        $specials = $wpdb->get_results($sql);

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

    public static function add($name, $date, $description, $onsale, $colour, $background, $fromstationid, $tostationid, $tickettypes, $longdesc, $survey, $surveyconfig) {
        global $wpdb;
        $data = new \stdclass();
        $data->name = $name;
        $data->date = $date;
        $data->onsale = $onsale;
        $data->description = $description;
        $data->colour = $colour;
        $data->background = $background;
        $data->fromstation = $fromstationid;
        $data->tostation = $tostationid;
        $data->tickettypes = json_encode($tickettypes);
        $data->longdesc = $longdesc;
        $data->survey = $survey;
        $data->surveyconfig = $surveyconfig;
        $wpdb->insert($wpdb->prefix.'wc_railticket_specials', get_object_vars($data));
    }

    public function get_onsale_data() {
        global $wpdb;

        $data = new \stdclass();
        $data->id = $this->data->id;
        $data->name = $this->data->name;
        $data->description = $this->data->description;
        $data->longdesc = $this->data->longdesc;

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

    public static function copy_special($id) {
        global $wpdb;

        if (strpos($id, "s:") === 0) {
            $id = substr($id, 2);
        }

        $special = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}wc_railticket_specials WHERE id = ".$id);
        if (!$special) {
            return false;
        }
        unset ($special->id);
        $special->onsale = 0;
        $special->name = $special->name." (copy)";
        $id = $wpdb->insert($wpdb->prefix.'wc_railticket_specials', get_object_vars($special));
        return $wpdb->insert_id;
    }

    public function get_id() {
        return $this->data->id;
    }

    public function get_dep_id() {
        return "s:".$this->data->id;
    }

    public function get_date($format = false) {
        if (!$format) {
            return $this->data->date;
        }
        $railticket_timezone = new \DateTimeZone(get_option('timezone_string'));
        $jdate = \DateTime::createFromFormat('Y-m-d', $this->data->date, $railticket_timezone);
        return railticket_timefunc(get_option('wc_railticket_date_format'), $jdate->getTimeStamp());
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
    
    public function is_from($id) {
        if ($this->data->fromstation == $id) {
            return true;
        }
        return false;
    }

    public function is_to($id) {
        if ($this->data->tostation == $id) {
            return true;
        }
        return false;
    }

    public function on_sale($format = false) {
        if ($format) {
            if ($this->data->onsale) {
                return __('Yes');
            } else {
                return __('No');
            }
        }
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

    public function get_long_description() {
        return $this->data->longdesc;
    }

    public function get_survey_type() {
        return $this->data->survey;
    }

    public function has_survey() {
        if (strlen($this->data->survey) > 0) {
            return true;
        } else {
            return false;
        }
    }

    public function get_survey() {
        if (strlen($this->data->survey) == 0) {
            return false;
        }
        return survey\Surveys::get_survey($this->data->survey, $this, $this->data->surveyconfig);
    }

    public function get_survey_config() {
        return $this->data->surveyconfig;
    }

    public function update($name, $date, $description, $onsale, $colour, $background, $fromstationid, $tostationid, $tickettypes, $longdesc, $survey, $surveyconfig) {
        $this->data->name = $name;
        $this->data->date = $date;
        $this->data->onsale = $onsale;
        $this->data->description = $description;
        $this->data->colour = $colour;
        $this->data->background = $background;
        $this->data->fromstation = $fromstationid;
        $this->data->tostation = $tostationid;
        $this->data->tickettypes = json_encode($tickettypes);
        $this->data->longdesc = $longdesc;
        $this->data->survey = $survey;
        $this->data->surveyconfig = $surveyconfig;
        $this->update_record();

    }

    private function update_record() {
        global $wpdb;

        $wpdb->update($wpdb->prefix.'wc_railticket_specials',
            get_object_vars($this->data),
            array('id' => $this->data->id));
    }

}
