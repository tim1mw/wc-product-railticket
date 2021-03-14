<?php
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

// Hook for adding admin menus
add_action('admin_menu', 'railticket_add_pages');
add_action('admin_init', 'railticket_register_settings' );
add_shortcode('railticket_manager', 'railticket_view_bookings');
add_action('admin_post_railticket-importtimetable', 'railticket_importtimetable');
add_action('admin_post_railticket-editbookableday', 'railticket_editbookableday');
add_action ('admin_post_waybill.csv', 'railticket_get_waybillcsv');
// Add simple_role capabilities, priority must be after the initial role definition.
add_action( 'init', 'railticket_roles', 11 );
add_action ('admin_post_ordersummary.csv', 'railticket_get_ordersummary_csv');
add_action( 'wp_ajax_railticket_adminajax', 'railticket_ajax_adminrequest');

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
    $role->add_cap( 'admin_tickets', true );

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
            case 'viewwaybill':
                railticket_get_waybill(false);
                break;
            case 'viewseatsummary':
                railticket_get_seatsummary();
                break;
            case 'viewordersummary':
                railticket_get_ordersummary(false);
                return;
            case 'deletemanual';
                railticket_delete_manual_order();
                break;
            case 'editorderbook';
                railticket_show_edit_order();
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

function railticket_ajax_adminrequest() {
    $function = railticket_getpostfield('function');
    switch ($function) {
        case 'moveorderdata':
            $result = railticket_get_moveorderdata();
            break;
        case 'editorder':
            $result = railticket_editorder();
            break;
    }
    wp_send_json_success($result);
}

function railticket_get_page_url() {
    global $wp;
    //return home_url( add_query_arg( array(), $wp->request ) )."/";
    return '';
}

function railticket_options() {
    wp_register_style('railticket_style', plugins_url('wc-product-railticket/ticketbuilder.css'));
    wp_enqueue_style('railticket_style');
    ?>

    <h1>Heritage Railway Tickets</h1>
    <form method="post" action="options.php">
    <?php settings_fields('wc_product_railticket_options_main'); ?>
    <table class='railticket_admintable'>
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
        <tr valign="top">
            <th scope="row"><label for="wc_railticket_date_format">Display Date format</label></th>
            <td><input type="text" id="wc_railticket_date_format" name="wc_railticket_date_format" value="<?php echo get_option('wc_railticket_date_format'); ?>" /> Use <a href='https://www.php.net/manual/en/function.strftime' target='_blank'>PHP strftime formatting parameters</a> here</td>
        </tr>
        <tr valign="top">
            <th scope="row"><label for=wc_railticket_time_format">Display Time format</label></th>
            <td><input type="text" id="wc_railticket_time_format" name="wc_railticket_time_format" value="<?php echo get_option('wc_railticket_time_format'); ?>" /> Use <a href='https://www.php.net/manual/en/function.strftime' target='_blank'>PHP strftime formatting parameters</a> here</td>
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
    if (array_key_exists('action', $_REQUEST)) {
        switch ($_REQUEST['action']) {
            case 'updatebookable':
                railticket_updatebookable();
            case 'filterbookable':
                railticket_show_cal_selector();
                railticket_showcalendaredit(intval($_REQUEST['year']), intval($_REQUEST['month']));
                break;
            case 'showbookableday':
                railticket_showbookableday();
                break;
            case 'addbookable':
                railticket_addbookableday();
                break;
            case 'deletebookable':
                railticket_deletebookableday();
                break;
        }
    } else {
        railticket_show_cal_selector();
        railticket_showcalendaredit(intval(date("Y")), intval(date("n")));
    }
}

function railticket_show_cal_selector() {
    if (array_key_exists('month', $_REQUEST)) {
        $month = sanitize_text_field($_REQUEST['month']);
    } else {
        $month = intval(date("n"));
    }

    ?>
    <h1>Heritage Railway Tickets - Set bookable dates</h1>
    <form method='post' action='<?php echo railticket_get_page_url() ?>'>
        <input type='hidden' name='action' value='filterbookable' />    
        <table><tr>
            <th>Year</th>
            <th>Month</th>
        </tr><tr>
            <td><?php echo wc_railticket_getyearselect();?></td>
            <td><?php echo wc_railticket_getmonthselect($month);?></td>
            <td><input type='submit' value='Show Bookable Days' /></td>
        </tr></table>
    </form>
    <hr />
    <?php
}

