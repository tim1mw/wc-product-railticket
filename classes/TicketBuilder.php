<?php

namespace wc_railticket;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class TicketBuilder {

    private $today, $tomorrow, $stations;

    public function __construct($dateoftravel, $fromstation, $journeychoice, $outtime, $rettime,
        $ticketselections, $ticketsallocated, $overridevalid, $disabledrequest, $notes, $nominimum, $show) {
        global $wpdb;
        $this->show = $show;

        // Deal with some dates
        $this->railticket_timezone = new \DateTimeZone(get_option('timezone_string'));
        $this->now = new \DateTime();
        $this->now->setTimezone($this->railticket_timezone);
        $this->today = new \DateTime();
        $this->today->setTimezone($this->railticket_timezone);
        $this->today->setTime(0,0,0);
        $this->tomorrow = new \DateTime();
        $this->tomorrow->setTimezone($this->railticket_timezone);
        $this->tomorrow->modify('+1 day');

        if ($dateoftravel == false) {
            $this->bookableday = BookableDay::get_bookable_day($this->today->format('Y-m-d'));
            return;
        }

        $this->bookableday = BookableDay::get_bookable_day($dateoftravel);
        $this->dateoftravel = $dateoftravel;
        $this->fromstation = Station::get_station($fromstation, $this->bookableday->timetable->get_revision());

        $jparts = explode('_', $journeychoice);
        $this->journeytype = $jparts[0];
        $this->tostation = Station::get_station($jparts[1], $this->bookableday->timetable->get_revision());
        if ($this->journeytype == 'round') {
            $this->rndtostation = Station::get_station($jparts[2], $this->bookableday->timetable->get_revision());
        } else {
            $this->rndtostation = false;
        }

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

        if ($nominimum == 'true') {
            $this->nominimum = true;
        } else {
            $this->nominimum = false;
        }

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

        if ($this->show || $this->bookableday == false) {
            return $this->get_all_html();
        }

        $fstation = $this->bookableday->timetable->get_terminal('up');
        $lstation = $this->bookableday->timetable->get_terminal('down');

        $fdeptime = $this->bookableday->timetable->next_train_from($fstation, true);
        $ldeptime = $this->bookableday->timetable->next_train_from($lstation, true);

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
        global $rtmustache;

        $alldata = new \stdclass();
        $alldata->javascript = $this->get_javascript();
        $alldata->datepick = $this->get_datepick();

        if ($this->is_guard()) {
            $alldata->ticket_guardopts = "<p class='railticket_terms'><input type='checkbox' name='nominimum' id='nominimum' />".
                "&nbsp;&nbsp;&nbsp;No Minimum Price</p>".
                "<p class='railticket_terms'><input type='checkbox' name='bypass' id='bypass'/>".
                "&nbsp;&nbsp;&nbsp;Bypass Ticket Restrictions</p>";

            $alldata->addtocartopts = "<p><label for='notes'>Guard's Notes:</label><br />".
                "<textarea id='notes' cols='40' rows='5' name='notes'></textarea></p>".
                "<p><input type='button' value='Create Booking' id='createbooking' /></p>";
        } else {
            $alldata->ticket_guardopts = "<input type='hidden' name='nominimum' id='nominimum' value='0' />";
            $alldata->addtocartopts = "<p class='railticket_terms'><input type='checkbox' name='terms' id='termsinput'/>".
                "&nbsp;&nbsp;&nbsp;I agree to the ticket sales terms and conditions.</p>".
                "<p><a href='".get_option('wc_product_railticket_termspage')."' target='_blank'>Click here to view terms and conditions in a new tab.</a></p>".
                "<div id='addticketstocart'><p class='railticket_terms'>Your tickets will be reserved for ".
                get_option('wc_product_railticket_reservetime')." minutes after you click add to cart.".
                " Please complete your purchases within that time.</p>".
                "<p><input type='hidden' name='notes' value='' /><input type='button' value='Add To Cart' id='addtocart_button' /></p></div></div>";
        }

        $template = $rtmustache->loadTemplate('ticketbuilder');
        echo $template->render($alldata);
    }

    private function get_preset_form(\wc_railticket\Station $fstation, \wc_railticket\Station $tstation, \stdclass $deptime, $direction) {

        $str = "<form action='/book/' method='post'>".
            "<input type='submit' value='Return tickets for the next train from ".$fstation->get_name()."' />".
            "<input type='hidden' name='a_dateofjourney' value='".$this->today->format('Y-m-d')."' />".
            "<input type='hidden' name='a_deptime' value='".$deptime->key."' />".
            "<input type='hidden' name='a_station' value='".$fstation->get_stnid()."' />".
            "<input type='hidden' name='a_destination' value='".$tstation->get_stnid()."' />".
            "<input type='hidden' name='a_direction' value='".$direction."' />".
            "<input type='hidden' name='show' value='1' />".
            "</form><br />";

        return $str;
    }

