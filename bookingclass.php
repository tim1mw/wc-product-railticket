<?php

class TicketBuilder {

    private $today, $tomorrow, $stations;

    public function __construct($dateoftravel, $fromstation, $tostation, $outtime, $rettime,
        $journeytype, $ticketselections, $ticketsallocated, $overridevalid, $disabledrequest) {
        global $wpdb;
        $this->railticket_timezone = new DateTimeZone(get_option('timezone_string'));
        $this->now = new DateTime();
        $this->now->setTimezone($this->railticket_timezone);
        $this->today = new DateTime();
        $this->today->setTime(0,0,0);
        $this->today->setTimezone($this->railticket_timezone);
        $this->tomorrow = new DateTime();
        $this->tomorrow->setTimezone($this->railticket_timezone);
        $this->tomorrow->modify('+1 day');
        $this->stations = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_stations ORDER BY sequence ASC");

        $this->dateoftravel = $dateoftravel;
        $this->fromstation = $fromstation;
        $this->tostation = $tostation;
        $this->outtime = $outtime;
        $this->rettime = $rettime;
        $this->journeytype = $journeytype;
        $this->ticketselections = $ticketselections;
        $this->ticketsallocated = $ticketsallocated;
        $this->overridevalid = $overridevalid;
        if ($disabledrequest == 'true') {
            $this->disabledrequest = true;
        } else {
            $this->disabledrequest = false;
        }
    }

    public function render() {
        if ($this->checkDuplicate()) {
            return '<p>Sorry, but you already have a ticket selection in your shopping cart, you can only have one ticket selection per order. Please remove the existing ticket selection if you wish to create a new one, or complete the purchase for the existing one.</p>';
        }

        return $this->get_javascript().
            $this->get_datepick().
            '<form action="post" name="railticketbooking">'.
            $this->get_stations().
            $this->get_deptimes().
            $this->get_ticket_choices().
            $this->get_addtocart().'</form>'.
            '<div id="pleasewait" class="railticket_loading">Fetching Ticket Data&#8230;</div>'.
            '<div id="railticket_error" class="railticket_stageblock" ></div>';
    }

    public function is_date_bookable($date) {
        if ($date < $this->today) {
            return false;
        }

        $rec = $this->get_bookable_record($date);
        if ($rec) {
            return true;
        } else {
            return false;
        }
    }

    public function is_train_bookable($time, $stn, $direction) {
        // Dates which are in the past are not allowed
        $parts = explode('.', $time);
        $deptime = DateTime::createFromFormat("Y-m-d", $this->dateoftravel, $this->railticket_timezone);
        $deptime->setTime($parts[0], $parts[1], 0);
        $deptime->modify('+'.get_option('wc_product_railticket_bookinggrace').' minutes');

        if ($this->now > $deptime) {
            return false;
        }

        $cap = $this->get_capacity($time, 'single', $stn);

        if ($cap->outseatsleft > 0) {
            return true;
        }
        return false;
    }

    private function get_bookable_record($date) {
        global $wpdb;
        if ($date instanceof DateTime) {
            $date = $date->format('Y-m-d');
        }

        $sql = "SELECT {$wpdb->prefix}wc_railticket_bookable.* FROM {$wpdb->prefix}railtimetable_dates ".
            "LEFT JOIN {$wpdb->prefix}wc_railticket_bookable ON ".
            " {$wpdb->prefix}wc_railticket_bookable.dateid = {$wpdb->prefix}railtimetable_dates.id ".
            "WHERE {$wpdb->prefix}railtimetable_dates.date = '".$date."' AND ".
            "{$wpdb->prefix}wc_railticket_bookable.bookable = 1 AND {$wpdb->prefix}wc_railticket_bookable.soldout = 0";

        $rec = $wpdb->get_results($sql);
        if (count($rec) > 0) {
            return $rec[0];
        } else {
            return false;
        }
    }

    public function get_bookable_stations() {
        $bookable = array();
        $bookable['from'] = array();
        $bookable['to'] = array();
        $bkrec = $this->get_bookable_record($this->dateoftravel);
        $bookinglimits = json_decode($bkrec->limits);

        $bookable['override'] = $bkrec->override;
        foreach ($bookinglimits as $limit) {
            if ($limit->enableout || $this->overridevalid == 1) {
                $bookable['from'][$limit->station] = true;
            } else {
                $bookable['from'][$limit->station] = false;
            }
            if ($limit->enableret || $this->overridevalid == 1) {
                $bookable['to'][$limit->station] = true;
            } else {
                $bookable['to'][$limit->station] = false;
            }
        }

        return $bookable;
    }

