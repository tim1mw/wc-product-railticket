<?php
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

// Hook for adding admin menus
add_action('admin_menu', 'railticket_add_pages');
add_action('admin_init', 'railticket_register_settings' );
add_shortcode('railticket_manager', 'railticket_view_bookings');

function railticket_register_settings() {
   add_option('wc_product_railticket_woocommerce_product', '');
   register_setting('wc_product_railticket_options_main', 'wc_product_railticket_woocommerce_product'); 
   register_setting('wc_product_railticket_options_main', 'wc_product_railticket_top_comment');
   register_setting('wc_product_railticket_options_main', 'wc_product_railticket_min_price');
   register_setting('wc_product_railticket_options_main', 'wc_product_railticket_reservetime');
   register_setting('wc_product_railticket_options_main', 'wc_product_railticket_releaseinventory');
   register_setting('wc_product_railticket_options_main', 'wc_product_railticket_sameservicereturn');
   register_setting('wc_product_railticket_options_main', 'wc_product_railticket_termspage');
   register_setting('wc_product_railticket_options_main', 'wc_product_railticket_bookinggrace');
   register_setting('wc_product_railticket_options_main', 'wc_product_railticket_defaultcoaches');
   register_setting('wc_product_railticket_options_main', 'wc_product_railticket_bookinglimits');
}

function railticket_add_pages() {
    add_menu_page('Rail Ticket', 'Rail Ticket', 'manage_tickets', 'railticket-top-level-handle', 'railticket_view_bookings', 
        '', 30);
    add_submenu_page('railticket-top-level-handle', "Settings", "Settings", 'manage_options', 'railticket-options', 'railticket_options');
    add_submenu_page('railticket-top-level-handle', "Bookable Days", "Bookable Days", 'manage_options', 'railticket-bookable-days', 'railticket_bookable_days');
}

function railticket_roles() {
    // Gets the simple_role role object.
    $role = get_role( 'administrator' );
    // Add a new capability.
    $role->add_cap( 'manage_tickets', true );
    $role->add_cap( 'delete_tickets', true );

    $role = get_role( 'shop_manager' );
    // Add a new capability.
    $role->add_cap( 'manage_tickets', true );
    add_role(
        'guard',
        'Guard',
        [
            'read'         => true,
            'manage_tickets'   => true,
        ]
    );
}
 
// Add simple_role capabilities, priority must be after the initial role definition.
add_action( 'init', 'railticket_roles', 11 );

function railticket_options() {
    ?>
    <h1>Heritage Railway Tickets</h1>
    <form method="post" action="options.php">
    <?php settings_fields('wc_product_railticket_options_main'); ?>
    <table>
        <tr valign="top">
            <th scope="row"><label for="wc_product_railticket_woocommerce_product">Woocommerce Product ID</label></th>
            <td><input size='6' type="text" id="wc_product_railticket_woocommerce_product" name="wc_product_railticket_woocommerce_product" value="<?php echo get_option('wc_product_railticket_woocommerce_product'); ?>" /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><label for="wc_product_railticket_top_comment">Top Comment</label></th>
            <td><textarea rows='10' cols='60' id="wc_product_railticket_top_comment" name="wc_product_railticket_top_comment"><?php echo get_option('wc_product_railticket_top_comment'); ?></textarea></td>
        </tr>
        <tr valign="top">
            <th scope="row"><label for="wc_product_railticket_min_price">Minimum Ticket Order Price</label></th>
            <td><input size='6'  type="text" id="wc_product_railticket_min_price" name="wc_product_railticket_min_price" value="<?php echo get_option('wc_product_railticket_min_price'); ?>" /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><label for="wc_product_railticket_reservetime">Reserve basket tickets for</label></th>
            <td><input size='6'  type="text" id="wc_product_railticket_reservetime" name="wc_product_railticket_reservetime" value="<?php echo get_option('wc_product_railticket_reservetime'); ?>" /> minutes</td>
        </tr>
        <tr valign="top">
            <th scope="row"><label for="wc_product_railticket_releaseinventory">Release inventory when expired after</label></th>
            <td><input size='6'  type="text" id="wc_product_railticket_releaseinventory" name="wc_product_railticket_releaseinventory" value="<?php echo get_option('wc_product_railticket_releaseinventory'); ?>" /> minutes</td>
        </tr>
        <tr valign="top">
            <th scope="row"><label for="wc_product_railticket_termspage">Ticket Terms Page</label></th>
            <td><input type="text" size='60' id="wc_product_railticket_termspage" name="wc_product_railticket_termspage" value="<?php echo get_option('wc_product_railticket_termspage'); ?>" /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><label for="wc_product_railticket_bookinggrace">Booking overrun period (minutes)</label></th>
            <td><input type="text" size='2' id="wc_product_railticket_bookinggrace" name="wc_product_railticket_bookinggrace" value="<?php echo get_option('wc_product_railticket_bookinggrace'); ?>" /></td>
        </tr>
        <tr><td colspan="2"><h3>Defaults for new bookable days<h3></td></tr>
        <tr valign="top">
            <th scope="row"><label for="wc_product_railticket_sameservicereturn">Travellers must return on the same service</label></th>
            <td><input type="checkbox" id="wc_product_railticket_sameservicereturn" name="wc_product_railticket_sameservicereturn" <?php if (get_option('wc_product_railticket_sameservicereturn')) {echo " checked";} ?> /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><label for="wc_product_railticket_defaultcoaches">Default Coaches</label></th>
            <td><textarea rows='10' cols='60' id="wc_product_railticket_defaultcoaches" name="wc_product_railticket_defaultcoaches"><?php echo get_option('wc_product_railticket_defaultcoaches'); ?></textarea></td>
        </tr>
        <tr valign="top">
            <th scope="row"><label for="wc_product_railticket_bookinglimits">Booking limits</label></th>
            <td><textarea rows='10' cols='60' id="wc_product_railticket_bookinglimits" name="wc_product_railticket_bookinglimits"><?php echo get_option('wc_product_railticket_bookinglimits'); ?></textarea></td>
        </tr>
    </table>
    <?php submit_button(); ?>
    </form>
    <?php
}


function railticket_bookable_days() {
    global $wp;

    if (array_key_exists('month', $_POST)) {
        $month = $_POST['month'];
    } else {
        $month = intval(date("n"));
    }
    ?>
    <h1>Heritage Railway Tickets - Set bookable dates</h1>
    <form method='post' action='<?php echo railticket_get_page_url() ?>'>
        <input type='hidden' name='action' value='filterbookable' />    
        <table><tr>
            <td>Select Year</td>
            <td><?php echo railtimetable_getyearselect();?></td>
        </tr><tr>
            <td>Month</td>
            <td><?php echo railtimetable_getmonthselect($month);?></td>
        </tr><tr>
            <td></td>
            <td><input type='submit' value='Show Bookable Days' /></td>
        </tr></table>
    </form>
    <hr />
    <?php

    if (array_key_exists('action', $_POST)) {
        switch ($_POST['action']) {
            case 'updatebookable':
                railticket_updatebookable();
            case 'filterbookable':
                railticket_showcalendaredit($_POST['year'], $_POST['month']);
        }
    } else {
        railticket_showcalendaredit(intval(date("Y")), $month);
    }
}


