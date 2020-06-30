<?php
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

// Hook for adding admin menus
add_action('admin_menu', 'railticket_add_pages');
add_action('admin_init', 'railticket_register_settings' );


function railticket_register_settings() {
   add_option('wc_product_railticket_woocommerce_product', '');
   register_setting('wc_product_railticket_options_main', 'wc_product_railticket_woocommerce_product'); 
   register_setting('wc_product_railticket_options_main', 'wc_product_railticket_min_price');
   register_setting('wc_product_railticket_options_main', 'wc_product_railticket_termspage');
   register_setting('wc_product_railticket_options_main', 'wc_product_railticket_bookinggrace');
   register_setting('wc_product_railticket_options_main', 'wc_product_railticket_defaultcoaches');
   register_setting('wc_product_railticket_options_main', 'wc_product_railticket_bookinglimits');
   register_setting('wc_product_railticket_options_main', 'wc_product_railticket_coachtypes');
}

function railticket_add_pages() {
    add_submenu_page('railtimetable-top-level-handle', "Railticket", "Railticket", 'manage_options', 'railticket-options', 'railticket_options');
    add_submenu_page('railtimetable-top-level-handle', "Bookable Days", "Bookable Days", 'manage_options', 'railticket-bookable-days', 'railticket_bookable_days');
}

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
            <th scope="row"><label for="wc_product_railticket_termspage">Ticket Terms Page</label></th>
            <td><input type="text" size='60' id="wc_product_railticket_min_price" name="wc_product_railticket_termspage" value="<?php echo get_option('wc_product_railticket_termspage'); ?>" /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><label for="wc_product_railticket_bookinggrace">Booking Grace (minutes)</label></th>
            <td><input type="text" size='2' id="wc_product_railticketbookinggrace" name="wc_product_railticket_bookinggrace" value="<?php echo get_option('wc_product_railticket_bookinggrace'); ?>" /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><label for="wc_product_railticket_defaultcoaches">Default Coaches</label></th>
            <td><textarea rows='10' cols='60' id="wc_product_railticket_defaultcoaches" name="wc_product_railticket_defaultcoaches"><?php echo get_option('wc_product_railticket_defaultcoaches'); ?></textarea></td>
        </tr>
        <tr valign="top">
            <th scope="row"><label for="wc_product_railticket_bookinglimits">Booking limits</label></th>
            <td><textarea rows='10' cols='60' id="wc_product_railticket_bookinglimits" name="wc_product_railticket_bookinglimits"><?php echo get_option('wc_product_railticket_bookinglimits'); ?></textarea></td>
        </tr>
        <tr valign="top">
            <th scope="row"><label for="wc_product_railticket_coachtypes">Coach Types</label></th>
            <td><textarea rows='10' cols='60' id="wc_product_railticket_coachtypes" name="wc_product_railticket_coachtypes"><?php echo get_option('wc_product_railticket_coachtypes'); ?></textarea></td>
        </tr>
    </table>
    <?php submit_button(); ?>
    </form>
    <?php
}


function railticket_bookable_days() {
    ?>
    <h1>Heritage Railway Ticket Sales - Set bookable dates</h1>
    <form method='post' action=''>
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
    global $wpdb;

    $daysinmonth = intval(date("t", mktime(0, 0, 0, $month, 1, $year)));
    $timetables = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_timetables");
    ?>
    <form method='post' action=''>
    <input type='hidden' name='action' value='updatebookable' />
    <input type='hidden' name='year' value='<?php echo $year; ?>' />
    <input type='hidden' name='month' value='<?php echo $month; ?>' />
    <table border="1">
    <tr><th>Day</th><th>Date</th><th>Timetable</th><th>Bookable</th><th>Formation</th></tr>
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
            $bookable = false;
        } else {
            $bookable = $bk[0]->bookable;
        }

        echo "<input type='checkbox' value='1' name='bookable_".$ct->id."' ";
        if ($bookable == 1) {
            echo "checked ";
        }
        echo "/><td>";
        $comp = json_decode($bk[0]->composition);

        $coachset = (array) $comp->coachset;
        foreach ($coachset as $c => $v) {
            echo $v."x ".$c.", ";
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

        if (count($bk) > 0) {
            $bays = railticket_process_bays($bk[0]->composition);
            //$data = array('bookable' => $bk, 'bays' => $bays);
            $wpdb->update("{$wpdb->prefix}wc_railticket_bookable",
                array('bookable' => $bookable, 'bays' => $bays),
                array('dateid' => $bk[0]->dateid),
                array('%d', '%s'),
                array('%d')
            );
        } else {
            if ($bookable) {
                $data = array(
                    'dateid' => $id,
                    'composition' => get_option('wc_product_railticket_defaultcoaches'),
                    'bays' => railticket_process_bays(get_option('wc_product_railticket_defaultcoaches')),
                    'bookclose' => railticket_get_booking_grace($bk[0]->dateid),
                    'limits' => get_option('wc_product_railticket_bookinglimits'),
                    'bookable' => 1,
                    'soldout' => 0,
                    'override' => randomString()
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
//echo $coach."   ";
       $comp = $wpdb->get_var("SELECT composition FROM {$wpdb->prefix}wc_railticket_coachtypes WHERE code = '".$coach."'");

       $bays = json_decode(stripslashes($comp));
//print_r($bays);
//echo "<br />";
       foreach ($bays as $bay) {
           $data[$bay->baysize] += $bay->quantity * $count;
       }

   }

   //print_r($data);
//echo "<br />";
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