    private function timefunc($fmt, $time) {
        $tz = date_default_timezone_get();
        date_default_timezone_set($this->railticket_timezone->getName());

        $result = strftime($fmt, strtotime($time));

        date_default_timezone_set($tz);
        return $result;
    }

    public function get_bookable_trains() {
        global $wpdb;
        $fmt = get_option('railtimetable_time_format');
        $bookable = array();

        $timetable = $this->findTimetable();
        $deptimesdata = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_times WHERE station = '".$this->fromstation."' ".
            " AND timetableid = ".$timetable->id)[0];
        $rettimesdata = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_times WHERE station = '".$this->tostation."' ".
            " AND timetableid = ".$timetable->id)[0];
        $direction = $this->get_direction();

        $bookable['out'] = array();
        $dd = $direction."_deps";
        $da = $direction."_arrs";
        $deptimes = explode(",", $deptimesdata->$dd);
        $deparrs = explode(",", $rettimesdata->$da);
        $firstdep = false;
        $outtotal = 0;
        foreach ($deptimes as $index => $dep) {
            if ($this->overridevalid == 1) {
                $canbook = true;
            } else {
                $canbook = $this->is_train_bookable($dep, $this->fromstation, $direction);
            }
            $bookable['out'][] = array('dep' => $dep, 'depdisp' => strftime($fmt, strtotime($dep)),
                'arr' => $deparrs[$index],  'arrdisp' => strftime($fmt, strtotime($deparrs[$index])),
                'bookable' => $canbook);
            if ($firstdep == false) {
                $firstdep = strtotime($dep);
            }
            if ($canbook) {
                $outtotal++;
            }
        }
        
        if ($direction == 'up') {
            $direction = 'down';
        } else {
            $direction = 'up';
        }

        $dd = $direction."_deps";
        $da = $direction."_arrs";
        $rettimes = explode(",", $rettimesdata->$dd);
        $retarrs = explode(",", $deptimesdata->$da);
        $bookable['ret'] = array();
        $testfirst = true;
        $intotal = 0;
        foreach ($rettimes as $index => $ret) {
            if ($testfirst && strtotime($ret) < $firstdep) {
                // If the first return trip is before the first departure,skip it
                $testfirst = false;
                continue;
            }
            if ($this->overridevalid == 1) {
                $canbook = true;
            } else {
                $canbook = $this->is_train_bookable($ret, $this->tostation, $direction);
            }
            $bookable['ret'][] = array('dep' => $ret, 'depdisp' => strftime($fmt, strtotime($ret)),
                'arr' => $retarrs[$index], 'arrdisp' => strftime($fmt, strtotime($retarrs[$index])), 
                'bookable' => $canbook);
            $testfirst = false;
            if ($canbook) {
                $intotal ++;
            }
        }

        $bk = $this->get_bookable_record($this->dateoftravel);
        if ($bk->sameservicereturn == 0) {
            $bookable['sameservicereturn'] = false;
        } else {
            $bookable['sameservicereturn'] = true;
        }
        $bookable['tickets'] = array();
        if ($outtotal > 0) {
            if ($this->can_sell_journeytype('single') && $bookable['sameservicereturn'] == false) {
                $bookable['tickets'][] = 'single';
            }
            if ($intotal > 0 && $this->can_sell_journeytype('return')) {
                $bookable['tickets'][] = 'return';
            }
        }

        return $bookable;
    }

    private function can_sell_journeytype($type) {
        global $wpdb;
        $jt = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_railticket_prices WHERE journeytype = '".$type."' AND ".
            "((stationone = ".$this->fromstation." AND stationtwo = ".$this->tostation.") OR ".
            "(stationone = ".$this->tostation." AND stationtwo = ".$this->fromstation.")) AND disabled = 0");
        if (count($jt)) {
            return true;
        }

        return false;
    }

    public function get_tickets() {
        global $wpdb;
        $tickets = new stdClass();
        $tickets->travellers = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_railticket_travellers", OBJECT );

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
            "WHERE ((stationone = ".$this->fromstation." AND stationtwo = ".$this->tostation.") OR ".
            "(stationone = ".$this->tostation." AND stationtwo = ".$this->fromstation.")) AND ".
            "journeytype = '".$this->journeytype."' AND disabled = 0 ".
            "ORDER BY {$wpdb->prefix}wc_railticket_tickettypes.sequence ASC";
        $ticketdata = $wpdb->get_results($sql, OBJECT);

        $tickets->prices = array();
        foreach($ticketdata as $ticketd) {
            $ticketd->composition = json_decode($ticketd->composition);
            $ticketd->depends = json_decode($ticketd->depends);
            $tickets->prices[$ticketd->tickettype] = $ticketd;
        }

        return $tickets;
    }

    private function get_service_inventory($date, $time, $fromstation, $tostation) {
        global $wpdb;
        $sql = "SELECT {$wpdb->prefix}wc_railticket_bookable.* FROM {$wpdb->prefix}railtimetable_dates ".
            "LEFT JOIN {$wpdb->prefix}wc_railticket_bookable ON ".
            " {$wpdb->prefix}wc_railticket_bookable.dateid = {$wpdb->prefix}railtimetable_dates.id ".
            "WHERE {$wpdb->prefix}railtimetable_dates.date = '".$this->dateoftravel."' AND ".
            "{$wpdb->prefix}wc_railticket_bookable.bookable = 1 AND {$wpdb->prefix}wc_railticket_bookable.soldout = 0";

        $rec = $wpdb->get_results($sql)[0];
        $basebays = (array) json_decode($rec->bays);

        // Get the bookings we need to subtract from this formation. Not using tostation, we'll want that for intermediate stops though.
        $sql = "SELECT {$wpdb->prefix}wc_railticket_booking_bays.* FROM ".
            "{$wpdb->prefix}wc_railticket_bookings ".
            " LEFT JOIN {$wpdb->prefix}wc_railticket_booking_bays ON ".
            " {$wpdb->prefix}wc_railticket_bookings.id = {$wpdb->prefix}wc_railticket_booking_bays.bookingid ".
            " WHERE ".
            "{$wpdb->prefix}wc_railticket_bookings.fromstation = '".$fromstation."' AND ".
            "{$wpdb->prefix}wc_railticket_bookings.date = '".$date."' AND ".
            "{$wpdb->prefix}wc_railticket_bookings.time = '".$time."' ";

        $bookings = $wpdb->get_results($sql);
        foreach ($bookings as $booking) {
            if ($booking->priority) {
                $i = $booking->baysize.'_priority';
            } else {
                $i = $booking->baysize.'_normal';
            }
            $basebays[$i] = $basebays[$i] - $booking->num;
        }

        $totalseats = 0;
        $priorityonly = array();
        foreach ($basebays as $bay => $numleft) {
            $bayd = $this->getBayDetails($bay);
            $totalseats += $bayd[0]*$numleft;
        }

        $bays = new stdclass();
        $bays->bays = $basebays;
        $bays->totalseats = $totalseats;
        return $bays;
    }

    public function get_capacity($outtime = null, $journeytype = null, $fromstation = null, $caponly = false) {
        if ($outtime == null) {
            $outtime = $this->outtime;
        }
        if ($journeytype == null) {
            $journeytype = $this->journeytype;
        }
        if ($fromstation == null) {
            $fromstation = $this->fromstation;
        }

        $allocatedbays = new stdclass();
        $allocatedbays->ok = false;
        $allocatedbays->tobig = false;
        $allocatedbays->error = false;
        $allocatedbays->disablewarn = false;

        $seatsreq = $this->count_seats();

        $outbays = $this->get_service_inventory($this->dateoftravel, $outtime, $fromstation, $this->tostation);
        // Is it worth bothering? If we don't have enough seats left in empty bays for this party give up...
        $allocatedbays->outseatsleft = $outbays->totalseats;
        if ($outbays->totalseats < $seatsreq) {
            return $allocatedbays;
        }
        if ($caponly) {
            return $allocatedbays;
        }

        if ($journeytype == 'return') {
            $retbays = $this->get_service_inventory($this->dateoftravel, $this->rettime, $this->tostation, $fromstation);
            // Is it worth bothering? If we don't have enough seats left in empty bays for this party give up...
            $allocatedbays->retseatsleft = $retbays->totalseats;
            if ($retbays->totalseats < $seatsreq) {
                return $allocatedbays;
            }

            $retallocatesm = $this->getBays($seatsreq, $retbays->bays, false);
            $retallocatelg = $this->getBays($seatsreq, $retbays->bays, true);

            if (!$retallocatesm && !$retallocatelg) {
                $allocatedbays->error = true;
                return $allocatedbays;
            }

            if ($retallocatesm[0] > $retallocatelg[0]) {
                $allocatedbays->retbays = $retallocatelg[1];
                if (!$retallocatelg[2] && $this->disabledrequest) {
                    $allocatedbays->disablewarn = true;
                }
            } else {
                $allocatedbays->retbays = $retallocatesm[1];
                if (!$retallocatesm[2] && $this->disabledrequest) {
                    $allocatedbays->disablewarn = true;
                }
            }
        }

        $outallocatesm = $this->getBays($seatsreq, $outbays->bays, false);
        $outallocatelg = $this->getBays($seatsreq, $outbays->bays, true);

        if (!$outallocatesm && !$outallocatelg) {
            $outallocatedbays->error = true;
            return $outallocatedbays;
        }

        $allocatedbays->ok = true;
        $dis = false;
        if ($outallocatesm[0] > $outallocatelg[0]) {
            $allocatedbays->outbays = $outallocatelg[1];
            if (!$outallocatelg[2] && $this->disabledrequest) {
                $allocatedbays->disablewarn = true;
            }
        } else {
            $allocatedbays->outbays = $outallocatesm[1];
            if (!$outallocatesm[2] && $this->disabledrequest) {
                $allocatedbays->disablewarn = true;
            }
        }

        return $allocatedbays;
    }

    private function getBays($seatsleft, $bays, $largest) {
        $allocatesm = array();
        $smcount = 0;
        $prioritydone = false;
        while ($seatsleft > 0) {
            $baychoice = false;

            if ($this->disabledrequest && $prioritydone === false) {
                $prioritybays = array();
                foreach ($bays as $bay => $numleft) {
                    $bayd = $this->getBayDetails($bay);
                    if ($bayd[1] == 'priority') {
                        $priorityonly[$bay] = $numleft;
                     }
                }

                if ($baychoice === false) {
                    if ($largest) {
                        $baychoice = $this->findLargest($priorityonly, true);
                    } else {
                        $baychoice = $this->findSmallest($priorityonly, true);
                    }
                }
            }

            if ($baychoice === false) {
                $baychoice = $this->findBay($seatsleft, $bays, false);
            }

            if ($baychoice === false) {
                if ($largest) {
                    $baychoice = $this->findLargest($bays, false);
                } else {
                    $baychoice = $this->findSmallest($bays, false);
                }
                // Bail out here, something is wrong....
                if ($baychoice === false) {
                    return false;
                }
            }

            if ($baychoice && $baychoice[1] == 'priority') {
                $prioritydone = true;
            }

            $seatsleft = $seatsleft - $baychoice[0];
            $bays[$baychoice[2]]--;

            if (array_key_exists($baychoice[2], $allocatesm)) {
                $allocatesm[$baychoice[2]] ++;
            } else {
                $allocatesm[$baychoice[2]] = 1;
            }
            $smcount ++;
            // Bail out here, something is wrong....
            if ($smcount > 100) {
                return false;
            }
        }
        return array($smcount, $allocatesm, $prioritydone);
    }

    private function findSmallest($bays, $allowpriority=false) {
        $chosen = false;
        foreach ($bays as $bay => $numleft) {
            if ($numleft > 0) {
                $bayd = $this->getBayDetails($bay);
                if ($allowpriority === false && $bayd[1] == 'priority') {
                    continue;
                }
                if ($chosen === false || $bayd[0] < $chosen[0]) {
                    $chosen = $bayd;
                }
            }
        }
        if ($chosen === false && $allowpriority === false) {
            return $this->findSmallest($bays, true);
        }

        return $chosen;
    }

    private function findLargest($bays, $allowpriority=false) {
        $chosen = false;
        foreach ($bays as $bay => $numleft) {
            if ($numleft > 0) {
                $bayd = $this->getBayDetails($bay);
                if ($allowpriority === false && $bayd[1] == 'priority') {
                    continue;
                }
                if ($chosen === false || $bayd[0] > $chosen[0]) {
                    $chosen = $bayd;
                }
            }
        }

        if ($chosen === false && $allowpriority === false) {
            return $this->findLargest($bays, true);
        }

        return $chosen;
    }

    private function findBay($seatsreq, $bays, $allowpriority=false) {
        foreach ($bays as $bay => $numleft) {
            $bayd = $this->getBayDetails($bay);
            if ($allowpriority === false && $bayd[1] == 'priority') {
                continue;
            }
            if ($seatsreq <= $bayd[0] && $numleft > 0) {
                return $bayd;
            }
        }

        if ($allowpriority === false) {
            return $this->findBay($seatsreq, $bays, true);
        }

        return false;
    }

    private function getBayDetails($bay) {
        $parts = explode('_', $bay);
        $parts[0] = intval($parts[0]);
        $parts[2] = $bay;
        return $parts;
    }

    private function checkDuplicate() {
        global $woocommerce, $wpdb;
        $items = $woocommerce->cart->get_cart();
        $ticketid = get_option('wc_product_railticket_woocommerce_product');
        $items = $woocommerce->cart->get_cart();
        foreach($items as $item => $values) { 
            if ($ticketid == $values['data']->get_id()) {
                return true;
            }
        } 
        return false;
    }

    public function do_purchase() {
        global $woocommerce, $wpdb;

        // Compensate for WordPresse's inconsistent timezone handling
        //$tz = date_default_timezone_get();
        //date_default_timezone_set($this->railticket_timezone->getName());

        $purchase = new stdclass();
        $purchase->ok = false;
        $purchase->duplicate = false;
        if ($this->checkDuplicate()) {
            $purchase->duplicate = true;
            return $purchase;
        }

        // Check we still have capacity
        $allocatedbays = $this->get_capacity();

        if (!$allocatedbays->ok && $this->overridevalid == 0) {
            return $purchase;
        }

        if ($this->overridevalid == 1 && !$allocatedbays->ok ) {
            $outbays = "As directed by the guard";
            if ($this->journeytype == 'return') {
                $retbays = "As directed by the guard";
            } else {
                $retbays = "n/a";
            }
        } else {
            $outbays = $this->baystring($allocatedbays->outbays);
            if ($this->journeytype == 'return') {
                $retbays = $this->baystring($allocatedbays->retbays);
            } else {
                $retbays = "n/a";
            }
        }

        $custom_price = 0;
        foreach ($this->ticketsallocated as $ttype => $qty) {
            $price = $wpdb->get_var("SELECT price FROM {$wpdb->prefix}wc_railticket_prices WHERE tickettype = '".$ttype."' AND ".
                "journeytype = '".$this->journeytype."' ");
            $custom_price += floatval($price)*floatval($qty);
        }

        $mprice = get_option('wc_product_railticket_min_price');
        $supplement = 0;
        if (strlen($mprice) > 0 && $custom_price < $mprice) {
            $mprice=floatval($mprice);
            $supplement = floatval($mprice) - floatval($custom_price);
            $custom_price = $mprice;
        } 
        $totalseats = $this->count_seats();
        $data = array(
            'fromstation' => $this->fromstation,
            'tostation' => $this->tostation,
            'outtime' => $this->outtime,
            'outbays' => $outbays,
            'rettime' => $this->rettime,
            'retbays' => $retbays,
            'dateoftravel' => $this->dateoftravel,
            'journeytype' => $this->journeytype,
            'totalseats' => $totalseats,
            'pricesupplement' => $supplement
        );

        $cart_item_data = array('custom_price' => $custom_price, 'ticketselections' => $this->ticketselections,
            'ticketsallocated' => $this->ticketsallocated, 'tickettimes' => $data);

        $bridge_product = get_option('wc_product_railticket_woocommerce_product');
        $itemkey = $woocommerce->cart->add_to_cart($bridge_product, 1, 0, array(), $cart_item_data);
        $woocommerce->cart->calculate_totals();
        $woocommerce->cart->set_session();
        $woocommerce->cart->maybe_set_cart_cookies();

        $this->insertBooking($itemkey, $this->outtime, $this->fromstation, $this->tostation, $totalseats, $allocatedbays);
        if ($this->journeytype == 'return') {
            $this->insertBooking($itemkey, $this->rettime, $this->tostation, $this->fromstation, $totalseats, $allocatedbays);
        }
        $purchase->ok = true;

        //date_default_timezone_set($tz);
        return $purchase;
    }

