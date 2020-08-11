<?php

class TicketBuilder {

    private $today, $tomorrow, $stations;

    public function __construct($dateoftravel, $fromstation, $tostation, $outtime, $rettime,
        $journeytype, $ticketselections, $ticketsallocated, $overridevalid, $disabledrequest, $notes, $nominimum, $show) {
        global $wpdb;
        $this->show = $show;
        $this->railticket_timezone = new DateTimeZone(get_option('timezone_string'));
        $this->now = new DateTime();
        $this->now->setTimezone($this->railticket_timezone);
        $this->today = new DateTime();
        $this->today->setTimezone($this->railticket_timezone);
        $this->today->setTime(0,0,0);
        $this->tomorrow = new DateTime();
        $this->tomorrow->setTimezone($this->railticket_timezone);
        $this->tomorrow->modify('+1 day');
        $this->stations = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_stations ORDER BY sequence ASC");
        $this->dateoftravel = $dateoftravel;
        $this->fromstation = $fromstation;
        $this->tostation = $tostation;
        $this->journeytype = $journeytype;
        if (strpos($outtime, 's:') === false) {
            $this->special = false;
            $this->outtime = $outtime;
            $this->rettime = $rettime;
        } else {
            $parts = explode(":", $outtime);
            $this->special = true;
            $this->outtime = $parts[1];
            $this->rettime = $parts[1];
            $this->journeytype = 'return';
        }
        $this->ticketselections = $ticketselections;
        $this->ticketsallocated = $ticketsallocated;
        $this->notes = $notes;
        $this->nominimum = $nominimum;

        if ($this->is_guard()) {
            $this->overridevalid = true;
        } else {
            $this->overridevalid = $overridevalid;
        }
        if ($disabledrequest == 'true') {
            $this->disabledrequest = true;
        } else {
            $this->disabledrequest = false;
        }
    }

    private function is_guard() {
        if( is_user_logged_in() ) {
            if (current_user_can('manage_tickets')) {
                return true;
            }
        }

        return false;
    }

    public function render() {

        $ua = htmlentities($_SERVER['HTTP_USER_AGENT'], ENT_QUOTES, 'UTF-8');
        if (preg_match('~MSIE|Internet Explorer~i', $ua) || (strpos($ua, 'Trident/7.0; rv:11.0') !== false)) {
            return "<p>Sorry, the ticket booking system isn't supported on Internet Explorer. If you are using Windows 10, then please swtich to ".
                "the Microsoft Edge browser which has replaced Internet Explorer to continue your purchase. Users of older Windows versions ".
                "will need to use Chrome or Firefox.</p>";
        }

        if ($this->checkDuplicate()) {
            return '<p>Sorry, but you already have a ticket selection in your shopping cart, you can only have one ticket selection per order. Please remove the existing ticket selection if you wish to create a new one, or complete the purchase for the existing one.</p>';
        }

        wp_register_style('railticket_style', plugins_url('wc-product-railticket/ticketbuilder.css'));
        wp_enqueue_style('railticket_style');

        if ($this->show || $this->is_date_bookable($this->today) == false) {
            return $this->get_all_html();
        }

        global $wpdb;
        $fstation = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_stations ORDER BY sequence ASC LIMIT 1", OBJECT)[0];
        $lstation = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_stations ORDER BY sequence DESC LIMIT 1", OBJECT)[0];

        $fdeptime = $this->next_train_today_from($fstation->id);
        $ldeptime = $this->next_train_today_from($lstation->id);

        if ($fdeptime === false && $ldeptime === false) {
            return $this->get_all_html();
        }

        $str = "<div class='railticket_selector'>".
            "<div class='railticket_container'>".
            "<h4>Would you like to buy tickets for:</h4>";

        $str .= "<form action='/book/' method='post'>".
            "<input type='submit' value='Tickets for any date/train' />".
            "<input type='hidden' name='show' value='1' />".
            "</form><br />";

        $str .= $this->get_preset_form($fstation, $lstation, $fdeptime, "down");
        $str .= $this->get_preset_form($lstation, $fstation, $ldeptime, "up");

        $str .= "</div></div>";

        return $str;
    }

    private function get_all_html() {
        return $this->get_javascript().
           '<div id="pleasewait" class="railticket_loading"></div>'.
           $this->get_datepick().
           '<form action="post" name="railticketbooking">'.
           $this->get_stations().
           $this->get_deptimes().
           $this->get_ticket_choices().
           $this->get_addtocart().'</form>'.
           '<div id="railticket_error" class="railticket_stageblock" ></div>';
    }

