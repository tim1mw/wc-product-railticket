<?php

namespace wc_railticket\survey;
use \wc_railticket\Special;
use \wc_railticket\BookingOrder;
use \wc_railticket\FareCalculator;
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class XmasSurvey implements SurveyBase {

    public function __construct(Special $special, $config) {
        $this->special = $special;
        $this->config = json_decode($config);
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
        $item = new \stdclass();
        $item->tickets = array();
        $tickets = $bookingorder->get_tickets();
        foreach ($this->config->tickets as $ticket) {
            if (!array_key_exists($ticket, $tickets)) {
                continue;
            }

            $name = FareCalculator::get_ticket_name($ticket);
            for ($loop=0; $loop < $tickets[$ticket]; $loop++) {
                $entry = new \stdclass();
                $entry->name = $name." ".($loop+1);
                $entry->key = $ticket."_".$loop;
                $item->tickets[] = $entry;
            }
        }


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
