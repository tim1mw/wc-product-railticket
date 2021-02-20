<?php
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

// Hook for adding admin menus
add_action('admin_menu', 'railticket_add_pages');
add_action('admin_init', 'railticket_register_settings' );
add_shortcode('railticket_manager', 'railticket_view_bookings');
add_action('admin_post_railticket-importtimetable', 'railticket_importtimetable');
add_action ('admin_post_waybill.csv', 'railticket_get_waybillcsv');
// Add simple_role capabilities, priority must be after the initial role definition.
add_action( 'init', 'railticket_roles', 11 );
add_action ('admin_post_ordersummary.csv', 'railticket_get_ordersummary_csv');

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

   add_option('wc_railticket_date_format', '%e-%b-%y');
   register_setting('wc_product_railticket_options_main', 'wc_railticket_date_format'); 
   add_option('wc_railticket_time_format', '%l.%M');
   register_setting('wc_railticket_options_main', 'wc_railticket_time_format');
}

function railticket_add_pages() {
    add_menu_page('Rail Ticket', 'Rail Ticket', 'manage_tickets', 'railticket-top-level-handle', 'railticket_view_bookings', 
        '', 30);
    add_submenu_page('railticket-top-level-handle', "Settings", "Settings", 'manage_options', 'railticket-options', 'railticket_options');
    add_submenu_page('railticket-top-level-handle', "Bookable Days", "Bookable Days", 'manage_options', 'railticket-bookable-days', 'railticket_bookable_days');
    add_submenu_page('railticket-top-level-handle', "Import Timetable", "Import Timetable", 'manage_options', 'railticket-import-timetable', 'railticket_import_timetable');
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

function railticket_view_bookings() {
    ?>
    <h1>Heritage Railway Tickets</h1>    
    <?php
    wp_register_style('railticket_style', plugins_url('wc-product-railticket/ticketbuilder.css'));
    wp_enqueue_style('railticket_style');

    if (array_key_exists('action', $_REQUEST)) {
        switch($_REQUEST['action']) {
            case 'cancelcollected';
                railticket_mark_ticket(false);
                railticket_show_order();
                break;
            case 'collected':
                railticket_mark_ticket(true);
            case 'showorder':
                railticket_show_order();
                break;
            case 'showdep':
            case 'showspecial':
                $station = \wc_railticket\Station::get_station(
                    sanitize_text_field($_REQUEST['station']), sanitize_text_field($_REQUEST['ttrevision']));
                railticket_show_departure(sanitize_text_field($_REQUEST['dateofjourney']), $station,
                   sanitize_text_field($_REQUEST['direction']), sanitize_text_field($_REQUEST['deptime']));
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
            case 'viewseatsummary':
                railticket_get_seatsummary();
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
        <tr valign="top">
            <th scope="row"><label for="wc_railticket_date_format">Display Date format</label></th>
            <td><input type="text" id="wc_railticket_date_format" name="wc_railticket_date_format" value="<?php echo get_option('wc_railticket_date_format'); ?>" /> Use <a href='https://www.php.net/manual/en/function.strftime' target='_blank'>PHP strftime formatting parameters</a> here</td>
        </tr>
        <tr valign="top">
            <th scope="row"><label for=wc_railticket_time_format">Display Time format</label></th>
            <td><input type="text" id="wc_railticket_time_format" name="wc_railticket_time_format" value="<?php echo get_option('wc_railticket_time_format'); ?>" /> Use <a href='https://www.php.net/manual/en/function.strftime' target='_blank'>PHP strftime formatting parameters</a> here</td>
        </tr>
    </table>
    <?php submit_button(); ?>
    </form>
    <?php
    if (array_key_exists('action', $_POST)) {
        switch ($_POST['action']) {
            case 'mapbookable':
                railticket_remapbookable();
        }
    }
    ?>
    <form action='' method='post'>
    <input type='hidden' name='action' value='mapbookable' />
    <input type='submit' value='Update Database' />
    </form>
    <?php
}

function railticket_remapbookable() {
    global $wpdb;

    $bookables = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_railticket_bookable");
    foreach ($bookables as $bookable) {
        if ($bookable->dateid == 0) {
            continue;
        }

        $date = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}railtimetable_dates WHERE id = ".$bookable->dateid);
        $bookable->date = $date->date;
        $bookable->timetableid = $date->timetableid;

        $ttrevision = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}wc_railticket_ttrevisions WHERE datefrom <= '".$date->date."' AND ".
            "dateto >= '".$date->date."'");
        $bookable->ttrevision = $ttrevision->id;

        $prevision = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}wc_railticket_pricerevisions WHERE datefrom <= '".$date->date."' AND ".
            "dateto >= '".$date->date."'");
        $bookable->pricerevision = $prevision->id;

        $bookable = (array) $bookable;
        $wpdb->update("{$wpdb->prefix}wc_railticket_bookable", $bookable, array('id' => $bookable['id']));

    }

    // Move price revision 0 to 1 and set localprice same as price
    $prices = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_railticket_prices WHERE revision = 0");
    foreach ($prices as $price) {
        $price->localprice = $price->price;
        $price->revision = 1;
        $price = (array) $price;
        $wpdb->update("{$wpdb->prefix}wc_railticket_prices", $price, array('id' => $price['id']));
    }

    echo "<p>DB Upgraded</p>";
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
            <td><?php echo wc_railticket_getyearselect();?></td>
        </tr><tr>
            <td>Month</td>
            <td><?php echo wc_railticket_getmonthselect($month);?></td>
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

