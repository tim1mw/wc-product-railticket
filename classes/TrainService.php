<?php

namespace wc_railticket;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class TrainService {
    private $bookableday, $deptime, $fromstation, $direction;
    public $special;

    public function __construct(BookableDay $bookableday, Station $fromstation, $deptime, Station $tostation) {
        $this->bookableday = $bookableday;
        $this->fromstation = $fromstation;
        $this->tostation = $fromstation;
        $this->deptime = $deptime;
        $this->direction = $fromstation->get_direction($tostation);

        // Is this is a special
        if (strpos($deptime, "s:") === 0) {
            $this->special = Special::get_special($deptime);
        } else {
            $this->special = false;
        }
    }

    public function get_capacity($caponly = false, $seatsreq = false, $disabledrequest = false) {

        $allocatedbays = new \stdclass();
        $allocatedbays->ok = false;
        $allocatedbays->error = false;
        $allocatedbays->disablewarn = false;

        $outbays = $this->get_inventory(false);
        $allocatedbays->seatsleft = $outbays->totalseats;

        if ($caponly) {
            return $allocatedbays;
        }

        // Is it worth bothering? If we don't have enough seats left in empty bays for this party give up...
        if ($outbays->totalseats < $seatsreq) {
            $allocatedbays->bays = array();
            return $allocatedbays;
        }

        $outallocatesm = $this->getBays($seatsreq, $outbays->bays, false, $disabledrequest);
        $outallocatelg = $this->getBays($seatsreq, $outbays->bays, true, $disabledrequest);

        if (!$outallocatesm && !$outallocatelg) {
            $outallocatedbays->error = true;
            return $outallocatedbays;
        }

        if ($outallocatesm[0] > $outallocatelg[0]) {
            $allocatedbays->bays = $outallocatelg[1];
            if (!$outallocatelg[2] && $disabledrequest) {
                $allocatedbays->disablewarn = true;
            }
        } else {
            $allocatedbays->bays = $outallocatesm[1];
            if (!$outallocatesm[2] && $disabledrequest) {
                $allocatedbays->disablewarn = true;
            }
        }

        $allocatedbays->ok = true;
        return $allocatedbays;
    }

    private function getBays($seatsleft, $bays, $largest, $disabledrequest) {
        $allocatesm = array();
        $smcount = 0;
        $prioritydone = false;
        while ($seatsleft > 0) {
            $baychoice = false;

            if ($disabledrequest && $prioritydone === false) {
                $prioritybays = array();
                foreach ($bays as $bay => $numleft) {
                    $bayd = CoachManager::get_bay_details($bay);
                    if ($bayd[1] == 'priority') {
                        $priorityonly[$bay] = $numleft;
                     }
                }

                if ($baychoice === false) {
                    if ($largest) {
                        $baychoice = $this->findLargest($priorityonly, true);
                    } else {
                        $baychoice = $this->findSmallest($priorityonly, true);
                    }
                }
            }

            if ($baychoice === false) {
                $baychoice = $this->findBay($seatsleft, $bays, false);
            }

            if ($baychoice === false) {
                if ($largest) {
                    $baychoice = $this->findLargest($bays, false);
                } else {
                    $baychoice = $this->findSmallest($bays, false);
                }
                // Bail out here, something is wrong....
                if ($baychoice === false) {
                    return false;
                }
            }

            if ($baychoice && $baychoice[1] == 'priority') {
                $prioritydone = true;
            }

            $seatsleft = $seatsleft - $baychoice[0];
            $bays[$baychoice[2]]--;

            if (array_key_exists($baychoice[2], $allocatesm)) {
                $allocatesm[$baychoice[2]] ++;
            } else {
                $allocatesm[$baychoice[2]] = 1;
            }
            $smcount ++;
            // Bail out here, something is wrong....
            if ($smcount > 100) {
                return false;
            }
        }
        return array($smcount, $allocatesm, $prioritydone);
    }

    private function findSmallest($bays, $allowpriority=false) {
        $chosen = false;
        foreach ($bays as $bay => $numleft) {
            if ($numleft > 0) {
                $bayd = CoachManager::get_bay_details($bay);
                if ($allowpriority === false && $bayd[1] == 'priority') {
                    continue;
                }
                if ($chosen === false || $bayd[0] < $chosen[0]) {
                    $chosen = $bayd;
                }
            }
        }
        if ($chosen === false && $allowpriority === false) {
            return $this->findSmallest($bays, true);
        }

        return $chosen;
    }

    private function findLargest($bays, $allowpriority=false) {
        $chosen = false;
        foreach ($bays as $bay => $numleft) {
            if ($numleft > 0) {
                $bayd = CoachManager::get_bay_details($bay);
                if ($allowpriority === false && $bayd[1] == 'priority') {
                    continue;
                }
                if ($chosen === false || $bayd[0] > $chosen[0]) {
                    $chosen = $bayd;
                }
            }
        }

        if ($chosen === false && $allowpriority === false) {
            return $this->findLargest($bays, true);
        }

        return $chosen;
    }

