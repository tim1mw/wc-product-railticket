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

    public static function get_ticket_name($ticket) {
        global $wpdb;
        return $wpdb->get_var("SELECT name FROM {$wpdb->prefix}wc_railticket_tickettypes WHERE code = '".$ticket."'");
    }

    private function get_date($key) {
        if (!$format) {
            return $this->data->$key;
        }

        $railticket_timezone = new \DateTimeZone(get_option('timezone_string'));
        $jdate = \DateTime::createFromFormat('Y-m-d', $this->data->$key, $railticket_timezone);
        return railticket_timefunc(get_option('wc_railticket_date_format'), $jdate->getTimeStamp());
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

    public function can_sell_journey(Station $fromstation, Station $tostation, $type, $guard, $special) {
        global $wpdb;

        // Note round trips go from and to the same station for the purposes of pricing.

        $sql = "SELECT COUNT(prices.id) FROM {$wpdb->prefix}wc_railticket_prices prices ".
            "INNER JOIN {$wpdb->prefix}wc_railticket_tickettypes tt ON ".
            "tt.code = prices.tickettype ".
            "WHERE prices.journeytype = '".$type."' AND ".
            "((prices.stationone = ".$fromstation->get_stnid()." AND prices.stationtwo = ".$tostation->get_stnid().") OR ".
            "(prices.stationone = ".$tostation->get_stnid()." AND prices.stationtwo = ".$fromstation->get_stnid().")) AND prices.disabled = 0 AND ".
            "prices.revision = ".$this->revision;

        if (!$guard) {
            $sql .= " AND tt.guardonly = 0";
        }

        if ($special) {
            $sql .= " AND tt.special = 1";
        } else {
            $sql .= " AND tt.special = 0";
        }

        $jt = $wpdb->get_var($sql);
        if ($jt > 0) {
            return true;
        }

        return false;
    }

    public function get_tickets(Station $fromstation, Station $tostation, $journeytype, $isguard, $localprice, $discountcode, $special) {
        global $wpdb;
        $tickets = new \stdClass();

        if (!$isguard) {
            $guardtra = " WHERE {$wpdb->prefix}wc_railticket_travellers.guardonly = 0 ";
            $guard = " AND {$wpdb->prefix}wc_railticket_tickettypes.guardonly = 0 ";
        } else {
            $guardtra = "";
            $guard = "";
        }

        if ($localprice) {
            $pfield = 'localprice';
        } else {
            $pfield = 'price';
        }

        if ($special) {
            $specialval = " AND tickettype IN ('";
            $tkts = $special->get_ticket_types();
            $specialval .= implode("','", $tkts)."')";
            // Note journeytype is ignored for specials. Specials are treated as singles for booking purposes because there is only one leg.
            // However the tickets could get entered as either round, return or single for presentation purposes.
            $specialval .= " AND special = 1 ";
        } else {
            $specialval = " AND special = 0 AND journeytype = '".$journeytype."'";
        }

        $sql = "SELECT {$wpdb->prefix}wc_railticket_prices.id, ".
            "{$wpdb->prefix}wc_railticket_prices.tickettype, ".
            "{$wpdb->prefix}wc_railticket_prices.".$pfield." as price, ".
            "{$wpdb->prefix}wc_railticket_tickettypes.name, ".
            "{$wpdb->prefix}wc_railticket_tickettypes.description, ".
            "{$wpdb->prefix}wc_railticket_tickettypes.composition, ".
            "{$wpdb->prefix}wc_railticket_tickettypes.depends, ".
            "{$wpdb->prefix}wc_railticket_prices.image ".
            "FROM {$wpdb->prefix}wc_railticket_prices ".
            "INNER JOIN {$wpdb->prefix}wc_railticket_tickettypes ON ".
            "{$wpdb->prefix}wc_railticket_tickettypes.code = {$wpdb->prefix}wc_railticket_prices.tickettype ".
            "WHERE ((stationone = ".$fromstation->get_stnid()." AND stationtwo = ".$tostation->get_stnid().") OR ".
            "(stationone = ".$tostation->get_stnid()." AND stationtwo = ".$fromstation->get_stnid().")) ".
            " AND disabled = 0 AND ".
            "{$wpdb->prefix}wc_railticket_prices.revision = ".$this->revision." ".
            $specialval." ".$guard.
            "ORDER BY {$wpdb->prefix}wc_railticket_tickettypes.sequence ASC";

        $ticketdata = $wpdb->get_results($sql, OBJECT);

        $tickets->prices = array();
        $tickets->travellers = array();
        $done = array();

        // TODO Apply discounts here

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

    public function ticket_allocation_price($ticketsallocated, Station $from, Station $to, $journeytype, $localprice,
        $nominimum, $discountcode, $special) {
        if ($localprice) {
            $pfield = 'localprice';
        } else {
            $pfield = 'price';
        }

        if ($journeytype == 'round') {
            // Round trips are priced as from > to the same station where available.
            $from = $to;
        }

        $pdata = new \stdclass();
        $pdata->supplement = 0;
        $pdata->price = 0;
        $pdata->ticketprices = array();
        $pdata->ticketprices['__pfield'] = $pfield;
        $pdata->ticketprices['__discountcode'] = $discountcode;
        $pdata->ticketprices['__discounttype'] = '';
        $pdata->revision = $this->revision;

        foreach ($ticketsallocated as $ttype => $qty) {
            $price = $this->get_fare($from, $to, $journeytype, $ttype, $pfield, $discountcode, $special);
            $pdata->price += floatval($price)*floatval($qty);
            $pdata->ticketprices[$ttype] = $price;
        }

        if ($nominimum || $pdata->price == 0) {
            return $pdata;
        }

        $mprice = get_option('wc_product_railticket_min_price');
        if (strlen($mprice) > 0 && $pdata->price < $mprice) {
            $mprice=floatval($mprice);
            $pdata->supplement = floatval($mprice) - floatval($pdata->price);
            $pdata->price = $mprice;
        }

        return $pdata;
    }

    public function get_fare(Station $from, Station $to, $journeytype, $ttype, $pfield, $discountcode, $special) {
        global $wpdb;
        if (!$special) {
            $jt = " AND journeytype = '".$journeytype."' ";
        } else {
            $jt = "";
        }

        $sql = "SELECT ".$pfield." FROM {$wpdb->prefix}wc_railticket_prices WHERE tickettype = '".$ttype."' ".
            $jt." AND revision = ".$this->revision." AND ".
            "((stationone = ".$from->get_stnid()." AND stationtwo = ".$to->get_stnid().") OR ".
            "(stationone = ".$to->get_stnid()." AND stationtwo = ".$from->get_stnid()."))";
        $price = $wpdb->get_var($sql);

        // TODO Apply Discounts here

        return $price;
    }

    public function count_seats($ticketselections) {
        global $wpdb;
        $total = 0;
        $tkts = (array) $ticketselections;

        foreach ($tkts as $ttype => $number) {
            $val = $wpdb->get_var("SELECT seats FROM {$wpdb->prefix}wc_railticket_travellers WHERE code='".$ttype."'") * $number;
            $total += $val;
        }
        return $total;
    }
}
