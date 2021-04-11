<?php

namespace wc_railticket;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class TicketBuilder {

    private $today, $tomorrow, $stations;

    public function __construct($dateoftravel, $fromstation, $journeychoice, $times,
        $ticketselections, $ticketsallocated, $overridevalid, $disabledrequest, $notes,
        $nominimum, $show, $localprice, $manual, $discountcode) {
        global $wpdb;
        $this->show = $show;

        // Deal with some dates
        $this->railticket_timezone = new \DateTimeZone(get_option('timezone_string'));
        $this->now = new \DateTime();
        $this->now->setTimezone($this->railticket_timezone);
        $this->today = new \DateTime();
        $this->today->setTimezone($this->railticket_timezone);
        $this->today->setTime(0,0,0);
        $this->tomorrow = new \DateTime();
        $this->tomorrow->setTimezone($this->railticket_timezone);
        $this->tomorrow->modify('+1 day');

        if ($dateoftravel == false) {
            $this->bookableday = BookableDay::get_bookable_day($this->today->format('Y-m-d'));
            return;
        }

        $this->bookableday = BookableDay::get_bookable_day($dateoftravel);
        $this->dateoftravel = $dateoftravel;

        /* There should only be one leg for a special, so check the first leg for the special indicator **/
        if ($times && count($times) > 0 && strpos($times[0], 's:') !== false) {
            $this->special = Special::get_special(intval(substr($times[0], 2)));
            // Specials stations won't be sent by the ticket selector, so force them here.
            $this->fromstation = $this->special->get_from_station();
            $this->tostation = $this->special->get_to_station();
            $this->rndtostation = false;
            // Specials are booked as a single leg, so treat it as a single here.
            $this->journeytype = 'single';
        } else {
            $this->special = false;
            if ($fromstation !== false && strlen($fromstation) > 0) {
                $this->fromstation = $this->bookableday->timetable->get_station($fromstation);
            } else {
                $fromstation = false;
            }
            $jparts = explode('_', $journeychoice);
            if (count($jparts) > 1) {

                $this->journeytype = $jparts[0];
                $this->tostation =  $this->bookableday->timetable->get_station($jparts[1]);
                if ($this->journeytype == 'round') {
                    $this->rndtostation = $this->bookableday->timetable->get_station($this->bookableday->timetable->get_revision());
                } else {
                    $this->rndtostation = false;
                }
            } else {
                $this->journeytype = false;
                $this->tostation = false;
                $this->rndtostation = false;
            }
        }
        $this->times = $times;

        $this->ticketselections = $ticketselections;
        $this->ticketsallocated = $ticketsallocated;
        $this->notes = $notes;

        if ($nominimum == 'true') {
            $this->nominimum = true;
        } else {
            $this->nominimum = false;
        }

        if ($this->is_guard()) {
            $this->overridevalid = true;
            $this->localprice = true;
            $this->manual = true;
        } else {
            $this->overridevalid = $overridevalid;
            $this->localprice = false;
            $this->manual = false;
        }

        if ($this->is_booking_admin()) {
            $this->localprice = $localprice;
            $this->manual = $manual;
        }

        if ($disabledrequest == 'true') {
            $this->disabledrequest = true;
        } else {
            $this->disabledrequest = false;
        }

        $this->discount = \wc_railticket\Discount::get_discount($discountcode);
        if ($this->discount && $this->fromstation && $this->tostation) {
            $this->discount->apply_stations($this->fromstation, $this->tostation);
        }
    }

    private function is_guard() {
        if( is_user_logged_in() ) {
            if (current_user_can('manage_tickets')) {
                return true;
            }
        }

        return false;
    }

    private function is_booking_admin() {
        if( is_user_logged_in() ) {
            if (current_user_can('admin_tickets')) {
                return true;
            }
        }

        return false;
    }