function wc_railticket_getmonthselect($chosenmonth = false) {
    if ($chosenmonth == false) {
        $chosenmonth = intval(date("m"));
    }

    if (array_key_exists('month', $_REQUEST)) {
        $chosenmonth = intval($_REQUEST['month']);
    }

    if (array_key_exists('date', $_REQUEST)) {
        $p = explode('-', sanitize_text_field($_REQUEST['date']));
        $chosenmonth = $p[1];
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

    if (array_key_exists('year', $_REQUEST)) {
        $chosenyear = sanitize_text_field($_REQUEST['year']);
    } else {
        $chosenyear = $currentyear;
    }

    if (array_key_exists('date', $_REQUEST)) {
        $p = explode('-', sanitize_text_field($_REQUEST['date']));
        $chosenyear = $p[0];
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
    if (array_key_exists($cbname, $_REQUEST)) {
        return sanitize_text_field($_REQUEST[$cbname]);
    } else {
        return 0;
    }
}

function railticket_showcalendaredit($year, $month) {
    global $rtmustache;

    $daysinmonth = intval(date("t", mktime(0, 0, 0, $month, 1, $year)));
    $render = new \stdclass();
    $render->days = array();
    $render->url =  railticket_get_page_url();

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
        $data->ttrev = $bookableday->timetable->get_revision_name();
        $data->pricerev = $bookableday->fares->get_name();

        $render->days[] = $data;
    }

    $timetables = \wc_railticket\Timetable::get_timetables();
    $render->timetables = array();
    foreach ($timetables as $tt) {
        $cl = new \stdclass();
        $cl->name = $tt->get_name();
        $cl->ttid = $tt->get_timetableid();
        $render->timetables[] = $cl;
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

function railticket_showbookableday() {
    global $rtmustache;
    $bkdate = sanitize_text_field($_REQUEST['date']);
    $bookable = \wc_railticket\BookableDay::get_bookable_day($bkdate);

    if (!$bookable) {
        $bookable = \wc_railticket\BookableDay::create_bookable_day($bkdate);
    }

    $alldata = $bookable->get_data();
    $alldata->dateformatted = $bookable->get_date(true);
    $cbs = array('bookable', 'soldout', 'sameservicereturn', 'sellreserve', 'specialonly');
    foreach ($cbs as $key) {
        if ($alldata->$key == 1) {
            $alldata->$key = 'checked';
        } else {
            $alldata->$key = '';
        }
    }

    $alldata->adminurl = esc_url( admin_url('admin-post.php'));

    $alldata->fares = array();

    if ($bookable->count_bookings() > 0) {
        $alldata->disabled = 'disabled';
        $alldata->locknote = 'Bookings present, revision locked';
    } 

    $allfares = \wc_railticket\FareCalculator::get_all_revisions();
    $chosenfr = $bookable->fares->get_revision();
    foreach ($allfares as $fare) {
        $f = new stdclass();
        $f->name = $fare->get_name();
        $f->value = $fare->get_revision();
        if ($f->value == $chosenfr) {
            $f->selected = 'selected';
        } else {
            $f->selected = '';
        }
        $alldata->fares[] = $f;
    }

    $chosenttr = $bookable->timetable->get_revision();
    $alldata->ttrevs = \wc_railticket\Timetable::get_all_revisions();
    foreach ($alldata->ttrevs as $ttrev) {
        if ($ttrev->id == $chosenttr) {
            $ttrev->selected = 'selected';
        } else {
            $ttrev->selected = '';
        }
    }

    $chosentt = $bookable->timetable->get_timetableid();
    $timetables = \wc_railticket\Timetable::get_timetables($chosenttr);
    $alldata->timetables = array();
    foreach ($timetables as $tt) {
        $cl = new \stdclass();
        if ($tt->get_timetableid() == $chosentt) {
            $cl->selected = 'selected';
        } else {
            $cl->selected = '';
        }
        $cl->name = $tt->get_name();
        $cl->ttid = $tt->get_timetableid();
        $alldata->timetables[] = $cl;
    }
    $alldata->ttrevision = $timetables[0]->get_revision();
    $alldata->override = $bookable->get_override();

    $template = $rtmustache->loadTemplate('bookableday');
    echo $template->render($alldata);
}

function railticket_editbookableday() {
    $bkdate = sanitize_text_field($_REQUEST['date']);
    $bkid = sanitize_text_field($_REQUEST['id']);
    if ($bkid == -1) {
        $bookable = \wc_railticket\BookableDay::create_bookable_day($bkdate);
    } else {
        $bookable = \wc_railticket\BookableDay::get_bookable_day($bkdate);
    }

    $ndata = new stdclass();
    if (array_key_exists('ttrevision', $_REQUEST)) {
        $ttrevision = sanitize_text_field($_REQUEST['ttrevision']);
        $ndata->ttrevision = $ttrevision;
    }
    if (array_key_exists('pricerevision', $_REQUEST)) {
        $pr = sanitize_text_field($_REQUEST['pricerevision']);
        $ndata->pricerevision = $pr;
    }
    if (array_key_exists('timetable', $_REQUEST)) {
        $tt = sanitize_text_field($_REQUEST['timetable']);
        $ndata->timetableid = $tt;
    }
    $ndata->bookable = railticket_get_cbval('bookable');
    $ndata->specialonly = railticket_get_cbval('specialonly');
    $ndata->sellreserve = railticket_get_cbval('sellreserve');
    $ndata->soldout = railticket_get_cbval('soldout');
    $ndata->sameservicereturn = railticket_get_cbval('sameservicereturn');
    $ndata->composition = stripslashes($_REQUEST['composition']);
    $bookable->update_bookable($ndata);

    $p = explode('-', $bkdate);
    wp_redirect(site_url().'/wp-admin/admin.php?page=railticket-bookable-days&month='.$p[1].'&year='.$p[0].'&action=filterbookable');
}

function railticket_addbookableday() {
    $date = sanitize_text_field($_REQUEST['date']);
    $ttid = sanitize_text_field($_REQUEST['timetable']);
    // Not using this for now...
    //$ttr = sanitize_text_field($_REQUEST['ttrevision']);

    \wc_railticket\BookableDay::create_timetable_date($date, $ttid);
    $d = DateTime::createFromFormat('Y-m-d', $date);
    railticket_show_cal_selector();
    railticket_showcalendaredit(intval($d->format("Y")), intval($d->format("n")));
}

function railticket_deletebookableday() {
    $date = sanitize_text_field($_REQUEST['date']);
    $sure = railticket_gettfpostfield('sure');
    if ($sure) {
        \wc_railticket\BookableDay::delete_timetable_date($date);
    } else {
       echo "<h3>".__("You weren't sure about deleting the date!", "wc_railticket")."</h3>";
       railticket_showbookableday();
       return;
    }
    $d = DateTime::createFromFormat('Y-m-d', $date);
    railticket_show_cal_selector();
    railticket_showcalendaredit(intval($d->format("Y")), intval($d->format("n")));
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
    <h1>Service Summaries</h1>
    <div class='railticket_editdate'>
    <form method='post' action='<?php echo railticket_get_page_url(); ?>'>
        <input type='hidden' name='action' value='filterbookings' />    
        <table><tr>
            <td>Day</td>
            <td>Month</td>
            <td>Year</td>
        </tr><tr>
            <td><?php echo railticket_getdayselect($chosenday);?></td>
            <td><?php echo wc_railticket_getmonthselect($chosenmonth);?></td>
            <td><?php echo wc_railticket_getyearselect($chosenyear);?></td>
        </tr><tr>
            <td colspan='3'><input type='submit' value='Show Departures' style='width:100%' /></td>
        </tr></table>
    </form>
    </div><br />
    <hr />
    <?php

    railticket_show_bookings_summary($dateofjourney);
}

function railticket_show_order_form() {
    ?>
    <hr /><br />
    <h1>Lookup Online Order<h1>
    <div class='railticket_editdate'>
    <form method='post' action='<?php echo railticket_get_page_url() ?>'>
        <input type='hidden' name='action' value='showorder' />    
        <table><tr>
            <td style='font-size:x-large;'>Order ID</td>
            <td><input style='width:120px;font-size:x-large;' type='test' name='orderid' required /></td>
            <td colspan='2'><input type='submit' value='Find' /></td>
        </tr></table>
    </form>
    <br />
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
    $dateofjourney = sanitize_text_field($_REQUEST['dateofjourney']);
    $timetable = \wc_railticket\Timetable::get_timetable_by_date($dateofjourney);
    ?><h1>Seat/Bay usage summary for <?php echo $timetable->get_date(true); ?></h1><?php

    $stations = $timetable->get_stations();

    foreach ($stations as $station) {
        railticket_show_station_summary($dateofjourney, $station, $timetable, true);
    }
}


function railticket_show_bookings_summary($dateofjourney) {
    $bookableday = \wc_railticket\BookableDay::get_bookable_day($dateofjourney);

    // If the override code is empty, this day has a timetable, but hasn't been initialised.
    if (!$bookableday) {
        echo "<h3>Booking data not initiailised for this day - please make this day bookable.</h3>";
        return;
    }

    ?><h1>Summary for <?php echo $bookableday->get_date(true); ?></h1><?php

    echo "<h2 style='font-size:x-large;line-height:120%;'>Booking override code:<span style='color:red'>".$bookableday->get_override()."</span></h2>";

    $stations = $bookableday->timetable->get_stations();

    foreach ($stations as $station) {
        railticket_show_station_summary($dateofjourney, $station, $bookableday->timetable);
    }

    $specials = \wc_railticket\Special::get_specials($dateofjourney);
    if ($specials && count($specials) > 0) {
        echo "<h3>Specials</h3>";
        echo "<div class='railticket_inlinedeplist'><ul>";
        foreach ($specials as $special) {
            $from = $special->get_from_station();
            $to = $special->get_to_station();
            ?>
            <li><form method='post' action='<?php echo railticket_get_page_url() ?>'>
                <input type='hidden' name='action' value='showspecial' />
                <input type='hidden' name='dateofjourney' value='<?php echo $dateofjourney; ?>' />
                <input type='hidden' name='station' value='<?php echo $from->get_stnid(); ?>' />
                <input type='hidden' name='direction' value='<?php echo $from->get_direction($to); ?>' />
                <input type='hidden' name='deptime' value='<?php echo $special->get_dep_id() ?>' />
                <input type='hidden' name='ttrevision' value='<?php echo $special->get_timetable_revision() ?>' />
                <input type='submit' name='submit' value='<?php echo $special->get_name() ?>' />
            </form></li>
            <?php
        }
        echo "</ul></div>";
    }
    ?>
    <hr />
    <div class='railticket_editdate' style='max-width:550px;margin-left:0px;margin-right:auto;'>
    <p><form method='post' action='<?php echo railticket_get_page_url() ?>'>
        <input type='hidden' name='action' value='viewwaybill' />
        <input type='hidden' name='dateofjourney' value='<?php echo $dateofjourney; ?>' />
        <input type='submit' name='submit' value='View Way Bill' style='width:100%' />
    </form></p>
    <p><form method='post' action='<?php echo railticket_get_page_url() ?>'>
        <input type='hidden' name='action' value='viewordersummary' />
        <input type='hidden' name='dateofjourney' value='<?php echo $dateofjourney; ?>' />
        <input type='submit' name='submit' value='View Order Summary'  style='width:100%'/>
    </form></p>
    <p><form method='post' action='<?php echo railticket_get_page_url() ?>'>
        <input type='hidden' name='action' value='viewseatsummary' />
        <input type='hidden' name='dateofjourney' value='<?php echo $dateofjourney; ?>' />
        <input type='submit' name='submit' value='View Seat/Bay Usage Summary' style='width:100%' />
    </form></p>
    <p><form method='get' action='<?php echo admin_url('admin-post.php') ?>'>
        <input type='hidden' name='action' value='waybill.csv' />
        <input type='hidden' name='dateofjourney' value='<?php echo $dateofjourney; ?>' />
        <input type='submit' name='submit' value='Get Way Bill as Spreadsheet file' style='width:100%' />
    </form></p>
    <p><form method='get' action='<?php echo admin_url('admin-post.php') ?>'>
        <input type='hidden' name='action' value='ordersummary.csv' />
        <input type='hidden' name='dateofjourney' value='<?php echo $dateofjourney; ?>' />
        <input type='submit' name='submit' value='Get Order Summary Spreadsheet file' style='width:100%' />
    </form></p>
    </ul>
    </div>
    <?php
}

function railticket_show_station_summary($dateofjourney, \wc_railticket\Station $station, \wc_railticket\Timetable $timetable, $showseats = false) {
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
    global $rtmustache;

    $alldata = new \stdclass();
    $alldata->dateofjourney = $dateofjourney;
    $alldata->station = $station->get_stnid();
    $alldata->direction = $direction;
    $alldata->timekey = $t->key;
    $alldata->ttrevison = $station->get_revision();
    if ($incstn) {
        $alldata->btnlabel = "Back to ".$t->formatted." from ".$station->get_name();
    } else {
        $alldata->btnlabel = $t->formatted;
    }

    $template = $rtmustache->loadTemplate('depbutton');
    echo $template->render($alldata);
}

function railticket_show_departure($dateofjourney, \wc_railticket\Station $station, $direction, $deptime, $summaryonly = false) {
    $bookableday = \wc_railticket\BookableDay::get_bookable_day($dateofjourney);
    $destination = $bookableday->timetable->get_terminal($direction);
    // If this is being called directly from a button click this will be a string
    if (is_string($deptime)) {
        $bookings = $bookableday->get_bookings_from_station($station, $deptime, $direction);
        $trainservice = new \wc_railticket\TrainService($bookableday, $station, $deptime, $destination);
        if ($trainservice->special == false) {
            $dt = new \stdclass();
            $dt->key = $deptime;
            $railticket_timezone = new \DateTimeZone(get_option('timezone_string'));
            $depname = \DateTime::createFromFormat("H.i", $deptime, $railticket_timezone);
            $dt->formatted = strftime(get_option('wc_railticket_time_format'), $depname->getTimeStamp());
            $deptime = $dt;
        } 
    } else {
        $bookings = $bookableday->get_bookings_from_station($station, $deptime->key, $direction);
        $trainservice = new \wc_railticket\TrainService($bookableday, $station, $deptime->key, $destination);
    }

    // Formatting for specials
    if ($trainservice->special) {
        $dt = new \stdclass();
        $dt->key = $deptime;
        $dt->formatted = $trainservice->special->get_name();
        $deptime = $dt;
    }

    $seats = 0;
    foreach ($bookings as $booking) {
        $seats += $booking->get_seats();
    }

    $basebays = $trainservice->get_inventory(true, true);
    $capused = $trainservice->get_inventory(false, false);
    $capcollected = $trainservice->get_inventory(false, true, true);

   if ($summaryonly) {
        echo "<table><tr><td>";
        railticket_show_dep_button($dateofjourney, $station, $direction, $deptime);
        echo "</td><td class='railticket_shortsummary'>Seats Used:".$seats."&nbsp&nbsp;</td><td class='railticket_shortsummary'>Seats Available:".$capused->totalseats."</td></tr></table>";
   } else {
        echo "<div class='railticket_editdate'><h2>Service summary</h2><table class='railticket_admintable' border='1'>".
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
    echo "<h2>Bay Usage (one way to destination)</h2><div class='railticket_trainbookings'>".
        "<table border='1' class='railticket_admintable'><th>Bay</th><th>Total</th><th>Used</th><th>Collected</th><th>Available</th></tr>";
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
    <h2>Booking summary</h2>
    <div class='railticket_trainbookings'>
    <table border='1' class='railticket_admintable' >
        <tr>
            <th>Order</th>
            <th>Name</th>
            <th>To</th>
            <th>Seats</th>
            <th>Bays</th>
            <th>Collected</th>
        </tr>
    <?php
    foreach ($bookings as $booking) {
        echo "<tr>";
        if (strlen($booking->in_cart()) > 0) {
            echo "<td>In Cart</td>";
        } else {
            $orderid = $booking->get_order_id();
            echo "<td><form action='".railticket_get_page_url()."' method='post'>".
                "<input type='hidden' name='action' value='showorder' />".
                "<input type='hidden' name='orderid' value='".$orderid."' />".
                "<input type='submit' value='".$orderid."' style='width:100%;margin-top:4px;margin-bottom:4px;' />".
                "</form></td>";
        }
        echo "<td>".$booking->get_order_name()."</td>".
            "<td>".$booking->get_to_station()->get_name()."</td>".
            "<td>".$booking->get_seats()."</td>".
            "<td>";
        echo $booking->get_bays(true);
        echo "</td>";

        if ($booking->is_collected()) {
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
    <div class='railticket_editdate' style='max-width:550px;margin-left:0px;margin-right:auto;'>
    <form action='<?php echo railticket_get_page_url() ?>' method='post'>
        <input type='hidden' name='action' value='<?php echo $_REQUEST['action']; ?>' />
        <input type='hidden' name='dateofjourney' value='<?php echo $dateofjourney; ?>' />
        <input type='hidden' name='station' value='<?php echo $station->get_stnid(); ?>' />
        <input type='hidden' name='ttrevision' value='<?php echo $station->get_revision(); ?>' />
        <input type='hidden' name='direction' value='<?php echo $direction ?>' />
        <input type='hidden' name='deptime' value='<?php echo $deptime->key ?>' />
        <input type='hidden' name='destination' value='<?php echo $destination->get_stnid() ?>' />
        <input type='submit' name='submit' value='Refresh Display' style='width:100%' />
    </form><br />
    <form action='<?php echo railticket_get_page_url() ?>' method='post'>
        <input type='hidden' name='action' value='filterbookings' />
        <input type='hidden' name='dateofjourney' value='<?php echo $dateofjourney; ?>' />
        <input type='submit' name='submit' value='Back to Services' style='width:100%' />
    </form><br />
    <form action='/book/' method='post'>
        <input type='hidden' name='action' value='createmanual' />
        <input type='hidden' name='show' value='1' />
        <input type='hidden' name='a_dateofjourney' value='<?php echo $dateofjourney; ?>' />
        <input type='hidden' name='a_station' value='<?php echo $station->get_stnid(); ?>' />
        <input type='hidden' name='a_deptime' value='<?php echo $deptime->key ?>' />
        <input type='submit' name='submit' value='Add Manual Booking' style='width:100%' />
    </form>
    </div>
    <?php
}

function railticket_mark_ticket($val) {
    $id = sanitize_text_field($_POST['bookingid']);
    $booking = \wc_railticket\Booking::set_collected($id, $val);
}

function railticket_delete_manual_order() {
    global $rtmustache;
    $orderid = sanitize_text_field($_REQUEST['orderid']);
    $sure = railticket_get_cbval('sure');
    if ($sure != '1') {
        echo "<h3>Refusing to delete ".$orderid." you weren't sure about it.</h3>";

        $template = $rtmustache->loadTemplate('delete_order_button');
        echo $template->render(array('orderid' => $orderid));
        return;
    }

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
    $orderdata[] = array('item' => __('Price', 'wc_railticket'), 'value' => $bookingorder->get_price(true));
    $orderdata[] = array('item' => __('Price Breakdown', 'wc_railticket'), 'value' => $bookingorder->get_ticket_prices(true));
    $orderdata[] = array('item' => __('Supplement', 'wc_railticket'), 'value' => $bookingorder->get_supplement(true));
    $orderdata[] = array('item' => __('Tickets'), 'value' => $bookingorder->get_tickets(true));
    $orderdata[] = array('item' => __('Date'), 'value' => $bookingorder->get_date(true, true));
    $orderdata[] = array('item' => __('Journey Type'), 'value' => $bookingorder->get_journeytype(true));
    $orderdata[] = array('item' => __('Seats'), 'value' => $bookingorder->get_seats());
    $orderdata[] = array('item' => __('Booked by'), 'value' => $bookingorder->get_created_by(true));
    $orderdata[] = array('item' => __('Notes'), 'value' => $bookingorder->get_notes());

    $alldata = array(
        'dateofjourney' => $bookingorder->get_date(),
        'details' => $orderdata,
        'bookings' => railticket_get_booking_render_data($bookingorder),
        'timestr' => __('Departure Time', 'wc_railticket'),
        'tripstr' => __('Trip', 'wc_railticket'),
        'baystr' => __('Bays', 'wc_railticket'),
        'otheritemsstr' => __('Shop Items to Collect', 'wc_railticket'),
        'collectedstr' => __('Collected', 'wc_railticket'),
        'orderid' => $orderid,
        'actionurl' => railticket_get_page_url(),
        'buttonstyle' => 'width:100%;',
        'otheritems' => $bookingorder->other_items()
    );

    if ($alldata['otheritems'] == false || count($alldata['otheritems']) == 0) {
        $alldata['otheritemsstyle'] = 'display:none';
    }

    $alldata['extrabuttons'] = '';

    if (current_user_can('admin_tickets')) {
         $template = $rtmustache->loadTemplate('edit_order_button');
         $alldata['extrabuttons'] .= $template->render($alldata)."<br />";
    }

    if ($bookingorder->is_manual() && current_user_can('delete_tickets')) {
         $template = $rtmustache->loadTemplate('delete_order_button');
         $alldata['extrabuttons'] .= $template->render($alldata);
    }

    $template = $rtmustache->loadTemplate('showorder');
    echo $template->render($alldata);
}

function railticket_get_booking_render_data($bookingorder) {
    $bookings = $bookingorder->get_bookings();
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
        if ($booking->is_special()) {
            $bk['btnlabel'] = "Back to ".$bk['deptime'];
        } else {
            $bk['btnlabel'] = "Back to ".$bk['deptime']." from ".$fromstn->get_name();
        }
        $data[] = $bk;
        $count++;
    }

    return $data;
}

function railticket_show_edit_order() {
    global $rtmustache;

    $orderid = sanitize_text_field($_REQUEST['orderid']);
    $bookingorder = \wc_railticket\BookingOrder::get_booking_order($orderid);
    if (!$bookingorder) {
        echo "<p>Invalid order number or no tickets were purchased with this order.</p>";
        railticket_summary_selector();
        return;
    }

    wp_register_script('railticket_script_mustache', plugins_url('wc-product-railticket/mustache.min.js'));
    wp_register_script('railticket_script_builder', plugins_url('wc-product-railticket/ticketeditor.js'));
    wp_enqueue_script('railticket_script_mustache');
    wp_enqueue_script('railticket_script_builder');
    wp_register_style('railticket_style', plugins_url('wc-product-railticket/ticketbuilder.css'));
    wp_enqueue_style('railticket_style');

    $orderdata = array();
    $orderdata[] = array('item' => __('Order ID', 'wc_railticket'), 'value' => $orderid);
    $orderdata[] = array('item' => __('Name', 'wc_railticket'), 'value' => $bookingorder->get_customer_name());
    $orderdata[] = array('item' => __('Price', 'wc_railticket'), 'value' => $bookingorder->get_price(true));
    $orderdata[] = array('item' => __('Price Breakdown', 'wc_railticket'), 'value' => $bookingorder->get_ticket_prices(true));
    $orderdata[] = array('item' => __('Supplement', 'wc_railticket'), 'value' => $bookingorder->get_supplement(true));
    $orderdata[] = array('item' => __('Tickets'), 'value' => $bookingorder->get_tickets(true));
    $orderdata[] = array('item' => __('Journey Type'), 'value' => $bookingorder->get_journeytype(true));
    $orderdata[] = array('item' => __('Seats'), 'value' => $bookingorder->get_seats());

    $bkdata = new \stdclass();
    $bkdata->bookings = array();
    $bkdata->date = $bookingorder->get_date();
    $tripdata = array();
    $count = 1;
    $allstns = \wc_railticket\Station::get_stations($bookingorder->bookableday->timetable->get_revision());
    foreach ($bookingorder->get_bookings() as $booking) {
        $from = $booking->get_from_station();
        $to = $booking->get_to_station();

        $data = new \stdclass();
        $trip = new stdclass();
        $data->num = $count;
        $trip->num = $count;

        $trip->from = $from->get_name();
        $trip->to = $to->get_name();
        $trip->dep = $booking->get_dep_time(true);
        $tripdata[] = $trip;

        $data->fromstns = railticket_get_stnselect($from, $allstns);
        $data->tostns = railticket_get_stnselect($to, $allstns);
        $data->deps = railticket_get_depselect($booking->bookableday, $from, $to, $booking->get_dep_time(), $bookingorder->get_bookings());
        $bkdata->bookings[] = $data;
        $count++;
    }

    $alldata = array(
        'date' => $bookingorder->get_date(true),
        'actionurl' => railticket_get_page_url(),
        'details' => $orderdata,
        'orderid' => $orderid,
        'ajaxurl' => admin_url( 'admin-ajax.php', 'relative' ),
        'defaultdata' => json_encode($bkdata),
        'tripdata' => $tripdata
    );
    $template = $rtmustache->loadTemplate('editorder');
    echo $template->render($alldata);
    echo file_get_contents(dirname(__FILE__).'/edit-templates.html');
}

function railticket_get_moveorderdata() {
    $orderid = railticket_getpostfield('orderid');
    $bookingorder = \wc_railticket\BookingOrder::get_booking_order($orderid);
    $dateoftravel = railticket_getpostfield('dateoftravel');
    $legs = json_decode(stripslashes($_REQUEST['legs']));
    $bkdata = new \stdclass();
    $bkdata->bookings = array();

    $bk = \wc_railticket\BookableDay::get_bookable_day($dateoftravel);
    if (!$bk) {
        $bkdata->bkerror = "Date not bookable";
        return $bkdata;
    }

    $bkdata->bkerror = false;

    $bkdata = new \stdclass();
    $bkdata->bookings = array();
    $bkdata->date = $dateoftravel;

    $count = 1;
    $allstns = \wc_railticket\Station::get_stations($bk->timetable->get_revision());
    foreach ($legs as $leg) {
        $data = new \stdclass();
        $data->num = $count;
        $from = \wc_railticket\Station::get_station($leg->from, $bk->timetable->get_revision());
        $to = \wc_railticket\Station::get_station($leg->to, $bk->timetable->get_revision());

        $data->fromstns = railticket_get_stnselect($from, $allstns);
        $data->tostns = railticket_get_stnselect($to, $allstns);

        if ($from->matches($to)) {
            $data->deps = array();
        } else {
            $data->deps = railticket_get_depselect($bk, $from, $to, $leg->dep, $bookingorder->get_bookings());
        }
        $bkdata->bookings[] = $data;
        $count++;
    }


    return $bkdata;
}

function railticket_get_depselect(\wc_railticket\BookableDay $bk, \wc_railticket\Station $from, \wc_railticket\Station $to, $dt, $exclude) {
    $method = 'get_'.$from->get_direction($to).'_deps';
    $deps = $bk->timetable->$method($from, true, $to);

    for ($i = 0; $i < count($deps); $i++) {
        if ($deps[$i]->key == $dt) {
            $deps[$i]->selected = 'selected';
        }
        $trainservice = new \wc_railticket\TrainService($bk, $from, $deps[$i]->key, $to);
        $capused = $trainservice->get_inventory(false, false, false, $exclude);
        $deps[$i]->seats = $capused->totalseats;
    }

    return $deps;
}

function railticket_get_stnselect(\wc_railticket\Station $selected, $allstns) {
    $data = array();
    foreach ($allstns as $stn) {
        $e = new \stdclass();
        $e->name = $stn->get_name();
        $e->stnid = $stn->get_stnid();
        if ($stn->matches($selected)) {
            $e->selected = 'selected';
        }
        $data[] = $e;
    }

    return $data;
}

function railticket_editorder() {
    $edit = new \stdclass();
    if (!current_user_can('admin_tickets')) {
        $edit->message = 'You do not have permission to do this.';
        return $edit;
    }

    $orderid = railticket_getpostfield('orderid');
    $notify = railticket_gettfpostfield('notify');
    $bookingorder = \wc_railticket\BookingOrder::get_booking_order($orderid);
    $dateoftravel = railticket_getpostfield('dateoftravel');
    $legs = json_decode(stripslashes($_REQUEST['legs']));

    if ($dateoftravel != $bookingorder->get_date()) {
        $bk = \wc_railticket\BookableDay::get_bookable_day($dateoftravel);
        if (!$bk) {
            $edit->message = 'Unable to make changes - date not bookable';
            return $edit;
        }
        $bookingorder->set_date($bk);
    }

    $bookings = $bookingorder->get_bookings();

    for ($i=0; $i < count($legs); $i++) {
        $nfrom = \wc_railticket\Station::get_station($legs[$i]->from, $bookingorder->bookableday->timetable->get_revision());
        $nto = \wc_railticket\Station::get_station($legs[$i]->to, $bookingorder->bookableday->timetable->get_revision());
        $ofrom = $bookings[$i]->get_from_station();
        $oto = $bookings[$i]->get_to_station();

        if (!$nfrom->matches($ofrom) || !$nto->matches($oto) || $bookings[$i]->get_dep_time() != $legs[$i]->dep) {
            $bookings[$i]->set_dep($nfrom, $nto, $legs[$i]->dep);
        }
    }

    if ($notify) {
        $bookingorder->notify();
    }

    $edit->message = 'Order update saved';
    return $edit;
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