    private function findBay($seatsreq, $bays, $allowpriority=false) {
        foreach ($bays as $bay => $numleft) {
            $bayd = CoachManager::get_bay_details($bay);
            if ($allowpriority === false && $bayd[1] == 'priority') {
                continue;
            }
            if ($seatsreq <= $bayd[0] && $numleft > 0) {
                return $bayd;
            }
        }

        if ($allowpriority === false) {
            return $this->findBay($seatsreq, $bays, true);
        }

        return false;
    }

    public function get_inventory($baseonly = false, $noreserve = false, $onlycollected = false) {
        global $wpdb;

        if ($this->bookableday->sold_out() || !$this->bookableday->is_bookable()) {
            return array();
        }

        // TODO: Need to get origin station dep time here when intermediate stops are enabled!
        switch ($this->bookableday->get_daytype()) {
            case 'simple':
                $basebays = (array) $this->bookableday->get_bays();
                break;
            case 'pertrain':
                $direction = $this->direction;
                $formations = $this->bookableday->get_bays();
                $deptime = $this->deptime;
                $set = $formations->$direction->$deptime;
                $basebays = (array) $formations->coachsets->$set;
                break;
        }

        if ($baseonly) {
            return $basebays;
        }

        // Get the bookings we need to subtract from this formation.
        // TODO This doesn't account for bookings from preceeding stations (it needs to).
        // Also TODO account for intermediate bookings, especially where somebody alights at an intermediate stop and the bay is
        // then taken by another booking. These must not be added together, but overlapping intermediate bookings should be!
        $sql = "SELECT {$wpdb->prefix}wc_railticket_booking_bays.* FROM ".
            "{$wpdb->prefix}wc_railticket_bookings ".
            " LEFT JOIN {$wpdb->prefix}wc_railticket_booking_bays ON ".
            " {$wpdb->prefix}wc_railticket_bookings.id = {$wpdb->prefix}wc_railticket_booking_bays.bookingid ".
            " WHERE ".
            "{$wpdb->prefix}wc_railticket_bookings.fromstation = '".$this->fromstation->get_stnid()."' AND ".
            "{$wpdb->prefix}wc_railticket_bookings.date = '".$this->bookableday->get_date()."' AND ".
            "{$wpdb->prefix}wc_railticket_bookings.time = '".$this->deptime."' ";

        if ($onlycollected) {
            $sql .= " AND {$wpdb->prefix}wc_railticket_bookings.collected = '1' ";
        }

        $bookings = $wpdb->get_results($sql);

        foreach ($bookings as $booking) {
            if ($booking->priority) {
                $i = $booking->baysize.'_priority';
            } else {
                $i = $booking->baysize.'_normal';
            }
            if (array_key_exists($i, $basebays)) {
                $basebays[$i] = $basebays[$i] - $booking->num;
            }
        }

        // Take out the booking reserve
        if ($noreserve == false && !$this->bookableday->sell_reserve() && $this->bookableday->has_reserve()) {

            // TODO: Do I need to do anything here to account for intermediate stops?
            switch ($this->bookableday->get_daytype()) {
                case 'simple':
                    $reserve = (array) $this->bookableday->get_reserve();
                    break;
                case 'pertrain':
                    $ropts = $this->bookableday->get_reserve();
                    $reserve = $ropts->$set;
                    break;
            }

            foreach ($reserve as $i => $num) {
                if (array_key_exists($i, $basebays)) {
                    $basebays[$i] = $basebays[$i] - $num;
                }
            }
        }

        $totalseats = 0;
        foreach ($basebays as $bay => $numleft) {
            $bayd = CoachManager::get_bay_details($bay);
            $totalseats += $bayd[0]*$numleft;
        }

        $bays = new \stdclass();
        $bays->bays = $basebays;
        $bays->totalseats = $totalseats;
        return $bays;
    }

    public function get_reserve($format = false) {
        $res = $this->bookableday->get_reserve();

        switch ($this->bookableday->get_daytype()) {
            case 'simple':
                $resset = $res;
                break;
            case 'pertrain':
                $comp = $this->bookableday->get_composition();
                $direction = $this->direction;
                $deptime = $this->deptime;
                $setkey = $comp->$direction->$deptime;
                $resset = $res->$setkey;
                break;
        }

        if (!$format) {
            return $resset;
        }

        return CoachManager::format_bays($resset);
    }

    public function get_coachset($format = false) {
        $comp = $this->bookableday->get_composition();

        switch ($this->bookableday->get_daytype()) {
            case 'simple':
                $cset = $comp->coachset;
                break;
            case 'pertrain':
                $direction = $this->direction;
                $deptime = $this->deptime;
                $setkey = $comp->$direction->$deptime;
                $cset = $comp->coachsets->$setkey->coachset;
                break;
        }

        if (!$format) {
            return $cset;
        }
        return CoachManager::format_coachset($cset);
    }

}
