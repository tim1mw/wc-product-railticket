<?php

namespace wc_railticket\survey;
use wc_railticket;
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

interface SurveyBase {
    public function getForm(BookingOrder $bookingorder);

    public function processInput(BookingOrder $bookingorder, $params);

    public function getReport();
}
