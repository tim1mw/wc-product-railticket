<?php
namespace wc_railticket;
defined('ABSPATH') or die('No script kiddies please!');

class Waybill extends WaybillBase {

    function __construct($date) {
        global $wpdb;
        parent::__construct();
        $this->date = $date;
        $this->bookableday = BookableDay::get_bookable_day($this->date);
        $this->bookings = $this->bookableday->get_all_bookings();

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

                    $dsn = $discounttype->get_shortname();
                    if ($discounttype->use_custom_type()) {
                        // If we have custom travellers, check this is a custom traveller and that it is valid for the ticket type
                        $tparts = explode('/', $ticket);
                        if (count($tparts) == 2 && $discounttype->ticket_has_discount($tparts[0])) {
                            $discounttypesn = $dsn;
                        }
                    } else {
                        // If we are not using custom travellers, then simply check if the discount is valid for the ticket type
                        if ($discounttype->ticket_has_discount($ticket)) {
                            $discounttypesn = $dsn;
                        }
                    }

                    if (array_key_exists($dsn, $this->discountuse)) {
                        $this->discountuse[$dsn] += $num;
                    } else {
                        $this->discountuse[$dsn] = $num;
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

    function get_date_display() {
        return $this->date;
    }

    function get_bookable_day() {
        return $this->bookableday;
    }

}
