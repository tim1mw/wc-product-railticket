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
        $tickets = $bookingorder->get_tickets();
        foreach ($this->config->tickets as $ticket) {
            if (!array_key_exists($ticket->type, $tickets)) {
                continue;
            }

            $name = FareCalculator::get_ticket_name($ticket->type);
            for ($loop=0; $loop < $tickets[$ticket->type]; $loop++) {
                $entry = new \stdclass();
                $entry->name = $name." ".($loop+1);
                $entry->key = $ticket->type."_".$loop;
                $entry->maxage = $ticket->maxage;
                $entry->minage = $ticket->minage;
                if ($entry->maxage != $entry->minage) {
                    $entry->age = true;
                }
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
            if (!array_key_exists($ticket->type, $tickets)) {
                continue;
            }

            $name = FareCalculator::get_ticket_name($ticket->type);
            for ($loop=0; $loop < $tickets[$ticket->type]; $loop++) {
                $entry = new \stdclass();
                $key = $ticket->type."_".$loop;
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

    public function get_report($bookings) {
        global $rtmustache, $wpdb;

        $maxage = 0;
        $minage = 100;
        foreach ($this->config->tickets as $ticket) {
            if ($ticket->maxage > $maxage) {
                $maxage = $ticket->maxage;
            }
            if ($ticket->minage < $minage) {
                $minage = $ticket->minage;
            }
        }

        $genders = array('m' => array(), 'f' => array());
        foreach ($genders as $genderkey => $genderval) {
            for ($loop = $minage; $loop < $maxage + 1; $loop ++) {
                $genders[$genderkey][$loop] = 0;
            }
        }

        foreach ($bookings as $booking) {
            $result = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}wc_railticket_surveyresp WHERE ".$this->get_select_fragment($booking));
            $response = json_decode($result->response);
            foreach ($response->children as $child) {
                $genders[$child->gender][$child->age] ++;
            }
        }

        $item = new \stdclass();
        $item->children = array();

        foreach ($genders as $genderkey => $genderval) {
            switch ($genderkey) {
                case 'f': $gname = 'Female'; break;
                case 'm': $gname = 'Male'; break;
            }

            for ($loop = $minage; $loop < $maxage + 1; $loop ++) {
                $group = new \stdclass();
                $group->gender = $gname;
                $group->age = $loop;
                $group->count = $genders[$genderkey][$loop];
                $item->children[] = $group;
            }
        }

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

    private function get_select_fragment($bookingorder) {
        if ($bookingorder->is_manual()) {
            $id = substr($bookingorder->get_order_id(), 1);
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
