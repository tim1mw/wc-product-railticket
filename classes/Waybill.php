<?php
namespace wc_railticket;
defined('ABSPATH') or die('No script kiddies please!');

class Waybill extends Report {

    function __construct($date) {
        global $wpdb;
        $this->date = $date;
        $this->bookableday = BookableDay::get_bookable_day($this->date);
        $bookings = $this->bookableday->get_all_bookings();
        $processed = array();
        $totals = array();
        $totalseats = 0;
        $totaltickets = 0;
        $totalsupplement = 0;
        $totalwoo = 0;
        $totalmanual = 0;
        $guardtotals = array();
        $totaljourneys = 0;

        foreach ($bookings as $booking) {
            if (strlen($booking->woocartitem) > 0) {
                continue;
            }

            if ($booking->wooorderid > 0 && !in_array($booking->wooorderid, $processed)) {
                $data_store = \WC_Data_Store::load('order-item');
                $totalseats+= $data_store->get_metadata($booking->wooorderitem, "tickettimes-totalseats", true);
                $journeytype = $data_store->get_metadata($booking->wooorderitem, "tickettimes-journeytype", true);
                $totalsupplement+= $data_store->get_metadata($booking->wooorderitem, "tickettimes-pricesupplement", true);
                if ($journeytype == 'single') {
                    $totaljourneys+= $data_store->get_metadata($booking->wooorderitem, "tickettimes-totalseats", true);
                } else {
                    $totaljourneys+= ($data_store->get_metadata($booking->wooorderitem, "tickettimes-totalseats", true) * 2);
                }
                $ta = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE order_item_id = " . $booking->wooorderitem . " AND meta_key LIKE 'ticketsallocated-%'");
                $ticketsallocated = array();
                foreach ($ta as $ticket) {
                    $tparts = explode('-', $ticket->meta_key);
                    $ticketsallocated[$tparts[1]] = intval($ticket->meta_value);
                }
                $processed[] = $booking->wooorderid;
                $totalwoo+= $data_store->get_metadata($booking->wooorderitem, "_line_total", true);
            } elseif ($booking->manual > 0 && !in_array('M' . $booking->manual, $processed)) {
                $processed[] = 'M' . $booking->manual;
                $mb = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_railticket_manualbook WHERE id = " . $booking->manual) [0];
                $totalseats+= $mb->seats;
                $journeytype = $mb->journeytype;

                if ($journeytype == 'single') {
                    $totaljourneys+= $mb->seats;
                } else {
                    $totaljourneys+= ($mb->seats * 2);
                }

                $ticketsallocated = (array)json_decode($mb->tickets);
                $totalmanual+= $mb->price;
                $totalsupplement+= $mb->supplement;

                if (array_key_exists($mb->createdby, $guardtotals)) {
                    $guardtotals[$mb->createdby]+= $mb->price;
                } else {
                    $guardtotals[$mb->createdby] = $mb->price;
                }

            } else {
                //Bookings should all be manual or woocommerce
                continue;
            }

            if (!array_key_exists($booking->fromstation, $totals)) {
                $totals[$booking->fromstation] = array();
            }

            if (!array_key_exists($booking->tostation, $totals[$booking->fromstation])) {
                $totals[$booking->fromstation][$booking->tostation] = array();
            }

            if (!array_key_exists($journeytype, $totals[$booking->fromstation][$booking->tostation])) {
                $totals[$booking->fromstation][$booking->tostation][$journeytype] = array();
            }

            foreach ($ticketsallocated as $ticket => $qty) {
                $totaltickets+= $qty;
                if (!array_key_exists($ticket, $totals[$booking->fromstation][$booking->tostation][$journeytype])) {
                    $totals[$booking->fromstation][$booking->tostation][$journeytype][$ticket] = $qty;
                } else {
                    $totals[$booking->fromstation][$booking->tostation][$journeytype][$ticket]+= $qty;
                }
            }
        }

        $this->totals = $totals;
        $this->totalseats = $totalseats;
        $this->totaltickets = $totaltickets;
        $this->totalsupplement = $totalsupplement;
        $this->totalwoo = $totalwoo;
        $this->totalmanual = $totalmanual;
        $this->guardtotals = $guardtotals;
        $this->bookings = $bookings;
        $this->totaljourneys = $totaljourneys;
    }

