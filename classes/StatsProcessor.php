<?php

namespace wc_railticket;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class StatsProcessor {

    private $bookableday;

    public function __construct(BookableDay $bookableday) {
        $this->bookableday = $bookableday;
    }

    public function updateStats() {
        global $wpdb;

        $orders = $this->bookableday->get_all_order_ids();
        $seats = 0;
        $revenue = 0;
        $maxload = 0;
        $prebook1 = 0;
        $prebook2 = 0;
        $totalonline = 0;
        $totalmanual = 0;
        $allpostcodes = array();
        $postcodefirst = array();
        $postcodezone = array();

        $dtz = new \DateTimeZone(get_option('timezone_string'));
        $pbdate1 = \DateTime::createFromFormat('Y-m-d H:i', $this->bookableday->get_date().' 18:00', $dtz);
        $pbdate1 = $pbdate1->modify( '-1 day' );
        $pbdate2 = \DateTime::createFromFormat('Y-m-d H:i', $this->bookableday->get_date().' 09:00', $dtz);

        foreach ($orders as $bookingid) {
            $bookingorder = BookingOrder::get_booking_order($bookingid);
            $seats += $bookingorder->get_seats();

            if ($bookingorder->is_manual()) {
                $totalmanual++;
            } else {
                $totalonline++;
            }

            $revenue += $bookingorder->get_price();

            $created = $bookingorder->get_created();
            if ($created < $pbdate1) {
                $prebook1 += $bookingorder->get_seats();
            }
            if ($created < $pbdate2) {
                $prebook2 += $bookingorder->get_seats();
            }

            $postcode = trim($bookingorder->get_postcode());
            if (strlen($postcode) == 0) {
                continue;
            }

            $postcode = $this->postcode_format($postcode);
            if (strlen($postcode) < 6) {
                continue;
            }

            if (is_numeric($postcode)) {
                continue;
            }

            $postcodeparts = explode(' ', $postcode);
            if ($postcodeparts == null) {
                continue;
            } 

            if (count($postcodeparts) > 0) {
                if (!array_key_exists($postcode, $allpostcodes)) {
                    $allpostcodes[$postcode] = 1;
                } else {
                    $allpostcodes[$postcode] ++;
                }

                $postcodeparts[0] = trim($postcodeparts[0]);
                if (is_numeric($postcodeparts[0])) {
                    continue;
                }
                if (strlen($postcodeparts[0]) > 1) {
                    if (!array_key_exists($postcodeparts[0], $postcodefirst)) {
                        $postcodefirst[$postcodeparts[0]] = 1;
                    } else {
                        $postcodefirst[$postcodeparts[0]] ++;
                    }
                }
                $letters = trim(preg_replace('#^([a-z]+).*#i','$1', $postcode));
                if (strlen($letters) > 0 && strlen($letters) < 3) {
                    if (!array_key_exists($letters, $postcodezone)) {
                        $postcodezone[$letters] = 1;
                    } else {
                        $postcodezone[$letters] ++;
                    }
                }
            }
        }

        $stations = $this->bookableday->timetable->get_stations();
        $tostationup = $this->bookableday->timetable->get_terminal('up');
        $tostationdown = $this->bookableday->timetable->get_terminal('down');
        foreach ($stations as $station) {
            $maxload = $this->check_max_load($station, $tostationup, $this->bookableday->timetable->get_up_deps($station), $maxload);
            $maxload = $this->check_max_load($station, $tostationdown,  $this->bookableday->timetable->get_down_deps($station), $maxload);
        }

        $specials = \wc_railticket\Special::get_specials($this->bookableday->get_date());

        $rec = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}wc_railticket_stats WHERE date = '".$this->bookableday->get_date()."'");
        if ($rec) {
            $rec->passengers = $seats;
            $rec->orders = count($orders);
            $rec->totalmanual = $totalmanual;
            $rec->totalonline = $totalonline;
            $rec->revenue = $revenue;
            $rec->maxload = $maxload;
            $rec->prebook1 = $prebook1;
            $rec->prebook2 = $prebook2;
            $rec->postcodes = json_encode($allpostcodes);
            $rec->postcodefirst = json_encode($postcodefirst);
            $rec->postcodezone = json_encode($postcodezone);
            $wpdb->update("{$wpdb->prefix}wc_railticket_stats", (array) $rec, array('id' => $rec->id));
        } else {
            $rec = new \stdclass();
            $rec->date = $this->bookableday->get_date();
            $rec->passengers = $seats;
            $rec->orders = count($orders);
            $rec->totalmanual = $totalmanual;
            $rec->totalonline = $totalonline;
            $rec->revenue = $revenue;
            $rec->maxload = $maxload;
            $rec->prebook1 = $prebook1;
            $rec->prebook2 = $prebook2;
            $rec->postcodes = json_encode($allpostcodes);
            $rec->postcodefirst = json_encode($postcodefirst);
            $rec->postcodezone = json_encode($postcodezone);
            $wpdb->insert("{$wpdb->prefix}wc_railticket_stats", (array) $rec);
        }
    }


    private function postcode_format($postcode) {
        //remove non alphanumeric characters
        $cleanPostcode = preg_replace("/[^A-Za-z0-9]/", '', $postcode);
 
        //make uppercase
        $cleanPostcode = strtoupper($cleanPostcode);
 
        //insert space
        $postcode = substr($cleanPostcode, 0, -3) . " " . substr($cleanPostcode, -3);
 
        return trim($postcode);
    }

    private function check_max_load($fromstation, $tostation, $deps, $cmax) {
        foreach ($deps as $dep) {
            $ts = new TrainService($this->bookableday, $fromstation, $dep->key, $tostation);
            $bookings = $ts->get_bookings();
            $seats = 0;
            foreach ($bookings as $booking) {
                $seats += $booking->get_seats();
            }
            if ($seats > $cmax) {
                $cmax = $seats;
            }
        }
        return $cmax;
    }
}