function wc_railticket_getmonthselect($chosenmonth = false) {
    if ($chosenmonth == false) {
        $chosenmonth = 1;
    }

    if (array_key_exists('month', $_POST)) {
        $chosenmonth = intval($_POST['month']);
    }

    $sel = "<select name='month'>";
    for($m=1; $m<=12; ++$m){
        if ($m == $chosenmonth) {
            $s = ' selected="selected" ';
        } else {
            $s = '';
        }
        $sel .= "<option value='".$m."'".$s.">".date('F', mktime(0, 0, 0, $m, 1))."</option>";
    }
    $sel .= "</select>";
    return $sel;
}

function wc_railticket_getyearselect($currentyear = false) {
    global $wpdb;
    if ($currentyear == false) {
        $currentyear = intval(date("Y"));
    }
    if (array_key_exists('year', $_POST)) {
        $chosenyear = sanitize_text_field($_POST['year']);
    } else {
        $chosenyear = $currentyear;
    }

    $firstdate = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_dates ORDER BY date ASC LIMIT 1 ");
    if ($firstdate) {
        $d = reset($firstdate);
        $startdate = intval(explode('-', $d->date)[0]);
    } else {
        $startdate = $currentyear;
    }
    $enddate = $currentyear + 2;

    $sel = "<select name='year'>";
    for ($loop = $startdate; $loop < $enddate; $loop++) {
        if ($loop == $chosenyear) {
            $s = ' selected="selected" ';
        } else {
            $s = '';
        }
        $sel .= "<option value='".$loop."'".$s.">".$loop."</option>";
    }
    $sel .= "</select>";
    return $sel;
}

function railticket_import_timetable() {
    global $rtmustache;
    $alldata = array(
        'adminurl' => esc_url( admin_url('admin-post.php')),
        'submitbutton' => get_submit_button(__("Import Data"))
    );

    $template = $rtmustache->loadTemplate('import_timetable');
    echo $template->render($alldata);
}