    function show_waybill($iscsv) {
        global $wpdb;

        wp_register_style('railticket_style', plugins_url('wc-product-railticket/ticketbuilder.css'));
        wp_enqueue_style('railticket_style');

        $header = array('Journey', 'Journey Type', 'Ticket Type', 'Number', 'Fare', 'Total');
        $td = array('Date', $this->date);

        if ($iscsv) {;
            header('Content-Type: application/csv');
            header('Content-Disposition: attachment; filename="waybill-' . $this->date . '.csv";');
            header('Pragma: no-cache');
            $f = fopen('php://output', 'w');
            fputcsv($f, $td);
            fputcsv($f, array('', '', '', '', '', ''));
            fputcsv($f, $header);
        } else {
            echo "<table border='1' class='railticket_admintable'>";
            $this->report_row($td);
            $this->report_row(array('', '', '', '', '', ''));
            $this->report_row($header, 'th');
        }

        $stn = $this->bookableday->timetable->get_stations();

        foreach ($this->totals as $stationone => $dataone) {
            foreach ($dataone as $stationtwo => $datatwo) {
                foreach ($datatwo as $journeytype => $datathree) {
                    ksort($datathree);
                    foreach ($datathree as $tickettype => $qty) {
                        $sql = "SELECT {$wpdb->prefix}wc_railticket_prices.id, " .
                            "{$wpdb->prefix}wc_railticket_prices.tickettype, " .
                            "{$wpdb->prefix}wc_railticket_prices.price, " .
                            "{$wpdb->prefix}wc_railticket_tickettypes.name " .
                            "FROM {$wpdb->prefix}wc_railticket_prices " .
                            "INNER JOIN {$wpdb->prefix}wc_railticket_tickettypes ON " .
                            "{$wpdb->prefix}wc_railticket_tickettypes.code = {$wpdb->prefix}wc_railticket_prices.tickettype " .
                            "WHERE ((stationone = " . $stationone .
                            " AND stationtwo = " . $stationtwo . ") OR " .
                            "(stationone = " . $stationtwo . " AND stationtwo = " . $stationone . ")) AND " .
                            "journeytype = '" . $journeytype . 
                            "' AND {$wpdb->prefix}wc_railticket_tickettypes.code ='" . $tickettype . "' AND ".
                            "{$wpdb->prefix}wc_railticket_prices.revision = ".$this->bookableday->get_price_revision();
                        $ticketdata = $wpdb->get_results($sql, OBJECT);
                        $line = array($stn[$stationone]->get_name() . " - " . $stn[$stationtwo]->get_name(), $journeytype, $tickettype, $qty, $ticketdata[0]->price, $qty * $ticketdata[0]->price);
                        if ($iscsv) {
                            fputcsv($f, $line);
                        } else {
                            $this->report_row($line);
                        }
                    }
                }
            }
        }

        $summary = array();
        $summary[] = array('Total Supplements', '', '', '', '', $this->totalsupplement);
        $summary[] = array('Total Revenue', '', '', '', '', $this->totalmanual + $this->totalwoo);
        $summary[] = array('', '', '', '', '', '');
        $summary[] = array('Total Passengers', $this->totalseats);
        $summary[] = array('Total Tickets', $this->totaltickets);
        $summary[] = array('Total One Way Journeys', $this->totaljourneys);

        foreach ($this->guardtotals as $id => $total) {
            if ($id > 0) {
                $u = get_userdata($id);
            } else {
                $u = new \stdclass();
                $u->first_name = 'Unknown';
                $u->last_name = 'User (id=' . $id . ')';
            }
            $summary[] = array('Manual Bookings - ' . $u->first_name . ' ' . $u->last_name, $total);
        }

        $summary[] = array('Total Manual Booking Revenue', $this->totalmanual);
        $summary[] = array('Total Online Bookings Revenue', $this->totalwoo);

        if ($iscsv) {
            foreach ($summary as $s) {
                fputcsv($f, $s);
            }
            fclose($f);
        } else {
            foreach ($summary as $s) {
                $this->report_row($s);
            }
            echo "</table>";
         ?>
        <p><form method='post' action='<?php echo railticket_get_page_url() ?>'>
            <input type='hidden' name='action' value='viewwaybill' />
            <input type='hidden' name='dateofjourney' value='<?php echo $this->date; ?>' />
            <input type='submit' name='submit' value='Refresh Data' />
        </form><br />
        <form action='<?php echo railticket_get_page_url() ?>' method='post'>
            <input type='hidden' name='action' value='filterbookings' />
            <input type='hidden' name='dateofjourney' value='<?php echo $this->date; ?>' />
            <input type='submit' name='submit' value='Back to Services' />
        </form></p>
        <?php
        }
    }

}
