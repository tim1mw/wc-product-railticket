<?php

namespace wc_railticket;
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

// Coach sets and configurations inherently belong to a BookableDay, but we need to manage the default outside of a BookableDay
// So use a set of static utility classes so they can be used outside of the BookableDay

class CoachManager {
    private $composition, $reserve, $bays;

    public static function add_coach($code, $name, $capacity, $maxcapacity, $priority, $image) {
        global $wpdb;
        $code = FareCalculator::clean_code($code);

        $c = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_railticket_coachtypes WHERE code='".$code."'");
        if ($c != false) {
            return false;
        }

        $wpdb->insert("{$wpdb->prefix}wc_railticket_coachtypes", 
            array('code' => $code, 'name' => $name, 'capacity' => $capacity, 'maxcapacity' => $maxcapacity,
                'priority' => $priority, 'image' => $image, 'composition' => '[]'));
        return true;
    }

    public static function delete_coach($id) {
        global $wpdb;
        $wpdb->delete("{$wpdb->prefix}wc_railticket_coachtypes", array('id' => $id));
    }

    public static function update_coach($id, $name, $capacity, $maxcapacity, $priority, $image, $hidden, $composition) {
        global $wpdb;

        $wpdb->update("{$wpdb->prefix}wc_railticket_coachtypes", 
            array('name' => $name, 'capacity' => $capacity, 'maxcapacity' => $maxcapacity, 'image' => $image,
                'priority' => $priority, 'hidden' => $hidden, 'composition' => $composition), array('id' => $id));
        return true;
    }

    public static function get_all_coachset_data($inchidden = true, $noprocess = false) {
        global $wpdb;

        if ($inchidden) {
            $where = "";
        } else {
            $where = " WHERE hidden = 0";
        }

        $coaches = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_railticket_coachtypes".$where);

        if ($noprocess) {
            return $coaches;
        }

        $chs = array();
        foreach ($coaches as $coach) {
            $coach->composition = json_decode($coach->composition);
            $code = $coach->code;
            unset($coach->code);
            unset($coach->id);
            $chs[$code] = $coach;
        }
        return $chs;
    }

    public static function format_reserve($res, $daytype) {
        switch ($daytype) {
            case 'simple':
                return self::format_bays($res);
            case 'pertrain':
                $c = array();
                foreach ($res as $key => $set) {
                    $kparts = explode('_', $key);
                    $c[] = __('Set', 'wc_railticket')."&nbsp;".(intval($kparts[1])+1).":&nbsp;".self::format_bays($set);
                }
               return implode('<br />', $c);
        }
        return '';
    }

    public static function format_bays($bays) {
        $c = array();
        foreach ($bays as $i => $num) {
            if ($num > 0) {
                $c[] = self::format_bay($i, $num);
            }
        }
        return implode(', ', $c);
    }

    public static function format_bay($bay, $num = false) {
        $parts = explode('_', $bay);

        if ($parts[0] == 1) {
            switch ($parts[1]) {
                case 'normal': $name = __('Seats', 'wc_railticket'); break;
                case 'priority': $name = __('Wheelchair Spaces', 'wc_railticket'); break;
                default: $name = $bay; break;
            }
        } else {
            switch ($parts[1]) {
                case 'normal': $name = $parts[0].' '.__('Seat Bay', 'wc_railticket'); break;
                case 'priority': $name = $parts[0].' '.__('Seat Wheelchair Bay', 'wc_railticket'); break;
                default: $name = $bay; break;
            }
        }

        if ($num !== false ) {
            return $num."x ".$name;
        } else {
            return $name;
        }
    }

    public static function format_booking_bays($bays) {
        $c = array();
        foreach ($bays as $bay) {
            if ($bay->num > 0) {
                $c[] = self::format_booking_bay($bay);
            }
        }
        return implode(', ', $c);
    }

    public static function format_booking_bay($bay) {
        if ($bay->num == 1) {
            if ($bay->priority) {
                $name = __('Wheelchair space(s)', 'wc_railticket');
            } else {
                $name = __('Seat(s)', 'wc_railticket');
            }
            return $bay->num."x ".self::format_bay_name($bay);
        }

        if ($bay->priority) {
            $name = __('Seat Wheelchair Bay', 'wc_railticket');
        } else {
            $name = __('Seat Bay', 'wc_railticket');
        }

        return $bay->num."x ".self::format_bay_name($bay);
    }

    public static function format_bay_name($bay) {
        if ($bay->baysize == 1) {
            if ($bay->priority) {
                return __('Wheelchair Space(s)', 'wc_railticket');
            } else {
                return __('Seat(s)', 'wc_railticket');
            }
        }

        if ($bay->priority) {
            $name = __('Seat Wheelchair Bay', 'wc_railticket');
        } else {
            $name = __('Seat Bay', 'wc_railticket');
        }

        return $bay->baysize." ".$name;
    }