    public function render() {

        $ua = htmlentities($_SERVER['HTTP_USER_AGENT'], ENT_QUOTES, 'UTF-8');
        if (preg_match('~MSIE|Internet Explorer~i', $ua) || (strpos($ua, 'Trident/7.0; rv:11.0') !== false)) {
            return "<p>Sorry, the ticket booking system isn't supported on Internet Explorer. If you are using Windows 10, then please switch to ".
                "the Microsoft Edge browser which has replaced Internet Explorer to continue your purchase. Users of older Windows versions ".
                "will need to use Chrome or Firefox.</p>";
        }

        if ($this->checkDuplicate()) {
            return '<p>Sorry, but you already have a ticket selection in your shopping cart, you can only have one ticket selection per order. Please remove the existing ticket selection if you wish to create a new one, or complete the purchase for the existing one.</p>';
        }

        wp_register_style('railticket_style', plugins_url('wc-product-railticket/ticketbuilder.css'));
        wp_enqueue_style('railticket_style');

        if ($this->show || $this->bookableday == false) {
            return $this->get_all_html();
        }

        $fstation = $this->bookableday->timetable->get_terminal('up');
        $lstation = $this->bookableday->timetable->get_terminal('down');

        $fdeptime = $this->bookableday->timetable->next_train_from($fstation, true);
        $ldeptime = $this->bookableday->timetable->next_train_from($lstation, true);

        if ($fdeptime === false && $ldeptime === false) {
            return $this->get_all_html();
        }

        $str = "<div class='railticket_selector'>".
            "<div class='railticket_container'>".
            "<h4>Would you like to buy tickets for:</h4>";

        $str .= "<form action='/book/' method='post'>".
            "<input type='submit' value='Tickets for any date/train' />".
            "<input type='hidden' name='show' value='1' />".
            "</form><br />";

        if ($fdeptime !== false) {
            $str .= $this->get_preset_form($fstation, $lstation, $fdeptime);
        }
        if ($ldeptime !== false) {
            $str .= $this->get_preset_form($lstation, $fstation, $ldeptime);
        }
        $str .= "</div></div>";

        return $str;

    }

    private function get_all_html() {
        global $rtmustache;

        $alldata = new \stdclass();
        $alldata->javascript = $this->get_javascript();
        $alldata->datepick = $this->get_datepick();
        $alldata->ticket_opts = array();

        $disabled = new \stdclass();
        $disabled->name = 'disabledrequest';
        $disabled->title = __('Request Wheelchair Space', 'wc_railticket');
        $alldata->ticket_opts[] = $disabled;

        $alldata->fields = array();
        $alldata->buttons = array();

        if ($this->is_guard()) {
            $nm = new \stdclass();
            $nm->name = 'nominimum';
            $nm->title = __('No Minimum Price', 'wc_railticket');
            $alldata->ticket_opts[] = $nm;

            $bp = new \stdclass();
            $bp->name = 'bypass';
            $bp->title = __('Bypass Ticket Restrictions', 'wc_railticket');
            $alldata->ticket_opts[] = $bp;

            if ($this->is_booking_admin()) {
                $op = new \stdclass();
                $op->name = 'onlineprice';
                $op->title = __('Use Online Sales Price', 'wc_railticket');
                $alldata->ticket_opts[] = $op;
            }

            $notes = new \stdclass();
            $notes->name = 'notes';
            $notes->cols = 40;
            $notes->rows = 5;
            $notes->title = "Guard's Notes:";
            $alldata->fields[] = $notes;

            $manb = new \stdclass();
            $manb->value = 'Create Manual Booking';
            $manb->id ='createbooking';
            $alldata->buttons[] = $manb;

            $alldata->hideterms = 'display:none;';
        } else {
            $alldata->hideterms = '';
            $alldata->termspage = get_option('wc_product_railticket_termspage');

            $alldata->comment = "Your tickets will be reserved for ".
                get_option('wc_product_railticket_reservetime')." minutes after you click add to cart.".
                " Please complete your purchases within that time.";

            $cart = new \stdclass();
            $cart->value = 'Add To Cart';
            $cart->id = 'addtocart_button';
            $alldata->buttons[] = $cart;
        }

        if ($this->is_booking_admin()) {
            $cart = new \stdclass();
            $cart->value = 'Add To Shopping Cart';
            $cart->id = 'addtocart_button';
            $alldata->buttons[] = $cart;
        }

        $template = $rtmustache->loadTemplate('ticketbuilder');
        echo $template->render($alldata);
    }

