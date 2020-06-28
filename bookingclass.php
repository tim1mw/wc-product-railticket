<?php

class TicketBuilder {

    private $today, $tomorrow, $stations;

    public function __construct($dateoftravel, $fromstation, $tostation, $type, $outtime, $rettime, $journeytype, $tickets) {
        global $wpdb;
        $this->today = new DateTime();
        $this->tomorrow = new DateTime();
        $this->tomorrow->modify('+1 day');
        //$this->tickettypes = railticket_get_ticket_data();
        $this->stations = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_stations ORDER BY sequence ASC");

        $this->dateoftravel = $dateoftravel;
        $this->fromstation = $fromstation;
        $this->tostation = $tostation;
        $this->type = $type;
        $this->outtime = $outtime;
        $this->rettime = $rettime;
        $this->journeytype = $journeytype;
        $this->tickets = $tickets;
    }

    public function render() {
        return $this->get_javascript().
            $this->get_datepick().
            '<form action="post" name="railticketbooking">'.
            $this->get_stations().
            $this->get_deptimes().
            $this->get_ticket_choices().
            $this->get_addtocart().'</form>'.
            '<div id="pleasewait" class="railticket_loading">Fetching Ticket Data&#8230;</div>';
    }

    public function is_date_bookable($date) {
        global $wpdb;

        if ($date instanceof DateTime) {
            $date = $date->format('Y-m-d');
        }

        $sql = "SELECT COUNT(*) FROM {$wpdb->prefix}railtimetable_dates ".
            "LEFT JOIN {$wpdb->prefix}wc_railticket_bookable ON ".
            " {$wpdb->prefix}wc_railticket_bookable.dateid = {$wpdb->prefix}railtimetable_dates.id ".
            "WHERE {$wpdb->prefix}railtimetable_dates.date = '".$date."' AND ".
            "{$wpdb->prefix}wc_railticket_bookable.bookable = 1 AND {$wpdb->prefix}wc_railticket_bookable.soldout = 0";

        $rec = $wpdb->get_var($sql );
        if ($rec > 0) {
            return true;
        } else {
            return false;
        }
    }

    public function is_train_bookable($time, $stn, $direction) {
        //if ($direction == "up") {
        //    return false;
        //}
        return true;
    }

    public function get_bookable_stations() {
        $bookable = array();
        $bookable['from'] = array(0 => true, 1 => false, 2 => true);
        $bookable['to'] = array(0 => true, 1 => false, 2 => true);
        return $bookable;
    }

/*
    private function station_bookable($station, $from) {
        if ($from) {
            foreach ($this->tickettypes->prices as $price) {
                // If we are going from this station, then make sure it has some destinations
                if ($price->station == $station->id && count($price->destinations) > 0) {
                    return true;
                }
            }
        } else {
            foreach ($this->tickettypes->prices as $price) {
                // Otherwise we want to see if this station appears as a destination from some other station
                foreach ($price->destinations as $dest) {
                    if ($dest->station == $station->id) {
                        return true;
                    }
                }
            }
        }
        return false;
    }
*/

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
            $canbook = $this->is_train_bookable($dep, $this->fromstation, $direction);
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
            $canbook = $this->is_train_bookable($ret, $this->$tostation, $direction);
            $bookable['ret'][] = array('dep' => $ret, 'depdisp' => strftime($fmt, strtotime($ret)),
                'arr' => $retarrs[$index], 'arrdisp' => strftime($fmt, strtotime($retarrs[$index])), 
                'bookable' => $canbook);
            $testfirst = false;
            if ($canbook) {
                $intotal ++;
            }
        }
        $bookable['tickets'] = array();
        if ($outtotal > 0) {
            $bookable['tickets'][] = 'single';
            if ($intotal > 0) {
                $bookable['tickets'][] = 'return';
            }
        }

        return $bookable;
    }

    public function get_tickets() {
        global $wpdb;
        $tickets = new stdClass();
        $tickets->travellers = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_railticket_travellers", OBJECT );
        //foreach ($travellers as $traveller) {
        //    $tickets['travellers'][$traveller->code] = array('name' => $traveller->name, 'description' => $traveller->description);
        //}

        $sql = "SELECT {$wpdb->prefix}wc_railticket_prices.id, ".
            "{$wpdb->prefix}wc_railticket_prices.tickettype, ".
            "{$wpdb->prefix}wc_railticket_prices.price, ".
            "{$wpdb->prefix}wc_railticket_tickettypes.name, ".
            "{$wpdb->prefix}wc_railticket_tickettypes.description, ".
            "{$wpdb->prefix}wc_railticket_tickettypes.composition, ".
            "{$wpdb->prefix}wc_railticket_tickettypes.depends ".
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

        file_put_contents('/home/httpd/balashoptest.my-place.org.uk/x.txt', $sql.print_r($tickets, true));
        return $tickets;
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

    private function findTimetable()
    {
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

        wp_register_script('railticket_script', plugins_url('wc-product-railticket/ticket-rules.js'));
        wp_enqueue_script('railticket_script');

        return "\n<script type='text/javascript'>\n".
            "var ajaxurl = '".admin_url( 'admin-ajax.php', 'relative' )."';\n".
            "\nvar stations = ".json_encode($stations).";\n".
            "var today = '".$this->today->format('Y-m-d')."'\n".
            "var tomorrow = '".$this->tomorrow->format('Y-m-d')."'\n".
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
            "<div id='datechosen' class='railticket_container'>No Date Chosen</div>".
            "<input type='hidden' id='dateoftravel' name='dateoftravel' value='' /></div>";

        return $str;
    }

    private function get_stations() {
        $str = "<div id='stations' class='railticket_stageblock railticket_listselect'><div class='railticket_container'>".
            "<div class='railticket_listselect_left'><div class='inner'><h3>From</h3>".$this->station_radio($stations, "fromstation", true)."</div></div>".
            "<div class='railticket_listselect_right'><div class='inner'><h3>To</h3>".$this->station_radio($stations, "tostation", false)."</div></div>".
            "</div></div>";

        return $str;
    }

    private function station_radio($stations, $name, $from) {
        $str="<ul>";
        foreach ($this->stations as $station) {
            $str .= "<li title='No tickets are available for this station'><input type='radio' name='".$name."' id='".$name.$station->id."' value='".$station->id.
                "' class='railticket_".$name." railticket_notbookable' disabled />\n".
                "<label for='".$name.$station->id."'>".$station->name."</label></li>";
        }
        $str.="</ul>";
        return $str;
    }

    private function get_deptimes() {
        $str =
            "<div id='deptimes' class='railticket_stageblock railticket_listselect'>".
            "  <h3>Choose a Departure</h3>".
            "  <div id='deptimes_data' class='railticket_container'>".
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
            "  <div id='ticket_travellers' class='railticket_container'>".
            "  </div>".
            "  <div id='ticket_summary' class='railticket_container'></div>".
            "</div>";

        return $str;
    }

    private function get_addtocart() {
        $str .= "<div id='addtocart' class='railticket_stageblock'>".
            "<input type='submit' value='Add To Cart' />".
            "</div>";

        return $str;
    }

} 
