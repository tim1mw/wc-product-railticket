<?php

class TicketBuilder {

    private $today, $tomorrow, $tickettypes, $stations;

    //private $date, $type, $outtime, $rettime,$outtime, $rettime, $tickets;

    public function __construct($dateoftravel, $fromstation, $tostation, $type, $outtime, $rettime, $tickets) {
        global $wpdb;
        $this->today = new DateTime();
        $this->tomorrow = new DateTime();
        $this->tomorrow->modify('+1 day');
        $this->tickettypes = railticket_get_ticket_data();
        $this->stations = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_stations ORDER BY sequence ASC");

        $this->dateoftravel = $date;
        $this->fromstation = $fromstation;
        $this->tostation = $tostation;
        $this->type = $type;
        $this->outtime = $outtime;
        $this->rettime = $rettime;
        $this->tickets = $tickets;
    }

    public function render() {
        return $this->get_javascript().
            $this->get_datepick().
            '<form action="post" name="railticketbooking">'.
            $this->get_stations().
            $this->get_deptimes().
            $this->get_ticket_choices().
            $this->get_addtocart().'</form>';
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

    public function get_bookable_stations() {
        $bookable = array();
        $bookable['from'] = array(0 => true, 1 => false, 2 => false);
        $bookable['to'] = array(0 => false, 1 => true, 2 => true);
        return $bookable;
    }

    public function get_bookable_trains() {
        $bookable = array();
        $bookable['out'] = array(
            0 => array( 'dep' => '11:00', 'arr' => '11:25'),
            1 => array( 'dep' => '12:45', 'arr' => '13:10'),
            2 => array( 'dep' => '2:20', 'arr' => '2:45'),
            3 => array( 'dep' => '3:55', 'arr' => '4:20'));

        $bookable['ret'] = array(
            0 => array( 'dep' => '11:40', 'arr' => '12:05'),
            1 => array( 'dep' => '1:25', 'arr' => '1:50'),
            2 => array( 'dep' => '3:00', 'arr' => '3:25'),
            3 => array( 'dep' => '4:25', 'arr' => '4:50'));

        $bookable['tickets'] = array('single', 'return');

        return $bookable;
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

    private function get_deptimes() {
        $str =
            "<div id='deptimes' class='railticket_stageblock railticket_listselect'>".
            "  <h3>Choose a Departure</h3>".
            "  <div id='deptimes_data' class='railticket_container'>".
            "    <div id='deptimes_data_out' class='railticket_listselect_left'></div>".
            "    <div id='deptimes_data_ret' class='railticket_listselect_right'></div>".
            "  </div>".
            "  <div id='ticket_type' class='railticket_container'></div>".
            "</div>";

        return $str;
    }

    private function get_ticket_choices() {
        $str = "<div id='tickets' class='railticket_stageblock'>";

        $str .= "</div>";

        return $str;
    }

    private function get_addtocart() {
        $str .= "<div id='addtocart' class='railticket_stageblock'>".
            "<input type='submit' value='Add To Cart' />".
            "</div>";

        return $str;
    }

} 