    private function get_preset_form(\wc_railticket\Station $fstation, \wc_railticket\Station $tstation, \stdclass $deptime) {

        $str = "<form action='/book/' method='post'>".
            "<input type='submit' value='Return tickets for the next train from ".$fstation->get_name()."' />".
            "<input type='hidden' name='a_dateofjourney' value='".$this->today->format('Y-m-d')."' />".
            "<input type='hidden' name='a_deptime' value='".$deptime->key."' />".
            "<input type='hidden' name='a_station' value='".$fstation->get_stnid()."' />".
            "<input type='hidden' name='a_journeychoice' value='return_".$tstation->get_stnid()."' />".
            "<input type='hidden' name='show' value='1' />".
            "</form><br />";

        return $str;
    }

    public function get_bookable_stations() {
        global $wpdb;
        $bookable = array();
        $bookable['specialonly'] = $this->bookableday->special_only();
        if ($bookable['specialonly']) {
            $bookable['stations'] = array();;
        } else {
            $bookable['stations'] = $this->bookableday->timetable->get_stations(true);
        }
        $bookable['override'] = $this->bookableday->get_override();

        // Are their any specials today?
        $bookable['specials'] = $this->bookableday->get_specials_onsale_data($this->is_guard());

        return $bookable;
    }

    public function get_journey_options() {

        $allpopular = array();
        $allother = array();

        $up_terminal = $this->bookableday->timetable->get_terminal('up');
        $down_terminal = $this->bookableday->timetable->get_terminal('down');
        $allstations = $this->bookableday->timetable->get_stations();
        $otherterm = false;

        // Find the popular journeys. If this is a terminal, a return to the other terminal.
        // If an intermediate stop Round trips

        // TODO : Not handling sameservicereturn here yet....

        if ($this->fromstation->get_stnid() == $up_terminal->get_stnid()) {
            $this->add_returntrip_opt($allpopular, $this->fromstation, $down_terminal, true);
            $this->add_singletrip_opt($allpopular, $this->fromstation, $down_terminal);
            $otherterm = $down_terminal;
        } elseif ($this->fromstation->get_stnid() == $down_terminal->get_stnid()) {
            $this->add_returntrip_opt($allpopular, $this->fromstation, $up_terminal, true);
            $this->add_singletrip_opt($allpopular, $this->fromstation, $up_terminal);
            $otherterm = $up_terminal;
        } else {
            // Must be an intermediate stop if we got here, so offer round trips
            $this->add_roundtrip_opt($allpopular, $this->fromstation, $up_terminal, $down_terminal);
            $this->add_roundtrip_opt($allpopular, $this->fromstation, $down_terminal, $up_terminal);
        }

        foreach ($allstations as $stn) {
            if ($stn->is_closed() || $stn->get_stnid() == $this->fromstation->get_stnid() ||
               ($otherterm && $otherterm->get_stnid() == $stn->get_stnid()) ) {
                 // this is the station we are at, or it is closed, we can't go there
                continue;
            }

            $this->get_returntrip_opt($allother, $this->fromstation, $stn);
            $this->get_singletrip_opt($allother, $this->fromstation, $stn);
        }

        return ['popular' => $allpopular, 'other' => $allother];
    }

    private function add_returntrip_opt(&$data, Station $from, Station $to, $fullline = false) {
        // Do we have any tickets for this?
        if (!$this->bookableday->fares->can_sell_journey($from, $to, 'return', $this->is_guard(), $this->special)) {
            return false;
        }

        $trp = new \stdclass();
        $trp->journeytype = 'return';
        $trp->journeydesc = __('Return Trip to ', 'wc_railticket').$to->get_name();

        if ($fullline) {
            $trp->extradesc =  __('A full line return trip', 'wc_railticket').", ".
                $from->get_name()." - ".$to->get_name()." - ".$from->get_name();
        } else {
            $trp->extradesc = $from->get_name()." - ".$to->get_name()." - ".$from->get_name();
        }

        $trp->code = 'return_'.$to->get_stnid();

        $trp->disabled = '';

        $data[] = $trp;

        return true; 
   }