    private function get_preset_form($fstation, $tstation, $deptime, $direction) {
        $str = "<form action='/book/' method='post'>".
            "<input type='submit' value='Return tickets for the next train from ".$fstation->name."' />".
            "<input type='hidden' name='a_dateofjourney' value='".$this->today->format('Y-m-d')."' />".
            "<input type='hidden' name='a_deptime' value='".$deptime."' />".
            "<input type='hidden' name='a_station' value='".$fstation->id."' />".
            "<input type='hidden' name='a_destination' value='".$tstation->id."' />".
            "<input type='hidden' name='a_direction' value='".$direction."' />".
            "<input type='hidden' name='show' value='1' />".
            "</form><br />";

        return $str;
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

    public function is_train_bookable($time, $fromstn, $tostn) {
        // Dates which are in the past are not allowed
        $parts = explode('.', $time);
        $deptime = DateTime::createFromFormat("Y-m-d", $this->dateoftravel, $this->railticket_timezone);
        $deptime->setTime($parts[0], $parts[1], 0);
        $deptime->modify('+'.get_option('wc_product_railticket_bookinggrace').' minutes');

        $r = new stdclass();
        $r->bookable = false;
        $r->full = false;
        $r->seats = 0;

        if (!$this->is_guard() && $this->now > $deptime) {
            return $r;
        }

        $cap = $this->get_capacity($time, 'single', $fromstn, $tostn);

        if ($cap->outseatsleft > 0) {
            $r->bookable = true;
            $r->seats = $cap->outseatsleft;
            return $r;
        }

        $r->full = true;
        return $r;
    }

    private function is_today_bookable() {
    
    }

    private function next_train_today_from($from) {
        global $wpdb;
        $timetable = $this->findTimetable($this->today);
        if (!$timetable) {
            return false;
        }
        $deptimesdata = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_times WHERE station = '".$from."' ".
            " AND timetableid = ".$timetable->id)[0];

        if (strlen($deptimesdata->down_deps) > 0) {
            $deps = $deptimesdata->down_deps;
        } else {
            if (strlen($deptimesdata->up_deps) == 0) {
                return false;
            }
            $deps = $deptimesdata->up_deps;
        }
        $nowtime = ($this->now->format('G')*60) + $this->now->format('i');

        $alldeps = explode(",", $deps);
        foreach ($alldeps as $dep) {
            $t = explode(".", $dep);
            $time = (intval($t[0])*60) + intval($t[1]) + intval(get_option("wc_product_railticket_bookinggrace"));
            if ($time > $nowtime) {
                return $dep;
            }
        }

        return false;
    }

    private function last_train_today() {
        global $wpdb;
        $timetable = $this->findTimetable($this->today);
        if (!$timetable) {
            return false;
        }
        $deptime = $wpdb->get_var("SELECT lastdep FROM {$wpdb->prefix}railtimetable_times WHERE ".
            "timetableid = ".$timetable->id." ORDER BY lastdep DESC LIMIT 1");
        return $deptime;
    }

