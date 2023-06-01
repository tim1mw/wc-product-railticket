<?php
namespace wc_railticket;
defined('ABSPATH') or die('No script kiddies please!');

class WaybillCombined extends WaybillBase {

    function __construct($startdate, $enddate) {
        global $wpdb;
        parent::__construct();
        $this->startdate = $startdate;
        $this->enddate = $enddate;
        // TODO The fare data will fail if we have dates that go outside of a fare revision. Block this!
        $date = BookableDay::get_next_bookable_dates($this->startdate, 1);
        $this->bookableday = BookableDay::get_bookable_day($date[0]->date);

        $days = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_railticket_stats WHERE ".
        "date >= '".$this->startdate."' AND date <= '".$this->enddate."' ORDER BY DATE ASC");

        foreach ($days as $day) {

            $daydata = json_decode($day->waybill);
            $this->merge_array('lines', $daydata->lines);

            $this->totalseats += $daydata->totalseats;
            $this->totaltickets += $daydata->totaltickets;
            $this->totalsupplements += $daydata->totalsupplements;
            $this->totaldiscounts += $daydata->totaldiscounts;
            $this->totalwoo += $daydata->totalwoo;
            $this->totalmanual += $daydata->totalmanual;
            $this->merge_array('guardtotals', $daydata->guardtotals);
            $this->totaljourneys += $daydata->totaljourneys;
            $this->totalonlineprice += $daydata->totalonlineprice;
            $this->totalguardprice += $daydata->totalguardprice;
            $this->merge_array('totaltravellers', $daydata->totaltravellers);
        }
    }

    private function merge_array($akey, $ndata) {
        foreach ($ndata as $itemkey => $itemdata) {
            if (array_key_exists($itemkey, $this->$akey)) {
                // Add the new data to the existing key
                $this->$akey[$itemkey] += $itemdata;
            } else {
                // New key, so add to the combined array.
                $this->$akey[$itemkey] = $itemdata;
            }
        }
    }
    
    function get_date_display() {
        return $this->startdate."_".$this->enddate;
    }

    function get_bookable_day() {
        return $this->bookableday;
    }

}