    private function add_singletrip_opt(&$data, Station $from, Station $to) {
        // Do we have any tickets for this?
        if (!$this->bookableday->fares->can_sell_journey($from, $to, 'single', $this->is_guard(), $this->special)) {
            return false;
        }

        $trp = new \stdclass();
        $trp->journeytype = 'single';
        $trp->journeydesc = __('Single Trip to ', 'wc_railticket').$to->get_name();
        $trp->extradesc = $from->get_name()." - ".$to->get_name();
        $trp->code = 'single_'.$to->get_stnid();

        $trp->disabled = '';

        $data[] = $trp;

        return true; 
   }

    private function add_roundtrip_opt(&$data, Station $from, Station $term1, Station $term2) {
        // Do we have any tickets for this?
        if (!$this->bookableday->fares->can_sell_journey($from, $from, 'round', $this->is_guard(), $this->special)) {
            return false;
        }

        $rnd = new \stdclass();
        $rnd->journeytype = 'round';
        $rnd->journeydesc = __('Full Line Round Trip', 'wc_railticket');
        $rnd->extradesc = $from->get_name()." - ".
            $term1->get_name()." - ".
            $term2->get_name()." - ".
            $from->get_name();
        $rnd->code = 'round_'.$term1->get_stnid()."_".$term2->get_stnid();;
        // TODO Do a check here to see if this can be purchased
        $trp->disabled = '';

        $data[] = $trp;

        return $rnd;
    }

    public function get_ticket_data() {
        return $this->bookableday->fares->get_tickets($this->fromstation, $this->tostation, $this->journeytype,
            $this->is_guard(), $this->localprice, $this->discount, $this->special);
    }

    public function get_bookable_trains() {
        $data = new \stdclass();
        // TODO : Do I need to do anything else with this here?
        $data->sameservicereturn = $this->bookableday->same_service_return();
        $data->legs = array();

        $data->tickets = $this->get_ticket_data();

        // There are no fares to sell...
        if (count($data->tickets->prices) == 0) {
            return $data;
        }

        $nowdt = new \DateTime();
        $nowdt->setTimezone($this->railticket_timezone);
        if ($nowdt->format('Y-m-d') == $this->dateoftravel) {
            $today = true;
        } else {
            $today = false;
        }

/*
        if ($this->is_guard() || $this->overridevalid) {
            $nodisable = true;
        } else {
            $nodisable = false;
        }
*/

        $data->legs[0] = new \stdclass();
        $data->legs[0]->times = $this->bookableday->get_bookable_trains($this->fromstation, $this->tostation, $this->overridevalid);
        $data->legs[0]->header ='';
        $data->legs[0]->leg = 0;
        if ($this->journeytype == 'return') {
            $data->legs[1] = new \stdclass();
            $data->legs[1]->times = $this->bookableday->get_bookable_trains($this->tostation, $this->fromstation, $this->overridevalid,
                reset($data->legs[0]->times)->stopsat,
                $this->get_first_enabled_stopsat($data->legs[0]->times));
            $data->legs[1]->leg = 1;
            $data->legs[0]->header = __('Outbound', 'wc_railticket');
            $data->legs[1]->header = __('Return', 'wc_railticket');
        } elseif ($this->journeytype == 'round') {
            $data->legs[1] = new \stdclass();
            $data->legs[1]->times = $this->bookableday->get_bookable_trains($this->tostation, $this->rndtostation, $this->overridevalid,
                reset($data->legs[0]->times)->stopsat, $this->get_first_enabled_stopsat($data->legs[0]->times));
            $data->legs[1]->leg = 1;
            $data->legs[2] = new \stdclass();
            $data->legs[2]->times = $this->bookableday->get_bookable_trains($this->rndtostation, $this->fromstation, $this->overridevalid,
                reset($data->legs[1]->times)->stopsat, $this->get_first_enabled_stopsat($data->legs[1]->times));
            $data->legs[2]->leg = 1;
            $data->legs[0]->header = __('1st Train', 'wc_railticket');
            $data->legs[1]->header = __('2nd Train', 'wc_railticket');
            $data->legs[2]->header = __('3rd Train', 'wc_railticket');
        }

        return $data;
    }

    public function get_first_enabled_stopsat($times) {
        foreach ($times as $time) {
            if (!$time->notbookable) {
                return $time->stopsat;
            }
        }

        return false;
    }