/*
    private function setSoldOut($dateoftravel, $fromstation, $tostation) {
        $timetable = $wpdb->get_results("SELECT {$wpdb->prefix}railtimetable_timetables.* FROM {$wpdb->prefix}railtimetable_dates ".
            "LEFT JOIN {$wpdb->prefix}railtimetable_timetables ON ".
            " {$wpdb->prefix}railtimetable_dates.timetableid = {$wpdb->prefix}railtimetable_timetables.id ".
            "LEFT JOIN {$wpdb->prefix}wc_railticket_bookable ON ".
            " {$wpdb->prefix}wc_railticket_bookable.dateid = {$wpdb->prefix}railtimetable_dates.id ".
            "WHERE {$wpdb->prefix}railtimetable_dates.date = '".$this->dateoftravel."'", OBJECT );

        $outbays = $this->get_service_inventory($dateoftravel, $outtime, $fromstation, $tostation);
        if ($outbays->totalseats == 0) {

        }
    }
*/
    private function insertBooking($itemkey, $time, $fromstation, $tostation, $totalseats, $allocatedbays) {
        global $wpdb;
        // TODO originstation and origintime reflect the station this train originally started from. Right now
        // with end to end bookings only this will always be the same as fromstation and time. Needs to be set properly
        // when intermediate stops are added. The aim is to allow the entire inventory for this service to be retrieved.

        $fs = $wpdb->get_var("SELECT sequence FROM {$wpdb->prefix}railtimetable_stations WHERE id = ".$fromstation);
        $ts = $wpdb->get_var("SELECT sequence FROM {$wpdb->prefix}railtimetable_stations WHERE id = ".$tostation);
        if ($fs > $ts) {
            $direction = 'up';
        } else {
            $direction = 'down';
        }

        $dbdata = array(
            'woocartitem' => $itemkey,
            'date' => $this->dateoftravel,
            'time' => $time,
            'fromstation' => $fromstation,
            'tostation' => $tostation,
            'direction' => $direction,
            'seats' => $totalseats,
            'usebays' => 1,
            'originstation' => $fromstation,
            'origintime' => $time,
            'created' => time(),
            'expiring' => 0
        );
        $wpdb->insert("{$wpdb->prefix}wc_railticket_bookings", $dbdata);
        $id = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}wc_railticket_bookings WHERE woocartitem = '".$itemkey."' AND ".
            " fromstation = '".$fromstation."' AND tostation = '".$tostation."'");
        foreach ($allocatedbays->outbays as $bay => $num) {
            $bayd = $this->getBayDetails($bay);
            if ($bayd[1] == 'priority') {
                $pr = true;
            } else {
                $pr = false;
            }
            $bdata = array(
                'bookingid' => $id,
                'num' => $num,
                'baysize' => $bayd[0],
                'priority' => $pr
            );
            $wpdb->insert("{$wpdb->prefix}wc_railticket_booking_bays", $bdata);
        }
    }

    private function bayString($bays) {
        $str = '';
        foreach ($bays as $bay => $num) {
            $bayd = $this->getBayDetails($bay);
            $str .= $num.'x '.$bayd[0].' seat bay';
            if ($bayd[1] == 'priority') {
                $str .= ' (with disabled space)';
            }
            $str .=', ';
        }
        return substr($str, 0, strlen($str)-2);
    }

    private function count_seats() {
        global $wpdb;
        $total = 0;
        $tkts = (array) $this->ticketselections;

        foreach ($tkts as $ttype => $number) {
            $val = $wpdb->get_var("SELECT seats FROM {$wpdb->prefix}wc_railticket_travellers WHERE code='".$ttype."'") * $number;
            $total += $val;
        }
        return $total;
    }

    /**
    * Gets the outbound direction
    **/

    private function get_direction() {
        global $wpdb;

        $from = $this->getStationData($this->fromstation);
        $to = $this->getStationData($this->tostation);

        if ($from->sequence > $to->sequence) {
            return "up";
        } else {
            return "down";
        }
    }

    private function getStationData($stationid) {
        global $wpdb;
        $stn = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_stations WHERE id = ".$stationid);
        return ($stn[0]) ? : false;
    }

    private function findTimetable() {
        global $wpdb;
        $timetable = $wpdb->get_results("SELECT {$wpdb->prefix}railtimetable_timetables.* FROM {$wpdb->prefix}railtimetable_dates ".
            "LEFT JOIN {$wpdb->prefix}railtimetable_timetables ON ".
            " {$wpdb->prefix}railtimetable_dates.timetableid = {$wpdb->prefix}railtimetable_timetables.id ".
            "LEFT JOIN {$wpdb->prefix}wc_railticket_bookable ON ".
            " {$wpdb->prefix}wc_railticket_bookable.dateid = {$wpdb->prefix}railtimetable_dates.id ".
            "WHERE {$wpdb->prefix}railtimetable_dates.date = '".$this->dateoftravel."'", OBJECT );

        return ($timetable[0]) ? : false;
    }

    private function get_javascript() {

        wp_register_script('railticket_script', plugins_url('wc-product-railticket/ticketbuilder.js'));
        wp_enqueue_script('railticket_script');
        wp_register_style('railticket_style', plugins_url('wc-product-railticket/ticketbuilder.css'));
        wp_enqueue_style('railticket_style');

        $minprice = 'false';
        $opt = get_option('wc_product_railticket_min_price');
        if (strlen($opt) > 0) {
            $minprice = $opt;
        }

        $sameservicereturn = 'false';
        if (get_option('wc_product_railticket_sameservicereturn')) {
            $sameservicereturn = 'true';
        }

        return "\n<script type='text/javascript'>\n".
            "var ajaxurl = '".admin_url( 'admin-ajax.php', 'relative' )."';\n".
            "var today = '".$this->today->format('Y-m-d')."'\n".
            "var tomorrow = '".$this->tomorrow->format('Y-m-d')."'\n".
            "var minprice = ".$minprice."\n".
            "var dateFormat = '".get_option('railtimetable_date_format')."';".
            "</script>";
    }

    private function get_datepick() {
        global $wpdb;
        $calendar = new TicketCalendar();

        $startyear = $this->today->format('Y');
        $startmonth = $this->today->format('n');

        $endyear = date("Y", strtotime("+1 month"));
        $endmonth = date("n", strtotime("+2 month"));

        if ($endmonth < $startmonth) {
            $stop = 13;
        } else {
            $stop = $endmonth++;
        }
        $cal="";

        for ($year=$startyear; $year<$endyear+1; $year++) {
            for ($month=$startmonth; $month<$stop; $month++) {
                $cal .= "<div class='calendar-box-wrapper' id='railtimetable-cal-".$year."-".$month."'>".$calendar->draw(date($year."-".$month."-01"))."</div>";
             }
             $startmonth = 1;
             $stop = $endmonth+1;
        }

        $scroll = $this->today->format("Y-n");

        $cal .= '<script type="text/javascript">var baseurl = "'.railtimetable_currentlang()."/".get_site_url().'";var closetext="'.__("Close", "railtimetable").'";var scrollto="'.$scroll.'"; initTrainTimes();</script>';

        $str = "<div id='datechoosetitle' class='railticket_stageblock' style='display:block;'><h3>Choose Date of Travel</h3></div>".
            "<div id='railtimetable-cal' class='calendar-wrapper'>.$cal.</div>";

        $str .= "<div id='datechooser' class='railticket_stageblock'><div class='railticket_container'>";
        if ($this->today->format('H') < 19 && $this->is_date_bookable($this->today)) { 
             $str .= "<input type='button' value='Travel Today' id='todaybutton' title='Click to travel today' class='railticket_datebuttons' />&nbsp;";
        }

        if ($this->is_date_bookable($this->tomorrow)) {
            $str .= "<input type='button' value='Travel Tomorrow' id='tomorrowbutton' title='Click to travel tomorrow' ".
                "class='railticket_datebuttons' />";
        }
        $str .= "</form></div>".
            "<div id='datechosen' class='railticket_container'>Tap or click a date to choose</div>".
            "<input type='hidden' id='dateoftravel' name='dateoftravel' value='' />".
            "  <div id='overridecodediv' class='railticket_overridecode railticket_container'>".
            "  <label for='override'>Override code</label>&nbsp;&nbsp;<input id='overrideval' type='text' size='6' name='override' />".
            "  <input type='button' value='Validate' id='validateOverrideIn' /> ".
            "  <div id='overridevalid'>".
            "  <p class='railticket_overridedesc'>The override code can be used to unlock services not available for booking below, ".
            "  eg if a train is running late. The code, if needed can be obtained from the guard once the train has arrived.</p>".
            "  </div></div></div>";

        return $str;
    }

    private function get_stations() {
        $str = "<div id='stations' class='railticket_stageblock railticket_listselect'>".
            "<h3>Choose Stations</h3>".
            "<p class='railticket_help'>Tap or click the stations to select</p>".
            "<div class='railticket_container'>".
            "<div class='railticket_listselect_left'><div class='inner'><h3>From</h3>".$this->station_radio("fromstation", true)."</div></div>".
            "<div class='railticket_listselect_right'><div class='inner'><h3>To</h3>".$this->station_radio("tostation", false)."</div></div>".
            "</div></div>";

        return $str;
    }

    private function station_radio($name, $from) {
        $str="<ul>";
        foreach ($this->stations as $station) {
            $str .= "<li><input type='radio' name='".$name."' id='".$name.$station->id."' value='".$station->id.
                "' class='railticket_".$name." railticket_notbookable' disabled />\n".
                "<label for='".$name.$station->id."'>".$station->name."</label></li>";
        }
        $str.="</ul>";
        return $str;
    }

    private function get_deptimes() {
        $str =
            "<div id='deptimes' class='railticket_stageblock railticket_listselect'><div id='deptimes_data' class='railticket_container'>".
            "  <h3>Choose a Departure</h3>".
            "  <p class='railticket_help'>Tap or click the times and ticket type to select. ".
            "Times shown with a paler background and a strikethrough should have departed if trains are running on time, but can still be ".
            " booked if you are certain the train is simply running late. Services which are greyed out cannot be booked on line.</p></div>";

        $str .= "  <div id='deptimes_data' class='railticket_container'>".
            "    <div id='deptimes_data_out' class='railticket_listselect_left'>".
            "    <input type='hidden' name='outtime' value='' /></div>".
            "    <div id='deptimes_data_ret' class='railticket_listselect_right'>".
            "    <input type='hidden' name='rettime' value='' /></div>".
            "  </div>".
            "  <div id='ticket_type' class='railticket_container'><input type='hidden' name='journeytype' value='' /></div>".
            "</div>";

        return $str;
    }

    private function get_ticket_choices() {
        $str = "<div id='tickets' class='railticket_stageblock'>".
            "<h3>Choose Tickets</h3>".
            "<p class='railticket_help'>Use the boxes on the left to enter the number of tickets required</p>".
            "  <div id='ticket_travellers' class='railticket_container'>".
            "  </div>".
            "  <div id='ticket_summary' class='railticket_container'></div>".
            "  <div id='ticket_capbutton' class='railticket_container'>".
            "  <p class='railticket_terms'><input type='checkbox' name='disabledrequest' id='disabledrequest'/>&nbsp;&nbsp;&nbsp;Request space for wheelchair user</p>".
            "  <input type='button' value='Confirm Choices' id='confirmchoices' /></div>".
            "  <div id='ticket_capacity' class='railticket_container'></div>".
            "</div>";

        return $str;
    }

    private function get_addtocart() {
        $str = "<div id='addtocart' class='railticket_stageblock'>".
            "<input type='hidden' name='ticketselections' />".
            "<input type='hidden' name='ticketallocations' />".
            "<div class='railticket_container'>".
            "<p class='railticket_terms'><input type='checkbox' name='terms' id='termsinput'/>&nbsp;&nbsp;&nbsp;I agree to the ticket sales terms and conditions.</p>".
            "<p><a href='".get_option('wc_product_railticket_termspage')."' target='_blank'>Click here to view terms and conditions in a new tab.</a></p>".
            "<p class='railticket_terms'>Your tickets will be reserved for ".get_option('wc_product_railticket_reservetime')." minutes after you click add to cart.".
            " Please complete your purchases within that time.</p>".
            "<p><input type='button' value='Add To Cart' id='addticketstocart' /></p></div>".
            "</div>";

        return $str;
    }

} 
