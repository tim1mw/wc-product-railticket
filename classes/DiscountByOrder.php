<?php
namespace wc_railticket;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class DiscountByOrder extends Discount {

    public function __construct($data, $fromstation, $tostation, $journeytype, $dateoftravel, $order) {
        parent::__construct($data, $fromstation, $tostation, $journeytype, $dateoftravel);

        $this->order = $order;
        $this->message = false;

        // No point in doing further validation tests if we have already failed.
        if (!$this->valid) {
            return;
        }

        switch ($journeytype) {
            case 'single': $this->triplegs = 1; break;
            case 'return': $this->triplegs = 2; break;
            case 'round': $this->triplegs = 3; break;
            default: return;
        }

        if (!property_exists($this->data->rules, 'maxlegs') || $this->data->rules->maxlegs < 0) {
            return;
        }

        $legsrequested = $this->triplegs * $this->order->get_seats();
        $legsavailable = $this->data->rules->maxlegs * $this->order->get_seats();
        $legsbooked = $this->count_legs_booked();
        $legsleft = $legsavailable - $legsbooked;

        if ($legsrequested > $legsleft) {
            $this->valid = false;
            $this->message = "You have insufficient trips left to make this booking, ".($legsbooked/$this->order->get_seats()).
                " out of ".$this->data->rules->maxlegs." single trips used.";
        } else {
            $this->message = "Valid code: You currently have ".$legsavailable." single trips remaining for all passengers.";
        }
    }

    public static function get_discount($code, $fromstation, $tostation, $journeytype, $dateoftravel) {
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

        // This is a list of potential discounts in order of priority. Work through it till we get a valid one.
        $dtypes = explode(',', $dtypes[0]);
        foreach ($dtypes as $type) {
            $do = self::get_discountbyorder($code, $type, $fromstation, $tostation, $journeytype, $dateoftravel, $order);
            if (!$do) {
                continue;
            }

            if ($do->is_valid()) {
                return $do;
            }
        }

        // Nothing was valid, return the last invalid one.
        return $do;
    }

    private static function get_discountbyorder($code, $type, $fromstation, $tostation, $journeytype, $dateoftravel, $order) {
        global $wpdb;
        $data = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}wc_railticket_discounts ".
            "WHERE shortname = '".$type."'");

        if (!$data) {
            // Invalid discount type, give up...
            return false;
        }

        $bk = BookableDay::get_bookable_day($dateoftravel);
        if (in_array($data->shortname, $bk->get_discount_exclude())) {
            return false;
        }

        $data->code = $code;
        $data->start = $order->get_date();
        $data->end = $data->start;
        $data->single = 0;
        $data->disabled = 0;

        return new DiscountByOrder($data, $fromstation, $tostation, $journeytype, $dateoftravel, $order);
    }

    private function count_legs_booked() {
        return 8;
    }

    public function get_travellers() {
        return $this->order->get_travellers();
    }

    public function get_max_seats() {
        // We need to use the max seats from the linked order for this type of discount
        return $this->order->get_seats();
    }

    public function lock_travellers() {
        if (!property_exists($this->data->rules, 'locktravellers')) {
            return false;
        }

        return $this->data->rules->locktravellers;
    }

    public function get_message() {
        if ($this->message) {
            return $this->message;
        }

        return parent::get_message();
    }

    public function apply_price_rule($tickettype, $price) {
        $price = parent::apply_price_rule($tickettype, $price);

        if (property_exists($this->data->rules, 'pricelegmultiply') && $this->data->rules->pricelegmultiply == true) {
            return $price * $this->triplegs;
        }

        return $price;
    }
}