    public function get_capacity() {

        $seatsreq = $this->bookableday->fares->count_seats($this->ticketselections);

        $capdata = new \stdclass();
        $capdata->capacity = array();
        $capdata->allocateby = $this->bookableday->get_allocation_type();

        switch ($this->journeytype) {
            case 'round':
                $ts0 = new TrainService($this->bookableday, $this->fromstation, $this->times[0], $this->tostation);
                $capdata->capacity[] = $ts->get_capacity(false, $seatsreq, $this->disabledrequest);
                $ts1 = new TrainService($this->bookableday, $this->tostation, $this->times[1], $this->rndstation);
                $capdata->capacity[] = $ts1->get_capacity(false, $seatsreq, $this->disabledrequest);
                $ts2 = new TrainService($this->bookableday, $this->rndstation, $this->times[2], $this->tostation);
                $capdata->capacity[] = $ts2->get_capacity(false, $seatsreq, $this->disabledrequest);
                break;
            case 'return':
                $ts1 = new TrainService($this->bookableday, $this->tostation, $this->times[1], $this->fromstation);
                $capdata->capacity[] = $ts1->get_capacity(false, $seatsreq, $this->disabledrequest);
            case 'single':
                $ts0 = new TrainService($this->bookableday, $this->fromstation, $this->times[0], $this->tostation);
                $capdata->capacity[] = $ts0->get_capacity(false, $seatsreq, $this->disabledrequest);
                $capdata->capacity = array_reverse($capdata->capacity);
        }

        return $capdata;
    }

    private function checkDuplicate() {
        global $woocommerce, $wpdb;
        $items = $woocommerce->cart->get_cart();
        $ticketid = get_option('wc_product_railticket_woocommerce_product');
        $items = $woocommerce->cart->get_cart();
        foreach($items as $item => $values) { 
            if ($ticketid == $values['data']->get_id()) {
                return true;
            }
        } 
        return false;
    }

