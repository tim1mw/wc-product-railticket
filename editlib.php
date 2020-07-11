<?php
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

// Hook for adding admin menus
add_action('admin_menu', 'railticket_add_pages');
add_action('admin_init', 'railticket_register_settings' );
add_shortcode('railticket_manager', 'railticket_view_bookings');

function railticket_register_settings() {
   add_option('wc_product_railticket_woocommerce_product', '');
   register_setting('wc_product_railticket_options_main', 'wc_product_railticket_woocommerce_product'); 
   register_setting('wc_product_railticket_options_main', 'wc_product_railticket_min_price');
   register_setting('wc_product_railticket_options_main', 'wc_product_railticket_reservetime');
   register_setting('wc_product_railticket_options_main', 'wc_product_railticket_releaseinventory');
   register_setting('wc_product_railticket_options_main', 'wc_product_railticket_sameservicereturn');
   register_setting('wc_product_railticket_options_main', 'wc_product_railticket_termspage');
   register_setting('wc_product_railticket_options_main', 'wc_product_railticket_bookinggrace');
   register_setting('wc_product_railticket_options_main', 'wc_product_railticket_defaultcoaches');
   register_setting('wc_product_railticket_options_main', 'wc_product_railticket_defaultreserve');
   register_setting('wc_product_railticket_options_main', 'wc_product_railticket_bookinglimits');
}

function railticket_add_pages() {
    add_menu_page('Rail Ticket', 'Rail Ticket', 'manage_tickets', 'railticket-top-level-handle', 'railticket_view_bookings' );
    add_submenu_page('railticket-top-level-handle', "Settings", "Settings", 'manage_options', 'railticket-options', 'railticket_options');
    add_submenu_page('railticket-top-level-handle', "Bookable Days", "Bookable Days", 'manage_options', 'railticket-bookable-days', 'railticket_bookable_days');
}

