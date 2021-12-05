<?php
namespace wc_railticket;
defined('ABSPATH') or die('No script kiddies please!');

class Waybill extends Report {

    function __construct($date) {
        global $wpdb;
        $this->date = $date;
        $this->bookableday = BookableDay::get_bookable_day($this->date);
        $this->bookings = $this->bookableday->get_all_bookings();
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

        $bookingids = $this->bookableday->get_all_order_ids();
        foreach ($bookingids as $bookingid) {
            $bookingorder = BookingOrder::get_booking_order($bookingid);
            $bookings = $bookingorder->get_bookings();

            $this->totalseats += $bookingorder->get_seats();
            $this->totaltickets += $bookingorder->total_tickets();
            $this->totaljourneys += $bookingorder->get_journeys();
            $this->totaldiscounts += $bookingorder->get_discount();
            $this->totalsupplements += $bookingorder->get_supplement();

            if ($bookingorder->is_manual()) {
                $this->totalmanual += $bookingorder->get_price();
                $createdby = $bookingorder->get_created_by();
                if (array_key_exists($createdby, $this->guardtotals)) {
                    $this->guardtotals[$createdby]+= $bookingorder->get_price();
                } else {
                    $this->guardtotals[$createdby] = $bookingorder->get_price();
                }
            } else {
                $this->totalwoo += $bookingorder->get_price();
            }

            if ($bookingorder->is_guard_price()) {
                $this->totalguardprice += $bookingorder->get_price();
                $faretype = 'localprice';
            } else {
                $this->totalonlineprice += $bookingorder->get_price();
                $faretype = 'price';
            }

            // Construct a line key, then test to see if we have a match so we know if we have to increment an existing total
            // Or put in a new one
            // The key will be fromstnid|tostnid|journeytype|tickettype|faretype|discounttype|special (optional) (yes quite a lot....)

            // Account for the special case that the round trips go from-to the same station
            if ($bookingorder->get_journeytype() == 'round') {
                $tostn = $bookings[0]->get_from_station();
            } else {
                $tostn = $bookings[0]->get_to_station();
            }

            // If no discount type, the set a placeholder which will sort to the top
            $discounttype = $bookingorder->get_discount_type();

            foreach ($bookingorder->get_tickets() as $ticket => $num) {
                $discounttypesn = 'AAAAAAAAAAAAAAAAA';
                // If we have a discount add it as a sorting key
                if ($discounttype) {
                    if ($discounttype->use_custom_type()) {
                        // If we have custom travellers, check this is a custom traveller and that it is valid for the ticket type
                        $tparts = explode('/', $ticket);
                        if (count($tparts) == 2 && $discounttype->ticket_has_discount($tparts[0])) {
                            $discounttypesn = $discounttype->get_shortname();
                        }
                    } else {
                        // If we are not using custom travellers, then simply check if the discount is valid for the ticket type
                        if ($discounttype->ticket_has_discount($ticket)) {
                            $discounttypesn = $discounttype->get_shortname();
                        }
                    }
                } 

                $linekey = $bookings[0]->get_from_station()->get_stnid().'|'.
                    $tostn->get_stnid().'|'.
                    $bookingorder->get_journeytype().'|'.
                    $ticket.'|'.$faretype.'|'.$discounttypesn;

                if ($bookings[0]->is_special()) {
                    $linekey .= '|special';
                }

                if (array_key_exists($linekey, $this->lines)) {
                    $this->lines[$linekey] += $num;
                } else {
                    $this->lines[$linekey] = $num;
                }
            }    
        }

        foreach ($this->lines as $key => $numsold) {
            $keyparts = explode('|', $key);
            $ticketcomp = \wc_railticket\FareCalculator::get_ticket_composition($keyparts[3]);
            foreach ($ticketcomp as $traveller => $number) {
                if (!array_key_exists($traveller, $this->totaltravellers)) {
                    $this->totaltravellers[$traveller] = 0;
                }
                $this->totaltravellers[$traveller] += $number * $numsold;
            }
        }

        uksort($this->lines, function ($a, $b) {
            $a = mb_strtolower($a);
            $b = mb_strtolower($b);
            return strcmp($a, $b);
        });
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

    function show_waybill_csv($header, $gts) {
        header('Content-Type: application/csv');
        header('Content-Disposition: attachment; filename="ordersummary-' . $this->date . '.csv";');
        header('Pragma: no-cache');
        $f = fopen('php://output', 'w');
        fputcsv($f, array('Date', $this->date));
        fputcsv($f, array('', '', '', '', '', ''));
        fputcsv($f, $header);

        foreach ($this->lines as $linekey => $linevalue) {
            $keyparts = explode('|', $linekey);

            $from = $this->bookableday->timetable->get_station($keyparts[0]);
            $to = $this->bookableday->timetable->get_station($keyparts[1]);

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
            $nline[] = $this->bookableday->fares->get_ticket_name($keyparts[3]);
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

            $fare = $this->bookableday->fares->get_fare($from, $to, $keyparts[2], $keyparts[3], $keyparts[4], $dtype, $special);
            $nline[] = number_format($fare, 2);
            $nline[] = number_format($linevalue * $fare, 2);
            fputcsv($f, $nline);
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

            $from = $this->bookableday->timetable->get_station($keyparts[0]);
            $to = $this->bookableday->timetable->get_station($keyparts[1]);
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
            $nline->tickettype = $this->bookableday->fares->get_ticket_name($keyparts[3]);
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
            $nline->fare = $this->bookableday->fares->get_fare($from, $to, $keyparts[2], $keyparts[3], $keyparts[4], $dtype, $special);
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
        $alldata->date = $this->bookableday->get_date();
        $alldata->dateformatted = $this->bookableday->get_date(true);
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

}