function railticket_showcalendaredit($year, $month) {
    global $wpdb, $wp;

    $daysinmonth = intval(date("t", mktime(0, 0, 0, $month, 1, $year)));
    $timetables = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_timetables");
    ?>
    <form method='post' action='<?php echo railticket_get_page_url() ?>'>
    <input type='hidden' name='action' value='updatebookable' />
    <input type='hidden' name='year' value='<?php echo $year; ?>' />
    <input type='hidden' name='month' value='<?php echo $month; ?>' />
    <table border="1">
    <tr><th>Day</th><th>Date</th><th>Timetable</th><th>Bookable</th><th>Specials Only</th><th>Sold Out</th><th>Formation</th><th>Sell Reserve</th><th>Reserve</th></tr>
    <?php
    $ids = array();
    for ($day = 1; $day < $daysinmonth + 1; $day++) {
        $time = mktime(0, 0, 0, $month, $day, $year);

        $current = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_dates WHERE date = '".$year."-".$month."-".$day."'", OBJECT);
        if (count($current) === 0) {
            continue;
        }
        $ct = reset($current);
        $tt = $ct->timetableid;

        echo "<tr>".
            "<td>".date('l', $time)."</td>".
            "<td>".date('jS', $time)."</td>".
            "<td>";
        foreach ($timetables as $t) {
            if ($t->id == $tt) {
                echo ucfirst($t->timetable);
                break;
            }
        }
        echo "</td><td>\n";

        $bk = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_railticket_bookable WHERE dateid = ".$ct->id);
        if (count($bk) == 0) {
            echo "<input type='checkbox' value='1' name='bookable_".$ct->id."' />";
        } else {
            $bookable = $bk[0]->bookable;
        
            echo "<input type='checkbox' value='1' name='bookable_".$ct->id."' ";
            if ($bookable == 1) {
                echo "checked ";
            }
            echo "/></td><td>";
            echo "<input type='checkbox' value='1' name='specialonly_".$ct->id."' ";
            if ($bk[0]->specialonly == 1) {
                echo "checked ";
            }
            echo "/></td><td>";
            echo "<input type='checkbox' value='1' name='soldout_".$ct->id."' ";
            if ($bk[0]->soldout == 1) {
                echo "checked ";
            }
            echo "/></td><td>";
            echo railticket_get_coachset($bk[0]);
            echo "</td><td>";
            echo "<input type='checkbox' value='1' name='reserve_".$ct->id."' ";
            if ($bk[0]->sellreserve == 1) {
                echo "checked ";
            }
            echo "/><td>";
            echo railticket_get_reserve($bk[0]);
            echo "</td>";
        }

        echo "</td></tr>\n";
        $ids[] = $ct->id;
    }
    ?>
    </table>
    <input type="hidden" name="ids" value="<?php echo implode(',', $ids);?>" />
    <input type="submit" value="Update Bookable days" />
    </form>
    <?php
}

function railticket_get_reserve($bk,$deptime = false, $direction = false) {
    if (strlen($bk->reserve) > 0) {
        $reserve = json_decode($bk->reserve);
        switch ($bk->daytype) {
            case 'simple':
                return railticket_get_reserve_string($reserve);
            case 'pertrain':
                if ($deptime) {
                    $comp = json_decode($bk->composition);
                    $setkey = $comp->$direction->$deptime;
                    return railticket_get_reserve_string($reserve->$setkey);
                }
                $str = '';
                foreach ($reserve as $key => $set) {
                    $str .= $key.":&nbsp;".railticket_get_reserve_string($set)."<br />";
                }
                return $str;
        }
   }
}

function railticket_get_reserve_string($reserve) {
    $reserve = (array) $reserve;
    $str = '';
    foreach ($reserve as $i => $num) {
        if ($num > 0) {
            $str .= $i." x".$num.", ";
        }
    }

    return substr($str, 0, strlen($str)-2);
}

function railticket_get_coachset($bk, $deptime = false, $direction = false) {
    $comp = json_decode($bk->composition);
    switch ($bk->daytype) {
        case 'simple':
            return railticket_get_coachset_string($comp->coachset);
            break;
        case 'pertrain':
            if ($deptime) {
                $setkey = $comp->$direction->$deptime;
                return railticket_get_reserve_string($comp->coachsets->$setkey->coachset);
            }
            $str = '';
            foreach ($comp->coachsets as $key => $set) {
                $str .= $key.":&nbsp;".railticket_get_coachset_string($set->coachset)."<br />";
            }
            return $str;
            break;
    }
}

function railticket_get_coachset_string($coachset) {
    $coachset = (array) $coachset;
    $str = '';
    foreach ($coachset as $c => $v) {
        $str .= $v."x ".$c.", ";
    }

    return substr($str, 0, strlen($str)-2);
}

function railticket_updatebookable() {
    global $wpdb;
    $ids = explode(',', $_POST['ids']);

    foreach ($ids as $id) {
        if (array_key_exists("bookable_".$id, $_POST) && $_POST["bookable_".$id] == '1') {
            $bookable = 1;
        } else {
            $bookable = 0;
        }
        $bk = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_railticket_bookable WHERE dateid = ".$id);

        if (array_key_exists("specialonly_".$id, $_POST) && $_POST["specialonly_".$id] == '1') {
            $specialonly = 1;
        } else {
            $specialonly = 0;
        }

        if (array_key_exists("reserve_".$id, $_POST) && $_POST["reserve_".$id] == '1') {
            $reserve = 1;
        } else {
            $reserve = 0;
        }

        if (array_key_exists("soldout_".$id, $_POST) && $_POST["soldout_".$id] == '1') {
            $soldout = 1;
        } else {
            $soldout = 0;
        }

        $timetable = railticket_find_timetable($id, 'id');

        if (count($bk) > 0) {
            $coaches = railticket_process_coaches($bk[0]->composition);
            if (!$coaches->reserve) {
                $coaches->reserve = $bk[0]->reserve;
            }
            $wpdb->update("{$wpdb->prefix}wc_railticket_bookable",
                array('bookable' => $bookable, 'daytype' => $coaches->daytype, 'allocateby' => $coaches->allocateby, 
                    'bays' => $coaches->bays, 'sellreserve' => $reserve, 'soldout' => $soldout, 'reserve' => $coaches->reserve,
                    'specialonly' => $specialonly),
                array('dateid' => $bk[0]->dateid));
        } else {
            if ($bookable) {
                $ssr = false;
                if (get_option('wc_product_railticket_sameservicereturn') == 'on') {
                    $ssr = true;
                }
                $coaches = railticket_process_coaches(get_option('wc_product_railticket_defaultcoaches'), $timetable->timetable);
                $data = array(
                    'dateid' => $id,
                    'daytype' => $coaches->daytype,
                    'allocateby' => $coaches->allocateby,
                    'composition' => $coaches->coachset,
                    'bays' => $coaches->bays,
                    'bookclose' => '{}',
                    'limits' => get_option('wc_product_railticket_bookinglimits'),
                    'bookable' => 1,
                    'soldout' => 0,
                    'override' => randomString(),
                    'sameservicereturn' => $ssr,
                    'reserve' => $coaches->reserve,
                    'sellreserve' => 0,
                    'specialonly' => 0
                );
                $wpdb->insert("{$wpdb->prefix}wc_railticket_bookable", $data);
            }
        }
        $wpdb->flush();
    }
}

function railticket_get_booking_grace($dateid) {
    $bg = get_option('wc_product_railticket_bookinggrace');

    return "{}";
}

function railticket_process_coaches($json, $timetable = null) {
    global $wpdb;
    $parsed = json_decode($json);
    if ($timetable != null) {
        // Should we use the same data as some other timetable. Saves duplication.
        $copy = $parsed->$timetable->copy;
        if ($copy) {
            $parsed = $parsed->$copy;
        } else {
            $parsed = $parsed->$timetable;
        }
    }

    $r = new stdClass();
    $r->daytype = $parsed->daytype;
    $r->allocateby = $parsed->allocateby;
    $r->coachset = json_encode($parsed);
    switch ($parsed->daytype) {
        case 'simple':
            if (property_exists($parsed, 'reserve')) {
                $r->reserve = json_encode($parsed->reserve);
            } else {
                $r->reserve = false;
            }
            $r->bays = railticket_get_coachset_bays($parsed->coachset);
            break;
        case 'pertrain':
            $r->bays = railticket_process_set_allocations($parsed);
            $r->reserve = railticket_process_set_reserve($parsed);
            break;
    }
    return $r;
}

function railticket_process_set_reserve($parsed) {
    $data = array();

    foreach ($parsed->coachsets as $key => $set) {
        $data[$key] = $set->reserve;
    }

    return json_encode($data);
}