    public function do_purchase() {
        global $woocommerce, $wpdb;

        $purchase = new \stdclass();
        $purchase->ok = false;
        $purchase->duplicate = false;
        if ($this->checkDuplicate()) {
            $purchase->duplicate = true;
            return $purchase;
        }

        // Check we still have capacity
        $allocatedbays = $this->get_capacity();
        $legbayinfo = array();
        
        for ($legnum = 0; $legnum < count($allocatedbays->capacity); $legnum++) {
            $legbays = $allocatedbays->capacity[$legnum];

            // Are we full now?
            if ($this->overridevalid == 0 && !$legbays->ok) {
                return $purchase;
            }

            $legbaydata = new \stdclass();
            $legbaydata->deptime = $this->times[$legnum];

            switch ($this->journeytype) {
                case 'single': $legbaydata->name = ''; break;
                case 'return':
                    if ($legnum == 0) {
                        $legbaydata->name = __('Outbound', 'wc_railticket');
                    } else {
                        $legbaydata->name = __('Return', 'wc_railticket');
                    }
                    break;
                case 'round':
                    switch ($legnum) {
                        case 0: $legbaydata->name = __('1st Trip', 'wc_railticket'); break;
                        case 1: $legbaydata->name = __('2nd Trip', 'wc_railticket'); break;
                        case 2: $legbaydata->name = __('3rd Trip', 'wc_railticket'); break;
                    }
                    break;
            }

            if ($this->overridevalid == 1 && !$legbays->ok ) {
                if (count($legbays->bays) > 0) {
                    $legbaydata->str = CoachManager::bay_strings($legbays->bays);
                } else {
                    $legbaydata->str = __("As directed by the guard", 'wc_railticket');
                }
            } else {
                $legbaydata->str = CoachManager::bay_strings($legbays->bays);
            }
            $legbayinfo[$legnum] = $legbaydata;
        } 

        $pricedata = $this->bookableday->fares->ticket_allocation_price($this->ticketsallocated,
            $this->fromstation, $this->tostation, $this->journeytype, $this->is_guard(), $this->nominimum, $this->discount, $this->special);

        $totalseats = $this->bookableday->fares->count_seats($this->ticketselections);

        if ($this->manual) {
            $data = array(
                'journeytype' => $this->journeytype,
                'price' => $pricedata->price,
                'supplement' => $pricedata->supplement,
                'seats' => $totalseats,
                'travellers' => json_encode($this->ticketselections),
                'tickets' => json_encode($this->ticketsallocated),
                'ticketprices' => json_encode($pricedata->ticketprices),
                'notes' => $this->notes,
                'createdby' => get_current_user_id()
            );
            $wpdb->insert("{$wpdb->prefix}wc_railticket_manualbook", $data);
            $mid = $wpdb->insert_id;
            $itemkey = '';
            $purchase->id = 'M'.$mid;
        } else {
            // Note the unique field cures a weird problem in woocommerce where two similataneous users with the same selection
            // seem get their carts mixed up...
            $cart_item_data = array('custom_price' => $pricedata->price, 'ticketselections' => $this->ticketselections,
                'ticketsallocated' => $this->ticketsallocated, 'supplement' => $pricedata->supplement,
                'ticketprices' => $pricedata->ticketprices, 'unique' => uniqid());

            $bridge_product = get_option('wc_product_railticket_woocommerce_product');
            $itemkey = $woocommerce->cart->add_to_cart($bridge_product, 1, 0, array(), $cart_item_data);
            $woocommerce->cart->calculate_totals();
            $woocommerce->cart->set_session();
            $woocommerce->cart->maybe_set_cart_cookies();
            $mid = 0;
        }

        switch ($this->journeytype) {
            case 'round':
                Booking::insertBooking($this->dateoftravel, $itemkey, $this->times[0], $this->fromstation, $this->tostation, $totalseats,
                    $allocatedbays->capacity[0]->bays, $mid, $this->disabledrequest);
                Booking::insertBooking($this->dateoftravel, $itemkey, $this->times[1], $this->tostation, $this->rndstation, $totalseats,
                    $allocatedbays->capacity[1]->bays, $mid, $this->disabledrequest);
                Booking::insertBooking($this->dateoftravel, $itemkey, $this->times[2], $this->rndstation, $this->fromstation, $totalseats,
                    $allocatedbays->capacity[2]->bays, $mid, $this->disabledrequest);
                break;
            case 'single':
                Booking::insertBooking($this->dateoftravel, $itemkey, $legbayinfo[0]->deptime, $this->fromstation, $this->tostation, $totalseats,
                    $allocatedbays->capacity[0]->bays, $mid, $this->disabledrequest);
                break;
            case 'return':
                Booking::insertBooking($this->dateoftravel, $itemkey, $legbayinfo[0]->deptime, $this->fromstation, $this->tostation, $totalseats,
                    $allocatedbays->capacity[0]->bays, $mid, $this->disabledrequest);
                Booking::insertBooking($this->dateoftravel, $itemkey, $legbayinfo[1]->deptime, $this->tostation, $this->fromstation, $totalseats,
                    $allocatedbays->capacity[1]->bays, $mid, $this->disabledrequest);
        }

        $purchase->ok = true;

        return $purchase;
    }

    private function get_javascript() {
        wp_register_script('railticket_script_mustache', plugins_url('wc-product-railticket/js/mustache.min.js'));
        wp_register_script('railticket_script_builder', plugins_url('wc-product-railticket/js/ticketbuilder.js'));
        wp_enqueue_script('railticket_script_mustache');
        wp_enqueue_script('railticket_script_builder');

        $minprice = 'false';
        $opt = get_option('wc_product_railticket_min_price');
        if (strlen($opt) > 0) {
            $minprice = $opt;
        }
        
        $str = file_get_contents(dirname(__FILE__).'/../templates/remote-templates.html').
            "\n<script type='text/javascript'>\n".
            "var ajaxurl = '".admin_url( 'admin-ajax.php', 'relative' )."';\n".
            "var today = '".$this->today->format('Y-m-d')."'\n".
            "var tomorrow = '".$this->tomorrow->format('Y-m-d')."'\n".
            "var minprice = ".$minprice."\n".
            "var dateFormat = '".get_option('wc_railticket_date_format')."';\n";

        $str .= $this->preset_javascript('a_dateofjourney');
        $str .= $this->preset_javascript('a_station');
        $str .= $this->preset_javascript('a_journeychoice');
        $str .= $this->preset_javascript('a_deptime');

        if ($this->is_guard()) {
            $str .= 'var guard=true;';
        } else {
            $str .= 'var guard=false;';
        }

        $str .= "</script>";

        return $str;
    }