function railticket_importtimetable() {
    global $wpdb;

    if (!array_key_exists('dataimport', $_FILES)) {
        echo __("No file uploaded");
        return;
    }

    $fileTmpPath = $_FILES['dataimport']['tmp_name'];
    $fileName = $_FILES['dataimport']['name'];
    $fileSize = $_FILES['dataimport']['size'];
    $fileType = $_FILES['dataimport']['type'];

    if ($fileSize == 0) {
        echo __("You have uploaded an empty file");
        return;
    }

    if ($fileType != "text/json" && $fileType != "application/json" ) {
        echo __("Not a JSON file");
        return;
    }
    $data = json_decode(file_get_contents($fileTmpPath));
    $revision = array();
    $revision['datefrom'] = sanitize_text_field($_POST['datefrom']);
    $revision['dateto'] = sanitize_text_field($_POST['dateto']);
    $revision['name'] = sanitize_text_field($_POST['name']);

    //Create a new timetable revision
    $imprev = railticket_get_cbval('imprev');
    if ($imprev) {
        $wpdb->insert($wpdb->prefix.'wc_railticket_ttrevisions', $revision);

        $revisionid = $wpdb->insert_id;

        railticket_import_table('stations', $data, $revisionid, 'stnid');
        railticket_import_table('timetables', $data, $revisionid, 'timetableid');
        railticket_import_table('stntimes', $data, $revisionid);
    } else {
        $revs = $wpdb->get_row("SELECT COUNT(id) FROM {$wpdb->prefix}wc_railticket_ttrevisions ");
        if ($revs === 0) {
            echo __("You need to have at least 1 existing timetable revision to import without creating a new one.");
            return;
        }
        
    }

    //Update the timetables for days, only in the range specified
    if (railticket_get_cbval('upttdates')) {  
        $datefrom = DateTime::createFromFormat('d-m-Y', $revision['datefrom']);
        $dateto = DateTime::createFromFormat('d-m-Y', $revision['dateto']);

        foreach($data->dates as $dateentry) {
            $thisdate = DateTime::createFromFormat('d-m-Y', $dateentry->date);
            if ($thisdate >= $datefrom && $thisdate <= $dateto) {
                $existing = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_railticket_dates WHERE date = '".$dateentry->date."'");
                if ($existing && count($existing) > 0) {
                    $first = (array) reset($existing);
                    if ($first['timetableid'] != $dateentry->timetableid) {
                        $first['timetableid'] = $dateentry->timetableid;
                        $wpdb->update("{$wpdb->prefix}wc_railticket_dates", $first, array('id' => $first['id']));
                    }
                } else {
                    $dateentry = (array) $dateentry;
                    unset($dateentry['id']);
                    $wpdb->insert("{$wpdb->prefix}wc_railticket_dates", $dateentry);
                }
            }
        }
    }

    unlink($fileTmpPath);

    wp_redirect(site_url().'/wp-admin/admin.php?page=railticket-bookable-days');
}

function railticket_import_table($table, $data, $revision = false, $idmap = false) {
    global $wpdb;
    $entrycount = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}wc_railticket_".$table);

    foreach ($data->$table as $entry) {
        $entry = (array) $entry;
        if ($revision) {
            $entry['revision'] = $revision;
        }
        if ($idmap) {
            $entry[$idmap] = $entry['id'];
        }
        unset($entry['id']);
        $wpdb->insert($wpdb->prefix.'wc_railticket_'.$table, $entry);
    }
}

function railticket_get_cbval($cbname) {
    if (array_key_exists($cbname, $_POST)) {
        return sanitize_text_field($_POST[$cbname]);
    } else {
        return 0;
    }
}

function railticket_showcalendaredit($year, $month) {
    global $wp, $rtmustache;
    $daysinmonth = intval(date("t", mktime(0, 0, 0, $month, 1, $year)));
    $render = new \stdclass();
    $render->days = array();
    $render->formurl =  railticket_get_page_url();

    for ($day = 1; $day < $daysinmonth + 1; $day++) {
        $time = mktime(0, 0, 0, $month, $day, $year);
        $dateofjourney = date('Y-m-d', $time);
        // Check directly for a timetable first, since not all dates will be bookable yet
        $timetable = \wc_railticket\Timetable::get_timetable_by_date($dateofjourney);
        if (!$timetable) {
            continue;
        }

        $data = new \stdclass();
        $data->dayofweek = date('l', $time);
        $data->dayofmonth = date('jS', $time);
        $data->timetable = $timetable->get_name();
        $data->colour = $timetable->get_colour();
        $data->background = $timetable->get_background();
        $data->date = $dateofjourney;

        $bookableday = \wc_railticket\BookableDay::get_bookable_day($dateofjourney);
        if (!$bookableday) {
            $render->days[] = $data;
            continue;
        }

        $data->formation = $bookableday->get_composition(true);
        $data->reserve = $bookableday->get_reserve(true);
        $data->bookable = railticket_get_yn($bookableday->is_bookable());
        $data->soldout = railticket_get_yn($bookableday->sold_out());
        $data->sellreserve = railticket_get_yn($bookableday->sell_reserve());
        $data->specialonly = railticket_get_yn($bookableday->special_only());

        $render->days[] = $data;
    }

    if (count($render->days) == 0) {
        return '';
    }

    $template = $rtmustache->loadTemplate('bookable_calendar');
    echo $template->render($render);
}