function railticket_process_set_allocations($parsed) {
    $data = new stdclass();
    $data->coachsets = array();
    foreach ($parsed->coachsets as $key => $set) {
        $data->coachsets[$key] = railticket_get_coachset_bays($set->coachset, false);
    }
    $data->up = $parsed->up;
    $data->down = $parsed->down;

    return json_encode($data);
}

function railticket_get_coachset_bays($coachset, $json = true) {
    global $wpdb;
    $coachset = (array) $coachset;
    $data = array();
    foreach ($coachset as $coach => $count) {
        $comp = $wpdb->get_var("SELECT composition FROM {$wpdb->prefix}wc_railticket_coachtypes WHERE code = '".$coach."'");
        $bays = json_decode(stripslashes($comp));
        foreach ($bays as $bay) {
            if ($bay->priority) {
                $key = $bay->baysize.'_priority';
            } else {
                $key = $bay->baysize.'_normal';
            }
            if (array_key_exists($key, $data)) {
                $data[$key] += $bay->quantity * $count;
            } else {
                $data[$key] = $bay->quantity * $count;
            }
        }
    }
    ksort($data);
    if ($json) {
        return json_encode($data);
    } else {
        return $data;
    }
}


function randomString()
{
    $characters = '0123456789abcdefghijklmnopqrstuvwxyz';
    $randstring = '';
    for ($i = 0; $i < 6; $i++) {
        $randstring .= $characters[rand(0, strlen($characters))];
    }
    return $randstring;
}

function railticket_view_bookings() {
    ?>
    <h1>Heritage Railway Tickets</h1>    
    <?php
    wp_register_style('railticket_style', plugins_url('wc-product-railticket/ticketbuilder.css'));
    wp_enqueue_style('railticket_style');

    if (array_key_exists('action', $_REQUEST)) {
        switch($_REQUEST['action']) {
            case 'cancelcollected';
                railticket_mark_ticket(false, 'collected');
                railticket_show_order();
                break;
            case 'collected':
                railticket_mark_ticket(true, 'collected');
            case 'showorder':
                railticket_show_order();
                break;
            case 'cancelreturned';
                railticket_mark_ticket(false, 'returned');
                railticket_show_order();
                break;
            case 'returned':
                railticket_mark_ticket(true, 'returned');
                railticket_show_order();
                break;
            case 'showorder':
                railticket_show_order();
                break;
            case 'showdep':
            case 'showspecial':
                railticket_show_departure();
                break;
            case 'createmanual':
                railticket_create_manual();
                break;
            case 'viewwaybill':
                railticket_get_waybill(false);
                break;
            case 'viewordersummary':
                railticket_get_ordersummary(false);
                break;
            case 'deletemanual';
                railticket_delete_manual_order();
                break;
            case 'filterbookings':
            default:
                railticket_summary_selector();
                break;
        }
    } else {
        railticket_summary_selector();
    }

}

function railticket_get_page_url() {
    global $wp;
    //return home_url( add_query_arg( array(), $wp->request ) )."/";
    return '';
}

function railticket_summary_selector() {
    if (array_key_exists('dateofjourney', $_REQUEST)) {
        $dateofjourney = $_REQUEST['dateofjourney'];
        $parts = explode('-', $dateofjourney);
        $chosenyear = $parts[0];
        $chosenmonth = $parts[1];
        $chosenday =  $parts[2];
    } else {
        if (array_key_exists('year', $_REQUEST)) {
            $chosenyear = $_REQUEST['year'];
        } else {
            $chosenyear = intval(date_i18n("Y"));
        }

        if (array_key_exists('month', $_REQUEST)) {
            $chosenmonth = intval($_REQUEST['month']);
        } else {
            $chosenmonth = intval(date_i18n("n"));
        }
    
        if (array_key_exists('day', $_REQUEST)) {
            $chosenday = intval($_REQUEST['day']);
        } else {
            $chosenday = intval(date_i18n("j"));
        }

        $date = new DateTime();
        $date->setDate($chosenyear, $chosenmonth, $chosenday);
        $dateofjourney = $date->format('Y-m-d');
    }
    railticket_show_order_form();
   ?>
    <hr />
    <h2>Service Summaries</h2>
    <div class='railticket_editdate'>
    <form method='post' action='<?php echo railticket_get_page_url(); ?>'>
        <input type='hidden' name='action' value='filterbookings' />    
        <table><tr>
            <td>Day</td>
            <td><?php echo railticket_getdayselect($chosenday);?></td>
          </tr><tr>
            <td>Month</td>
            <td><?php echo railtimetable_getmonthselect($chosenmonth);?></td>
          </tr><tr>
            <td>Year</td>
            <td><?php echo railtimetable_getyearselect($chosenyear);?></td>
        </tr><tr>
            <td colspan='2'><input type='submit' value='Show Departures' /></td>
        </tr></table>
    </form>
    </div>
    <hr />
    <?php

    railticket_show_bookings_summary($dateofjourney);
}

function railticket_show_order_form() {
    global $wp;
    ?>
    <h2>Lookup Online Order<h2>
    <div class='railticket_editdate'>
    <form method='post' action='<?php echo railticket_get_page_url() ?>'>
        <input type='hidden' name='action' value='showorder' />    
        <table><tr>
            <td>Order ID</td>
            <td><input style='width:200px;font-size:large;' type='test' name='orderid' required /></td>
        </tr><tr>
            <td colspan='2'><input type='submit' value='Find Booking' /></td>
        </tr></table>
    </form>
    </div>
    <?php
}

function railticket_getdayselect($chosenday) {
    $sel = "<select name='day'>";
    for($m=1; $m<=31; ++$m){
        if ($m == $chosenday) {
            $s = ' selected="selected" ';
        } else {
            $s = '';
        }
        $sel .= "<option value='".$m."'".$s.">".$m."</option>";
    }
    $sel .= "</select>";
    return $sel;
}

function railticket_find_timetable($param, $field = 'date') {
    global $wpdb;
    $sql = "SELECT {$wpdb->prefix}railtimetable_timetables.*,{$wpdb->prefix}wc_railticket_bookable.override,".
        "{$wpdb->prefix}wc_railticket_bookable.composition, {$wpdb->prefix}wc_railticket_bookable.reserve, ".
        "{$wpdb->prefix}wc_railticket_bookable.daytype ".
        "FROM {$wpdb->prefix}railtimetable_dates ".
        "LEFT JOIN {$wpdb->prefix}railtimetable_timetables ON ".
        " {$wpdb->prefix}railtimetable_dates.timetableid = {$wpdb->prefix}railtimetable_timetables.id ".
        "LEFT JOIN {$wpdb->prefix}wc_railticket_bookable ON ".
        " {$wpdb->prefix}wc_railticket_bookable.dateid = {$wpdb->prefix}railtimetable_dates.id ".
        "WHERE {$wpdb->prefix}railtimetable_dates.".$field." = '".$param."'";
    $timetable = $wpdb->get_results($sql, OBJECT );

    if (count($timetable) > 0) {
        return $timetable[0];
    } else {
        return false;
    }
}