function railticket_roles() {
    // Gets the simple_role role object.
    $role = get_role( 'administrator' );
    // Add a new capability.
    $role->add_cap( 'manage_tickets', true );

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
            <td><input type="text" size='2' id="wc_product_railticketbookinggrace" name="wc_product_railticket_bookinggrace" value="<?php echo get_option('wc_product_railticket_bookinggrace'); ?>" /></td>
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
            <th scope="row"><label for="wc_product_railticket_defaultreserve">Default Reserve Capacity</label></th>
            <td><textarea rows='10' cols='60' id="wc_product_railticket_defaultreserve" name="wc_product_railticket_defaultreserve"><?php echo get_option('wc_product_railticket_defaultreserve'); ?></textarea></td>
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
    ?>
    <h1>Heritage Railway Tickets - Set bookable dates</h1>
    <form method='post' action='<?php echo railticket_get_page_url() ?>'>
        <input type='hidden' name='action' value='filterbookable' />    
        <table><tr>
            <td>Select Year</td>
            <td><?php echo railtimetable_getyearselect();?></td>
        </tr><tr>
            <td>Month</td>
            <td><?php echo railtimetable_getmonthselect();?></td>
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
    <tr><th>Day</th><th>Date</th><th>Timetable</th><th>Bookable</th><th>Sold Out</th><th>Formation</th><th>Sell Reserve</th><th>Reserve</th></tr>
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
            echo "/><td>";
            echo "<input type='checkbox' value='1' name='soldout_".$ct->id."' ";
            if ($bk[0]->soldout == 1) {
                echo "checked ";
            }
            echo "/><td>";

            $comp = json_decode($bk[0]->composition);

            $coachset = (array) $comp->coachset;
            foreach ($coachset as $c => $v) {
                echo $v."x ".$c.", ";
            }
            echo "</td><td>";
            echo "<input type='checkbox' value='1' name='reserve_".$ct->id."' ";
            if ($bk[0]->sellreserve == 1) {
                echo "checked ";
            }
            echo "/><td>";
            if (strlen($bk[0]->reserve) > 0) {
                $reserve = (array) json_decode($bk[0]->reserve);
                foreach ($reserve as $i => $num) {
                    if ($num > 0) {
                        echo $i." x".$num.", ";
                    }
                }
            }

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

        if (count($bk) > 0) {
            $bays = railticket_process_bays($bk[0]->composition);
            $wpdb->update("{$wpdb->prefix}wc_railticket_bookable",
                array('bookable' => $bookable, 'bays' => $bays, 'sellreserve' => $reserve, 'soldout' => $soldout),
                array('dateid' => $bk[0]->dateid));
        } else {
            if ($bookable) {
                $ssr = false;
                if (get_option('wc_product_railticket_sameservicereturn') == 'on') {
                    $ssr = true;
                }
                $data = array(
                    'dateid' => $id,
                    'composition' => get_option('wc_product_railticket_defaultcoaches'),
                    'bays' => railticket_process_bays(get_option('wc_product_railticket_defaultcoaches')),
                    'bookclose' => '{}',
                    'limits' => get_option('wc_product_railticket_bookinglimits'),
                    'bookable' => 1,
                    'soldout' => 0,
                    'override' => randomString(),
                    'sameservicereturn' => $ssr,
                    'reserve' => get_option('wc_product_railticket_defaultreserve'),
                    'sellreserve' => 0
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

function railticket_process_bays($json) {
   global $wpdb;
   $data = array();
   $parsed = json_decode($json);

   $coachset = (array) $parsed->coachset;
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
   return wp_json_encode($data);
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
                railticket_mark_collected(false);
                railticket_show_order();
                break;
            case 'collected':
                railticket_mark_collected(true);
            case 'showorder':
                railticket_show_order();
                break;
            case 'showdep':
                railticket_show_departure();
                break;
            case 'createmanual':
                railticket_create_manual();
                break;
//            case 'waybill':
//                railticket_get_waybill();
//                break;
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
            $chosenyear = intval(date("Y"));
        }

        if (array_key_exists('month', $_REQUEST)) {
            $chosenmonth = intval($_REQUEST['month']);
        } else {
            $chosenmonth = intval(date("n"));
        }
    
        if (array_key_exists('day', $_REQUEST)) {
            $chosenday = intval($_REQUEST['day']);
        } else {
            $chosenday = intval(date("j"));
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
            <td><input style='width:200px;' type='number' max='1000000' name='orderid' required /></td>
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

function findTimetable($dateoftravel) {
    global $wpdb;
    $sql = "SELECT {$wpdb->prefix}railtimetable_timetables.*,{$wpdb->prefix}wc_railticket_bookable.override ".
        "FROM {$wpdb->prefix}railtimetable_dates ".
        "LEFT JOIN {$wpdb->prefix}railtimetable_timetables ON ".
        " {$wpdb->prefix}railtimetable_dates.timetableid = {$wpdb->prefix}railtimetable_timetables.id ".
        "LEFT JOIN {$wpdb->prefix}wc_railticket_bookable ON ".
        " {$wpdb->prefix}wc_railticket_bookable.dateid = {$wpdb->prefix}railtimetable_dates.id ".
        "WHERE {$wpdb->prefix}railtimetable_dates.date = '".$dateoftravel."'";
    $timetable = $wpdb->get_results($sql, OBJECT );

    if (count($timetable) > 0) {
        return $timetable[0];
    } else {
        return false;
    }
}


function railticket_show_bookings_summary($dateofjourney) {
    global $wpdb;
    $timetable = findTimetable($dateofjourney);
    if (!$timetable) {
        echo "<h3>No trains or bookings today</h3>";
        return;
    }

    echo "<h2 style='font-size:xx-large;line-height:120%;'>Booking override code:<span style='color:red'>".$timetable->override."</span></h2>";

    $stations = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_stations ORDER BY sequence ASC", OBJECT);
    foreach ($stations as $station) {
        railticket_show_station_summary($dateofjourney, $station, $timetable);
    }

    ?>
    <form method='get' action='<?php echo admin_url('admin-post.php') ?>'>
        <input type='hidden' name='action' value='waybill.csv' />
        <input type='hidden' name='dateofjourney' value='<?php echo $dateofjourney; ?>' />
        <input type='submit' name='submit' value='Get Waybill' />
    </form>
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

    $tk = new TicketBuilder($dateofjourney, $station->id, $destination->id, $deptime, false, 'single', false, false, false, false, false);
    $basebays = $tk->get_service_inventory($deptime, $station->id, $destination->id, true, true);
    $capused = $tk->get_service_inventory($deptime, $station->id, $destination->id, false, true);

    echo "<div class='railticket_editdate'><h3>Service summary</h3><table border='1'>".
        "<tr><th>Station</th><th>".$station->name."</th></tr>".
        "<tr><th>Destination</th><th>".$destination->name."</th></tr>".
        "<tr><th>Date</td><th>".$dateofjourney."</th></tr>".
        "<tr><th>Time</td><th>".$deptime."</th></tr>".
        "<tr><th>Direction</th><th>".$direction."</th></tr>".
        "<tr><th>Total Orders</th><th>".count($bookings)."</th></tr>".
        "<tr><th>Seats Used</th><th>".$seats."</th></tr>".
        "<tr><th>Seats Available</th><th>".$capused->totalseats."</th></tr>".
        "</table></div><br />";

    echo "<h3>Bay Usage (one way to destination)</h3><div class='railticket_trainbookings'>".
        "<table border='1'><th>Bay</th><th>Total</th><th>Used</th><th>Empty</th></tr>";
    foreach ($basebays as $bay => $space) {
        $bayd = str_replace('_', ' seat ', $bay);
        $bayd = str_replace('priority', 'disabled', $bayd);
        echo "<td>".$bayd."</td><td>".$space."</td><td>".
            ($space-$capused->bays[$bay])."</td><td>".$capused->bays[$bay]."</td></tr>";
    }
    echo "</table></div>"

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
            echo "<td>".$booking->id." (M)</td>";
        } else {
            echo "<td><form action='".railticket_get_page_url()."' method='post'>".
                "<input type='hidden' name='action' value='showorder' />".
                "<input type='hidden' name='orderid' value='".$booking->wooorderid."' />".
                "<input type='submit' value='".$booking->wooorderid."' />".
                "</form></td>";
        }
        echo "<td>".$booking->name."</td>".
            "<td>".$booking->seats."</td>".
            "<td>";

        $bays = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_railticket_booking_bays WHERE bookingid = ".$booking->id);
        foreach ($bays as $bay) {
            echo $bay->baysize;
            if ($bay->priority) {
                echo "d";
            }
            echo "X".$bay->num." ";
        }
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
        <input type='hidden' name='action' value='showdep' />
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


function railticket_mark_collected($val) {
    global $wpdb;
    $orderid = $_POST['orderid'];
    $wpdb->update("{$wpdb->prefix}wc_railticket_bookings",
                array('collected' => $val),
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

    if (strlen($_POST['orderid']) == 0) {
        echo "<p>No order number entered.</p>";
        railticket_summary_selector();
        return;
    }

    $order = wc_get_order($_POST['orderid']);

    if (!$order) {
        echo "<p>Order not found.</p>";
        railticket_summary_selector();
        return;
    }

    $order_item = railticket_get_ticket_item($order);

    if (!$order_item) {
        echo "<p>No tickets were purchased with this order.</p>";
        railticket_summary_selector();
        return;
    }
    $formatted_meta_data = $item->get_formatted_meta_data( '_', true );

    ?>
    <div class='railticket_editdate'>
    <table border='1'>
        <tr><th>Order ID</th><td class='railticket_meta'><?php echo $_POST['orderid']; ?></td></tr>
        <tr><th>Name</th><td class='railticket_meta'><?php echo $order->get_formatted_billing_full_name(); ?></td></tr>
        <tr><th>Postcode</th><td class='railticket_meta'><?php echo $order->get_billing_postcode(); ?></td></tr>
        <tr><th>Paid</th><td class='railticket_meta'><?php if ($order->is_paid()) {echo "Yes";} else {echo "No";} ?></td></tr>
    <?php
    $fromstation = false;
    $tostation = false;
    $journeytype = false;
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
    echo "</td></tr></table>";

    if (!$booking[0]->collected) {
        echo "<form action='".railticket_get_page_url()."' method='post'>".
            "<input type='hidden' name='action' value='collected' />".
            "<input type='hidden' name='orderid' value='".$_POST['orderid']."' />".
            "<input type='submit' value='Mark Collected' />".
            "</form>";
    } else {
        echo "<form action='".railticket_get_page_url()."' method='post'>".
            "<input type='hidden' name='action' value='cancelcollected' />".
            "<input type='hidden' name='orderid' value='".$_POST['orderid']."' />".
            "<input type='submit' value='Cancel Collected' />".
            "</form>";
    }
    ?>
    </div>
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

add_action ('admin_post_waybill.csv', 'railticket_get_waybill');
function railticket_get_waybill() {
    global $wpdb;
    $date = $_GET['dateofjourney'];
    header('Content-Type: application/csv');
    header('Content-Disposition: attachment; filename="waybill-'.$date.'.csv";');
    header('Pragma: no-cache');
    $f = fopen('php://output', 'w');
    fputcsv($f, array('Journey', 'Journey Type', 'Ticket Type', 'Number', 'Fare', 'Total'));

    $totalsdata = railticket_get_waybill_data($date);

    $stations = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_stations ORDER BY sequence ASC", OBJECT);
    $stn = array();
    foreach ($stations as $station) {
        $stn[$station->id] = $station->name;
    }
    $revenue = 0;
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

                    $revenue += $qty*$ticketdata[0]->price;
                    fputcsv($f, $line);
                }
            }
        }
    }
    fputcsv($f,array());
    fputcsv($f, array('Total Passengers', $totalsdata->totalseats));
    fputcsv($f, array('Total Tickets', $totalsdata->totaltickets));
    fputcsv($f, array('Total Revenue', $revenue));
    fclose($f);
}

function railticket_get_waybill_data($date) {
    global $wpdb;

    $bookings = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_railticket_bookings WHERE date = '".$date."'");

    $processed = array();
    $totals = array();
    $totalseats = 0;
    $totaltickets = 0;
    foreach ($bookings as $booking) {
        if ($booking->wooorderid > 0 && !in_array($booking->wooorderid, $processed)) {
            $data_store = WC_Data_Store::load( 'order-item' );
            $totalseats += $data_store->get_metadata($booking->wooorderitem, "tickettimes-totalseats", true);
            $journeytype = $data_store->get_metadata($booking->wooorderitem, "tickettimes-journeytype", true);

            $ta = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE order_item_id = ".$booking->wooorderitem.
                " AND meta_key LIKE 'ticketsallocated-%'");
            $ticketsallocated = array();
            foreach ($ta as $ticket) {
                $tparts = explode('-', $ticket->meta_key);
                $ticketsallocated[$tparts[1]] = intval($ticket->meta_value);
            }
            $processed[] = $booking->wooorderid;
        } elseif ($booking->manual > 0 && !in_array($booking->manual.'M', $processed)) {
            $processed[] = $booking->manual.'M';
            $mb = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_railticket_manualbook WHERE id = ".$booking->manual)[0];
            $totalseats += $mb->seats;
            $journeytype = $mb->journeytype;
            $ticketsallocated = (array) json_decode($mb->tickets);
            $processed[] = $booking->manual.'M';
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
    return $r;
}
