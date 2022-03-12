<?php

namespace wc_railticket;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class TrainService {
    private $bookableday, $deptime, $fromstation, $direction;
    public $special;

    public function __construct(BookableDay $bookableday, Station $fromstation, $deptime, Station $tostation, $service = false) {
        $this->bookableday = $bookableday;
        $this->fromstation = $fromstation;
        $this->tostation = $tostation;
        $this->deptime = $deptime;
        $this->direction = $fromstation->get_direction($tostation);
        if ($this->direction == 'up') {
            $this->revdirection = 'down';
        } else {
            $this->revdirection = 'up';
        }

        // Is this is a special
        if (strpos($deptime, "s:") === 0) {
            $this->special = Special::get_special($deptime);

            // Create a service entry
            $srv = new \stdclass();
            $srv->time = $deptime;
            $srv->stnid = $fromstation->get_stnid();
            $this->service = array($srv);
        } else {
            $this->special = false;
            if ($service == false) {
                $this->service = $this->bookableday->timetable->get_service_by_station($this->fromstation, $this->deptime, $this->direction,
                    $this->tostation->get_sequence());
            } else {
                $this->service = $service;
            }
        }
    }

    public function get_next_trainservice() {
        if ($this->special) {
            return false;
        }

        if ($this->direction == 'up') {
            $nextseq = $this->fromstation->get_sequence()-1;
        } else {
            $nextseq = $this->fromstation->get_sequence()+1;
        }
        if (array_key_exists($nextseq, $this->service)) {
            $nfrom = Station::get_station($this->service[$nextseq]->stnid, $this->bookableday->timetable->get_revision());
            if ($nfrom->get_stnid() == $this->tostation->get_stnid()) {
                return false;
            }

            return new TrainService($this->bookableday, $nfrom, $this->service[$nextseq]->time, $this->tostation, $this->service);
        }
        return false;
    }

    public function get_from_station() {
        return $this->fromstation;
    }

    public function get_to_station() {
        return $this->tostation;
    }

    public function set_to_station(\wc_railticket\Station $to) {
        $this->tostation = $to;
    }

    public function get_bookings() {
        return $this->bookableday->get_bookings_from_station($this->fromstation, $this->deptime, $this->direction);
    }