function railticket_show_bookings_summary($dateofjourney) {
    global $wpdb;
    ?><h1>Summary for <?php echo $dateofjourney; ?></h1><?php

    $timetable = railticket_find_timetable($dateofjourney);
    if (!$timetable) {
        echo "<h3>No trains today</h3>";
        return;
    }

    // If the override code is empty, this day has a timetable, but hasn't been initialised.
    if (strlen($timetable->override) == 0) {
        echo "<h3>Booking data not initiailised for this day - please make this day bookable.</h3>";
        return;
    }

    echo "<h2 style='font-size:x-large;line-height:120%;'>Booking override code:<span style='color:red'>".$timetable->override."</span></h2>";

    $stations = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_stations ORDER BY sequence ASC", OBJECT);
    foreach ($stations as $station) {
        railticket_show_station_summary($dateofjourney, $station, $timetable);
    }

    $specials = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_railticket_specials WHERE date = '".$dateofjourney."'");
    if (count($specials) > 0) {
        echo "<h3>Specials</h3>";
        echo "<div class='railticket_inlinedeplist'><ul>";
        foreach ($specials as $special) {
            ?>
            <li><form method='post' action='<?php echo railticket_get_page_url() ?>'>
                <input type='hidden' name='action' value='showspecial' />
                <input type='hidden' name='dateofjourney' value='<?php echo $dateofjourney; ?>' />
                <input type='hidden' name='station' value='<?php echo $special->fromstation; ?>' />
                <input type='hidden' name='destination' value='<?php echo $special->tostation ?>' />
                <input type='hidden' name='direction' value='down' />
                <input type='hidden' name='deptime' value='s:<?php echo  $special->id ?>' />
                <input type='submit' name='submit' value='<?php echo $special->name ?>' />
            </form></li>
            <?php
        }
        echo "</ul></div>";
    }


    ?>
    <hr />
    <div class='railticket_editdate'>
    <p><form method='post' action='<?php echo railticket_get_page_url() ?>'>
        <input type='hidden' name='action' value='viewwaybill' />
        <input type='hidden' name='dateofjourney' value='<?php echo $dateofjourney; ?>' />
        <input type='submit' name='submit' value='View Way Bill' />
    </form></p>
    <p><form method='post' action='<?php echo railticket_get_page_url() ?>'>
        <input type='hidden' name='action' value='viewordersummary' />
        <input type='hidden' name='dateofjourney' value='<?php echo $dateofjourney; ?>' />
        <input type='submit' name='submit' value='View Order Summary' />
    </form></p>
    <p><form method='get' action='<?php echo admin_url('admin-post.php') ?>'>
        <input type='hidden' name='action' value='waybill.csv' />
        <input type='hidden' name='dateofjourney' value='<?php echo $dateofjourney; ?>' />
        <input type='submit' name='submit' value='Get Way Bill as Spreadsheet file' />
    </form></p>
    <p><form method='get' action='<?php echo admin_url('admin-post.php') ?>'>
        <input type='hidden' name='action' value='ordersummary.csv' />
        <input type='hidden' name='dateofjourney' value='<?php echo $dateofjourney; ?>' />
        <input type='submit' name='submit' value='Get Order Summary Spreadsheet file' />
    </form></p>
    </ul>
    </div>
    <?php
}

function railticket_show_station_summary($dateofjourney, $station, $timetable) {
    global $wpdb;
    $deptimes = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_times WHERE station = ".$station->id." AND timetableid = ".$timetable->id)[0];

    if (strlen($deptimes->down_deps) > 0) {
        echo "\n<h3>Down Departures from ".$station->name."</h3>\n";
        railticket_show_dep_buttons($dateofjourney, $station, $timetable, $deptimes, "down");
    }

    if (strlen($deptimes->up_deps) > 0) {
        echo "\n<h3>Up Departures from ".$station->name."</h3>\n";
        railticket_show_dep_buttons($dateofjourney, $station, $timetable, $deptimes, "up");
    }
}

function railticket_show_dep_buttons($dateofjourney, $station, $timetable, $deptimes, $direction) {
    global $wpdb, $wp;
    $key = $direction.'_deps';
    $alltimes = explode(',', $deptimes->$key);

    if ($direction == 'up') {
        $destination = $wpdb->get_var("SELECT id FROM `wp_railtimetable_stations` ORDER BY SEQUENCE ASC LIMIT 1");
    } else {
        $destination = $wpdb->get_var("SELECT id FROM `wp_railtimetable_stations` ORDER BY SEQUENCE DESC LIMIT 1");
    }

    echo "<div class='railticket_inlinedeplist'><ul>";
    foreach ($alltimes as $t) {
        ?>
        <li><form method='post' action='<?php echo railticket_get_page_url() ?>'>
            <input type='hidden' name='action' value='showdep' />
            <input type='hidden' name='dateofjourney' value='<?php echo $dateofjourney; ?>' />
            <input type='hidden' name='station' value='<?php echo $station->id; ?>' />
            <input type='hidden' name='destination' value='<?php echo $destination; ?>' />
            <input type='hidden' name='direction' value='<?php echo $direction ?>' />
            <input type='hidden' name='deptime' value='<?php echo $t ?>' />
            <input type='submit' name='submit' value='<?php echo $t ?>' />
        </form></li>
        <?php
    }

    echo "</ul></div>";
}

function railticket_show_departure() {
    global $wpdb, $wp;

    $dateofjourney = $_REQUEST['dateofjourney'];
    $station =  $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_stations WHERE id =".$_REQUEST['station'], OBJECT)[0];
    $destination =  $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_stations WHERE id =".$_REQUEST['destination'], OBJECT)[0];
    $direction = $_REQUEST['direction'];
    $deptime = $_REQUEST['deptime'];
    $timetable = railticket_find_timetable($dateofjourney);

    if ($_REQUEST['action'] == 'showspecial') {
        $parts = explode(':',  $_REQUEST['deptime']);
        $depname = $wpdb->get_var("SELECT name FROM {$wpdb->prefix}wc_railticket_specials WHERE id = ".$parts[1]);
    } else {
        $depname = $deptime;
    }

    $bookings = $wpdb->get_results("SELECT {$wpdb->prefix}wc_railticket_bookings.*, {$wpdb->prefix}railtimetable_stations.name ".
        "FROM {$wpdb->prefix}wc_railticket_bookings ".
        "LEFT JOIN {$wpdb->prefix}railtimetable_stations ON ".
        "{$wpdb->prefix}railtimetable_stations.id = {$wpdb->prefix}wc_railticket_bookings.tostation ".
        "WHERE date='".$dateofjourney."' AND ".
        "time = '".$deptime."' AND fromstation = ".$station->id." AND direction = '".$direction."' ");

    $seats = 0;
    foreach ($bookings as $booking) {
        $seats += $booking->seats;
    }

    $tk = new TicketBuilder($dateofjourney, $station->id, $destination->id, $deptime, false, 'single', false, false, false, false, '',false, false);
    $basebays = $tk->get_service_inventory($deptime, $station->id, $destination->id, true, true);
    $capused = $tk->get_service_inventory($deptime, $station->id, $destination->id, false, true);
    $capcollected = $tk->get_service_inventory($deptime, $station->id, $destination->id, false, true, true);

    echo "<div class='railticket_editdate'><h3>Service summary</h3><table border='1'>".
        "<tr><th>Timetable</th><th>".$timetable->timetable."</th></tr>".
        "<tr><th>Station</th><th>".$station->name."</th></tr>".
        "<tr><th>Destination</th><th>".$destination->name."</th></tr>".
        "<tr><th>Date</td><th>".$dateofjourney."</th></tr>".
        "<tr><th>Time</td><th>".$depname."</th></tr>".
        "<tr><th>Direction</th><th>".$direction."</th></tr>".
        "<tr><th>Total Orders</th><th>".count($bookings)."</th></tr>".
        "<tr><th>Seats Used</th><th>".$seats."</th></tr>".
        "<tr><th>Seats Available</th><th>".$capused->totalseats."</th></tr>".
        "</table></div><br />";

    echo "<h3>Bay Usage (one way to destination)</h3><div class='railticket_trainbookings'>".
        "<table border='1'><th>Bay</th><th>Total</th><th>Sold</th><th>Collected</th><th>Empty</th></tr>";
    foreach ($basebays as $bay => $space) {
        $bayd = str_replace('_', ' seat ', $bay);
        $bayd = str_replace('priority', 'disabled', $bayd);
        echo "<td>".$bayd."</td><td>".$space."</td><td>".
            ($space-$capused->bays[$bay])."</td><td>".($space-$capcollected->bays[$bay])."</td><td>".$capused->bays[$bay]."</td></tr>";
    }
    echo "</table></div>";

    echo "<p>Coaches: ".railticket_get_coachset($timetable, $deptime, $direction)."<br />".
        "Reserve: ".railticket_get_reserve($timetable, $deptime, $direction)."</p>";
    ?>
    <br />
    <h3>Booking summary</h3>
    <div class='railticket_trainbookings'>
    <table border='1'>
        <tr>
            <th>Order</th>
            <th>To</th>
            <th>Seats</th>
            <th>Bays</th>
            <th>Collected</th>
        </tr>
    <?php
    foreach ($bookings as $booking) {
        echo "<tr>";
        if ($booking->manual) {
            echo "<td><form action='".railticket_get_page_url()."' method='post'>".
                "<input type='hidden' name='action' value='showorder' />".
                "<input type='hidden' name='orderid' value='M".$booking->manual."' />".
                "<input type='submit' value='M".$booking->manual."' />".
                "</form></td>";
        } else {
           if (strlen($booking->woocartitem) > 0) {
            echo "<td>In Cart</td>";
           } else {
            echo "<td><form action='".railticket_get_page_url()."' method='post'>".
                "<input type='hidden' name='action' value='showorder' />".
                "<input type='hidden' name='orderid' value='".$booking->wooorderid."' />".
                "<input type='submit' value='".$booking->wooorderid."' />".
                "</form></td>";
           }
        }
        echo "<td>".$booking->name."</td>".
            "<td>".$booking->seats."</td>".
            "<td>";
        echo railticket_get_bookingbays_display($booking->id);
        echo "</td>";

        if ($booking->collected) {
            echo "<td>Y</td>";   
        } else {
            echo "<td>N</td>";
        }
        
        echo "</tr>";
    }

    ?>
    </table>

    <br />
    </div>
    <div class='railticket_editdate'>
    <form action='<?php echo railticket_get_page_url() ?>' method='post'>
        <input type='hidden' name='action' value='<?php echo $_REQUEST['action']; ?>' />
        <input type='hidden' name='dateofjourney' value='<?php echo $dateofjourney; ?>' />
        <input type='hidden' name='station' value='<?php echo $station->id; ?>' />
        <input type='hidden' name='direction' value='<?php echo $direction ?>' />
        <input type='hidden' name='deptime' value='<?php echo $deptime ?>' />
        <input type='hidden' name='destination' value='<?php echo $destination->id ?>' />
        <input type='submit' name='submit' value='Refresh Display' />
    </form><br />
    <form action='<?php echo railticket_get_page_url() ?>' method='post'>
        <input type='hidden' name='action' value='filterbookings' />
        <input type='hidden' name='dateofjourney' value='<?php echo $dateofjourney; ?>' />
        <input type='submit' name='submit' value='Back to Services' />
    </form><br />
    <form action='/book/' method='post'>
        <input type='hidden' name='action' value='createmanual' />
        <input type='hidden' name='show' value='1' />
        <input type='hidden' name='a_dateofjourney' value='<?php echo $dateofjourney; ?>' />
        <input type='hidden' name='a_station' value='<?php echo $station->id; ?>' />
        <input type='hidden' name='a_direction' value='<?php echo $direction ?>' />
        <input type='hidden' name='a_deptime' value='<?php echo $deptime ?>' />
        <input type='hidden' name='a_destination' value='<?php echo $destination->id ?>' />
        <input type='submit' name='submit' value='Add Manual Booking' />
    </form>
    </div>
    <?php
}

