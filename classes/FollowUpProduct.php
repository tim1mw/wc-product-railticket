<?php

namespace wc_railticket;
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class FollowUpProduct {

    private $data;

    public const BOOKABLE = "bookable";
    public const SPECIAL = "special";

    public const RTYPE_PERSEAT = "perseat";
    public const RTYPE_FIXED = "fixed";
    public const RTYPE_PERCENT = "percent";
    public const RTYPE_ORIGINAL = "original";

    private function __construct($data) {
        $this->data = $data;
        $this->data->use_choice = (bool) $this->data->use_choice;
        $this->data->data = json_decode($this->data->data);
    }

    public static function get_follow_ups_bookable(\wc_railticket\BookableDay $bookableday) {
        return self::get_follow_up_products($bookableday->get_id(), self::BOOKABLE);
    }

    public static function get_follow_ups_special(\wc_railticket\Special $special) {
        return self::get_follow_up_products($special->get_id(), self::SPECIAL);
    }

    public static function get_follow_ups_ticketbuilder(\wc_railticket\TicketBuilder $tb) {
        if ($tb->get_special()) {
            return self::get_follow_ups_special($tb->get_special());
        } else {
            return self::get_follow_ups_bookable($tb->get_bookable_day());
        }
    }

    public static function get_follow_ups_bookingorder(\wc_railticket\BookingOrder $bo) {
        if ($bo->is_special()) {
            return self::get_follow_ups_special($bo->get_special());
        } else {
            return self::get_follow_ups_bookable($bo->get_bookable_day());
        }
    }

    private static function get_follow_up_products($entity, $type) {
        global $wpdb;
        $sql = "SELECT fp.* FROM {$wpdb->prefix}wc_railticket_followupprod fp ".
            "INNER JOIN {$wpdb->prefix}wc_railticket_productlink pl ON pl.follow_id = fp.id ".
            "WHERE pl.entity_id = ".$entity." AND pl.type = '".$type."'";

        $pdatas = $wpdb->get_results($sql);
        $objs = [];
        foreach ($pdatas as $pdata) {
            $objs[] = new FollowUpProduct($pdata);
        }

        return $objs;
    }

    public static function get_follow_up_product($product_id) {
        global $wpdb;
        $sql = "SELECT * FROM {$wpdb->prefix}wc_railticket_followupprod ".
            "WHERE product_id = ".$product_id;

        $data = $wpdb->get_row($sql);
        if ($data == false) {
            return false;
        }
        return new FollowUpProduct($data);
    }

    public function get_product_id() {
        return $this->data->id;
    }

    public function use_choice() {
        return $this->data->use_choice;
    }

    public function is_allowed(\wc_railticket\BookingOrder $bookingorder) {
        global $wpdb;

        if ($bookingorder->is_special()) {
            $entity = $bookingorder->get_special()->get_id();
            $type = self::SPECIAL;
        } else {
            $entity = BookableDay::get_bookable_day($bookingorder->get_date())->get_id();
            $type = self::BOOKABLE;
        }

        $sql = "SELECT id FROM {$wpdb->prefix}wc_railticket_productlink WHERE ".
            "follow_id = ".$this->data->id." AND entity_id = ".$entity." AND type = '".$type."' LIMIT 1";

        $a = $wpdb->get_var($sql);
        if ($a) {
            return true;
        }
        return false;
    }

    public function calculate_price(\wc_railticket\BookingOrder $bookingorder, $price) {

        switch ($this->data->ruletype) {
            case self::RTYPE_PERSEAT:
                return $this->data->data->price * $bookingorder->get_seats();
            case self::RTYPE_FIXED:
                return $this->data->data->price;
            case self::RTYPE_PERCENT:
                return ($bookingorder->get_price() / 100) * $this->data->data->percent;
            case self::RTYPE_ORIGINAL:
                return $price;
        }

        throw new TicketException("Unknown follow up product rule type: ".$this->data->ruletype );
    }

    public function get_url() {
        return get_permalink($this->data->product_id);
    }
}
