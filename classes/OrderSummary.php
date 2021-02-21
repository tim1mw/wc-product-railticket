<?php
namespace wc_railticket;
defined('ABSPATH') or die('No script kiddies please!');

class OrderSummary extends Report{

    function __construct($date) {
        $this->date = $date;
        $this->bookableday = BookableDay::get_bookable_day($this->date);
    }

    function show_summary($iscsv) {
        global $wpdb;

        wp_register_style('railticket_style', plugins_url('wc-product-railticket/ticketbuilder.css'));
        wp_enqueue_style('railticket_style');

        $stns = $this->bookableday->timetable->get_stations();
        $header = array('Order ID', 'Name', 'Email', 'Phone', 'From', 'To', 'Journey Type', 
            'Tickets', 'Seats', 'Supplement', 'Total Price', 'Notes');
        $td = array('Date', $this->date);

        if ($iscsv) {
            header('Content-Type: application/csv');
            header('Content-Disposition: attachment; filename="ordersummary-' . $this->date . '.csv";');
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

        $bookings = $this->bookableday->get_all_bookings();
        $processed = array();
        $lines = array();

        foreach ($bookings as $booking) {
            if (strlen($booking->woocartitem) > 0) {
                continue;
            }

            $line = array();

            if ($booking->wooorderid > 0 && !in_array($booking->wooorderid, $processed)) {
                $processed[] = $booking->wooorderid;
                $order = wc_get_order($booking->wooorderid);
                $data_store = \WC_Data_Store::load('order-item');

                if ($iscsv) {
                    $line[] = $booking->wooorderid;
                } else {
                    $line[] = "<form action='" . railticket_get_page_url() . "' method='post'>" .
                        "<input type='hidden' name='action' value='showorder' />" .
                        "<input type='hidden' name='orderid' value='" . $booking->wooorderid . "' />" .
                        "<input type='submit' value='" . $booking->wooorderid . "' />" .
                        "</form>";
                }

                $line[] = $order->get_formatted_billing_full_name();
                $line[] = $order->get_billing_email();
                $line[] = $order->get_billing_phone();
                $line[] = $stns[$data_store->get_metadata($booking->wooorderitem, "tickettimes-fromstation") ]->get_name();
                $line[] = $stns[$data_store->get_metadata($booking->wooorderitem, "tickettimes-tostation") ]->get_name();
                $line[] = $data_store->get_metadata($booking->wooorderitem, "tickettimes-journeytype", true);
                $ta = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE order_item_id = " .
                    $booking->wooorderitem . " AND meta_key LIKE 'ticketsallocated-%'");
                $ticketsallocated = '';

                foreach ($ta as $ticket) {
                    $tparts = explode('-', $ticket->meta_key);
                    $ticketsallocated.= $tparts[1] . " x" . $ticket->meta_value . ", ";
                }

                $line[] = $ticketsallocated;
                $line[] = $data_store->get_metadata($booking->wooorderitem, "tickettimes-totalseats", true);
                $line[] = $data_store->get_metadata($booking->wooorderitem, "tickettimes-pricesupplement", true);
                $line[] = $data_store->get_metadata($booking->wooorderitem, "_line_total", true);
                $key = $order->get_billing_last_name() . " " . $order->get_billing_first_name() . " " . wp_generate_uuid4();
                $notes = wc_get_order_notes(['order_id' => $order->get_id(), 'type' => 'customer', ]);
                $line[] = $order->get_customer_note();
                $lines[$key] = $line;
            } elseif ($booking->manual > 0 && !in_array('M' . $booking->manual, $processed)) {
                $processed[] = 'M' . $booking->manual;

                if ($iscsv) {
                    $line[] = 'M' . $booking->manual;
                } else {
                    $line[] = "<form action='" . railticket_get_page_url() . "' method='post'>" .
                        "<input type='hidden' name='action' value='showorder' />" .
                        "<input type='hidden' name='orderid' value='M" . $booking->manual . "' />" .
                        "<input type='submit' value='M" . $booking->manual . "' />" . "</form>";
                }

                $line[] = '';
                $line[] = '';
                $line[] = '';

                $mb = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_railticket_manualbook WHERE id = " . $booking->manual) [0];
                $booking = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_railticket_bookings WHERE manual = " . $booking->manual) [0];

                $line[] = $stns[$booking->fromstation]->get_name();
                $line[] = $stns[$booking->tostation]->get_name();
                $line[] = $mb->journeytype;
                $ta = (array)json_decode($mb->tickets);
                $ticketsallocated = '';

                foreach ($ta as $ticket => $num) {
                    $ticketsallocated.= $ticket . " x" . $num . ", ";
                }

                $line[] = $ticketsallocated;
                $line[] = $mb->seats;
                $line[] = $mb->supplement;
                $line[] = $mb->price;
                $line[] = $mb->notes;
                $lines['zzzzzzzzzzzz' . $booking->manual] = $line;
            } else {
                //Bookings should all be manual or woocommerce
                continue;
            }
        }

        uksort($lines, function ($a, $b) {
            $a = mb_strtolower($a);
            $b = mb_strtolower($b);
            return strcmp($a, $b);
        });

        if ($iscsv) {
            foreach ($lines as $line) {
                fputcsv($f, $line);
            }
            fclose($f);
        } else {
            foreach ($lines as $line) {
                $this->report_row($line);
            }
            echo "</table>";
?>
        <p><form method='post' action='<?php echo railticket_get_page_url() ?>'>
        <input type='hidden' name='action' value='viewordersummary' />
        <input type='hidden' name='dateofjourney' value='<?php echo $this->date; ?>' />
        <input type='submit' name='submit' value='Refresh Data' />
        </form><br />
        <form action='<?php echo railticket_get_page_url() ?>' method='post'>
        <input type='hidden' name='action' value='filterbookings' />
        <input type='hidden' name='dateofjourney' value='<?php echo $this->date; ?>' />
        <input type='submit' name='submit' value='Back to Services' />
        </form>
        </p>
        <?php
        }
    }
}