    private function get_next_bookable($date, $num) {
        global $wpdb;
        if ($date instanceof DateTime) {
            $date = $date->format('Y-m-d');
        }

        $sql = "SELECT {$wpdb->prefix}railtimetable_dates.* FROM {$wpdb->prefix}railtimetable_dates ".
            "LEFT JOIN {$wpdb->prefix}wc_railticket_bookable ON ".
            " {$wpdb->prefix}wc_railticket_bookable.dateid = {$wpdb->prefix}railtimetable_dates.id ".
            "WHERE {$wpdb->prefix}railtimetable_dates.date >= '".$date."' AND ".
            "{$wpdb->prefix}wc_railticket_bookable.bookable = 1 AND {$wpdb->prefix}wc_railticket_bookable.soldout = 0 ORDER BY date ASC LIMIT ".$num;

        $rec = $wpdb->get_results($sql);
        if (count($rec) > 0) {
            return $rec;
        } else {
            return false;
        }
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
        global $wpdb;
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

        // Are their any specials today?

        $specials = $wpdb->get_results("SELECT id, name, description, fromstation, tostation FROM {$wpdb->prefix}wc_railticket_specials".
            " WHERE date = '".$this->dateoftravel."' AND onsale = 1");
        if ($specials && count($specials) > 0) {
            $bookable['specials'] = $specials;
        } else {
            $bookable['specials'] = false;
        }
        $bookable['specialonly'] = $bkrec->specialonly;

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
        $firstarr = false;
        $outtotal = 0;
        foreach ($deptimes as $index => $dep) {
            $canbook = $this->is_train_bookable($dep, $this->fromstation,  $this->tostation);
            if ($this->overridevalid == 1) {
                $canbook->bookable = true;
            }
            $bookable['out'][] = array('dep' => $dep, 'depdisp' => strftime($fmt, strtotime($dep)),
                'arr' => $deparrs[$index],  'arrdisp' => strftime($fmt, strtotime($deparrs[$index])),
                'bookable' => $canbook->bookable, 'full' => $canbook->full, 'seats' => $canbook->seats);
            if ($firstarr == false) {
                $firstarr = strtotime($deparrs[$index]);
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
        $intotal = 0;
        foreach ($rettimes as $index => $ret) {
            if (strtotime($ret) < $firstarr) {
                // If the first return trip is before the first arrival, skip it.
                continue;
            }
            $canbook = $this->is_train_bookable($ret, $this->tostation, $this->fromstation);
            if ($this->overridevalid == 1) {
                $canbook->bookable = true;
            } 
            $bookable['ret'][] = array('dep' => $ret, 'depdisp' => strftime($fmt, strtotime($ret)),
                'arr' => $retarrs[$index], 'arrdisp' => strftime($fmt, strtotime($retarrs[$index])), 
                'bookable' => $canbook->bookable, 'full' => $canbook->full, 'seats' => $canbook->seats);
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

        if (!$this->is_guard()) {
            $guardtra = " WHERE {$wpdb->prefix}wc_railticket_travellers.guardonly = 0 ";
            $guard = " AND {$wpdb->prefix}wc_railticket_tickettypes.guardonly = 0 ";
        } else {
            $guardtra = "";
            $guard = "";
        }

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
            $specialval = " AND special = 0 ";
        }

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
            "journeytype = '".$this->journeytype."' AND disabled = 0 ".$specialval." ".$guard.
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

                        if (!$this->is_guard()) {
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

    public function get_service_inventory($time, $fromstation, $tostation, $baseonly = false, $noreserve = false, $onlycollected = false) {
        global $wpdb;

        if ($this->special && strpos($time, ':') === false) {
            $time = "s:".$time;
        }

        $sql = "SELECT {$wpdb->prefix}wc_railticket_bookable.* FROM {$wpdb->prefix}railtimetable_dates ".
            "LEFT JOIN {$wpdb->prefix}wc_railticket_bookable ON ".
            " {$wpdb->prefix}wc_railticket_bookable.dateid = {$wpdb->prefix}railtimetable_dates.id ".
            "WHERE {$wpdb->prefix}railtimetable_dates.date = '".$this->dateoftravel."' AND ".
            "{$wpdb->prefix}wc_railticket_bookable.bookable = 1 AND {$wpdb->prefix}wc_railticket_bookable.soldout = 0";

        $rec = $wpdb->get_results($sql)[0];

        // NOTE: Need to get origin station dep time here when intermediate stops are enabled!
        switch ($rec->daytype) {
            case 'simple':
                $basebays = (array) json_decode($rec->bays);
                break;
            case 'pertrain':
                $direction = $this->get_direction($fromstation, $tostation);
                $formations = json_decode($rec->bays);
                $set = $formations->$direction->$time;
                $basebays = (array) $formations->coachsets->$set;
                break;
        }

        if ($baseonly) {
            return $basebays;
        }

        // Get the bookings we need to subtract from this formation. Not using tostation, we'll want that for intermediate stops though.
        $sql = "SELECT {$wpdb->prefix}wc_railticket_booking_bays.* FROM ".
            "{$wpdb->prefix}wc_railticket_bookings ".
            " LEFT JOIN {$wpdb->prefix}wc_railticket_booking_bays ON ".
            " {$wpdb->prefix}wc_railticket_bookings.id = {$wpdb->prefix}wc_railticket_booking_bays.bookingid ".
            " WHERE ".
            "{$wpdb->prefix}wc_railticket_bookings.fromstation = '".$fromstation."' AND ".
            "{$wpdb->prefix}wc_railticket_bookings.date = '".$this->dateoftravel."' AND ".
            "{$wpdb->prefix}wc_railticket_bookings.time = '".$time."' ";

        if ($onlycollected) {
            $sql .= " AND {$wpdb->prefix}wc_railticket_bookings.collected = '1' ";
        }

        $bookings = $wpdb->get_results($sql);

        foreach ($bookings as $booking) {
            if ($booking->priority) {
                $i = $booking->baysize.'_priority';
            } else {
                $i = $booking->baysize.'_normal';
            }
            if (array_key_exists($i, $basebays)) {
                $basebays[$i] = $basebays[$i] - $booking->num;
            }
        }

        // Take out the booking reserve
        if ($noreserve == false && $rec->sellreserve == 0 && strlen($rec->reserve) > 0) {

            // NOTE: Need to get origin station dep time here when intermediate stops are enabled!
            switch ($rec->daytype) {
                case 'simple':
                    $reserve = (array) json_decode($rec->reserve);
                    break;
                case 'pertrain':
                    $ropts = json_decode($rec->reserve);
                    $reserve = $ropts->$set;
                    break;
            }

            foreach ($reserve as $i => $num) {
                if (array_key_exists($i, $basebays)) {
                    $basebays[$i] = $basebays[$i] - $num;
                }
            }
        }

        $totalseats = 0;
        foreach ($basebays as $bay => $numleft) {
            $bayd = $this->getBayDetails($bay);
            $totalseats += $bayd[0]*$numleft;
        }

        $bays = new stdclass();
        $bays->bays = $basebays;
        $bays->totalseats = $totalseats;
        return $bays;
    }

    public function get_capacity($outtime = null, $journeytype = null, $fromstation = null, $tostation = null, $caponly = false) {
        if ($outtime == null) {
            $outtime = $this->outtime;
        }
        if ($journeytype == null) {
            $journeytype = $this->journeytype;
        }
        if ($fromstation == null) {
            $fromstation = $this->fromstation;
        }
        if ($tostation == null) {
            $tostation = $this->tostation;
        }

        $allocatedbays = new stdclass();
        $allocatedbays->ok = false;
        $allocatedbays->tobig = false;
        $allocatedbays->error = false;
        $allocatedbays->disablewarn = false;

        $seatsreq = $this->count_seats();

        $outbays = $this->get_service_inventory($outtime, $fromstation, $tostation, false, $this->is_guard());
        $allocatedbays->outseatsleft = $outbays->totalseats;

        if ($caponly) {
            return $allocatedbays;
        }

        if ($journeytype == 'return') {
            $retbays = $this->get_service_inventory($this->rettime, $tostation, $fromstation, false, $this->is_guard());
            // Is it worth bothering? If we don't have enough seats left in empty bays for this party give up...
            $allocatedbays->retseatsleft = $retbays->totalseats;
            if ($retbays->totalseats < $seatsreq) {
                $allocatedbays->retbays = array();
            } else {

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
        }

        // Is it worth bothering? If we don't have enough seats left in empty bays for this party give up...
        if ($outbays->totalseats < $seatsreq) {
            $allocatedbays->outbays = array();
            return $allocatedbays;
        }

        $outallocatesm = $this->getBays($seatsreq, $outbays->bays, false);
        $outallocatelg = $this->getBays($seatsreq, $outbays->bays, true);

        if (!$outallocatesm && !$outallocatelg) {
            $outallocatedbays->error = true;
            return $outallocatedbays;
        }

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

        $allocatedbays->match = true;
        if ($journeytype == 'single') {
            $allocatedbays->ok = true;
            return $allocatedbays;
        }

        if (count($allocatedbays->retbays) > 0) {
            $allocatedbays->ok = true;
            ksort($allocatedbays->outbays);
            ksort($allocatedbays->retbays);

            foreach ($allocatedbays->outbays as $bay => $num) {
                if (!array_key_exists($bay, $allocatedbays->retbays)) {
                    $allocatedbays->match = false;
                    break;
                }
                if ($allocatedbays->retbays[$bay] != $num) {
                    $allocatedbays->match = false;
                    break;
                }
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
            if (count($allocatedbays->outbays) > 0) {
                $outbays = $this->baystring($allocatedbays->outbays);
            } else {
                $outbays = "As directed by the guard";
            }
            if ($this->journeytype == 'return') {
                if (count($allocatedbays->retbays) > 0) {
                    $retbays = $this->baystring($allocatedbays->retbays);
                } else {
                    $retbays = "As directed by the guard";
                }
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
            if ($custom_price == 0 && $this->is_guard()) {
                // We will want to record the price for "other" here at some point, but that's not happening now
            } else {
                if ($this->is_guard() && $this->nominimum) {
                    $supplement = 0;
                } else {
                    $mprice=floatval($mprice);
                    $supplement = floatval($mprice) - floatval($custom_price);
                    $custom_price = $mprice;
                }
            }
        } 
        $totalseats = $this->count_seats();

        if ($this->special) {
            $outtime = "s:".$this->outtime;
            $rettime = "s:".$this->rettime;
        } else {
            $outtime = $this->outtime;
            $rettime = $this->rettime;
        }

        if ($this->is_guard()) {
            $data = array(
                'journeytype' => $this->journeytype,
                'price' => $custom_price,
                'supplement' => $supplement,
                'seats' => $totalseats,
                'travellers' => json_encode($this->ticketselections),
                'tickets' => json_encode($this->ticketsallocated),
                'notes' => $this->notes
            );
            $wpdb->insert("{$wpdb->prefix}wc_railticket_manualbook", $data);
            $mid = $wpdb->insert_id;
            $this->insertBooking("", $outtime, $this->fromstation, $this->tostation, $totalseats, $allocatedbays->outbays, $mid);
            if ($this->journeytype == 'return') {
                $this->insertBooking("", $rettime, $this->tostation, $this->fromstation, $totalseats, $allocatedbays->retbays, $mid);
            }
            $purchase->id = 'M'.$mid;
        } else {
            $data = array(
                'fromstation' => $this->fromstation,
                'tostation' => $this->tostation,
                'outtime' => $outtime,
                'outbays' => $outbays,
                'rettime' => $rettime,
                'retbays' => $retbays,
                'dateoftravel' => $this->dateoftravel,
                'journeytype' => $this->journeytype,
                'totalseats' => $totalseats,
                'pricesupplement' => $supplement,
                'unique' => uniqid()
            );
            $cart_item_data = array('custom_price' => $custom_price, 'ticketselections' => $this->ticketselections,
                'ticketsallocated' => $this->ticketsallocated, 'tickettimes' => $data);

            $bridge_product = get_option('wc_product_railticket_woocommerce_product');
            //$itemkey = $woocommerce->cart->add_to_cart($bridge_product, 1, 0, array(), $cart_item_data);
            $itemkey = $woocommerce->cart->add_to_cart($bridge_product, 1, 0, array(), $cart_item_data);
            $woocommerce->cart->calculate_totals();
            $woocommerce->cart->set_session();
            $woocommerce->cart->maybe_set_cart_cookies();

            $this->insertBooking($itemkey, $outtime, $this->fromstation, $this->tostation, $totalseats, $allocatedbays->outbays, 0);
            if ($this->journeytype == 'return') {
                $this->insertBooking($itemkey, $rettime, $this->tostation, $this->fromstation, $totalseats, $allocatedbays->retbays, 0);
            }
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
    private function insertBooking($itemkey, $time, $fromstation, $tostation, $totalseats, $allocatedbays, $manual) {
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
            'expiring' => 0,
            'manual' => $manual
        );
        if ($manual) {
            $dbdata['collected'] = true;
        }

        $wpdb->insert("{$wpdb->prefix}wc_railticket_bookings", $dbdata);

        $id = $wpdb->insert_id;
        foreach ($allocatedbays as $bay => $num) {
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

    private function get_direction($f = false, $t = false) {
        if ($f === false) {
            $f = $this->fromstation;
        }
        if ($t === false) {
            $t = $this->tostation;
        } 

        $from = $this->getStationData($f);
        $to = $this->getStationData($t);

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

    private function findTimetable($date = null) {
        global $wpdb;

        if ($date == null) {
            $date = $this->dateoftravel;
        }

        if ($date instanceof DateTime) {
            $date = $date->format('Y-m-d');
        }

        $timetable = $wpdb->get_results("SELECT {$wpdb->prefix}railtimetable_timetables.* FROM {$wpdb->prefix}railtimetable_dates ".
            "LEFT JOIN {$wpdb->prefix}railtimetable_timetables ON ".
            " {$wpdb->prefix}railtimetable_dates.timetableid = {$wpdb->prefix}railtimetable_timetables.id ".
            "LEFT JOIN {$wpdb->prefix}wc_railticket_bookable ON ".
            " {$wpdb->prefix}wc_railticket_bookable.dateid = {$wpdb->prefix}railtimetable_dates.id ".
            "WHERE {$wpdb->prefix}railtimetable_dates.date = '".$date."'", OBJECT );


        if (count($timetable) > 0) {
            return $timetable[0];
        } else {
            return false;
        }
    }

    private function get_javascript() {

        wp_register_script('railticket_script', plugins_url('wc-product-railticket/ticketbuilder.js'));
        wp_enqueue_script('railticket_script');

        $minprice = 'false';
        $opt = get_option('wc_product_railticket_min_price');
        if (strlen($opt) > 0) {
            $minprice = $opt;
        }

        $str = "\n<script type='text/javascript'>\n".
            "var ajaxurl = '".admin_url( 'admin-ajax.php', 'relative' )."';\n".
            "var today = '".$this->today->format('Y-m-d')."'\n".
            "var tomorrow = '".$this->tomorrow->format('Y-m-d')."'\n".
            "var minprice = ".$minprice."\n".
            "var dateFormat = '".get_option('railtimetable_date_format')."';\n".
            "var stationData = ".json_encode(railticket_get_stations_map()).";\n";

        $str .= $this->preset_javascript('a_dateofjourney');
        $str .= $this->preset_javascript('a_station');
        $str .= $this->preset_javascript('a_destination');
        $str .= $this->preset_javascript('a_direction');
        $str .= $this->preset_javascript('a_deptime');

        if ($this->is_guard()) {
            $str .= 'var guard=true;';
        } else {
            $str .= 'var guard=false;';
        }

        $str .= "</script>";

        return $str;
    }

    private function preset_javascript($key) {
        if (array_key_exists('a_dateofjourney', $_REQUEST)) {
            return 'var '.$key.' = "'.$_REQUEST[$key].'";';
        } else {
            return 'var '.$key.' = false;';
        }
    }

    private function get_datepick() {
        global $wpdb;
        $calendar = new TicketCalendar();

        $startyear = $this->today->format('Y');
        $startmonth = $this->today->format('n');
        $endyear = date_i18n("Y", strtotime("+1 month"));
        $endmonth = date_i18n("n", strtotime("+2 month"));

        if ($endmonth < $startmonth) {
            $stop = 13;
        } else {
            $stop = $endmonth++;
        }
        $cal="";

        for ($year=$startyear; $year<$endyear+1; $year++) {
            for ($month=$startmonth; $month<$stop; $month++) {
                $cal .= "<div class='calendar-box-wrapper' id='railtimetable-cal-".$year."-".$month."'>".$calendar->draw(date_i18n($year."-".$month."-01"))."</div>";
             }
             $startmonth = 1;
             $stop = $endmonth+1;
        }

        $scroll = $this->today->format("Y-n");

        $cal .= '<script type="text/javascript">var baseurl = "'.railtimetable_currentlang()."/".get_site_url().'";var closetext="'.__("Close", "railtimetable").'";var scrollto="'.$scroll.'"; initTrainTimes();</script>';

        $str = "<div id='datechoosetitle' class='railticket_stageblock' style='display:block;'><h3>Choose Date of Travel</h3>";
        $str .= "<p>".get_option('wc_product_railticket_top_comment')."</p></div>".
            "<div id='railtimetable-cal' class='calendar-wrapper'>.$cal.</div>";

        $str .= "<div id='datechooser' class='railticket_stageblock'><div class='railticket_container'>".
            "<p class='railticket_help'>Choose a date from the calendar above, or use the buttons below.<br />Dates marked with an X are sold out.</p>";
        $toshow = 6;
        $act = false;

        $nowtime = ($this->now->format('G')*60) + $this->now->format('i');
        $lt = $this->last_train_today();
        if ($lt === false ) {
            $endtime = 60*24;
        } else {
            $et = explode(":", $lt);
            $endtime = (intval($et[0])*60) + intval($et[1]) + intval(get_option("wc_product_railticket_bookinggrace"));
        }
        if ($nowtime < $endtime || $this->is_guard()) {
            $nexttrains = $this->get_next_bookable($this->today, $toshow);
        } else {
            $nexttrains = $this->get_next_bookable($this->tomorrow, $toshow);
        }

        foreach ($nexttrains as $t) {
            $date = DateTime::createFromFormat("Y-m-d", $t->date);
            $str .= "<input type='button' value='".$date->format('j-M-Y')."' title='Click to travel on ".$date->format('j-M-Y')."' ".
                "class='railticket_datebuttons' data='".$date->format("Y-m-d")."' />";
            if ($act == false) {
                $str .= '&nbsp;';
            } else {
                $str .= '<br />';
            }
            $act = !$act;
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
            "<div id='stations_container'>".
            "<h3>Choose Stations</h3>".
            "<p class='railticket_help'>Tap or click the stations to select. Departure times and single or return journeys are chosen in the next section.</p>".
            "<div class='railticket_container'>".
            "<div class='railticket_listselect_left'><div class='inner'><h3>From</h3>".$this->station_radio("fromstation", true)."</div></div>".
            "<div class='railticket_listselect_right'><div class='inner'><h3>To</h3>".$this->station_radio("tostation", false)."</div></div>".
            "</div></div>".
            "<div class='railticket_container' id='railticket_specials'></div>".
            "</div>";

        return $str;
    }

    private function station_radio($name, $from) {
        $str="<ul>";
        foreach ($this->stations as $station) {
            $str .= "<li><input type='radio' name='".$name."' id='".$name.$station->id."' value='".$station->id.
                "' class='railticket_".$name." railticket_notbookable' disabled />\n".
                "<label for='".$name.$station->id."'>".$station->name."<br />".
                "<div class='railticket_stndesc'>".$station->description."</div></label></li>";
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
            " booked if you are certain the train is simply running late. Some seating capacity is held back and released around 9am each morning.</p></div>";

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
            "<div class='railicket_selected_service' id='railticket_summary_service'></div>".
            "<p class='railticket_help'>Use the boxes on the left to enter the number of tickets required</p>".
            "  <div id='ticket_travellers' class='railticket_container'>".
            "  </div>".
            "  <div id='ticket_summary' class='railticket_container'></div>".
            "  <div id='ticket_capbutton' class='railticket_container'>".
            "  <p class='railticket_terms'><input type='checkbox' name='disabledrequest' id='disabledrequest'/>&nbsp;&nbsp;&nbsp;Request space for disabled visitor</p>";

        if ($this->is_guard()) {
            $str .= "<p class='railticket_terms'><input type='checkbox' name='nominimum' id='nominimum'/>&nbsp;&nbsp;&nbsp;No Minimum Price</p>";
            $str .= "<p class='railticket_terms'><input type='checkbox' name='bypass' id='bypass'/>&nbsp;&nbsp;&nbsp;Bypass Ticket Restrictions</p>";
        } else {
            $str .= "<input type='hidden' name='nominimum' id='nominimum' value='0' />";
        }

        $str.= "  <input type='button' value='Confirm Choices' id='confirmchoices' /></div>".
            "  <div id='ticket_capacity' class='railticket_container'></div>".
            "</div>";

        return $str;
    }

    private function get_addtocart() {
        $str = "<div id='addtocart' class='railticket_stageblock'>".
            "<input type='hidden' name='ticketselections' />".
            "<input type='hidden' name='ticketallocations' />".
            "<div class='railticket_container'>";

        if ($this->is_guard()) {
            $str .= "<p><label for='notes'>Guard's Notes:</label><br /><textarea id='notes' cols='40' rows='5' name='notes'></textarea></p>".
                "<p><input type='button' value='Create Booking' id='createbooking' /></p>";
        } else {
            $str .= "<p class='railticket_terms'><input type='checkbox' name='terms' id='termsinput'/>&nbsp;&nbsp;&nbsp;I agree to the ticket sales terms and conditions.</p>".
                "<p><a href='".get_option('wc_product_railticket_termspage')."' target='_blank'>Click here to view terms and conditions in a new tab.</a></p>".
                "<div id='addticketstocart'><p class='railticket_terms'>Your tickets will be reserved for ".
                get_option('wc_product_railticket_reservetime')." minutes after you click add to cart.".
                " Please complete your purchases within that time.</p>".
                "<p><input type='hidden' name='notes' value='' /><input type='button' value='Add To Cart' id='addtocart_button' /></p></div></div>";
        }

        $str .= "<div id='railticket_processing' class='railticket_processing'><p>Processing - Please wait</p></div></div>";

        return $str;
    }

} 
