<?php
namespace wc_railticket;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class WaybillBase extends Report {
    function __construct() {
        $this->lines = array();
        $this->totalseats = 0;
        $this->totaltickets = 0;
        $this->totalsupplements = 0;
        $this->totaldiscounts = 0;
        $this->totalwoo = 0;
        $this->totalmanual = 0;
        $this->guardtotals = array();
        $this->totaljourneys = 0;
        $this->totalonlineprice = 0;
        $this->totalguardprice = 0;
        $this->totaltravellers = array();
        $this->discountuse = array();
    }

    function show_waybill($iscsv) {
        $header = array('From', 'To', 'Journey Type', 'Ticket Type', 'Fare Type', 'Discount', 'Number', 'Fare', 'Total');

        $gts = array();
        foreach ($this->guardtotals as $createdby => $total) {
            $gti = new \stdclass();
            if ($createdby > 0) {
                $u = get_userdata($createdby);
                $gti->name = $u->first_name." ".$u->last_name;
            } else {
                $gti->name = __('Unknown User', 'wc_railticket')." (id = ".$createdby.")";
            }
            $gti->total = number_format($total, 2);
            $gts[] = $gti;
        }

        if ($iscsv) {
            $this->show_waybill_csv($header, $gts);
        } else {
            $this->show_waybill_html($header, $gts);
        }
    }

    function get_date_display() {
        return "";
    }

    function get_bookable_day() {
        return null;
    }

    function show_waybill_csv($header, $gts) {
        header('Content-Type: application/csv');
        header('Content-Disposition: attachment; filename="waybill-' . $this->get_date_display() . '.csv";');
        header('Pragma: no-cache');
        $f = fopen('php://output', 'w');
        fputcsv($f, array('Date', $this->get_date_display()));
        fputcsv($f, array('', '', '', '', '', ''));
        fputcsv($f, $header);

        foreach ($this->lines as $linekey => $linevalue) {
            $keyparts = explode('|', $linekey);

            $from = $this->get_bookable_day()->timetable->get_station($keyparts[0]);
            $to = $this->get_bookable_day()->timetable->get_station($keyparts[1]);

            $nline = array();
            $nline[] = $from->get_name();
            $nline[] = $to->get_name();
            if (count($keyparts) == 7) {
                $special = true;
                $nline[] = __('Special', 'wc_railticket');
            } else {
                $special = false;
                $nline[] = __(ucfirst($keyparts[2]), 'wc_railticket');
            }
            $nline[] = $this->get_bookable_day()->fares->get_ticket_name($keyparts[3]);
            if ($keyparts[4] == 'price') {
                $nline[] = __('Online', 'wc_railticket');
            } else {
                $nline[] = __('Guard', 'wc_railticket');
            }
            if ($keyparts[5] != 'AAAAAAAAAAAAAAAAA') {
                $dtype = DiscountType::get_discount_type($keyparts[5]);
                $nline[] = $dtype->get_name();
            } else {
                $nline[] = '';
                $dtype = '';
            }
            $nline[] = $linevalue;

            $fare = $this->get_bookable_day()->fares->get_fare($from, $to, $keyparts[2], $keyparts[3], $keyparts[4], $dtype, $special);
            $nline[] = number_format($fare, 2);
            $nline[] = number_format($linevalue * $fare, 2);
            fputcsv($f, $nline);
        }

        fputcsv($f, array());
        fputcsv($f, array());

        $travellers = array();
        foreach ($this->totaltravellers as $key => $total) {
            fputcsv($f, array(\wc_railticket\FareCalculator::get_traveller($key)->name, $total));
        }

        fputcsv($f, array());
        fputcsv($f, array());

        fputcsv($f, array('Total Passengers', $this->totalseats, ' ', 'Total Manual Booking Revenue', $this->totalmanual));
        fputcsv($f, array('Total Tickets', $this->totaltickets, ' ', 'Total Online Bookings Revenue', $this->totalwoo));
        fputcsv($f, array('Total One Way Journeys', $this->totaljourneys, ' ', 'Total Guards Price Revenue', $this->totalguardprice));
        fputcsv($f, array('Total Supplement Revenue', $this->totalsupplements, ' ', 'Total Online Price Revenue', $this->totalonlineprice));
        fputcsv($f, array('Total Discount Deductions', $this->totaldiscounts, ' ', 'Total Revenue', $this->totalmanual + $this->totalwoo));

        fputcsv($f, array());
        fputcsv($f, array('Guard Breakdown'));
        fputcsv($f, array());
        foreach ($gts as $gt) {
            fputcsv($f, array($gt->name, $gt->total));
        }

        fputcsv($f, array());
        fputcsv($f, array('Discount Passengers Breakdown'));
        fputcsv($f, array());
        foreach ($this->discountuse as $dcode => $num) {
            $discount = DiscountType::get_discount_type($dcode);
            fputcsv($f, array($discount->get_name(), $num));
        }

        fclose($f);
        return;
    }