function railticket_get_bookingbays_display($bookingid) {
    global $wpdb;
    $str = '';
    $bays = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_railticket_booking_bays WHERE bookingid = ".$bookingid);
    foreach ($bays as $bay) {
        $str .= $bay->baysize;
        if ($bay->priority) {
            $str .= "d";
        }
        $str .= "X".$bay->num." ";
    }
    return $str;
}

function railticket_mark_ticket($val, $field) {
    global $wpdb;
    $orderid = $_POST['orderid'];
    $wpdb->update("{$wpdb->prefix}wc_railticket_bookings",
                array($field => $val),
                array('wooorderid' => $orderid));
}

function railticket_get_ticket_item($order) {
    foreach ( $order->get_items() as $item_id => $item ) {
        // Get the product object
        $product = $item->get_product();

        // Get the product Id
        $product_id = $product->get_id();
        if ($product_id == get_option('wc_product_railticket_woocommerce_product')) {
            return $item;
            break;
        }
    }
    return false;
}

function railticket_show_order() {
    global $wpdb, $woocommerce;

    if (strlen($_REQUEST['orderid']) == 0) {
        echo "<p>No order number entered.</p>";
        railticket_summary_selector();
        return;
    }

    if (strpos(strtoupper($_REQUEST['orderid']), 'M') === false) {
        railticket_show_wooorder($_REQUEST['orderid']);
    } else {
        railticket_show_manualorder($_REQUEST['orderid']);
    }
}

function railticket_show_manualorder($orderid) {
    global $wpdb;
    $orderid = substr($orderid, 1);
    $order = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_railticket_manualbook WHERE id = ".$orderid);

    if (!$order || count($order) == 0) {
        echo "<p style='font-size:large;color:red;font-weight:bold;'>Manual Order '".$orderid."' not found.</p>";
        railticket_summary_selector();
        return;
    }

    $order = $order[0];
    $bookings = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_railticket_bookings WHERE manual = ".$order->id);
    $stns = railticket_get_stations_map();

    $depdate = DateTime::createFromFormat("Y-m-d", $bookings[0]->date,  new DateTimeZone(get_option('timezone_string')));

    ?><div class='railticket_editdate'><table border='1'>
        <tr><th>Order ID</th><td class='railticket_meta'>M<?php echo $orderid; ?></td></tr>
        <tr><th>Total Paid</th><td class='railticket_meta'>£<?php echo $order->price; ?></td></tr>
        <tr><th>Fare Supplement</th><td class='railticket_meta'>£<?php echo $order->supplement; ?></td></tr>
        <tr><th>Seats</th><td class='railticket_meta'><?php echo $order->seats; ?></td></tr>
        <tr><th>Tickets</th><td class='railticket_meta'><?php
            $ta = (array) json_decode($order->tickets);
            foreach ($ta as $ticket => $num) {
                echo str_replace('_', ' ', $ticket)." x".$num.", ";
            }
         ?></td></tr>
        <tr><th>Journey Type</th><td class='railticket_meta'><?php echo $order->journeytype; ?></td></tr>
        <tr><th>Date of travel</td><td class='railticket_meta'><?php echo $depdate->format('j-M-Y'); ?></td></tr>
        <tr><th>Outbound Dep</th><td class='railticket_meta'>
            <?php echo $stns[$bookings[0]->fromstation]->name." ".date("g:i", strtotime($bookings[0]->time)); ?></td></tr>
        <?php railticket_show_bays($bookings[0]->id, $bookings[0]->fromstation, "Outbound Bays" ); ?>
        <?php
            if ($order->journeytype == 'return') {
                ?>
        <tr><th>Return Dep</th><td class='railticket_meta'>
            <?php echo $stns[$bookings[1]->fromstation]->name." ".date("g:i", strtotime($bookings[1]->time)); ?></td></tr>
            <?php railticket_show_bays($bookings[1]->id, $bookings[1]->fromstation, "Return Bays" );

            }
        ?>
        <tr><th>Notes</th><td class='railticket_meta'><?php echo $order->notes; ?></td></tr>
    <?php

    echo "<tr><th>Collected</th><td class='railticket_meta'>";
    if ($bookings[0]->collected) {
        echo "Yes";
    } else {
        echo "No";
    }
    echo "</td></tr>";
    if ($order->journeytype != 'single') {
        echo "<tr><th>Returned</th><td class='railticket_meta'>";
        if ($bookings[0]->returned) {
            echo "Yes";
        } else {
            echo "No";
        }
        echo "</td></tr>";
    }
    echo "</table>";

    if (!$bookings[0]->collected) {
        echo "<p><form action='".railticket_get_page_url()."' method='post'>".
            "<input type='hidden' name='action' value='collected' />".
            "<input type='hidden' name='orderid' value='M".$orderid."' />".
            "<input type='submit' value='Mark Collected' />".
            "</form></p>";
    } else {
        echo "<p><form action='".railticket_get_page_url()."' method='post'>".
            "<input type='hidden' name='action' value='cancelcollected' />".
            "<input type='hidden' name='orderid' value='M".$orderid."' />".
            "<input type='submit' value='Cancel Collected' />".
            "</form></p>";
    }

    if ($order->journeytype != 'single') {
        if (!$bookings[0]->returned) {
            echo "<p><form action='".railticket_get_page_url()."' method='post'>".
                "<input type='hidden' name='action' value='returned' />".
                "<input type='hidden' name='orderid' value='M".$orderid."' />".
                "<input type='submit' value='Mark Returned' />".
                "</form></p>";
        } else {
            echo "<p><form action='".railticket_get_page_url()."' method='post'>".
                "<input type='hidden' name='action' value='cancelreturned' />".
                "<input type='hidden' name='orderid' value='M".$orderid."' />".
                "<input type='submit' value='Cancel Returned' />".
                "</form></p>";
        }
    }

    if (current_user_can('delete_tickets')) {
        echo "<p><form action='".railticket_get_page_url()."' method='post'>".
            "<input type='hidden' name='action' value='deletemanual' />".
            "<input type='hidden' name='orderid' value='".$orderid."' />".
            "<input type='submit' value='Delete Manual Order' />".
            "</form></p>";
    }

    railticket_show_back_to_services($bookings[0]->date);
    echo "</div>";
}

