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

    public static function get_last_revision_id() {
        global $wpdb;
        return $wpdb->get_var("SELECT id FROM {$wpdb->prefix}wc_railticket_pricerevisions ORDER BY id DESC LIMIT 1");
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

    public static function get_ticket_name($ticket, $discount = false) {
        global $wpdb;
        $tparts = explode('/', $ticket);
        $name = $wpdb->get_var("SELECT name FROM {$wpdb->prefix}wc_railticket_tickettypes WHERE code = '".$tparts[0]."'");
        if ($discount && $discount->ticket_has_discount($tparts[0]) && count($tparts) > 1) {
            $name .= " ".$discount->get_name();
        }
        return $name;
    }

    public static function get_all_ticket_types($showhidden = false) {
        global $wpdb;
        if ($showhidden) {
            $where = "";
        } else {
            $where = " WHERE hidden = 0";
        }
        return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_railticket_tickettypes".$where." ORDER BY sequence ASC");
    }

    public static function add_ticket_type($code, $name, $description, $special, $guardonly) {
        global $wpdb;

        $code = self::clean_code($code);

        $t = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_railticket_tickettypes WHERE code='".$code."'");
        if ($t != false) {
            return false;
        }

        $seq = $wpdb->get_var("SELECT sequence FROM {$wpdb->prefix}wc_railticket_tickettypes ORDER BY sequence DESC LIMIT 1") + 1;

        $wpdb->insert("{$wpdb->prefix}wc_railticket_tickettypes",
            array('code' => $code, 'name' => $name, 'description' => $description, 'guardonly' => $guardonly,
            'special' => $special, 'composition' => '{}', 'depends' => '[]', 'sequence' => $seq));

        return true;
    }

    public static function update_ticket_type($id, $name, $description, $special, $guardonly, $hidden, $composition, $depends) {
        global $wpdb;

        $composition = json_encode($composition);
        $depends = json_encode($depends);

        $wpdb->update("{$wpdb->prefix}wc_railticket_tickettypes",
            array('name' => $name, 'description' => $description, 'guardonly' => $guardonly,
            'special' => $special, 'hidden' => $hidden, 'composition' => $composition, 'depends' => $depends),
            array('id' => $id));
    }

    public static function delete_ticket_type($id) {
        global $wpdb;
        $wpdb->delete("{$wpdb->prefix}wc_railticket_tickettypes", array('id' => $id));

        // Fix the sequence
        $tickets = self::get_all_ticket_types(true);
        for ($loop = 0; $loop < count($tickets); $loop++) {
            $wpdb->update($wpdb->prefix.'wc_railticket_tickettypes', array('sequence' => $loop),  array('id' => $tickets[$loop]->id));
        }
    }

    public static function move_ticket($id, $inc, $inchidden) {
        global $wpdb;
        $current = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}wc_railticket_tickettypes WHERE id = '".$id."'");
        $swap = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}wc_railticket_tickettypes WHERE sequence = '".($current->sequence+$inc)."'");
        $wpdb->update($wpdb->prefix.'wc_railticket_tickettypes', array('sequence' => $current->sequence),  array('id' => $swap->id));
        $wpdb->update($wpdb->prefix.'wc_railticket_tickettypes', array('sequence' => $swap->sequence),  array('id' => $current->id));
        // Check if we need to move this ticket above the next visibile ticket
        if ($swap->hidden && $inchidden) {
            self::move_ticket($id, $inc, $inchidden);
        }
    }


    public static function get_all_journey_types() {
        return array('return', 'round', 'single');
    }

    public static function get_all_travellers() {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_railticket_travellers");
    }

    public static function get_traveller($code) {
        global $wpdb;
        return $wpdb->get_row("SELECT * FROM {$wpdb->prefix}wc_railticket_travellers WHERE code='".$code."'");
    }

    public static function add_traveller($code, $name, $description, $seats, $guardonly) {
        global $wpdb;
        $code = self::clean_code($code);

        $t = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_railticket_travellers WHERE code='".$code."'");
        if ($t != false) {
            return false;
        }

        $wpdb->insert("{$wpdb->prefix}wc_railticket_travellers",
            array('code' => $code, 'name' => $name, 'description' => $description, 'seats' => $seats, 'guardonly' => $guardonly));

        return true;
    }

    public static function clean_code($code) {
        $code = strtolower(str_replace('|', '_', $code));
        $code = str_replace('/', '_', $code);
        return str_replace(' ', '_', $code);
    }

    public static function update_traveller($id, $name, $description, $seats, $guardonly) {
        global $wpdb;
        $wpdb->update("{$wpdb->prefix}wc_railticket_travellers",
            array('name' => $name, 'description' => $description, 'seats' => $seats, 'guardonly' => $guardonly),
            array('id' => $id));
    }

    public static function delete_traveller($id) {
        global $wpdb;
        $wpdb->delete("{$wpdb->prefix}wc_railticket_travellers", array('id' => $id));
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

    public function get_first_timetable() {
        return Timetable::get_first_timetable($this->data->datefrom, $this->data->dateto);
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

    public function get_tickets_from(Station $fromstation, $showdisabled) {
        global $wpdb;
        $tickets = new \stdclass();

        if (!$showdisabled) {
            $hd = " AND disabled = 0 ";
        } else {
            $hd = "";
        }

        $sql = "SELECT {$wpdb->prefix}wc_railticket_prices.id, ".
            "{$wpdb->prefix}wc_railticket_prices.*, ".
            "{$wpdb->prefix}wc_railticket_tickettypes.name, ".
            "{$wpdb->prefix}wc_railticket_tickettypes.guardonly, ".
            "{$wpdb->prefix}wc_railticket_tickettypes.special ".
            "FROM {$wpdb->prefix}wc_railticket_prices ".
            "INNER JOIN {$wpdb->prefix}wc_railticket_tickettypes ON ".
            "{$wpdb->prefix}wc_railticket_tickettypes.code = {$wpdb->prefix}wc_railticket_prices.tickettype ".
            "WHERE (stationone = ".$fromstation->get_stnid()." OR stationtwo = ".$fromstation->get_stnid().") AND ".
            "{$wpdb->prefix}wc_railticket_prices.revision = ".$this->revision." ".$hd.
            "ORDER BY {$wpdb->prefix}wc_railticket_prices.stationone, {$wpdb->prefix}wc_railticket_prices.stationtwo, ".
            "{$wpdb->prefix}wc_railticket_prices.journeytype, {$wpdb->prefix}wc_railticket_tickettypes.special, {$wpdb->prefix}wc_railticket_tickettypes.sequence ASC";

        return $wpdb->get_results($sql, OBJECT);
    }

    public function get_tickets(Station $fromstation, Station $tostation, $journeytype, $isguard, $localprice, $discount, $special) {
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

        // Check that the discount code doesn't override the price choice here
        // Also exclude any ticket types that should not be used with this discount
        $excludes = "";
        if ($discount) {
            $pfield = $discount->check_price_field($pfield);
            if ($discount->has_excludes()) {
                $excludes = " AND {$wpdb->prefix}wc_railticket_prices.tickettype NOT IN ".
                " ('".implode(',', $discount->get_excludes())."')";
            }
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
            $specialval." ".$guard." ".$excludes.
            "ORDER BY {$wpdb->prefix}wc_railticket_tickettypes.sequence ASC";

        $ticketdata = $wpdb->get_results($sql, OBJECT);

        $tickets->prices = array();
        $tickets->travellers = array();
        $dtravellers = array();

        foreach($ticketdata as $ticketd) {
            $ticketd->composition = json_decode($ticketd->composition);
            $ticketd->depends = json_decode($ticketd->depends);
            $tickets->prices[$ticketd->tickettype] = $ticketd;

            foreach ($ticketd->composition as $code => $num) {
                if ($num == 0) {
                    continue;
                } else {
                    if (!array_key_exists($code, $tickets->travellers)) {

                        if (!$isguard) {
                            $guardtra = " WHERE {$wpdb->prefix}wc_railticket_travellers.guardonly = 0 AND ";
                        } else {
                            $guardtra = " WHERE ";
                        }
                        $tickets->travellers[$code] = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}wc_railticket_travellers ".$guardtra." ".
                            " code = '".$code."'", OBJECT );
                        $tickets->travellers[$code]->max = 99;
                    }
                }
            }

            // If we have a discount and we are inheriting dependencies for the discount, duplicate the dependencies with
            // with the discount type. We may get some non-existent traveller types here, but this is of no consequence since
            // they will never match 
            if ($discount && $discount->inherit_deps()) {
                $ndeps = array();
                foreach ($ticketd->depends as $dep) {
                    $ndeps[] = $dep."/".$discount->get_shortname();
                }
                $ticketd->depends = array_merge($ticketd->depends, $ndeps);
            }

            // If we don't have any discounts, skip the rest...
            if (!$discount || !$discount->ticket_has_discount($ticketd->tickettype)) {
                continue;
            }

            // If we aren't using a custom ticket type+traveller here, just adjust the price and continue
            if (!$discount->use_custom_type()) {
                $ticketd->price = $discount->apply_price_rule($ticketd->tickettype, $ticketd->price);
                continue;
            }

            // We need a custom traveller and custom ticket type cloned from the standard data
            // PHP only performs a shallow copy of the object with clone(), that's no good. So use json to clone!
            $customticket = json_decode(json_encode($ticketd));
            $customticket->tickettype = $customticket->tickettype.'/'.$discount->get_shortname();
            $customticket->price = $discount->apply_price_rule($customticket->tickettype, $ticketd->price);
            $customticket->description = $discount->get_name();
            $customticket->composition = new \stdclass();
            $comp = (array) $ticketd->composition;

            foreach ($comp as $otkey => $otvalue) {
                $ntkey = $otkey."/".$discount->get_shortname();
                $customticket->composition->$ntkey = $otvalue;

                if (array_key_exists($ntkey, $dtravellers)) {
                    continue;
                }

                // Add a new traveller type for this discount
                $ntra = json_decode(json_encode($tickets->travellers[$otkey]));
                $ntra->code = $ntkey;
                $ntra->description = $discount->get_name();
                $ntra->max = $discount->get_ticket_max_travellers($ticketd->tickettype);
                $dtravellers[$ntkey] = $ntra;
            }

            $tickets->prices[$customticket->tickettype] = $customticket;
        }

        $tickets->dcomment = '';

        ksort($tickets->travellers);
        if ($discount) {
            ksort($dtravellers);
            $tickets->travellers = array_merge($dtravellers, $tickets->travellers);
        }
        // The code was put in the index to make life easy for discounts, but the JS doesn't expect this, so strip it out.
        $tickets->travellers = array_values($tickets->travellers);
        return $tickets;
    }

    public function ticket_allocation_price($ticketsallocated, $ticketselections, Station $from, Station $to, $journeytype, $localprice,
        $nominimum, $discount, $special) {
        if ($localprice) {
            $pfield = 'localprice';
        } else {
            $pfield = 'price';
        }

        if ($journeytype == 'round') {
            // Round trips are priced as from > to the same station where available.
            $from = $to;
        }

        // If we have a discount, count up the seats used by discounted and non discounted travellers
        $normalseats = 0;
        $customseats = 0;
        if ($discount) {
            foreach ($ticketselections as $tcode => $num) {
                if ($num == 0) {
                    continue;
                }
                $parts = explode('/', $tcode);
                $traveller = $this->get_traveller($parts[0]);
                if (count($parts) == 2) {
                    $customseats = $num * $traveller->seats;
                } else {
                    $normalseats = $num * $traveller->seats;
                }
            }
        }

        $pdata = new \stdclass();
        $pdata->supplement = 0;
        $pdata->price = 0;
        $pdata->ticketprices = array();
        $pdata->ticketprices['__pfield'] = $pfield;
        if ($discount) {
            $pdata->ticketprices['__discountcode'] = $discount->get_code();
            $pdata->ticketprices['__discounttype'] = $discount->get_shortname();
            $pdata->ticketprices['__discounttotal'] = 0;
        } else {
            $pdata->ticketprices['__discountcode'] = false;
            $pdata->ticketprices['__discounttype'] = '';
        }
        $pdata->revision = $this->revision;

        $discountpricetotal = 0;
        foreach ($ticketsallocated as $ttype => $qty) {
            $price = $this->get_fare($from, $to, $journeytype, $ttype, $pfield, $discount, $special);
            $pdata->price += floatval($price)*floatval($qty);
            $pdata->ticketprices[$ttype] = $price;
            if ($discount) {
                // What was the price without the discount?
                $nodiscountprice = $this->get_fare($from, $to, $journeytype, $ttype, $pfield, false, $special);
                $ds = floatval($nodiscountprice) - floatval($price);
                if ($ds > 0) {
                    $pdata->ticketprices['__discounttotal'] += $ds*floatval($qty);
                    $discountpricetotal += floatval($price);
                }
            }
        }

        if ($nominimum || $pdata->price == 0) {
            return $pdata;
        }

        $mprice = get_option('wc_product_railticket_min_price');
        if (strlen($mprice) > 0 && $pdata->price < $mprice) {
            // Deal with the special case of a 100% discount on some custom tickets, where there are also paying travellers
            // that don't need seats (aka dogs). We don't charge the supplement just for a dog.
            if ($discount && $customseats > 0 && $normalseats == 0 && $discountpricetotal == 0) {
                return $pdata;
            }

            $mprice=floatval($mprice);
            $pdata->supplement = floatval($mprice) - floatval($pdata->price);
            $pdata->price = $mprice;

            // Offset the discount by the value of the supplement
            if ($discount) {
                $pdata->ticketprices['__discounttotal'] -= $pdata->supplement;
                if ($pdata->ticketprices['__discounttotal'] < 0) {
                    $pdata->ticketprices['__discounttotal'] = 0;
                }
            }
        }

        return $pdata;
    }

    public function get_fare(Station $from, Station $to, $journeytype, $ttype, $pfield, $discount, $special) {
        global $wpdb;
        if (!$special) {
            $jt = " AND journeytype = '".$journeytype."' ";
        } else {
            $jt = "";
        }

        // Strip out any discount code we may have from the ticket type
        $tparts = explode('/', $ttype);

        $sql = "SELECT ".$pfield." FROM {$wpdb->prefix}wc_railticket_prices WHERE tickettype = '".$tparts[0]."' ".
            $jt." AND revision = ".$this->revision." AND ".
            "((stationone = ".$from->get_stnid()." AND stationtwo = ".$to->get_stnid().") OR ".
            "(stationone = ".$to->get_stnid()." AND stationtwo = ".$from->get_stnid()."))";
        $price = $wpdb->get_var($sql);

        if ($discount) {
            $price = $discount->apply_price_rule($ttype, $price);
        }

        return $price;
    }

    public function count_seats($ticketselections) {
        global $wpdb;
        $total = 0;
        $tkts = (array) $ticketselections;

        foreach ($tkts as $ttype => $number) {
            // Strip out any discount code we may have from the ticket type
            $parts = explode('/', $ttype);

            $val = $wpdb->get_var("SELECT seats FROM {$wpdb->prefix}wc_railticket_travellers WHERE code='".$parts[0]."'") * $number;
            $total += $val;
        }
        return $total;
    }

    public function delete_fare($id) {
        global $wpdb;
        $wpdb->delete("{$wpdb->prefix}wc_railticket_prices", array('id' => $id));
    }

    public function update_fare($id, $price, $localprice, $disabled, $image) {
        global $wpdb;

        $data = array(
            'price' => $price,
            'localprice' => $localprice,
            'disabled' => $disabled,
            'image' => $image
        );

        $wpdb->update("{$wpdb->prefix}wc_railticket_prices", $data, array('id' => $id));
    }

    public function add_fare($stnone, $stntwo, $tickettype, $journeytype, $price, $localprice, $disabled, $image) {
        global $wpdb;

        $id = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}wc_railticket_prices WHERE revision = ".$this->revision." AND ".
            "tickettype = '".$tickettype."' AND journeytype='".$journeytype."' AND stationone = ".$stnone." AND stationtwo = '".$stntwo."' ");

        if ($id) {
            return false;
        }

        $data = array(
            'revision' => $this->revision,
            'stationone' => $stnone,
            'stationtwo' => $stntwo,
            'tickettype' => $tickettype,
            'journeytype' => $journeytype,
            'price' => $price,
            'localprice' => $localprice,
            'disabled' => $disabled,
            'image' => $image
        );

        $wpdb->insert("{$wpdb->prefix}wc_railticket_prices", $data);

        return true;
    }
}