    private function preset_javascript($key) {
        if (array_key_exists($key, $_REQUEST)) {
            return 'var '.$key.' = "'.$_REQUEST[$key].'";';
        } else {
            return 'var '.$key.' = false;';
        }
    }

    private function get_datepick() {
        global $wpdb;
        // TODO Use a template here
        $calendar = new \wc_railticket\TicketCalendar();

        $startyear = $this->today->format('Y');
        $startmonth = $this->today->format('n');
        $endyear = date_i18n("Y", strtotime("+1 month"));
        $endmonth = date_i18n("n", strtotime("+2 month"));

        if ($endmonth < $startmonth) {
            $stop = 13;
        } else {
            $stop = $endmonth++;
        }
        $cal="";

        for ($year=$startyear; $year<$endyear+1; $year++) {
            for ($month=$startmonth; $month<$stop; $month++) {
                $cal .= "<div class='railticket-calendar-box-wrapper' id='railticket-cal-".$year."-".$month."'>".$calendar->draw(date_i18n($year."-".$month."-01"), $this->is_guard())."</div>";
             }
             $startmonth = 1;
             $stop = $endmonth+1;
        }

        $str = "<div id='datechoosetitle' class='railticket_stageblock' style='display:block;'><h3>Choose Date of Travel</h3>";
        $str .= "<p>".get_option('wc_product_railticket_top_comment')."</p></div>".
            "<div id='railticket-cal' class='railticket-calendar-wrapper'>.$cal.</div>";

        $str .= "<div id='datechooser' class='railticket_stageblock'><div class='railticket_container'>".
            "<p class='railticket_help'>Choose a date from the calendar above, or use the buttons below.<br />Dates marked with an X are sold out.</p>";
        $toshow = 6;
        $act = false;

        $nowtime = ($this->now->format('G')*60) + $this->now->format('i');
        $timetable = \wc_railticket\Timetable::get_timetable_by_date($this->now->format('Y-m-d'));

        if ($timetable) {
            $lt = $timetable->get_last_train();
            if ($lt === false ) {
                $endtime = 60*24;
            } else {
                $endtime = (intval($lt->hour)*60) + intval($lt->min) + intval(get_option("wc_product_railticket_bookinggrace"));
            }
        } else {
            $endtime = 60*24;
        }

        if ($nowtime < $endtime || $this->is_guard()) {
            $nexttrains = \wc_railticket\BookableDay::get_next_bookable_dates($this->today->format('Y-m-d'), $toshow);
        } else {
            $nexttrains = \wc_railticket\BookableDay::get_next_bookable_dates($this->tomorrow->format('Y-m-d'), $toshow);
        }



        foreach ($nexttrains as $t) {
            $date = \DateTime::createFromFormat("Y-m-d", $t->date, $this->railticket_timezone);

            $str .= "<input type='button' value='".$date->format('j-M-Y')."' title='Click to travel on ".$date->format('j-M-Y')."' ".
                "class='railticket_datebuttons' data='".$date->format("Y-m-d")."' />";
            if ($act == false) {
                $str .= '&nbsp;';
            } else {
                $str .= '<br />';
            }
            $act = !$act;
        }

        $str .= "</form></div>".
            "<div id='datechosen' class='railticket_container'>Tap or click a date to choose</div>".
            "<input type='hidden' id='dateoftravel' name='dateoftravel' value='' />".
            "  <div id='overridecodediv' class='railticket_overridecode railticket_container'>".
            "  <label for='override'>Override code</label>&nbsp;&nbsp;<input id='overrideval' type='text' size='6' name='override' />".
            "  <input type='button' value='Validate' id='validateOverrideIn' /> ".
            "  <div id='overridevalid'>".
            "  <p class='railticket_overridedesc'>The override code can be used to unlock services not available for booking below, ".
            "  eg if a train is running late. The code, if needed can be obtained from the guard once the train has arrived.</p>".
            "  </div></div></div>";

        return $str;
    }


    public function get_validate_discount() {
        if ($this->discount == false || !$this->discount->is_valid()) {
            return array('valid' => false,
                'tickets' => $this->get_ticket_data());
        }

        return array('valid' => true,
            'name' => $this->discount->get_name(),
            'tickets' => $this->get_ticket_data());
    }
} 