    public static function format_composition($comp, $daytype) {
        switch ($daytype) {
            case 'simple':
                return self::format_coachset($comp->coachset);
            case 'pertrain':
                $str = '';
                foreach ($comp->coachsets as $key => $set) {
                    $kparts = explode('_', $key);
                    $str .= __('Set', 'wc_railticket')."&nbsp;".(intval($kparts[1])+1).":&nbsp;".self::format_coachset($set->coachset)."<br />";
                }
               return $str;
        }

        return '';
    }

    public static function format_coachset($cset) {

        $c = array();
        foreach ($cset as $i => $num) {
            if ($num > 0) {
                $name = self::format_coach($i);
                $c[] = $num."x ".$name;
            }
        }

        return implode(', ', $c);
    }

    public static function format_coach($code) {
        global $wpdb;
        return $wpdb->get_var("SELECT name FROM {$wpdb->prefix}wc_railticket_coachtypes WHERE code = '".$code."'");
    }

    public static function bay_strings($bays) {
        $str = '';
        foreach ($bays as $bay => $num) {
            $bayd = self::get_bay_details($bay);
            $str .= $num.'x '.$bayd[0].' '.__('seat bay', 'wc_railticket');
            if ($bayd[1] == 'priority') {
                $str .= ' '.__('(with wheelchair space)', 'wc_railticket');
            }
            $str .=', ';
        }
        return substr($str, 0, strlen($str)-2);
    }

    public static function get_bay_details($bay) {
        $parts = explode('_', $bay);
        $parts[0] = intval($parts[0]);
        $parts[2] = $bay;
        return $parts;
    }

    public static function process_coaches($parsed, Timetable $timetable = null) {
        global $wpdb;
        if ($timetable != null) {
            $timetable = $timetable->get_key_name();
            // Should we use the same data as some other timetable. Saves duplication.
            if ($parsed && property_exists($parsed, $timetable)) {
                $copy = $parsed->$timetable->copy;
                if ($copy) {
                    $parsed = $parsed->$copy;
                } else {
                    $parsed = $parsed->$timetable;
                }
                unset($parsed->copy);
            } else {
                $r = new \stdclass();
                $r->daytype = 'simple';
                $r->allocateby = 'seat';
                $r->coachset = new \stdclass();
                $r->coachset->daytype = 'simple';
                $r->coachset->allocateby = 'seat';
                $r->coachset->reserve = new \stdclass();
                $r->coachset->coachset = new \stdclass();
                $r->reserve = new \stdclass();
                $r->bays = new \stdclass();
                return $r;
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
                $r->bays = self::get_coachset_bays($parsed->coachset, $parsed->allocateby);
                break;
            case 'pertrain':
                $r->bays = self::process_set_allocations($parsed, $parsed->allocateby);
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

    private static function process_set_allocations($parsed, $allocateby) {
        $data = new \stdclass();
        $data->coachsets = array();
        foreach ($parsed->coachsets as $key => $set) {
            $data->coachsets[$key] = self::get_coachset_bays($set->coachset, $allocateby);
        }
        $data->up = $parsed->up;
        $data->down = $parsed->down;
        if (property_exists($parsed, 'specials')) {
            $data->specials = $parsed->specials;
        }
        return $data;
    }

    private static function get_coachset_bays($coachset, $allocateby) {
        global $wpdb;
        $coachset = (array) $coachset;
        $data = array();
        if ($allocateby == 'seat') {
            $data['1_normal'] = 0;
            $data['1_priority'] = 0;
            $data['1_normal/max'] = 0;
        }

        foreach ($coachset as $coach => $count) {

            if ($allocateby == 'seat') {
                $coach = $wpdb->get_row("SELECT capacity,maxcapacity,priority FROM {$wpdb->prefix}wc_railticket_coachtypes WHERE code = '".$coach."'");
                $data['1_normal'] += $coach->capacity * $count;
                $data['1_priority'] += $coach->priority * $count;
                $data['1_normal/max'] += $coach->maxcapacity * $count;
                continue;
            }

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

    /*
    * Sanity check filter, ensures that all the bay types in the $tocheck parameter exist as keys in $validtypes
    * Anything that doesn't exist gets removed.
    */

    public static function valid_bay_check($validtypes, $tocheck) {
        $tocheck = (array) $tocheck;
        $validtypes = (array) $validtypes;
        $ret = new \stdclass();
        foreach ($tocheck as $key => $cap) {
            if (array_key_exists($key, $validtypes)) {
                $ret->$key = $cap;
            }
        }

        return $ret;
    }
}
