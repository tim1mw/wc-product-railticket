<?php

namespace wc_railticket;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class Booking {

    private $data, $bookableday, $bays;
    public $special;

    public function __construct($data, BookableDay $bkd) {
        global $wpdb;
        $this->data = $data;
        $this->bookableday = $bkd;
        $this->bays = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_railticket_booking_bays WHERE bookingid = ".$this->data->id);
        if ($this->data->collected == 1) {
            $this->data->collected = true;
        } else {
            $this->data->collected = false;
        }

        if (strpos($this->data->time, "s:") === 0) {
            $this->special = Special::get_special($this->data->time);
        } else {
            $this->special = false;
        }
    }

    public function get_date($format = false, $nottoday = false) {
        if ($format) {
            $railticket_timezone = new \DateTimeZone(get_option('timezone_string'));
            $jdate = \DateTime::createFromFormat('Y-m-d', $this->data->date, $railticket_timezone);
            $now = new \DateTime();
            if ($nottoday && $now != $jdate) {
                return strftime(get_option('wc_railticket_date_format'), $jdate->getTimeStamp()).
                    " <span style='color:red;font-weight:bold;font-size:small;'>(".__("Booking not for Today", "wc_railticket").")</span>";
            }

            return strftime(get_option('wc_railticket_date_format'), $jdate->getTimeStamp());
        }

        return $this->data->date;
    }

    public function get_dep_time($format = false) {
        global $wpdb;
        if ($this->special) {
            if ($format) {
                return $this->special->get_name();
            } else {
                return $this->special->get_dep_id();
            }
        }

        if ($format) {
            $railticket_timezone = new \DateTimeZone(get_option('timezone_string'));
            $dtime = \DateTime::createFromFormat("H.i", $this->data->time, $railticket_timezone);
            if ($dtime) {
                // Despite the config option not having a space, this is contriving to put in a leading space I don't want. Trim it!
                return trim(strftime(get_option('wc_railticket_time_format'), $dtime->getTimeStamp()));
            }
        }
        return $this->data->time;
    }

    public function get_id() {
        return $this->data->id;
    }

    public function get_from_station() {
        return Station::get_station($this->data->fromstation, $this->bookableday->timetable->get_revision());
    }

    public function get_to_station() {
        return Station::get_station($this->data->tostation, $this->bookableday->timetable->get_revision());
    }

    public function get_bays($format = false) {
        if (!$format) {
            return $this->data->bays;
        }

        $fb = '';
        foreach ($this->bays as $bay) {
            $fb .= $bay->baysize. " ".__('seat', 'wc_railticket')." ";
            if ($bay->priority) {
                $fb.= " ".__('disabled', 'wc_railticket');
            }
            $fb.= " ".__('bay', 'wc_railticket')." x".$bay->num.", ";
        }
        $fb = substr($fb, 0, strlen($fb)-2);

        return $fb;
    }

    public function get_seats() {
        return $this->data->seats;
    }

    public function is_collected() {
        return $this->data->collected;
    }
}