function railticket_delete_manual_order() {
    global $wpdb;
    $orderid = $_POST['orderid'];

    $bookings = $wpdb->get_results("SELECT id, date FROM {$wpdb->prefix}wc_railticket_bookings WHERE manual = ".$orderid);
    foreach ($bookings as $booking) {
        $wpdb->get_results("DELETE FROM {$wpdb->prefix}wc_railticket_booking_bays WHERE bookingid = ".$booking->id);
    }
    $wpdb->get_results("DELETE FROM {$wpdb->prefix}wc_railticket_bookings WHERE manual = ".$orderid);
    $wpdb->get_results("DELETE FROM {$wpdb->prefix}wc_railticket_manualbook WHERE id = ".$orderid);
    echo "<h3>Order M".$orderid." deleted</h3>";
    railticket_show_back_to_services($bookings[0]->date);
}

function railticket_show_wooorder($orderid) {
    global $wpdb;
    $order = wc_get_order($orderid);

    if (!$order) {
        echo "<p style='font-size:large;color:red;font-weight:bold;'>Online Order '".$orderid."' not found.</p>";
        railticket_summary_selector();
        return;
    }

    $order_item = railticket_get_ticket_item($order);

    if (!$order_item) {
        echo "<p>No tickets were purchased with this order.</p>";
        railticket_summary_selector();
        return;
    }
    $formatted_meta_data = $order_item->get_formatted_meta_data( '_', true );

    ?>
    <div class='railticket_editdate'>
    <table border='1'>
        <tr><th>Order ID</th><td class='railticket_meta'><?php echo $orderid; ?></td></tr>
        <tr><th>Name</th><td class='railticket_meta'><?php echo $order->get_formatted_billing_full_name(); ?></td></tr>
        <tr><th>Postcode</th><td class='railticket_meta'><?php echo $order->get_billing_postcode(); ?></td></tr>
        <tr><th>Paid</th><td class='railticket_meta'><?php if ($order->is_paid()) {echo "Yes";} else {echo "No";} ?></td></tr>
    <?php
    $fromstation = false;
    $tostation = false;
    $journeytype = false;
    $dateofjourney = false;
    foreach ($formatted_meta_data as $meta) {
        switch ($meta->key) {
            case 'tickettimes-outbays':
            case 'tickettimes-retbays':
            case 'tickettimes-pricesupplement':
                continue 2;
            case 'tickettimes-fromstation':
                $fromstation = $meta->value;
                break;
            case 'tickettimes-tostation':
                $tostation = $meta->value;
                break;
            case 'tickettimes-journeytype':
                $journeytype = $meta->value;
                break;
            case 'tickettimes-dateoftravel':
                $dateofjourney = $meta->value;
        }
        echo "<tr><th>".$meta->display_key."</th><td class='railticket_meta'>".strip_tags($meta->display_value)."</td></tr>";
    }
    $booking = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_railticket_bookings WHERE wooorderid = ".$_POST['orderid'].
        " AND fromstation = ".$fromstation);
    railticket_show_bays($booking[0]->id, $fromstation, 'Outbound bays');
    if ($journeytype == 'return') {
        $rbooking = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_railticket_bookings WHERE wooorderid = ".$_POST['orderid'].
            " AND fromstation = ".$tostation);
        railticket_show_bays($rbooking[0]->id, $tostation, 'Return bays');
    }
    echo "<tr><th>Collected</th><td class='railticket_meta'>";
    if ($booking[0]->collected) {
        echo "Yes";
    } else {
        echo "No";
    }
    echo "</td></tr>";
    if ($journeytype != 'single') {
        echo "<tr><th>Returned</th><td class='railticket_meta'>";
        if ($booking[0]->returned) {
            echo "Yes";
        } else {
            echo "No";
        }
        echo "</td></tr>";
    }
    echo "</table>";

    if (!$booking[0]->collected) {
        echo "<p><form action='".railticket_get_page_url()."' method='post'>".
            "<input type='hidden' name='action' value='collected' />".
            "<input type='hidden' name='orderid' value='".$_POST['orderid']."' />".
            "<input type='submit' value='Mark Collected' />".
            "</form></p>";
    } else {
        echo "<p><form action='".railticket_get_page_url()."' method='post'>".
            "<input type='hidden' name='action' value='cancelcollected' />".
            "<input type='hidden' name='orderid' value='".$_POST['orderid']."' />".
            "<input type='submit' value='Cancel Collected' />".
            "</form></p>";
    }

    if ($journeytype != 'single') {
        if (!$booking[0]->returned) {
            echo "<p><form action='".railticket_get_page_url()."' method='post'>".
                "<input type='hidden' name='action' value='returned' />".
                "<input type='hidden' name='orderid' value='".$_POST['orderid']."' />".
                "<input type='submit' value='Mark Returned' />".
                "</form></p>";
        } else {
            echo "<p><form action='".railticket_get_page_url()."' method='post'>".
                "<input type='hidden' name='action' value='cancelreturned' />".
                "<input type='hidden' name='orderid' value='".$_POST['orderid']."' />".
                "<input type='submit' value='Cancel Returned' />".
                "</form></p>";
        }
    }
    railticket_show_back_to_services($dateofjourney);
    echo "</div>";
}

function railticket_show_back_to_services($dateofjourney) {
    ?>
    <br /><br />
    <form action='<?php echo railticket_get_page_url() ?>' method='post'>
        <input type='hidden' name='action' value='filterbookings' />
        <input type='hidden' name='dateofjourney' value='<?php echo $dateofjourney; ?>' />
        <input type='submit' name='submit' value='Back to Services' />
    </form>
    <?php
}

function railticket_show_bays($bookingid, $fromstation, $jt) {
    global $wpdb;
    $frombays = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_railticket_booking_bays WHERE bookingid = ".$bookingid);
    $fb = '';
    foreach ($frombays as $bay) {
        $fb .= $bay->baysize. " seat ";
        if ($bay->priority) {
            $fb.= " disabled ";
        }
        $fb.= " bay x".$bay->num.", ";
    }
    $fb = substr($fb, 0, strlen($fb)-2);
    echo "<tr><th>".$jt."</th><td class='railticket_meta'>".$fb."</td></tr>";
}

function railticket_create_manualold() {
   $tkt = new TicketBuilder();
   echo $tkt->render();
}

