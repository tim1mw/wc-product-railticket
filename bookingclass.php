<?php

class TicketBuilder {

    private $today, $tomorrow, $tickettypes, $stations;

    private $date, $type, $outtime, $rettime,$outtime, $rettime, $tickets;

    public function __construct($date, $fromstation, $tostation, $type, $outtime, $rettime, $tickets) {
        global $wpdb;
        $this->today = new DateTime();
        $this->tomorrow = new DateTime();
        $this->tomorrow->modify('+1 day');
        $this->tickettypes = railticket_get_ticket_data();
        $this->stations = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_stations ORDER BY sequence ASC");

        $this->date = $date;
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
            '<form action="post">'.
            $this->get_stations().
            $this->get_deptimes().
            $this->get_ticket_choices().
            $this->get_addtocart().'</form>';
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

        $cal .= '<script type="text/javascript">var baseurl = "'.railtimetable_currentlang()."/".get_site_url().'";var closetext="'.__("Close", "railtimetable").'";var scrollto="'.$scroll.'"; initTrainTimes();</script><form>';

        if ($this->today->format('H') < 19) { 
             $cal .= "<input type='button' value='Travel Today' id='todaybutton' title='Click to travel today' />";
        }
        $cal .= "<input type='button' value='Travel Tomorrow' id='tomorrowbutton' title='Click to travel tomorrow' /></form>";

        return "<div id='datechoser' class='calendar-wrapper' id='railtimetable-cal'>".
            "<h3>Choose Date of Travel</h3>".$cal.
            "<div id='datechosen'>No Date Chosen</div>".
            "<input type='hidden' id='dateoftravel' name='dateoftravel' value='' /></div>";
    }

    private function get_stations() {
        $str = "<div id='stations' class='railticket_stageblock'>".
            "<div id='stationdiv_from'><div class='inner'><h3>From</h3>".$this->station_radio($stations, "fromstation", true)."</div></div>".
            "<div id='stationdiv_to'><div  class='inner'><h3>To</h3>".$this->station_radio($stations, "tostation", false)."</div></div>".
            "</div>";

        return $str;
    }

    private function station_radio($stations, $name, $from) {
        $str="<ul>";
        foreach ($this->stations as $station) {
            if ($this->station_bookable($station, $from)) {
                $class='';
                $title='Click to select this station';
                $att='';
            } else {
                $class='railticket_notbookable';
                $title='No tickets are available for this station';
                $att='disabled ';
            }

            $str .= "<li title='".$title."'><input type='radio' name='".$name."' id='".$name.$station->id."' value='".$station->id.
                "' class='railticket_stationselect railticket_".$name." ".$class."' ".$att."/>\n".
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
        $str = "<div id='deptimes' class='railticket_stageblock'>".
            "<h3>Choose a Departure</h3>";

        $str .= "</div>";

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
