<?php

namespace wc_railticket\survey;
use \wc_railticket\Special;
use \wc_railticket\BookingOrder;
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class XmasSurvey implements SurveyBase {
    public function __construct(Special $special) {
        $this->special = $special;
    }

    public function do_survey(BookingOrder $bookingorder) {
        $submission = railticket_gettfpostfield('submission');
        if (!$this->completed($bookingorder) && !$submission) {
            return $this->get_form($bookingorder);
        } else {
            return $this->process_intput($bookingorder);
        }
    }

    public function get_form(BookingOrder $bookingorder) {
        global $rtmustache;
        $template = $rtmustache->loadTemplate('survey/xmassurvey');
        return $template->render($item);
    }

    public function process_input(BookingOrder $bookingorder) {

    }

    public function get_report() {

    }

    public function completed(BookingOrder $bookingorder) {
        return false;
    }
}