function railticket_create_manual($id = false) {
    global $wpdb;
    $dateofjourney = $_POST['dateofjourney'];
    $station = $_POST['station'];
    $deptime = $_POST['deptime'];
    $direction = $_POST['direction'];
    $destination = $_POST['destination'];
    
    if ($id !== false) {
        $rec = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_railticket_manualbook WHERE id=".$id)[0];
    } else {
        $rec = new stdclass();
        $rec->id = false;
        $rec->journeytype = 'return';
        $rec->bookingout = false;
        $rec->bookingret = false;
        $rec->price = false;
        $rec->seats = false;
        $rec->travellers = "{}";
        $rec->tickets = "{}";
        $rec->notes = "";
    }
    ?>
    <form action='<?php echo railticket_get_page_url() ?>' method='post'>
    <input type='hidden' name='action' value='savemanual' />
    <p>Date of Journey: <?php echo $dateofjourney; ?></p>
    <input type='hidden' name='dateofjourney' value='<?php echo $dateofjourney; ?>' />
    <p>Departure station: 
    <?php
    $stns = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_stations ", OBJECT);
    echo $stns[$station]->name;
    ?>
    </p>
    <p>Destination station: <?php echo $stns[$destination]; ?></p>
    <?php
    $timetable = $wpdb->get_results("SELECT {$wpdb->prefix}railtimetable_timetables.* FROM {$wpdb->prefix}railtimetable_dates ".
        "LEFT JOIN {$wpdb->prefix}railtimetable_timetables ON ".
        " {$wpdb->prefix}railtimetable_dates.timetableid = {$wpdb->prefix}railtimetable_timetables.id ".
        "LEFT JOIN {$wpdb->prefix}wc_railticket_bookable ON ".
        " {$wpdb->prefix}wc_railticket_bookable.dateid = {$wpdb->prefix}railtimetable_dates.id ".
        "WHERE {$wpdb->prefix}railtimetable_dates.date = '".$dateoftravel."'", OBJECT );

    
    ?>
    <input type='submit' value='Create Booking' />
    </form>
    <?php
}

add_action ('admin_post_waybill.csv', 'railticket_get_waybillcsv');
function railticket_get_waybillcsv() {
    railticket_get_waybill(true);
}

function railticket_get_waybill($iscsv) {
    global $wpdb;
    $date = $_REQUEST['dateofjourney'];
    $header = array('Journey', 'Journey Type', 'Ticket Type', 'Number', 'Fare', 'Total');
    $td = array('Date', $date);
    if ($iscsv) {;
        header('Content-Type: application/csv');
        header('Content-Disposition: attachment; filename="waybill-'.$date.'.csv";');
        header('Pragma: no-cache');
        $f = fopen('php://output', 'w');
        fputcsv($f, $td);
        fputcsv($f, array('', '', '', '', '', ''));
        fputcsv($f, $header);
    } else {
        echo "<table border='1'>";
        railticket_waybill_row($td);
        railticket_waybill_row(array('', '', '', '', '', ''));
        railticket_waybill_row($header, 'th');
    }
    $totalsdata = railticket_get_waybill_data($date);

    $stations = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_stations ORDER BY sequence ASC", OBJECT);
    $stn = array();
    foreach ($stations as $station) {
        $stn[$station->id] = $station->name;
    }

    foreach ($totalsdata->totals as $stationone => $dataone) {
        foreach ($dataone as $stationtwo => $datatwo) {
            foreach ($datatwo as $journeytype => $datathree) {
                ksort($datathree);
                foreach ($datathree as $tickettype => $qty) {

                    $sql = "SELECT {$wpdb->prefix}wc_railticket_prices.id, ".
                        "{$wpdb->prefix}wc_railticket_prices.tickettype, ".
                        "{$wpdb->prefix}wc_railticket_prices.price, ".
                        "{$wpdb->prefix}wc_railticket_tickettypes.name ".
                        "FROM {$wpdb->prefix}wc_railticket_prices ".
                        "INNER JOIN {$wpdb->prefix}wc_railticket_tickettypes ON ".
                        "{$wpdb->prefix}wc_railticket_tickettypes.code = {$wpdb->prefix}wc_railticket_prices.tickettype ".
                        "WHERE ((stationone = ".$stationone." AND stationtwo = ".$stationtwo.") OR ".
                        "(stationone = ".$stationtwo." AND stationtwo = ".$stationone.")) AND ".
                        "journeytype = '".$journeytype."' AND {$wpdb->prefix}wc_railticket_tickettypes.code ='".$tickettype."'";
                    $ticketdata = $wpdb->get_results($sql, OBJECT);

                    $line = array(
                        $stn[$stationone]." - ".$stn[$stationtwo],
                        $journeytype,
                        $tickettype,
                        $qty,
                        $ticketdata[0]->price,
                        $qty*$ticketdata[0]->price
                    );

                    if ($iscsv) {
                        fputcsv($f, $line);
                    } else {
                        railticket_waybill_row($line);
                    }
                }
            }
        }
    }

    $summary = array();
    $summary[] = array('Total Supplements', '', '', '', '', $totalsdata->totalsupplement);
    $summary[] = array('Total Revenue', '', '', '', '', $totalsdata->totalmanual+$totalsdata->totalwoo);
    $summary[] = array('', '', '', '', '', '');

    $summary[] = array('Total Passengers', $totalsdata->totalseats);
    $summary[] = array('Total Tickets', $totalsdata->totaltickets);
    $summary[] = array('Total One Way Journeys', $totalsdata->totaljourneys); 
    $summary[] = array('Total Manual Booking Revenue', $totalsdata->totalmanual);
    $summary[] = array('Total Online Bookings Revenue', $totalsdata->totalwoo);


    if ($iscsv) {
        foreach ($summary as $s) {
            fputcsv($f, $s);
        }
        fclose($f);
    } else {
        foreach ($summary as $s) {
            railticket_waybill_row($s);
        }
        echo "</table>";
        ?>
        <p><form method='post' action='<?php echo railticket_get_page_url() ?>'>
        <input type='hidden' name='action' value='viewwaybill' />
        <input type='hidden' name='dateofjourney' value='<?php echo $date; ?>' />
        <input type='submit' name='submit' value='Refresh Data' />
        </form><br />
        <form action='<?php echo railticket_get_page_url() ?>' method='post'>
        <input type='hidden' name='action' value='filterbookings' />
        <input type='hidden' name='dateofjourney' value='<?php echo $date; ?>' />
        <input type='submit' name='submit' value='Back to Services' />
        </form>
        </p>
        <?php
    }

}

function railticket_get_waybill_data($date) {
    global $wpdb;

    $bookings = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_railticket_bookings WHERE date = '".$date."'");

    $processed = array();
    $totals = array();
    $totalseats = 0;
    $totaltickets = 0;
    $totalsupplement = 0;
    $totalwoo = 0;
    $totalmanual = 0;
    $totaljourneys = 0;

    foreach ($bookings as $booking) {
        if (strlen($booking->woocartitem) > 0) {
            continue;
        }

        if ($booking->wooorderid > 0 && !in_array($booking->wooorderid, $processed)) {
            $data_store = WC_Data_Store::load( 'order-item' );
            $totalseats += $data_store->get_metadata($booking->wooorderitem, "tickettimes-totalseats", true);
            $journeytype = $data_store->get_metadata($booking->wooorderitem, "tickettimes-journeytype", true);
            $totalsupplement += $data_store->get_metadata($booking->wooorderitem, "tickettimes-pricesupplement", true);
            if ($journeytype == 'single') {
                $totaljourneys += $data_store->get_metadata($booking->wooorderitem, "tickettimes-totalseats", true);
            } else {
                $totaljourneys += ($data_store->get_metadata($booking->wooorderitem, "tickettimes-totalseats", true) * 2);
            }
            $ta = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE order_item_id = ".$booking->wooorderitem.
                " AND meta_key LIKE 'ticketsallocated-%'");
            $ticketsallocated = array();
            foreach ($ta as $ticket) {
                $tparts = explode('-', $ticket->meta_key);
                $ticketsallocated[$tparts[1]] = intval($ticket->meta_value);
            }
            $processed[] = $booking->wooorderid;
            $totalwoo += $data_store->get_metadata($booking->wooorderitem, "_line_total", true);
        } elseif ($booking->manual > 0 && !in_array('M'.$booking->manual, $processed)) {
            $processed[] = 'M'.$booking->manual;
            $mb = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_railticket_manualbook WHERE id = ".$booking->manual)[0];
            $totalseats += $mb->seats;
            $journeytype = $mb->journeytype;
            if ($journeytype == 'single') {
                $totaljourneys += $mb->seats;
            } else {
                $totaljourneys += ($mb->seats * 2);
            }
            $ticketsallocated = (array) json_decode($mb->tickets);
            $totalmanual += $mb->price;
            $totalsupplement += $mb->supplement;
        } else {
            //Bookings should all be manual or woocommerce
            continue;
        }
        if (!array_key_exists($booking->fromstation, $totals)) {
            $totals[$booking->fromstation] = array();
        }
        if (!array_key_exists($booking->tostation, $totals[$booking->fromstation])) {
            $totals[$booking->fromstation][$booking->tostation] = array();
        }
        if (!array_key_exists($journeytype, $totals[$booking->fromstation][$booking->tostation])) {
            $totals[$booking->fromstation][$booking->tostation][$journeytype] = array();
        }
        foreach ($ticketsallocated as $ticket => $qty) {
            $totaltickets += $qty;
            if (!array_key_exists($ticket, $totals[$booking->fromstation][$booking->tostation][$journeytype])) {
                $totals[$booking->fromstation][$booking->tostation][$journeytype][$ticket] = $qty;
            } else {
                $totals[$booking->fromstation][$booking->tostation][$journeytype][$ticket] += $qty;
            }
        }

    }

    $r = new Stdclass();
    $r->totals = $totals;
    $r->totalseats = $totalseats;
    $r->totaltickets = $totaltickets;
    $r->totalsupplement = $totalsupplement;
    $r->totalwoo = $totalwoo;
    $r->totalmanual = $totalmanual;
    $r->bookings = $bookings;
    $r->totaljourneys = $totaljourneys;
    return $r;
}