    function show_waybill_html($header, $gts) {
        global $rtmustache;
        wp_register_style('railticket_style', plugins_url('wc-product-railticket/ticketbuilder.css'));
        wp_enqueue_style('railticket_style');

        $plines = array();
        foreach ($this->lines as $linekey => $linevalue) {
            $keyparts = explode('|', $linekey);

            $from = $this->get_bookable_day()->timetable->get_station($keyparts[0]);
            $to = $this->get_bookable_day()->timetable->get_station($keyparts[1]);
            $nline = new \stdclass();

            if (count($keyparts) == 7) {
                $special = true;
                $nline->journeytype = __('Special', 'wc_railticket');
            } else {
                $special = false;
                $nline->journeytype = __(ucfirst($keyparts[2]), 'wc_railticket');
            }

            $nline->from = $from->get_name();
            $nline->to = $to->get_name();
            $nline->tickettype = $this->get_bookable_day()->fares->get_ticket_name($keyparts[3]);
            if ($keyparts[4] == 'price') {
                $nline->faretype = __('Online', 'wc_railticket');
            } else {
                $nline->faretype = __('Guard', 'wc_railticket');
            }
            if ($keyparts[5] != 'AAAAAAAAAAAAAAAAA') {
                $dtype = DiscountType::get_discount_type($keyparts[5]);
                $nline->discounttype = $dtype->get_name();
            } else {
                $dtype = false;
            }

            $nline->number = $linevalue;
            $nline->fare = $this->get_bookable_day()->fares->get_fare($from, $to, $keyparts[2], $keyparts[3], $keyparts[4], $dtype, $special);
            $nline->total = number_format($linevalue * $nline->fare, 2);
            $nline->fare = number_format($nline->fare, 2);
            $plines[] = $nline;
        }

        $travellers = array();
        foreach ($this->totaltravellers as $key => $total) {
            $traveller = new \stdclass();
            $traveller->total = $total;
            $traveller->name = \wc_railticket\FareCalculator::get_traveller($key)->name;
            $travellers[] = $traveller;
        }

        $alldata = new \stdclass();
        $alldata->date = $this->get_bookable_day()->get_date();
        $alldata->dateformatted = $this->get_bookable_day()->get_date(true);
        $alldata->header = $header;

        $alldata->lines = $plines;

        $alldata->totalrevenue = number_format($this->totalmanual + $this->totalwoo, 2);
        $alldata->totalseats = $this->totalseats;
        $alldata->totaltickets = $this->totaltickets;
        $alldata->totaljourneys = $this->totaljourneys;
        $alldata->guards = array_values($this->guardtotals);
        $alldata->totalmanual = number_format($this->totalmanual, 2);
        $alldata->totalwoo = number_format($this->totalwoo, 2);
        $alldata->totalonlineprice = number_format($this->totalonlineprice, 2);
        $alldata->totalguardprice = number_format($this->totalguardprice, 2);
        $alldata->totaldiscounts = number_format($this->totaldiscounts, 2);
        $alldata->totalsupplements = number_format($this->totalsupplements, 2);

        $alldata->guards = $gts;

        $alldata->travellers = $travellers;

        $alldata->url = railticket_get_page_url();

        $template = $rtmustache->loadTemplate('waybill');
        echo $template->render($alldata);
    }

    function get_waybill_json() {
        $data = new \stdclass();
        $data->lines = $this->lines;
        $data->totalseats = $this->totalseats;
        $data->totaltickets = $this->totaltickets;
        $data->totalsupplements = $this->totalsupplements;
        $data->totaldiscounts = $this->totaldiscounts;
        $data->totalwoo = $this->totalwoo;
        $data->totalmanual = $this->totalmanual;
        $data->guardtotals = $this->guardtotals;
        $data->totaljourneys = $this->totaljourneys;
        $data->totalonlineprice = $this->totalonlineprice;
        $data->totalguardprice = $this->totalguardprice;
        $data->totaltravellers = $this->totaltravellers;
        $data->discountuse = $this->discountuse;

        return json_encode($data);
    }
}