    public function is_train_bookable($time, $fromstn, $tostn) {
        // Dates which are in the past are not allowed
        $parts = explode('.', $time);
        $deptime = \DateTime::createFromFormat("Y-m-d", $this->dateoftravel, $this->railticket_timezone);
        $deptime->setTime($parts[0], $parts[1], 0);
        $deptime->modify('+'.get_option('wc_product_railticket_bookinggrace').' minutes');

        $r = new \stdclass();
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

    public function get_bookable_stations() {
        global $wpdb;
        $bookable = array();
        $bookable['override'] = $this->bookableday->get_override();
        $bookable['stations'] = $this->bookableday->timetable->get_stations(true);

        // Are their any specials today?

        $specials = $this->bookableday->get_specials(true);
        if ($specials && count($specials) > 0) {
            $bookable['specials'] = $specials;
        } else {
            $bookable['specials'] = false;
        }
        $bookable['specialonly'] = $this->bookableday->special_only();

        return $bookable;
    }

    private function timefunc($fmt, $time) {
        $tz = date_default_timezone_get();
        date_default_timezone_set($this->railticket_timezone->getName());

        $result = strftime($fmt, strtotime($time));

        date_default_timezone_set($tz);
        return $result;
    }

    public function get_journey_options() {

        $allpopular = array();
        $allother = array();

        $up_terminal = $this->bookableday->timetable->get_terminal('up');
        $down_terminal = $this->bookableday->timetable->get_terminal('down');
        $allstations = $this->bookableday->timetable->get_stations();
        $otherterm = false;

        // Find the popular journeys. If this is a terminal, a return to the other terminal.
        // If an intermediate stop Round trips

        if ($this->fromstation->get_stnid() == $up_terminal->get_stnid()) {
            $allpopular[] = $this->get_returntrip_opt($this->fromstation, $down_terminal, true);
            $allpopular[] = $this->get_singletrip_opt($this->fromstation, $down_terminal);
            $otherterm = $down_terminal;
        } elseif ($this->fromstation->get_stnid() == $down_terminal->get_stnid()) {
            $allpopular[] = $this->get_returntrip_opt($this->fromstation, $up_terminal, true);
            $allpopular[] = $this->get_singletrip_opt($this->fromstation, $up_terminal);
            $otherterm = $up_terminal;
        } else {
            // Must be an intermediate stop if we got here, so offer round trips
            $allpopular[] = $this->get_roundtrip_opt($this->fromstation, $up_terminal, $down_terminal);
            $allpopular[] = $this->get_roundtrip_opt($this->fromstation, $down_terminal, $up_terminal);
        }

        foreach ($allstations as $stn) {
            if ($stn->is_closed() || $stn->get_stnid() == $this->fromstation->get_stnid() ||
               ($otherterm && $otherterm->get_stnid() == $stn->get_stnid()) ) {
                 // this is the station we are at, or it is closed, we can't go there
                continue;
            }

            $allother[] = $this->get_returntrip_opt($this->fromstation, $stn);
            $allother[] = $this->get_singletrip_opt($this->fromstation, $stn);
        }

        return ['popular' => $allpopular, 'other' => $allother];
    }

    private function get_returntrip_opt(Station $from, Station $to, $full = false) {
        $trp = new \stdclass();
        $trp->journeytype = 'return';
        $trp->journeydesc = __('Return Trip to ', 'wc_railticket').$to->get_name();

        if ($full) {
            $trp->extradesc =  __('A full line return trip', 'wc_railticket').", ".
                $from->get_name()." - ".$to->get_name()." - ".$from->get_name();
        } else {
            $trp->extradesc = $from->get_name()." - ".$to->get_name()." - ".$from->get_name();
        }

        $trp->code = 'return_'.$to->get_stnid();

        // Do a check here to see if this can be purchased
        $trp->disabled = '';

        return $trp; 
   }

    private function get_singletrip_opt(Station $from, Station $to) {
        $trp = new \stdclass();
        $trp->journeytype = 'single';
        $trp->journeydesc = __('Single Trip to ', 'wc_railticket').$to->get_name();
        $trp->extradesc = $from->get_name()." - ".$to->get_name();
        $trp->code = 'single_'.$to->get_stnid();
        // Do a check here to see if this can be purchased
        $trp->disabled = '';
        return $trp; 
   }

    private function get_roundtrip_opt(Station $from, Station $term1, Station $term2) {
        $rnd = new \stdclass();
        $rnd->journeytype = 'round';
        $rnd->journeydesc = __('Full Line Round Trip', 'wc_railticket');
        $rnd->extradesc = $from->get_name()." - ".
            $term1->get_name()." - ".
            $term2->get_name()." - ".
            $from->get_name();
        $rnd->code = 'round_'.$term1->get_stnid()."_".$term2->get_stnid();;
        // Do a check here to see if this can be purchased
        $trp->disabled = '';
        return $rnd;
    }

    public function get_bookable_trains() {
        global $wpdb;
        $fmt = get_option('wc_railticket_time_format');
        $bookable = array();

        $timetable = $this->findTimetable();
        $deptimesdata = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_railticket_times WHERE station = '".$this->fromstation."' ".
            " AND timetableid = ".$timetable->id)[0];
        $rettimesdata = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_railticket_times WHERE station = '".$this->tostation."' ".
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
        $tickets = new \stdClass();

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

        $allocatedbays = new \stdclass();
        $allocatedbays->ok = false;
        $allocatedbays->tobig = false;
        $allocatedbays->error = false;
        $allocatedbays->disablewarn = false;

        $seatsreq = $this->count_seats();

        $outbays = $this->get_service_inventory($outtime, $fromstation, $tostation, false);
        $allocatedbays->outseatsleft = $outbays->totalseats;

        if ($caponly) {
            return $allocatedbays;
        }

        if ($journeytype == 'return') {
            $retbays = $this->get_service_inventory($this->rettime, $tostation, $fromstation, false);
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

        $purchase = new \stdclass();
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
                'notes' => $this->notes,
                'createdby' => get_current_user_id()
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

        return $purchase;
    }

    private function insertBooking($itemkey, $time, $fromstation, $tostation, $totalseats, $allocatedbays, $manual) {
        global $wpdb;
        // TODO originstation and origintime reflect the station this train originally started from. Right now
        // with end to end bookings only this will always be the same as fromstation and time. Needs to be set properly
        // when intermediate stops are added. The aim is to allow the entire inventory for this service to be retrieved.

        $fs = $wpdb->get_var("SELECT sequence FROM {$wpdb->prefix}wc_railticket_stations WHERE id = ".$fromstation);
        $ts = $wpdb->get_var("SELECT sequence FROM {$wpdb->prefix}wc_railticket_stations WHERE id = ".$tostation);
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

    private function get_javascript() {
        wp_register_script('railticket_script_mustache', plugins_url('wc-product-railticket/mustache.min.js'));
        wp_register_script('railticket_script_builder', plugins_url('wc-product-railticket/ticketbuilder.js'));
        wp_enqueue_script('railticket_script_mustache');
        wp_enqueue_script('railticket_script_builder');

        $minprice = 'false';
        $opt = get_option('wc_product_railticket_min_price');
        if (strlen($opt) > 0) {
            $minprice = $opt;
        }
        
        $str = file_get_contents(dirname(__FILE__).'/../remote-templates.html').
            "\n<script type='text/javascript'>\n".
            "var ajaxurl = '".admin_url( 'admin-ajax.php', 'relative' )."';\n".
            "var today = '".$this->today->format('Y-m-d')."'\n".
            "var tomorrow = '".$this->tomorrow->format('Y-m-d')."'\n".
            "var minprice = ".$minprice."\n".
            "var dateFormat = '".get_option('wc_railticket_date_format')."';\n";

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
        $calendar = new \wc_railticket\TicketCalendar();

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
                $cal .= "<div class='calendar-box-wrapper' id='railtimetable-cal-".$year."-".$month."'>".$calendar->draw(date_i18n($year."-".$month."-01"), $this->is_guard())."</div>";
             }
             $startmonth = 1;
             $stop = $endmonth+1;
        }

        $str = "<div id='datechoosetitle' class='railticket_stageblock' style='display:block;'><h3>Choose Date of Travel</h3>";
        $str .= "<p>".get_option('wc_product_railticket_top_comment')."</p></div>".
            "<div id='railtimetable-cal' class='calendar-wrapper'>.$cal.</div>";

        $str .= "<div id='datechooser' class='railticket_stageblock'><div class='railticket_container'>".
            "<p class='railticket_help'>Choose a date from the calendar above, or use the buttons below.<br />Dates marked with an X are sold out.</p>";
        $toshow = 6;
        $act = false;

        $nowtime = ($this->now->format('G')*60) + $this->now->format('i');
        $timetable = \wc_railticket\Timetable::get_timetable_by_date($this->now->format('Y-m-d'));

        if ($timetable) {
            $lt = $timetable->get_last_train();
            if ($lt === false ) {
                $endtime = 60*24;
            } else {
                $endtime = (intval($lt->hour)*60) + intval($lt->min) + intval(get_option("wc_product_railticket_bookinggrace"));
            }
        } else {
            $endtime = 60*24;
        }

        if ($nowtime < $endtime || $this->is_guard()) {
            $nexttrains = \wc_railticket\BookableDay::get_next_bookable_dates($this->today->format('Y-m-d'), $toshow);
        } else {
            $nexttrains = \wc_railticket\BookableDay::get_next_bookable_dates($this->tomorrow->format('Y-m-d'), $toshow);
        }



        foreach ($nexttrains as $t) {
            $date = \DateTime::createFromFormat("Y-m-d", $t->date);

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

} 