function railticket_waybill_row($rows, $type = 'td', $cola = 'th') {
    echo "<tr>";
    $first = true;
    foreach ($rows as $row) {
        if ($first) {
            echo "<".$cola.">".$row."</".$cola.">";
            $first = false;
        } else {
            echo "<".$type.">".$row."</".$type.">";
        }
    }
    echo "</tr>";
}


add_action ('admin_post_ordersummary.csv', 'railticket_get_ordersummary_csv');
function railticket_get_ordersummary_csv() {
    railticket_get_ordersummary(true);
}

function railticket_get_stations_map() {
    global $wpdb;
    $stations = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_stations ORDER BY sequence ASC", OBJECT);
    $stns = array();
    foreach ($stations as $s) {
        $stns[$s->id] = $s;
    }
    return $stns;
}

function railticket_get_ordersummary($iscsv = false) {
    global $wpdb;

    $stns = railticket_get_stations_map();
    $date = $_REQUEST['dateofjourney'];
    $header = array( 'Order ID', 'Name' , 'Email', 'From', 'To', 'Journey Type', 'Tickets', 'Seats', 'Supplement', 'Total Price');
    $td = array('Date', $date);
    if ($iscsv) {
        header('Content-Type: application/csv');
        header('Content-Disposition: attachment; filename="ordersummary-'.$date.'.csv";');
        header('Pragma: no-cache');
        $f = fopen('php://output', 'w');
        fputcsv($f, $td);
        fputcsv($f, array('', '', '', '', '', ''));
        fputcsv($f, $header);
    } else {
        echo "<table border='1'>";
        railticket_waybill_row($td);
        railticket_waybill_row(array('', '', '', '', '', ''));
        railticket_waybill_row($header, 'th');
    }

    $bookings = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_railticket_bookings WHERE date = '".$date."'");
    $processed = array();
    $lines = array();

    foreach ($bookings as $booking) {
        if (strlen($booking->woocartitem) > 0) {
            continue;
        }

        $line = array();
        if ($booking->wooorderid > 0 && !in_array($booking->wooorderid, $processed)) {
            $processed[] = $booking->wooorderid;
            $order = wc_get_order($booking->wooorderid);
            $data_store = WC_Data_Store::load( 'order-item' );
            if ($iscsv) {
                $line[] = $booking->wooorderid;
            } else {
                 $line[] = "<form action='".railticket_get_page_url()."' method='post'>".
                "<input type='hidden' name='action' value='showorder' />".
                "<input type='hidden' name='orderid' value='".$booking->wooorderid."' />".
                "<input type='submit' value='".$booking->wooorderid."' />".
                "</form>";
            }
            $line[] = $order->get_formatted_billing_full_name();
            $line[] = $order->get_billing_email();
            $line[] = $stns[$data_store->get_metadata($booking->wooorderitem, "tickettimes-fromstation")]->name;
            $line[] = $stns[$data_store->get_metadata($booking->wooorderitem, "tickettimes-tostation")]->name;
            $line[] = $data_store->get_metadata($booking->wooorderitem, "tickettimes-journeytype", true);

            $ta = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE order_item_id = ".$booking->wooorderitem.
                " AND meta_key LIKE 'ticketsallocated-%'");
            $ticketsallocated = '';
            foreach ($ta as $ticket) {
                $tparts = explode('-', $ticket->meta_key);
                $ticketsallocated .= $tparts[1]." x".$ticket->meta_value.", ";
            }
            $line[] = $ticketsallocated;

            $line[] = $data_store->get_metadata($booking->wooorderitem, "tickettimes-totalseats", true);
            $line[] = $data_store->get_metadata($booking->wooorderitem, "tickettimes-pricesupplement", true);
            $line[] = $data_store->get_metadata($booking->wooorderitem, "_line_total", true);

            $key = $order->get_billing_last_name()." ".$order->get_billing_first_name()." ".wp_generate_uuid4();
            $lines[$key] = $line;
        } elseif ($booking->manual > 0 && !in_array('M'.$booking->manual, $processed)) {
            $processed[] = 'M'.$booking->manual;
            if ($iscsv) {
                $line[]  = 'M'.$booking->manual;
            } else {
                 $line[] = "<form action='".railticket_get_page_url()."' method='post'>".
                "<input type='hidden' name='action' value='showorder' />".
                "<input type='hidden' name='orderid' value='M".$booking->manual."' />".
                "<input type='submit' value='M".$booking->manual."' />".
                "</form>";
            }
            $line[] = '';
            $line[] = '';
            $mb = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_railticket_manualbook WHERE id = ".$booking->manual)[0];
            $booking = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_railticket_bookings WHERE manual = ".$booking->manual)[0];
            $line[] = $stns[$booking->fromstation]->name;
            $line[] = $stns[$booking->tostation]->name;
            $line[] = $mb->journeytype;
            $ta = (array) json_decode($mb->tickets);
            $ticketsallocated = '';
            foreach ($ta as $ticket => $num) {
                $ticketsallocated .= $ticket." x".$num.", ";
            }
            $line[] = $ticketsallocated;
            $line[] = $mb->seats;
            $line[] = $mb->supplement;
            $line[] = $mb->price;
            $lines['zzzzzzzzzzzz'.$booking->manual] = $line;
        } else {
            //Bookings should all be manual or woocommerce
            continue;
        }
    }
    uksort($lines, function ($a, $b) {
        $a = mb_strtolower($a);
        $b = mb_strtolower($b);
        return strcmp($a, $b);
    });
    if ($iscsv) {
        foreach ($lines as $line) {
            fputcsv($f, $line);
        }
        fclose($f);
    } else {
        foreach ($lines as $line) {
            railticket_waybill_row($line);
        }
        echo "</table>";
        ?>
        <p><form method='post' action='<?php echo railticket_get_page_url() ?>'>
        <input type='hidden' name='action' value='viewordersummary' />
        <input type='hidden' name='dateofjourney' value='<?php echo $date; ?>' />
        <input type='submit' name='submit' value='Refresh Data' />
        </form><br />
        <form action='<?php echo railticket_get_page_url() ?>' method='post'>
        <input type='hidden' name='action' value='filterbookings' />
        <input type='hidden' name='dateofjourney' value='<?php echo $date; ?>' />
        <input type='submit' name='submit' value='Back to Services' />
        </form>
        </p>
        <?php
    }


}