function railticket_get_yn($cl) {
    if ($cl) {
        return __('Y', 'wc_railticket');
    }
    return __('N', 'wc_railticket');
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

        $timetable = railticket_find_timetable($id, true);

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
            <td><?php echo wc_railticket_getmonthselect($chosenmonth);?></td>
          </tr><tr>
            <td>Year</td>
            <td><?php echo wc_railticket_getyearselect($chosenyear);?></td>
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

function railticket_get_seatsummary() {
    global $wpdb;
    $dateofjourney = sanitize_text_field($_REQUEST['dateofjourney']);
    $timetable = \wc_railticket\Timetable::get_timetable_by_date($dateofjourney);
    ?><h1>Seat/Bay usage summary for <?php echo $timetable->get_date(true); ?></h1><?php

    $stations = $timetable->get_stations();

    foreach ($stations as $station) {
        railticket_show_station_summary($dateofjourney, $station, $timetable, true);
    }
}


function railticket_show_bookings_summary($dateofjourney) {
    global $wpdb;
    $bookableday = \wc_railticket\BookableDay::get_bookable_day($dateofjourney);

    ?><h1>Summary for <?php echo $bookableday->get_date(true); ?></h1><?php

    // If the override code is empty, this day has a timetable, but hasn't been initialised.
    if (!$bookableday) {
        echo "<h3>Booking data not initiailised for this day - please make this day bookable.</h3>";
        return;
    }

    echo "<h2 style='font-size:x-large;line-height:120%;'>Booking override code:<span style='color:red'>".$bookableday->get_override()."</span></h2>";

    $stations = $bookableday->timetable->get_stations();

    foreach ($stations as $station) {
        railticket_show_station_summary($dateofjourney, $station, $bookableday->timetable);
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
    <p><form method='post' action='<?php echo railticket_get_page_url() ?>'>
        <input type='hidden' name='action' value='viewseatsummary' />
        <input type='hidden' name='dateofjourney' value='<?php echo $dateofjourney; ?>' />
        <input type='submit' name='submit' value='View Seat/Bay Usage Summary' />
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

function railticket_show_station_summary($dateofjourney, \wc_railticket\Station $station, \wc_railticket\Timetable $timetable, $showseats = false) {
    global $wpdb;

    if ($showseats) {
        $h = "<br /><h1>";
        $hs = "</h1>";
    } else {
        $h = "<h3>";
        $hs = "</h3>";
    }

    $down_deps = $timetable->get_down_deps($station, true);
    if (count($down_deps) > 0) {
        echo "\n".$h."Down Departures from ".$station->get_name().$hs."\n<span class='railticket_inlinedeplist'>";
        railticket_show_dep_buttons($dateofjourney, $station, $timetable, $down_deps, "down", $showseats);
        echo "</span>";
    }

    $up_deps = $timetable->get_up_deps($station, true);
    if (count($up_deps) > 0) {
        echo "\n".$h."Up Departures from ".$station->get_name().$hs."\n<span class='railticket_inlinedeplist'>";
        railticket_show_dep_buttons($dateofjourney, $station, $timetable, $up_deps, "up", $showseats);
        echo "</span>";
    }
}

function railticket_show_dep_buttons($dateofjourney, \wc_railticket\Station $station, $timetable, $alltimes, $direction, $showseats = false) {
    global $wpdb, $wp;
    $key = $direction.'_deps';

    if ($showseats) {
        foreach ($alltimes as $t) {
            railticket_show_departure($dateofjourney, $station, $direction, $t, true);
        }
    } else {
        echo "<div class='railticket_inlinedeplist'><ul>";
        foreach ($alltimes as $t) {
            echo "<li>";
            railticket_show_dep_button($dateofjourney, $station, $direction, $t);
            echo "</li>";
        }
        echo "</ul></div>";
    }
}

function railticket_show_dep_button($dateofjourney, \wc_railticket\Station $station, $direction, $t, $incstn = false) {
    $label = $t->formatted;
    if ($incstn) {
        $label = "Back to ".$t->formatted." from ".$station->get_name();
    }
    ?>
    <form method='post' action='<?php echo railticket_get_page_url() ?>'>
       <input type='hidden' name='action' value='showdep' />
       <input type='hidden' name='dateofjourney' value='<?php echo $dateofjourney; ?>' />
       <input type='hidden' name='station' value='<?php echo $station->get_stnid(); ?>' />
       <input type='hidden' name='direction' value='<?php echo $direction; ?>' />
       <input type='hidden' name='deptime' value='<?php echo $t->key; ?>' />
       <input type='hidden' name='ttrevision' value='<?php echo $station->get_revision(); ?>' />
       <input type='submit' name='submit' value='<?php echo $label; ?>' />
    </form>
    <?php
}

function railticket_show_departure($dateofjourney, \wc_railticket\Station $station, $direction, $deptime, $summaryonly = false) {
    global $wpdb, $wp;

    $bookableday = \wc_railticket\BookableDay::get_bookable_day($dateofjourney);
    $destination = $bookableday->timetable->get_terminal($direction);

    // TODO Deal with Specials

    // If this is being called directly from a button click, we won't have the forrmatted version of the time passed through
    // So get it here.
    if (is_string($deptime)) {
        $dt = new \stdclass();
        $dt->key = $deptime;
        $railticket_timezone = new \DateTimeZone(get_option('timezone_string'));
        $depname = \DateTime::createFromFormat("H.i", $deptime, $railticket_timezone);
        $dt->formatted = strftime(get_option('wc_railticket_time_format'), $depname->getTimeStamp());
        $deptime = $dt;
    }

    if ($_REQUEST['action'] == 'showspecial') {
        $parts = explode(':', $deptime);
        $depname = $wpdb->get_var("SELECT name FROM {$wpdb->prefix}wc_railticket_specials WHERE id = ".$parts[1]);
    } 

    $bookings = $bookableday->get_bookings_from_station($station, $deptime->key, $direction);

    $seats = 0;
    foreach ($bookings as $booking) {
        $seats += $booking->seats;
    }

    $trainservice = new \wc_railticket\TrainService($bookableday, $station, $deptime->key, $direction, false);
    $basebays = $trainservice->get_inventory(true, true);
    $capused = $trainservice->get_inventory(false, false);
    $capcollected = $trainservice->get_inventory(false, true, true);

   if ($summaryonly) {
        echo "<table><tr><td>";
        railticket_show_dep_button($dateofjourney, $station, $direction, $deptime);
        echo "</td><td class='railticket_shortsummary'>Seats Used:".$seats."&nbsp&nbsp;</td><td class='railticket_shortsummary'>Seats Available:".$capused->totalseats."</td></tr></table>";
   } else {
        echo "<div class='railticket_editdate'><h3>Service summary</h3><table border='1'>".
            "<tr><th>Timetable</th><th>".$bookableday->timetable->get_name()."</th></tr>".
            "<tr><th>Station</th><th>".$station->get_name()."</th></tr>".
            "<tr><th>Destination</th><th>".$destination->get_name()."</th></tr>".
            "<tr><th>Date</td><th>".$dateofjourney."</th></tr>".
            "<tr><th>Time</td><th>".$deptime->formatted."</th></tr>".
            "<tr><th>Direction</th><th>".$direction."</th></tr>".
            "<tr><th>Total Orders</th><th>".count($bookings)."</th></tr>".
            "<tr><th>Seats Used</th><th>".$seats."</th></tr>".
            "<tr><th>Seats Available</th><th>".$capused->totalseats."</th></tr>".
            "</table></div><br />";
    }
    echo "<h3>Bay Usage (one way to destination)</h3><div class='railticket_trainbookings'>".
        "<table border='1'><th>Bay</th><th>Total</th><th>Used</th><th>Collected</th><th>Available</th></tr>";
    foreach ($basebays as $bay => $space) {
        $bayd = str_replace('_', ' seat ', $bay);
        $bayd = str_replace('priority', 'disabled', $bayd);
        echo "<td>".$bayd."</td><td>".$space."</td><td>".
            ($space-$capused->bays[$bay])."</td><td>".($space-$capcollected->bays[$bay])."</td><td>".$capused->bays[$bay]."</td></tr>";
    }
    echo "</table></div>";

    echo "<p>Coaches: ".$trainservice->get_coachset(true)."<br />".
        "Reserve: ".$trainservice->get_reserve(true)."</p>";
    if ($summaryonly) {
        echo "<hr />";
        return;
    }
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
        <input type='hidden' name='station' value='<?php echo $station->get_stnid(); ?>' />
        <input type='hidden' name='ttrevision' value='<?php echo $station->get_revision(); ?>' />
        <input type='hidden' name='direction' value='<?php echo $direction ?>' />
        <input type='hidden' name='deptime' value='<?php echo $deptime->key ?>' />
        <input type='hidden' name='destination' value='<?php echo $destination->get_stnid() ?>' />
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
        <input type='hidden' name='a_station' value='<?php echo $station->get_stnid(); ?>' />
        <input type='hidden' name='a_ttrevision' value='<?php echo $station->get_revision(); ?>' />
        <input type='hidden' name='a_direction' value='<?php echo $direction ?>' />
        <input type='hidden' name='a_deptime' value='<?php echo $deptime->key ?>' />
        <input type='hidden' name='a_destination' value='<?php echo $destination->get_stnid() ?>' />
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

function railticket_mark_ticket($val) {
    global $wpdb;
    $id = $_POST['bookingid'];
    $wpdb->update("{$wpdb->prefix}wc_railticket_bookings",
        array('collected' => $val),
        array('id' => $id));
}

function railticket_delete_manual_order() {
    global $rtmustache;
    $orderid = sanitize_text_field($_REQUEST['orderid']);
    $bo = \wc_railticket\BookingOrder::get_booking_order($orderid);
    if (!$bo) {
        echo "<h3>Order ".$orderid." not found</h3>";
        return;
    }

    $bo->delete();

    echo "<h3>Order ".$orderid." deleted</h3>";

    $alldata = array('actionurl' => railticket_get_page_url(), 'dateofjourney' => $bo->get_date());
    $template = $rtmustache->loadTemplate('back_to_services');
    echo $template->render($alldata);
}

function railticket_show_order() {
    global $wpdb, $woocommerce;

    $orderid = sanitize_text_field($_REQUEST['orderid']);

    if (strlen($orderid) == 0) {
        echo "<p>No order number entered.</p>";
        railticket_summary_selector();
        return;
    }

    railticket_show_order_main($orderid);
}

function railticket_show_order_main($orderid) {
    global $rtmustache;

    $bookingorder = \wc_railticket\BookingOrder::get_booking_order($orderid);
    if (!$bookingorder) {
        echo "<p>Invalid order number or no tickets were purchased with this order.</p>";
        railticket_summary_selector();
        return;
    }

    $orderdata = array();
    $orderdata[] = array('item' => __('Order ID', 'wc_railticket'), 'value' => $orderid);
    $orderdata[] = array('item' => __('Name', 'wc_railticket'), 'value' => $bookingorder->get_customer_name());
    $orderdata[] = array('item' => __('Postcode', 'wc_railticket'), 'value' => $bookingorder->get_postcode());
    $orderdata[] = array('item' => __('Paid', 'wc_railticket'), 'value' => $bookingorder->is_paid(true));
    $orderdata[] = array('item' => __('Price', 'wc_railticket'), 'value' => $bookingorder->get_price());
    $orderdata[] = array('item' => __('Tickets'), 'value' => $bookingorder->get_tickets(true));
    $orderdata[] = array('item' => __('Date'), 'value' => $bookingorder->get_date(true, true));
    $orderdata[] = array('item' => __('Journey Type'), 'value' => $bookingorder->get_journeytype(true));
    $orderdata[] = array('item' => __('Seats'), 'value' => $bookingorder->get_seats());
    $orderdata[] = array('item' => __('Notes'), 'value' => $bookingorder->get_notes());

    $alldata = array(
        'dateofjourney' => $bookingorder->get_date(),
        'details' => $orderdata,
        'bookings' => railticket_get_booking_render_data($bookingorder->get_bookings()),
        'timestr' => __('Departure Time', 'wc_railticket'),
        'tripstr' => __('Trip', 'wc_railticket'),
        'baystr' => __('Bays', 'wc_railticket'),
        'collectedstr' => __('Collected', 'wc_railticket'),
        'orderid' => $orderid,
        'actionurl' => railticket_get_page_url()
    );

    if ($bookingorder->is_manual() && current_user_can('delete_tickets')) {
         $template = $rtmustache->loadTemplate('delete_order_button');
         $alldata['extrabuttons'] = $template->render($alldata);
    } else {
         $alldata['extrabuttons'] = '';
    }

    $template = $rtmustache->loadTemplate('showorder');
    echo $template->render($alldata);
}

function railticket_get_booking_render_data($bookings) {
    $data = array();

    $count = 1;
    foreach($bookings as $booking) {
        $fromstn = $booking->get_from_station();

        $bk = array();
        $bk['title'] = __('Trip', 'wc_railtickey')." ".$count;
        $bk['stns'] = $fromstn->get_name().' - '.$booking->get_to_station()->get_name();
        $bk['deptime'] = $booking->get_dep_time(true);
        $bk['bays'] = $booking->get_bays(true);
        if ($booking->is_collected()) {
            $bk['collected'] = __('Yes');
            $bk['actionstr'] = __('Cancel Collected', 'wc_railrticket');
            $bk['action'] = 'cancelcollected';
        } else {
            $bk['collected'] = __('No');
            $bk['actionstr'] = __('Mark Collected', 'wc_railrticket');
            $bk['action'] = 'collected';
        }
        $bk['bookingid'] = $booking->get_id();

        $bk['station'] = $fromstn->get_stnid();
        $bk['direction'] = $fromstn->get_direction($booking->get_to_station());
        $bk['ttrevison'] = $fromstn->get_revision();
        $bk['timekey'] = $booking->get_dep_time(false);
        $bk['btnlabel'] = "Back to ".$bk['deptime']." from ".$fromstn->get_name();

        $data[] = $bk;
        $count++;
    }

    return $data;
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
    $stns = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_railticket_stations ", OBJECT);
    echo $stns[$station]->name;
    ?>
    </p>
    <p>Destination station: <?php echo $stns[$destination]; ?></p>
    <?php
    $timetable = $wpdb->get_results("SELECT {$wpdb->prefix}wc_railticket_timetables.* FROM {$wpdb->prefix}wc_railticket_dates ".
        "LEFT JOIN {$wpdb->prefix}wc_railticket_timetables ON ".
        " {$wpdb->prefix}wc_railticket_dates.timetableid = {$wpdb->prefix}wc_railticket_timetables.id ".
        "LEFT JOIN {$wpdb->prefix}wc_railticket_bookable ON ".
        " {$wpdb->prefix}wc_railticket_bookable.dateid = {$wpdb->prefix}wc_railticket_dates.id ".
        "WHERE {$wpdb->prefix}wc_railticket_dates.date = '".$dateoftravel."'", OBJECT );

    
    ?>
    <input type='submit' value='Create Booking' />
    </form>
    <?php
}

function railticket_get_waybillcsv() {
    railticket_get_waybill(true);
}

function railticket_get_waybill($iscsv) {
    $date = sanitize_text_field($_REQUEST['dateofjourney']);
    $wb = new \wc_railticket\Waybill($date);
    $wb->show_waybill($iscsv);
}


function railticket_get_ordersummary_csv() {
    railticket_get_ordersummary(true);
}

function railticket_get_ordersummary($iscsv = false) {
    $date = sanitize_text_field($_REQUEST['dateofjourney']);
    $os = new \wc_railticket\OrderSummary($date);
    $os->show_summary($iscsv);
}
