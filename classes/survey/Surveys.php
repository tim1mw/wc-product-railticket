<?php

namespace wc_railticket\survey;
use \wc_railticket\Special;
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class Surveys {

    public static function get_survey($type, Special $special, $config) {
        switch ($type) {
            case 'XmasSurvey': return new XmasSurvey($special, $config); 
        }

        throw new TicketException("Invalid Survey Type: ".$type);
    }

    public static function get_types() {
        return array(
            'XmasSurvey' => 'Children for Santa Specials'
        );
    }

    public static function get_types_template($selected = false) {
        $types = self::get_types();
        $data = array();
        foreach ($types as $code => $type) {
            $d = new \stdclass();
            $d->name = $type;
            $d->code = $code;
            if ($selected == $code) {
                $d->selected = 'selected';
            }
            $data[] = $d;
        }

        return $data;
    }

    public static function do_purchase($orderid, $cartkey) {
        global $wpdb;

        $wpdb->update('wc_railticket_surveyresp', array('woocartitem' => 0, 'wooorderid' => $orderid), array('woocartitem' => $cartkey))
    }

}
