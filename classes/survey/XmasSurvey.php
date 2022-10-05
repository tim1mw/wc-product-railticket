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

        if ($this->completed($bookingorder)) {
            return "<h5>You have already completed this survey, please continue to the checkout to complete your purchase.</h5>";
        }

        if (!$submission) {
            return $this->get_form($bookingorder);
        } else {
            return $this->process_input($bookingorder);
        }
    }

    public function get_form(BookingOrder $bookingorder) {
        global $rtmustache;
        $item = new \stdclass();
        $item->tickets = array();
        $item->maxage = $this->config->maxage;
        $item->minage = $this->config->minage;
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
        global $wpdb;
        $tickets = $bookingorder->get_tickets();
        $response = new \stdclass();
        $response->children = array();
        foreach ($this->config->tickets as $ticket) {
            if (!array_key_exists($ticket, $tickets)) {
                continue;
            }

            $name = FareCalculator::get_ticket_name($ticket);
            for ($loop=0; $loop < $tickets[$ticket]; $loop++) {
                $entry = new \stdclass();
                $key = $ticket."_".$loop;
                $entry->gender = railticket_getpostfield('gender_'.$key);
                $entry->age = railticket_getpostfield('age_'.$key);
                $response->children[] = $entry;
            }
        }

        $data = array();
        $data['timecreated'] = time();
        $data['type']='xmassurvey';
        $data['response'] = json_encode($response);
        if ($bookingorder->is_manual()) {
            $data['manual'] = substr($bookingorder->get_order_id());
        } else {
            if ($bookingorder->in_cart()) {
                $data['woocartitem'] = $bookingorder->get_order_id();
            } else {
                $data['wooorderid'] = $bookingorder->get_order_id();
            }
        }

        $wpdb->insert("{$wpdb->prefix}wc_railticket_surveyresp", $data);
        return "<h5>Thankyou for completing the survey, please continue to the <a href='/checkout'>checkout</a> to complete your purchase.</h5>";
    }

    public function get_report() {
        global $rtmustache, $wpdb;
        $item = new \stdclass();
        $template = $rtmustache->loadTemplate('survey/xmassurvey_report');
        return $template->render($item);
    }

    public function completed(BookingOrder $bookingorder) {
        global $wpdb;

        $c = $wpdb->get_var("SELECT COUNT(id) FROM {$wpdb->prefix}wc_railticket_surveyresp WHERE ".$this->get_select_fragment($bookingorder));
        if ($c > 0) {
            return true;
        } else {
            return false;
        }
    }

    private function get_select_fragment(BookingOrder $bookingorder) {
        if ($bookingorder->is_manual()) {
            $id = substr($bookingorder->get_order_id());
            $field = 'manual';
        } else {
            if ($bookingorder->in_cart()) {
                $id = $bookingorder->get_order_id();
                $field = 'woocartitem';
            } else {
                $id = $bookingorder->get_order_id();
                $field = 'wooorderid';
            }
        }

        return $field." = '".$id."'";
    }

    public function format_response(BookingOrder$bookingorder) {
        global $rtmustache, $wpdb;
        $template = $rtmustache->loadTemplate('survey/xmassurvey_response');
        $result = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}wc_railticket_surveyresp WHERE ".$this->get_select_fragment($bookingorder));

        $item = json_decode($result->response);

        return $template->render($item);
    }


}