    public function get_capacity($caponly = false, $seatsreq = false, $disabledrequest = false, $usemax = false) {

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
        if ($usemax && $outbays->totalseatsmax > $outbays->totalseats) {
            $field = 'totalseatsmax';
        } else {
            $field = 'totalseats';
        }
        if ($outbays->$field < $seatsreq) {
            $allocatedbays->bays = array();
            return $allocatedbays;
        }

        // We need to take /max out here (or move its value to normal if this is the guard
        $nbays = array();
        foreach ($outbays->bays as $key => $value) {
            //$bayd = CoachManager::get_bay_details($bay);
            $pos = strpos($key, '/max');
            if ($pos !== false) {
                if ($usemax) {
                    $nkey = substr($key, 0, $pos);
                    $nbays[$nkey] = $value;
                }
                continue;
            }

            // If we're using the max value here, don't overwrite it.
            if (!array_key_exists($key, $nbays)) {
                $nbays[$key] = $value;
            }
        }
        $outbays->bays = $nbays;

        $outallocatesm = $this->getBays($seatsreq, $outbays->bays, false, $disabledrequest);
        $outallocatelg = $this->getBays($seatsreq, $outbays->bays, true, $disabledrequest);

        if (!$outallocatesm && !$outallocatelg) {
            $allocatedbays->error = true;
            return $allocatedbays;
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
        if ($this->special) {
            $allocatedbays->name = $this->special->get_name();
        } else {
            $allocatedbays->name = $this->fromstation->get_name().' - '.$this->tostation->get_name();
        }
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
            if ($smcount > 200) {
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

    public function count_priority_requested() {
        global $wpdb;
        $sql = "SELECT COUNT(id) FROM ".
            "{$wpdb->prefix}wc_railticket_bookings WHERE ".
            "fromstation = '".$this->fromstation->get_stnid()."' AND ".
            "date = '".$this->bookableday->get_date()."' AND ".
            "time = '".$this->deptime."' AND priority = 1";

        return $wpdb->get_var($sql);
    }

    public function get_inventory($baseonly = false, $noreserve = false, $onlycollected = false, $excludes = false) {
        switch ($this->bookableday->get_daytype()) {
            case 'simple':
                $basebays = (array) $this->bookableday->get_bays();
                break;
            case 'pertrain':
                $direction = $this->direction;
                $formations = $this->bookableday->get_bays();
                $set = $this->get_setkey($formations);
                $basebays = (array) $formations->coachsets->$set;
                break;
        }

        if ($baseonly) {
            return $basebays;
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
                if (array_key_exists($i.'/max', $basebays)) {
                    $basebays[$i.'/max'] = $basebays[$i.'/max'] - $num;
                }
            }
        }

        $bookings = $this->get_bookings_on_train($onlycollected, $excludes);
        $totals = $this->get_totals($bookings);
        $inventory = $this->offset_bookings($totals, $basebays, $onlycollected);

        if ($onlycollected) {
            $bays = new \stdclass();
            $bays->bays = $inventory;
            return $bays;
        }

        // Set an offset to account for journey stage being offset by 1 compared to station sequence in the up direction
        if ($this->direction == 'up') {
            $offset = 1;
        } else {
            $offset = 0;
        }

        // Calculate totals
        $totalseats = 0;
        $totalseatsmax = 0;
        $leaveempty = array();
        foreach ($inventory as $bay => $numleft) {
            // Ignore "max" parameters here. Special case should not be counted
            $bayd = CoachManager::get_bay_details($bay);
            if (strpos($bay, '/max') !== false) {
                continue;
            }
            $totalseats += $bayd[0]*$numleft;
            if (array_key_exists($bay.'/max', $inventory)) {
               $totalseatsmax += $bayd[0]*$inventory[$bay.'/max'];
            } else {
               $totalseatsmax += $bayd[0]*$numleft;
            }

            if (array_key_exists($bay, $totals)) {
                $leaveempty[$bay] = ($basebays[$bay] - $inventory[$bay]) - $totals[$bay][$this->fromstation->get_sequence()-$offset];
            } else {
                $leaveempty[$bay] = 0;
            }
        }

        $bays = new \stdclass();
        $bays->bays = $inventory;
        $bays->totalseats = $totalseats;
        $bays->totalseatsmax = $totalseatsmax;
        $bays->leaveempty = $leaveempty;
        return $bays;
    }

    /*
    * This method returns all the bookings for people who are on the train between the specified stations
    * so will include both those getting on at this station and through passengers
    * from preceeding stations.
    */

    public function get_bookings_on_train($onlycollected, $excludes) {
        global $wpdb;

        $queries = array();
        foreach ($this->service as $dep) {
            $queries[] = $this->get_bookings_sql($dep->time, $dep->stnid, $onlycollected, $excludes);
            if ($onlycollected && $dep->time == $this->deptime) {
                // If we are only interested in colleted tickets, stop when we get to the departure that matches.
                break;
            }
        }

        return $wpdb->get_results(implode(' UNION ', $queries));
    }

    private function get_bookings_sql($deptime, $depstnid, $onlycollected, $excludes) {
        global $wpdb;
        $sql = "SELECT {$wpdb->prefix}wc_railticket_booking_bays.*, ".
            "{$wpdb->prefix}wc_railticket_bookings.fromstation, ".
            "{$wpdb->prefix}wc_railticket_bookings.tostation ".
            "FROM {$wpdb->prefix}wc_railticket_bookings ".
            "LEFT JOIN {$wpdb->prefix}wc_railticket_booking_bays ON ".
            "{$wpdb->prefix}wc_railticket_bookings.id = {$wpdb->prefix}wc_railticket_booking_bays.bookingid ".
            " WHERE ".
            "{$wpdb->prefix}wc_railticket_bookings.fromstation = ".$depstnid." AND ".
            "{$wpdb->prefix}wc_railticket_bookings.date = '".$this->bookableday->get_date()."' AND ".
            "{$wpdb->prefix}wc_railticket_bookings.time = '".$deptime."' ";

        if ($onlycollected) {
            $sql .= " AND {$wpdb->prefix}wc_railticket_bookings.collected = '1' ";
        }

        if ($excludes) {
            $ids = array();
            foreach ($excludes as $exclude) {
                $ids[] = $exclude->get_id();
            }
            $sql .= " AND {$wpdb->prefix}wc_railticket_bookings.id NOT IN (".implode(',', $ids).")";
        }
        return $sql;
    }

    private function get_totals($bookings) {
        $stations = Station::get_stations($this->bookableday->timetable->get_revision());
        $topseq = end($stations)->get_sequence();

        if ($this->direction == 'up') {
            $last = reset($stations);
            $first = end($stations);
        } else {
            $first = reset($stations);
            $last = end($stations);
        }

        $totals = array();
        if ($this->direction == 'up') {
            $fld1 = 'tsequence';
            $fld2 = 'fsequence';
        } else {
            $fld1 = 'fsequence';
            $fld2 = 'tsequence';
        }

        foreach ($bookings as $booking) {
            if ($booking->priority) {
                $i = $booking->baysize.'_priority';
            } else {
                $i = $booking->baysize.'_normal';
            }

            if (!array_key_exists($i, $totals)) {
                $totals[$i] = array();
                for($l=0 ; $l<$topseq; $l++) {
                    $totals[$i][$l] = 0;
                }
            }
            $booking->fsequence = $stations[$booking->fromstation]->get_sequence();
            $booking->tsequence = $stations[$booking->tostation]->get_sequence();

            // Note this while this number is taken from the sequence it actually is the "stage of the journey.
            // So stage 0 is for stations 0-1, Stage 1 1-2 and so on. This means that for a down train the
            // stage number = station sequence number. For an up tains, it's stage number = station seq -1.
            for ($l = $booking->$fld1; $l < $booking->$fld2; $l++) {
                $totals[$i][$l] += $booking->num;
            }

        }
        return $totals;
    }

    private function offset_bookings($totals, $basebays, $onlycollected) {
        foreach ($totals as $baytype => $ttl) {
            $highest = $this->get_highest($ttl, $onlycollected);

            if (array_key_exists($baytype, $basebays)) {
                $basebays[$baytype] = $basebays[$baytype] - $highest;
            }
            if (array_key_exists($baytype.'/max', $basebays)) {
                $basebays[$baytype.'/max'] = $basebays[$baytype.'/max'] - $highest;
            }
        }

        return $basebays;
    }

    private function get_highest($ttl, $onlycollected) {
        $highest = 0;

        if ($this->direction == 'up') {
            // If we are getting a collected total, we only care about the figure for the station we are at, not the highest.
            if ($onlycollected) {
                return $ttl[$this->fromstation->get_sequence()-1];
            }
            // In the up direction, the journey stage is sequence -1
            for ($loop = $this->fromstation->get_sequence()-1; $loop >= $this->tostation->get_sequence() ; $loop--) {
                if ($ttl[$loop] > $highest) {
                    $highest = $ttl[$loop];
                }
            }
        } else {
            if ($onlycollected) {
                return $ttl[$this->fromstation->get_sequence()];
            }
            for ($loop = $this->fromstation->get_sequence(); $loop < $this->tostation->get_sequence(); $loop++) {
                if ($ttl[$loop] > $highest) {
                    $highest = $ttl[$loop];
                }
            }
        }
        return $highest;
    }

    public function find_non_overlap_booking($booking, $bookings) {
        for ($loop = 0; $loop < count($bookings); $loop++) {
             $testb = $bookings[$loop];
             if ($testb->id == $booking->id) {
                 continue;
             }
             if ($testb->tsequence <= $booking->fsequence) {
                 return $loop;
             }
             if ($testb->fsequence >= $booking->tsequence) {
                 return $loop;
             }
        }
        return false;
    }

    public function get_reserve($format = false) {
        $res = $this->bookableday->get_reserve();

        switch ($this->bookableday->get_daytype()) {
            case 'simple':
                $resset = $res;
                break;
            case 'pertrain':
                $comp = $this->bookableday->get_composition();
                $setkey = $this->get_setkey($comp);
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
                $setkey = $this->get_setkey($comp);
                $cset = $comp->coachsets->$setkey->coachset;
                break;
        }

        if (!$format) {
            return $cset;
        }
        return CoachManager::format_coachset($cset);
    }

    private function get_setkey($comp) {
        if ($this->special) {
            $sid = $this->special->get_id();
            return $comp->specials->$sid;
        } else {
            $direction = $this->direction;
            $deptime = reset($this->service)->time;
            return $comp->$direction->$deptime;
        }
    }

}
