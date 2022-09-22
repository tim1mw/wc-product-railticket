<?php

namespace wc_railticket\survey;
use wc_railticket;
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class Surveys {

    public static function getSurvey($type, Special $special) {
        switch ($type) {
            case 'XmasSurvey': return new XmasSurvey($special); 
        }

        throw new TicketException("Invalid Survey Type: ".$type);
    }

    public static function getTypes() {
        return array(
            'XmasSurvey' => 'Children for Santa Specials'
        );
    }

    public static function getTypesTemplate($selected = false) {
        $types = self::getTypes();
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

}
