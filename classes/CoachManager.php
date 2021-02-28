<?php

namespace wc_railticket;
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

// Coach sets and configurations inherently belong to a BookableDay, but we need to manage the default outside of a BookableDay
// So use a set of static utility classes so they can be used outside of the BookableDay

class CoachManager {
    private $composition, $reserve, $bays;

    public static function format_reserve($res, $daytype) {
        switch ($daytype) {
            case 'simple':
                return self::get_string($res);
            case 'pertrain':
                $str = '';
                foreach ($res as $key => $set) {
                    $str .= $key.":&nbsp;".self::get_string($set)."<br />";
                }
               return $str;
        }
        return '';
    }

    public static function format_composition($comp, $daytype) {
        switch ($daytype) {
            case 'simple':
                return self::get_string($comp->coachset);
            case 'pertrain':
                $str = '';
                foreach ($comp->coachsets as $key => $set) {
                    $str .= $key.":&nbsp;".self::get_string($set->coachset)."<br />";
                }
               return $str;
        }

        return '';
    }

    private static function get_string($reserve) {
        $reserve = (array) $reserve;
        $str = '';
        foreach ($reserve as $i => $num) {
            if ($num > 0) {
                $str .= $i." x".$num.", ";
            }
        }

        return substr($str, 0, strlen($str)-2);
    }

    public static function process_coaches($parsed, Timetable $timetable = null) {
        global $wpdb;

        if ($timetable != null) {
            $timetable = $timetable->get_key_name();
            // Should we use the same data as some other timetable. Saves duplication.
            $copy = $parsed->$timetable->copy;
            if ($copy) {
                $parsed = $parsed->$copy;
            } else {
                $parsed = $parsed->$timetable;
            }
        }
    
        $r = new \stdclass();
        $r->daytype = $parsed->daytype;
        $r->allocateby = $parsed->allocateby;
        $r->coachset = $parsed;
        switch ($parsed->daytype) {
            case 'simple':
                if (property_exists($parsed, 'reserve')) {
                    $r->reserve = $parsed->reserve;
                } else {
                    $r->reserve = false;
                }
                $r->bays = self::get_coachset_bays($parsed->coachset);
                break;
            case 'pertrain':
                $r->bays = self::process_set_allocations($parsed);
                $r->reserve = self::process_set_reserve($parsed);
                break;
        }
        return $r;
    }

   private static function process_set_reserve($parsed) {
        $data = array();

        foreach ($parsed->coachsets as $key => $set) {
            $data[$key] = $set->reserve;
        }

        return $data;
    }

    private static function process_set_allocations($parsed) {
        $data = new \stdclass();
        $data->coachsets = array();
        foreach ($parsed->coachsets as $key => $set) {
            $data->coachsets[$key] = self::get_coachset_bays($set->coachset, false);
        }
        $data->up = $parsed->up;
        $data->down = $parsed->down;

        return $data;
    }

    private static function get_coachset_bays($coachset) {
        global $wpdb;
        $coachset = (array) $coachset;
        $data = array();
        foreach ($coachset as $coach => $count) {
            $comp = $wpdb->get_var("SELECT composition FROM {$wpdb->prefix}wc_railticket_coachtypes WHERE code = '".$coach."'");
            $bays = json_decode(stripslashes($comp));
            foreach ($bays as $bay) {
                if ($bay->priority) {
                    $key = $bay->baysize.'_priority';
                } else {
                    $key = $bay->baysize.'_normal';
                }
                if (array_key_exists($key, $data)) {
                    $data[$key] += $bay->quantity * $count;
                } else {
                    $data[$key] = $bay->quantity * $count;
                }
            }
        }
        ksort($data);
        return $data;
    }
}
