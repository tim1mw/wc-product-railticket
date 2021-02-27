<?php

namespace wc_railticket;
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class FareCalculator {

    private $data;

    private function __construct($data) {
        $this->data = $data;
        $this->revision = $this->data->id;
    }

    public static function get_fares($rev) {
        global $wpdb;
        $revision = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}wc_railticket_pricerevisions WHERE ".
            "id = ".$rev);

        if (!$revision) {
            return false;
        }

        return new FareCalculator($revision);
    }

    public static function get_fares_by_date($date) {
        global $wpdb;
        $revision = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}wc_railticket_pricerevisions WHERE ".
            "datefrom <= '".$date."' AND dateto >= '".$date."'");

        if (!$revision) {
            return false;
        }
        return new FareCalculator($revision);
    }

    public static function get_all_revisions() {
        global $wpdb;
        $revisions = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_railticket_pricerevisions");
        $r = array();
        foreach ($revisions as $rev) {
            $r[] = new FareCalculator($rev);
        }
        return $r;
    }

    private function get_date($key) {
        if (!$format) {
            return $this->data->$key;
        }

        $railticket_timezone = new \DateTimeZone(get_option('timezone_string'));
        $jdate = \DateTime::createFromFormat('Y-m-d', $this->data->$key, $railticket_timezone);
        return strftime(get_option('wc_railticket_date_format'), $jdate->getTimeStamp());
    }

    public function get_date_from($format = false) {
        return $this->get_date('datefrom', $format);
    }

    public function get_date_to($format = false) {
        return $this->get_date('datefrom', $format);
    }

    public function get_name() {
        return $this->data->name;
    }

    public function get_revision() {
        return $this->data->id;
    }

    public function get_tickets(Station $fromstation, Station $tostation, $journeytype, $isguard) {
        global $wpdb;
        $tickets = new \stdClass();

        if (!$isguard) {
            $guardtra = " WHERE {$wpdb->prefix}wc_railticket_travellers.guardonly = 0 ";
            $guard = " AND {$wpdb->prefix}wc_railticket_tickettypes.guardonly = 0 ";
        } else {
            $guardtra = "";
            $guard = "";
        }

/* TODO Fix specials...
        if ($this->special) {
            $t = $wpdb->get_results("SELECT id, tickettypes FROM {$wpdb->prefix}wc_railticket_specials WHERE ".
                "id = ".$this->outtime." AND onsale = '1'")[0];
            $specialval = " AND special = 1 AND (";
            $tkts = json_decode($t->tickettypes);
            $first = true;
            $tickets->travellers = array();
            foreach ($tkts as $tkt) {
                if (!$first) {
                    $specialval .= " OR ";
                }
                $first = false;
                $specialval .= " tickettype = '".$tkt."' ";
            }
            $specialval .= ")";
        } else {
*/
            $specialval = " AND special = 0 ";
//        }

        $sql = "SELECT {$wpdb->prefix}wc_railticket_prices.id, ".
            "{$wpdb->prefix}wc_railticket_prices.tickettype, ".
            "{$wpdb->prefix}wc_railticket_prices.price, ".
            "{$wpdb->prefix}wc_railticket_tickettypes.name, ".
            "{$wpdb->prefix}wc_railticket_tickettypes.description, ".
            "{$wpdb->prefix}wc_railticket_tickettypes.composition, ".
            "{$wpdb->prefix}wc_railticket_tickettypes.depends, ".
            "{$wpdb->prefix}wc_railticket_prices.image ".
            "FROM {$wpdb->prefix}wc_railticket_prices ".
            "INNER JOIN {$wpdb->prefix}wc_railticket_tickettypes ON ".
            "{$wpdb->prefix}wc_railticket_tickettypes.code = {$wpdb->prefix}wc_railticket_prices.tickettype ".
            "WHERE ((stationone = ".$fromstation->get_stnid()." AND stationtwo = ".$tostation->get_stnid().") OR ".
            "(stationone = ".$tostation->get_stnid()." AND stationtwo = ".$fromstation->get_stnid().")) AND ".
            "journeytype = '".$journeytype."' AND disabled = 0 AND ".
            "{$wpdb->prefix}wc_railticket_prices.revision = ".$this->revision." ".
            $specialval." ".$guard.
            "ORDER BY {$wpdb->prefix}wc_railticket_tickettypes.sequence ASC";

        $ticketdata = $wpdb->get_results($sql, OBJECT);

        $tickets->prices = array();
        $tickets->travellers = array();
        $done = array();

        foreach($ticketdata as $ticketd) {
            $ticketd->composition = json_decode($ticketd->composition);
            $ticketd->depends = json_decode($ticketd->depends);
            $tickets->prices[$ticketd->tickettype] = $ticketd;

            foreach ($ticketd->composition as $code => $num) {
                if ($num == 0) {
                    continue;
                } else {
                    if (!in_array($code, $done)) {
                        $done[] = $code;

                        if (!$isguard) {
                            $guardtra = " WHERE {$wpdb->prefix}wc_railticket_travellers.guardonly = 0 AND ";
                        } else {
                            $guardtra = " WHERE ";
                        }
                        $tickets->travellers[] = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_railticket_travellers ".$guardtra." ".
                            " code = '".$code."'", OBJECT )[0];
                    }
                }
            }
        }

        return $tickets;
    }
}
