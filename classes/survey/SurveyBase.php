<?php

namespace wc_railticket\survey;
use wc_railticket\BookingOrder;
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

interface SurveyBase {
    public function do_survey(BookingOrder $bookingorder);

    public function get_form(BookingOrder $bookingorder);

    public function process_input(BookingOrder $bookingorder);

    public function get_report();

    public function completed(BookingOrder $bookingorder);

    public function format_response(BookingOrder $bookingorder);
}
