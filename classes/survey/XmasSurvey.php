<?php

namespace wc_railticket\survey;
use wc_railticket;
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class XmasSurvey implements SurveyBase {
    protected __construct(Special $special) {
        $this->special = $special;
    }

    public function getForm(BookingOrder $bookingorder) {

    }

    public function processInput(BookingOrder $bookingorder, $params) {

    }

    public function getReport() {

    }
}
