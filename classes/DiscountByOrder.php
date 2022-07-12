<?php
namespace wc_railticket;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class DiscountByOrder extends Discount {

    public function __construct($data, $fromstation, $tostation, $journeytype, $dateoftravel, $order) {
        parent::__construct($data, $fromstation, $tostation, $journeytype, $dateoftravel);

        $this->order = $order;
    }

    public static function get_discount($code, $fromstation, $tostation, $journeytype, $dateoftravel) {
        global $wpdb;
        $order = BookingOrder::get_booking_order($code);
        if (!$order) {
            return false;
        }

        $dtypes = $order->get_discountcode_ticket_codes();

        if (!$dtypes) {
            return false;
        }

        if (count($dtypes) > 1) {
            // TODO ??? We can't cope if there is more than one ticket linked discount type here, so behave as though there is no discount
            return false;
        }

        $data = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}wc_railticket_discounts ".
            "WHERE shortname = '".$dtypes[0]."'");

        if (!$data) {
            // Invalid discount type, give up...
            return false;
        }

        $data->code = $dtypes[0];
        $data->start = $order->get_date();
        $data->end = $data->start;
        $data->single = 0;
        $data->disabled = 0;

        $bk = BookableDay::get_bookable_day($dateoftravel);
        if (in_array($data->shortname, $bk->get_discount_exclude())) {
            return false;
        }

        return new DiscountByOrder($data, $fromstation, $tostation, $journeytype, $dateoftravel, $order);
    }



    public function get_max_seats() {
        // We need to use the max seats from the linked order for this type of discount
        return $this->order->get_seats();
    }
}
