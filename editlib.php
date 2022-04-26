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
add_action('railticket_process_stats_cron', 'railticket_process_stats');
add_action('admin_post_railticketstats.csv', 'railticket_get_stats');
add_action('admin_post_railticketgeo.csv', 'railticket_get_geo');

// Schedule an action if it's not already scheduled
if ( ! wp_next_scheduled( 'railticket_process_stats_cron' ) ) {
    $dtz = new \DateTimeZone(get_option('timezone_string'));
    $dt = new \DateTime('now', $dtz);
    $dt->setTime(2,0,0);
    wp_schedule_event($dt->getTimestamp(), 'daily', 'railticket_process_stats_cron' );
}


function railticket_register_settings() {
   add_option('wc_product_railticket_woocommerce_product', '');
   register_setting('wc_product_railticket_options_main', 'wc_product_railticket_woocommerce_product'); 
   register_setting('wc_product_railticket_options_main', 'wc_product_railticket_top_comment');
   register_setting('wc_product_railticket_options_main', 'wc_product_railticket_min_price');
   register_setting('wc_product_railticket_options_main', 'wc_product_railticket_reservetime');
   register_setting('wc_product_railticket_options_main', 'wc_product_railticket_releaseinventory');
   register_setting('wc_product_railticket_options_main', 'wc_product_railticket_sameservicereturn');
   register_setting('wc_product_railticket_options_main', 'wc_product_railticket_termspage');
   register_setting('wc_product_railticket_options_main', 'wc_product_railticket_termscomment');
   register_setting('wc_product_railticket_options_main', 'wc_product_railticket_bookinggrace');
   register_setting('wc_product_railticket_options_main', 'wc_product_railticket_defaultcoaches');
   register_setting('wc_product_railticket_options_main', 'wc_product_railticket_bookinglimits');
   register_setting('wc_product_railticket_options_main', 'wc_product_railticket_prioritynotify');
   register_setting('wc_product_railticket_options_main', 'wc_product_railticket_calmonths');

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
    add_submenu_page('railticket-top-level-handle', "Specials", "Specials", 'manage_options', 'railticket-specials', 'railticket_manage_specials');
    add_submenu_page('railticket-top-level-handle', "Coach Types", "Coach Types", 'manage_options', 'railticket-coach-types', 'railticket_coach_types');
    add_submenu_page('railticket-top-level-handle', "Travellers", "Travellers", 'manage_options', 'railticket-travellers', 'railticket_travellers');
    add_submenu_page('railticket-top-level-handle', "Ticket Types", "Ticket Types", 'manage_options', 'railticket-tickets', 'railticket_tickets');
    add_submenu_page('railticket-top-level-handle', "Fares", "Fares", 'manage_options', 'railticket-fares', 'railticket_fares');
    add_submenu_page('railticket-top-level-handle', "Discount Codes", "Discount Codes", 'manage_options', 'railticket-discount-codes', 'railticket_discount_codes');
    add_submenu_page('railticket-top-level-handle', "Discount Types", "Discount Types", 'manage_options', 'railticket-discount-types', 'railticket_discount_types');
    add_submenu_page('railticket-top-level-handle', "Import Timetable", "Import Timetable", 'manage_options', 'railticket-import-timetable', 'railticket_import_timetable');
    add_submenu_page('railticket-top-level-handle', "Statistics", "Statistics", 'manage_options', 'railticket-stats', 'railticket_stats');
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
                break;
            case 'collected':
                railticket_mark_ticket(true);
                break;
            case 'showorder':
                railticket_show_order();
                break;
            case 'showdep':
            case 'showspecial':
                $rev = sanitize_text_field($_REQUEST['ttrevision']);
                $station = \wc_railticket\Station::get_station(sanitize_text_field($_REQUEST['station']), $rev);
                if (array_key_exists('destination', $_REQUEST)) {
                    $destination = \wc_railticket\Station::get_station(sanitize_text_field($_REQUEST['destination']), $rev);
                } else {
                    $destination = false;
                }
                railticket_show_departure(sanitize_text_field($_REQUEST['dateofjourney']), $station,
                   sanitize_text_field($_REQUEST['direction']), sanitize_text_field($_REQUEST['deptime']), $destination);
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
            case 'saveordernote':
                railticket_save_order_note();
                break;
            case 'editorderbook';
                railticket_show_edit_order();
                break;
            case 'rebookall':
            case 'rebookservice':
                railticket_rebook($_REQUEST['action']);
                break;
            case 'managespecials':
                railticket_manage_specials();
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
        case 'overridebays':
            $result = railticket_overridebays();
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
            <th scope="row"><label for="wc_product_railticket_calmonths">Max number of months ahead to show</label></th>
            <td><input size='2' type="number" min='1' max='12' id="wc_product_railticket_" name="wc_product_railticket_calmonths" value="<?php echo get_option('wc_product_railticket_calmonths'); ?>" /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><label for="wc_product_railticket_min_price">Default Minimum Ticket Order Price</label></th>
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
            <th scope="row"><label for="wc_product_railticket_termscomment">Ticket Terms Summary</label></th>
            <td><textarea rows='4' cols='60' id="wc_product_railticket_termscomment" name="wc_product_railticket_termscomment"><?php echo get_option('wc_product_railticket_termscomment'); ?></textarea></td>
        </tr>
        <tr valign="top">
            <th scope="row"><label for="wc_product_railticket_bookinggrace">Booking overrun period (minutes)</label></th>
            <td><input type="text" size='2' id="wc_product_railticket_bookinggrace" name="wc_product_railticket_bookinggrace" value="<?php echo get_option('wc_product_railticket_bookinggrace'); ?>" /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><label for="wc_railticket_date_format">Display Date format</label></th>
            <td><input type="text" id="wc_railticket_date_format" name="wc_railticket_date_format" value="<?php echo get_option('wc_railticket_date_format'); ?>" /> Use <a href='https://www.php.net/manual/en/function.railticket_timefunc' target='_blank'>PHP railticket_timefunc formatting parameters</a> here</td>
        </tr>
        <tr valign="top">
            <th scope="row"><label for=wc_railticket_time_format">Display Time format</label></th>
            <td><input type="text" id="wc_railticket_time_format" name="wc_railticket_time_format" value="<?php echo get_option('wc_railticket_time_format'); ?>" /> Use <a href='https://www.php.net/manual/en/function.railticket_timefunc' target='_blank'>PHP railticket_timefunc formatting parameters</a> here</td>
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
            <th scope="row"><label for="wc_product_railticket_prioritynotify">Notify these email addresses when a wheelchair booking is made<br />(comma seperated list)</label></th>
            <td><input size='60'  type="text" id="wc_product_railticket_prioritynotify" name="wc_product_railticket_prioritynotify" value="<?php echo get_option('wc_product_railticket_prioritynotify'); ?>" /></td>
        </tr>
    </table>
    <?php submit_button(); ?>
    </form>
    <?php
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

    // The guard only gets the current year
    if (!current_user_can('admin_tickets')) {
        return  $currentyear."<input type='hidden' value='".intval(date("Y"))."' name='year' />";
    }

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

    $firstdate = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_railticket_dates ORDER BY date ASC LIMIT 1 ");
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
        $railticket_timezone = new \DateTimeZone(get_option('timezone_string'));
        $datefrom = DateTime::createFromFormat('d-m-Y', $revision['datefrom'], $railticket_timezone);
        $dateto = DateTime::createFromFormat('d-m-Y', $revision['dateto'], $railticket_timezone);

        foreach($data->dates as $dateentry) {
            $thisdate = DateTime::createFromFormat('d-m-Y', $dateentry->date, $railticket_timezone);
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
        $data->minprice = $bookableday->get_min_price();

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

    wp_register_script('railticket_script_mustache', plugins_url('wc-product-railticket/js/mustache.min.js'));
    wp_register_script('railticket_script_sp', plugins_url('wc-product-railticket/js/serviceparams.js'));
    wp_enqueue_script('railticket_script_mustache');
    wp_enqueue_script('railticket_script_sp');

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

    $chosenfr = $bookable->fares->get_revision();
    $alldata->fares = railticket_get_all_pricerevisions($chosenfr);

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

    // Sort out the fixed data for the service parameter editor

    $alldata->coaches = json_encode(\wc_railticket\CoachManager::get_all_coachset_data());
    $upterm = $bookable->timetable->get_terminal('up');
    $downterm = $bookable->timetable->get_terminal('down');
    $alldata->dep_times_up = json_encode($bookable->timetable->get_up_deps($downterm, true));
    $alldata->dep_times_down = json_encode($bookable->timetable->get_down_deps($upterm, true));
    $alldata->specials = json_encode($bookable->get_specials(true));
    $alldata->discounts = array();

    $de = $bookable->get_discount_exclude();
    if (count($de) == 0) {
        $d = new \stdclass();
        $d->name = "None";
        $alldata->discounts[] = $d;
    } else {
        asort($de);
        foreach ($de as $discount) {
            $alldata->discounts[] = \wc_railticket\DiscountType::get_discount_type($discount, true);
        }
    }

    echo file_get_contents(dirname(__FILE__).'/templates/serviceparams.html');

    $template = $rtmustache->loadTemplate('bookableday');
    echo $template->render($alldata);
}

function railticket_get_all_pricerevisions($chosenfr) {
    $allfares = \wc_railticket\FareCalculator::get_all_revisions();
    $ret = array();
    foreach ($allfares as $fare) {
        $f = new stdclass();
        $f->name = $fare->get_name();
        $f->value = $fare->get_revision();
        if ($f->value == $chosenfr) {
            $f->selected = 'selected';
        } else {
            $f->selected = '';
        }
        $ret[] = $f;
    }
    return $ret;
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
    $ndata->minprice = sanitize_text_field($_REQUEST['minprice']);

    // Make sure the json is crunched here for efficieny.
    $composition = json_decode(stripslashes($_REQUEST['composition']));
    $ndata->composition = json_encode($composition);
    $bookable->update_bookable($ndata);

    $p = explode('-', $bkdate);

    if (railticket_get_cbval('compdefault')) {
        $tts = json_decode(get_option('wc_product_railticket_defaultcoaches'));
        $composition->copy = false;
        $timetable = $bookable->timetable->get_key_name();
        $tts->$timetable = $composition;
        update_option('wc_product_railticket_defaultcoaches', json_encode($tts, JSON_PRETTY_PRINT));
    }
    wp_redirect(site_url().'/wp-admin/admin.php?page=railticket-bookable-days&month='.$p[1].'&year='.$p[0].'&action=filterbookable');
}

function railticket_addbookableday() {
    $date = sanitize_text_field($_REQUEST['date']);
    $ttid = sanitize_text_field($_REQUEST['timetable']);
    // Not using this for now...
    //$ttr = sanitize_text_field($_REQUEST['ttrevision']);

    \wc_railticket\BookableDay::create_timetable_date($date, $ttid);
    $railticket_timezone = new \DateTimeZone(get_option('timezone_string'));
    $d = DateTime::createFromFormat('Y-m-d', $date, $railticket_timezone);
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
    $railticket_timezone = new \DateTimeZone(get_option('timezone_string'));
    $d = DateTime::createFromFormat('Y-m-d', $date, $railticket_timezone);
    railticket_show_cal_selector();
    railticket_showcalendaredit(intval($d->format("Y")), intval($d->format("n")));
}

function railticket_summary_selector() {

    $railticket_timezone = new \DateTimeZone(get_option('timezone_string'));
    $today = new \DateTime();
    $today->setTimezone($railticket_timezone);
    $today->setTime(0,0,0);

    if (array_key_exists('dateofjourney', $_REQUEST)) {
        if (current_user_can('admin_tickets')) {
            $dateofjourney = $_REQUEST['dateofjourney'];
        } else {
            $dateofjourney = $today->format('Y-m-d');
        }
        $parts = explode('-', $dateofjourney);
        $chosenyear = $parts[0];
        $chosenmonth = $parts[1];
        $chosenday =  $parts[2];
    } else {
        if (current_user_can('admin_tickets')) {
            if (array_key_exists('year', $_REQUEST)) {
                $chosenyear = $_REQUEST['year'];
            } else {
                $chosenyear = intval(date_i18n("Y"));
            }
        } else {
            $chosenyear = $today->format('Y');
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
        $date->setTimezone($railticket_timezone);
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

    railticket_show_bookings_summary($dateofjourney, $today->format('Y-m-d'));
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
    $showall = false;
    $allstns = 1;
    $allstnsmes = "Show All Stations";
    if (array_key_exists('allstns', $_REQUEST) && $_REQUEST['allstns'] == 1) {
        $allstns = 0;
        $allstnsmes = "Show Principal Stations Only";
        $showall = true;
    }


    ?><h1>Seat/Bay usage summary for <?php echo $timetable->get_date(true); ?></h1>
    <div class='railticket_editdate'>
    <form method='post' action='<?php echo railticket_get_page_url(); ?>'>
        <input type='hidden' name='action' value='viewseatsummary' />
        <input type='hidden' name='dateofjourney' value='<?php echo $dateofjourney;?>' />
        <input type='hidden' name='allstns' value='<?php echo $allstns;?>' />
        <input type='submit' value='<?php echo $allstnsmes;?>' style='max-width:550px;' />
    </form>
    </div>
    <?php

    $stations = $timetable->get_stations();

    foreach ($stations as $station) {
        if ($showall == false && !$station->is_principal()) {
            continue;
        }
        railticket_show_station_summary($dateofjourney, $station, $timetable, 'down', true);
        railticket_show_station_summary($dateofjourney, $station, $timetable, 'up', true);
    }
    ?>
    <form action='<?php echo railticket_get_page_url() ?>' method='post'>
        <input type='hidden' name='action' value='filterbookings' />
        <input type='hidden' name='dateofjourney' value='<?php echo $dateofjourney; ?>' />
        <input type='submit' name='submit' value='Back to Services' style='font-size:x-large'/>
    </form>
    <?php
}


function railticket_show_bookings_summary($dateofjourney, $today) {
    global $rtmustache;
    $bookableday = \wc_railticket\BookableDay::get_bookable_day($dateofjourney);

    // If the override code is empty, this day has a timetable, but hasn't been initialised.
    if (!$bookableday) {
        echo "<h3>Booking data not initiailised for this day - please make this day bookable.</h3>";
        return;
    }
    echo "<h1>Summary for ".$bookableday->get_date(true);
    if ($dateofjourney != $today) {
        echo " <span style='color:red'>***".__('Not today', 'wc_railticket')."***</span>";
    }
    echo "</h1>";

    echo "<h2 style='font-size:x-large;line-height:120%;'>Booking override code:<span style='color:red'>".$bookableday->get_override()."</span></h2>";

    $showall = false;
    $allstns = 1;
    $allstnsalt = 0;
    $allstnsmes = "Show All Stations";
    if (array_key_exists('allstns', $_REQUEST) && $_REQUEST['allstns'] == 1) {
        $allstns = 0;
        $allstnsalt = 1;
        $allstnsmes = "Show Principal Stations Only";
        $showall = true;
    }

    ?>
    <div class='railticket_editdate'>
    <form method='post' action='<?php echo railticket_get_page_url(); ?>'>
        <input type='hidden' name='action' value='filterbookings' />
        <input type='hidden' name='dateofjourney' value='<?php echo $dateofjourney;?>' />
        <input type='hidden' name='allstns' value='<?php echo $allstns;?>' />
        <input type='submit' value='<?php echo $allstnsmes;?>' style='max-width:550px;' />
    </form>
    </div>
    <?php

    $stations = $bookableday->timetable->get_stations();
    foreach ($stations as $station) {
        if ($showall == false && !$station->is_principal()) {
            continue;
        }
        railticket_show_station_summary($dateofjourney, $station, $bookableday->timetable, 'down');
    }
    echo "<hr />";
    $stations = array_reverse($stations);
    foreach ($stations as $station) {
        if ($showall == false && !$station->is_principal()) {
            continue;
        }
        railticket_show_station_summary($dateofjourney, $station, $bookableday->timetable, 'up');
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
    <table style='width:100%'><tr>
    <td><form method='post' action='<?php echo railticket_get_page_url() ?>'>
        <input type='hidden' name='action' value='viewwaybill' />
        <input type='hidden' name='dateofjourney' value='<?php echo $dateofjourney; ?>' />
        <input type='submit' name='submit' value='Way Bill' style='width:100%' />
    </form></td>
    <td><form method='post' action='<?php echo railticket_get_page_url() ?>'>
        <input type='hidden' name='action' value='viewordersummary' />
        <input type='hidden' name='dateofjourney' value='<?php echo $dateofjourney; ?>' />
        <input type='submit' name='submit' value='Order Summary'  style='width:100%'/>
    </form></td>
    </tr>
    <?php
    if (current_user_can('admin_tickets')) {
    ?>
    <tr>
    <td><form method='get' action='<?php echo admin_url('admin-post.php') ?>'>
        <input type='hidden' name='action' value='waybill.csv' />
        <input type='hidden' name='dateofjourney' value='<?php echo $dateofjourney; ?>' />
        <input type='submit' name='submit' value='Way Bill Spreadsheet' style='width:100%;font-size:large;' />
    </form></td>
    <td><form method='get' action='<?php echo admin_url('admin-post.php') ?>'>
        <input type='hidden' name='action' value='ordersummary.csv' />
        <input type='hidden' name='dateofjourney' value='<?php echo $dateofjourney; ?>' />
        <input type='submit' name='submit' value='Order Summary Spreadsheet' style='width:100%;font-size:large;' />
    </form></td>
    </tr>
    <?php }?>
    <tr>
    <td colspan='2'><form method='post' action='<?php echo railticket_get_page_url() ?>'>
        <input type='hidden' name='allstns' value='<?php echo $allstnsalt;?>' />
        <input type='hidden' name='action' value='viewseatsummary' />
        <input type='hidden' name='dateofjourney' value='<?php echo $dateofjourney; ?>' />
        <input type='submit' name='submit' value='Seat/Bay Usage Summary' style='width:100%' />
    </form></td>
    </tr></table>
    <?php
    if (current_user_can('admin_tickets')) {
        $template = $rtmustache->loadTemplate('rebooking-button');
        $alldata = new \stdclass();
        $alldata->dateofjourney = $dateofjourney;
        $alldata->action = 'rebookall';
        echo $template->render($alldata);
    }
    ?>
    </div>
    <?php
}

function railticket_show_station_summary($dateofjourney, \wc_railticket\Station $station, \wc_railticket\Timetable $timetable,
    $direction, $showseats = false) {
    if ($showseats) {
        $h = "<br /><h1>";
        $hs = "</h1>";
    } else {
        $h = "<h3>";
        $hs = "</h3>";
    }

    $method = 'get_'.$direction.'_deps';

    $deps = $timetable->$method($station, true);
    if (count($deps) > 0) {
        echo "\n".$h.$station->get_name()." - ".ucfirst($direction)." Departures ".$hs."\n<span class='railticket_inlinedeplist'>";
        railticket_show_dep_buttons($dateofjourney, $station, $timetable, $deps, $direction, $showseats);
        echo "</span>";
    }
}

function railticket_show_dep_buttons($dateofjourney, \wc_railticket\Station $station, $timetable, $alltimes, $direction, $showseats = false) {
    $key = $direction.'_deps';

    if ($showseats) {
        foreach ($alltimes as $t) {
            railticket_show_departure($dateofjourney, $station, $direction, $t, false, true);
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


function railticket_show_departure($dateofjourney, \wc_railticket\Station $station, $direction, $deptime, $destination = false, $summaryonly = false) {
    global $rtmustache;

    $bookableday = \wc_railticket\BookableDay::get_bookable_day($dateofjourney);
    $finaldestination = $bookableday->timetable->get_terminal($direction);
    if ($destination === false) {
        $destination = $finaldestination;
    }
    // If this is being called directly from a button click this will be a string
    if (is_string($deptime)) {
        $trainservice = new \wc_railticket\TrainService($bookableday, $station, $deptime, $finaldestination);
        if ($trainservice->special == false) {
            $dt = new \stdclass();
            $dt->key = $deptime;
            $railticket_timezone = new \DateTimeZone(get_option('timezone_string'));
            $depname = \DateTime::createFromFormat("H.i", $deptime, $railticket_timezone);
            $dt->formatted = railticket_timefunc(get_option('wc_railticket_time_format'), $depname->getTimeStamp());
            $deptime = $dt;
        } 
    } else {
        $trainservice = new \wc_railticket\TrainService($bookableday, $station, $deptime->key, $finaldestination);
    }

    // Formatting for specials
    if ($trainservice->special) {
        $dt = new \stdclass();
        $dt->key = $deptime;
        $dt->formatted = $trainservice->special->get_name();
        $deptime = $dt;
    }

    $bookings = array($station->get_stnid() => $trainservice->get_bookings());

    $allstns = array($station->get_stnid() => $station);
    $nextts = $trainservice->get_next_trainservice();
    $baydests = array();
    $nextstn = false;
    while($nextts !== false) {
        $nextstn = $nextts->get_from_station();
        $bookings[$nextstn->get_stnid()] = $nextts->get_bookings(); 
        $allstns[$nextstn->get_stnid()] = $nextstn;

        $bd = new \stdclass();
        $bd->name = $nextstn->get_name();
        $bd->stnid = $nextstn->get_stnid();
        if ($bd->stnid == $destination->get_stnid()) {
            $bd->selected = 'selected';
        }
        $baydests[] = $bd;
        $nextts = $nextts->get_next_trainservice();
    }

    // Add the final destination in
    $bd = new \stdclass();
    $bd->name = $finaldestination->get_name();
    $bd->stnid = $finaldestination->get_stnid();
    if ($bd->stnid == $destination->get_stnid()) {
        $bd->selected = 'selected';
    }
    $baydests[] = $bd;

    $seats = 0;
    foreach ($bookings[$station->get_stnid()] as $booking) {
        $seats += $booking->get_seats();
    }

    // Fudge so we don't have to create another train service object....
    $trainservice->set_to_station($destination);
    $basebays = $trainservice->get_inventory(true, true);
    $capused = $trainservice->get_inventory(false, false);
    $capcollected = $trainservice->get_inventory(false, true, true);
    $trainservice->set_to_station($finaldestination);

   if ($summaryonly) {
        echo "<table><tr><td>";
        railticket_show_dep_button($dateofjourney, $station, $direction, $deptime);
        echo "</td><td class='railticket_shortsummary'>Seats Used:".$seats."&nbsp&nbsp;</td><td class='railticket_shortsummary'>Seats Available:".$capused->totalseats."</td></tr></table>";
   } else {
        echo "<div class='railticket_editdate'><h2>Service summary</h2><table class='railticket_admintable' border='1'>".
            "<tr><th>Timetable</th><th>".$bookableday->timetable->get_name()."</th></tr>".
            "<tr><th>Station</th><th>".$station->get_name()."</th></tr>".
            "<tr><th>Final destination</th><th>".$finaldestination->get_name()."</th></tr>".
            "<tr><th>Date</td><th>".$dateofjourney."</th></tr>".
            "<tr><th>Time</td><th>".$deptime->formatted."</th></tr>".
            "<tr><th>Direction</th><th>".$direction."</th></tr>".
            "<tr><th>Total orders here</th><th>".count($bookings[$station->get_stnid()])."</th></tr>".
            "<tr><th>Wheelchair requests</th><th>".$trainservice->count_priority_requested()."</th></tr>".
            "<tr><th>Seats available</th><th>".$capused->totalseats;

        if ($capused->totalseats != $capused->totalseatsmax) {
            echo " (".$capused->totalseatsmax.")";
        }

        echo "</th></tr><tr><th>Seating Reserve Enabled</th><th>";

        if ($bookableday->has_reserve()) {
            echo 'Yes';
        } else {
            echo 'No';
        }

        echo "</th></tr></table>".
            "<h2>Passengers Summary</h2>".
            "<table class='railticket_admintable' border='1'>".
            "<tr><th>Total boarding here</th><th>".$seats."</th></tr>";

        $travellers = array();
        foreach ($bookings[$station->get_stnid()] as $booking) {
            $bo = \wc_railticket\BookingOrder::get_booking_order($booking->get_order_id());
            $tr = $bo->get_travellers();
            foreach ($tr as $tk => $tt) {
                $tk = explode('/', $tk)[0];
                if (!array_key_exists($tk, $travellers)) {
                    $travellers[$tk] = 0;
                }

                $travellers[$tk] += $tt;
            }
        }

        foreach ($travellers as $tk => $tt) {
            if ($tt > 0) {
                echo "<tr><th>".\wc_railticket\FareCalculator::get_traveller($tk)->name."</th><th style='padding-left:10px;padding-right:10px;'>".$tt."</th></tr>";
            }
        }

        echo "</div><br />";
    }

    if ($trainservice->special) {
        $raction = 'showspecial';
    } else {
        $raction = 'showdep';
    }

    $budata = new \stdclass();
    $budata->raction = $raction;
    $budata->dateofjourney = $dateofjourney;
    $budata->station = $station->get_stnid();
    $budata->revision = $station->get_revision();
    $budata->direction = $direction;
    $budata->deptime = $deptime->key;
    if ($finaldestination->get_stnid() != $destination->get_stnid()) {
        $budata->destwarn = true;
    }

    $budata->from = $station->get_name();
    $budata->baydests = $baydests;
    $budata->atype = $bookableday->get_allocation_type(true);
    $budata->bays = array();
    foreach ($basebays as $bay => $space) {
        // Ignore "max" parameters here. Special case...
        if (strpos($bay, '/max') !== false) {
            continue;
        }
        $budatai = new \stdclass();
        $budatai->bayd = \wc_railticket\CoachManager::format_bay($bay);
        // Do we have a max parameter?
        if (array_key_exists($bay.'/max', $basebays)) {
            $budatai->custtotal = $space;
            $budatai->total = $basebays[$bay.'/max'];
            $budatai->used = ($space-$capused->bays[$bay])-$capused->leaveempty[$bay];
            $budatai->collected = $space-$capcollected->bays[$bay];
            $budatai->avcust = $capused->bays[$bay];
            $budatai->available = $capused->bays[$bay.'/max'];
            $budatai->leaveempty = $capused->leaveempty[$bay];
        } else {
            $budatai->total = $space;
            $budatai->maxtotal = false;
            $budatai->used = ($space-$capused->bays[$bay])-$capused->leaveempty[$bay];
            $budatai->collected = $space-$capcollected->bays[$bay];
            $budatai->available = $capused->bays[$bay];
            $budatai->avmax = false;
            $budatai->leaveempty = $capused->leaveempty[$bay];
        }
        $budata->bays[] = $budatai;
    }

    $butemplate = $rtmustache->loadTemplate('bayusage');
    echo $butemplate->render($budata);

    if ($bookableday->has_reserve()) {
        echo "<p><strong>Seating reserve is enabled, the 'Used Here' figure has been adjusted to account for this.</strong></p>";
    }

    if ($bookableday->get_allocation_type(false) == 'seat') {
        echo "<p>Note: The figure in brackets represents the capacity advertised to customers if it is less than the real capacity.<p>";
    }

    echo "<p>Coaches: ".$trainservice->get_coachset(true)."<br />".
        "Reserve: ".$trainservice->get_reserve(true)."</p>";
    if ($summaryonly) {
        echo "<hr />";
        return;
    }

    $collectedbtn = true;
    foreach ($bookings as $stnid => $bks) {
        railticket_show_bookings_table($bks, $allstns[$stnid], $collectedbtn);
        $collectedbtn = false;
    }

    ?>
    </table>

    <br />
    <div class='railticket_editdate' style='max-width:550px;margin-left:0px;margin-right:auto;'>
    <form action='<?php echo railticket_get_page_url() ?>' method='post'>
        <input type='hidden' name='action' value='<?php echo $raction; ?>' />
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
    <?php
    if (current_user_can('admin_tickets')) {
        $template = $rtmustache->loadTemplate('rebooking-button');
        $alldata = new \stdclass();
        $alldata->dateofjourney = $dateofjourney;
        $alldata->action = 'rebookservice';
        $alldata->from = $station->get_stnid();
        $alldata->ttrevision = $station->get_revision();
        $alldata->direction = $direction;
        $alldata->dep = $deptime->key;
        echo $template->render($alldata);
    }
    ?>
    </div>
    <?php
}

function railticket_show_bookings_table($bookings, \wc_railticket\Station $station, $collectedbtn) {
    global $rtmustache;

    if (count($bookings) == 0) {
        echo "<br /><h1>Bookings from ".$station->get_name().": None</h1>";
        return;
    }

    $stndepdata = new \stdclass();
    $stndepdata->bookings = array();
    $stndepdata->station = $station->get_name();
    foreach ($bookings as $booking) {
        $dbk = new \stdclass();
        if (strlen($booking->in_cart()) > 0) {
            $dbk->incart = "In Cart";
            $dbk->orderid = false;
            $bookingorder = false;
        } else {
            $dbk->incart = false;
            $dbk->orderid = $booking->get_order_id();
            $bookingorder = \wc_railticket\BookingOrder::get_booking_order($dbk->orderid);
        }
        $dbk->ordername = $booking->get_order_name();
        $dbk->to = $booking->get_to_station()->get_name();
        $dbk->seats = $booking->get_seats();
        $dbk->priority = $booking->get_priority(true);
        $dbk->bays = $booking->get_bays(true);

        if ($bookingorder) {
            $oitems = $bookingorder->other_items();
            if ($oitems && count($oitems) > 0) {
                $dbk->otheritems = __('Yes', 'wc_railticket');
            } else {
                $dbk->otheritems = __('No', 'wc_railticket');
            }

            if ($bookingorder->get_discount_type()) {
                $dbk->hasdiscount = __('Yes', 'wc_railticket');
                if ($booking->is_collected()) {
                    $dbk->iscollectedstr = __('Yes', 'wc_railticket');
                    $dbk->collected = true;
                } else {
                    $dbk->iscollectedstr = __('No', 'wc_railticket');
                }
            } else {
                $dbk->hasdiscount = __('No', 'wc_railticket');
                if ($collectedbtn) {
                    $bkc = new \stdclass();
                    if ($booking->is_collected()) {
                        $bkc->actionstr = __('Yes', 'wc_railticket');
                        $bkc->action = 'cancelcollected';
                        $dbk->collected = true;
                    } else {
                        $bkc->actionstr = __('No', 'wc_railticket');
                        $bkc->action = 'collected';
                    }
                    $bkc->returnto = 'departure';
                    $bkc->bookingid = $booking->get_id();
                    $dbk->iscollected = array($bkc);
                } else {
                    if ($booking->is_collected()) {
                        $dbk->iscollectedstr = __('Yes', 'wc_railticket');
                        $dbk->collected = true;
                    } else {
                        $dbk->iscollectedstr = __('No', 'wc_railticket');
                    }
                }
            }
        } else {
            $dbk->otheritems = '-';
            $dbk->hasdiscount = '-';
            $dbk->iscollectedstr = '-';
        }
        $stndepdata->bookings[] = $dbk;
    }

    $dtemplate = $rtmustache->loadTemplate('depbookings');
    echo $dtemplate->render($stndepdata);
}

function railticket_mark_ticket($val) {
    $id = sanitize_text_field($_POST['bookingid']);
    \wc_railticket\Booking::set_collected($id, $val);
    if ($_REQUEST['returnto'] == 'departure') {
        $orderid = sanitize_text_field($_POST['orderid']);
        $bookingorder = \wc_railticket\BookingOrder::get_booking_order($orderid);
        foreach ($bookingorder->get_bookings() as $booking) {
            if ($booking->get_id() == $id) {
                railticket_show_departure($booking->get_date(), $booking->get_from_station(), $booking->get_direction(), $booking->get_dep_time());
                break;
            }
        }
    } else {
        railticket_show_order();
    }
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
    global $wpdb;

    $bookingorder = \wc_railticket\BookingOrder::get_booking_order($orderid);
    if (!$bookingorder) {
        // Do we have a broken order with partial data?
        $item = $wpdb->get_var("SELECT COUNT(items.order_id) FROM wp_woocommerce_order_items items ".
            "INNER JOIN wp_woocommerce_order_itemmeta meta ON ".
            "items.order_item_id = meta.order_item_id AND meta.meta_key = '_product_id' AND ".
            "meta.meta_value = ".get_option('wc_product_railticket_woocommerce_product')." WHERE items.order_id = ".$orderid);

        if ($item == 0) {
            echo "<p style='font-size:large;color:red;'>Invalid order number '".$orderid."' or no tickets were purchased with this order.</p>";
        } else {
            echo "<p style='font-size:large;color:red;'>Order '".$orderid."' is missing part of it's data. Please report this to the manangement.</p>";
        }
        railticket_summary_selector();
        return;
    }
    railticket_show_bookingorder($bookingorder);
}

function railticket_show_bookingorder($bookingorder) {
    global $rtmustache;
    $alldata = railticket_get_booking_order_data($bookingorder);
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

function railticket_get_booking_order_data(\wc_railticket\BookingOrder $bookingorder) {
    $discount = $bookingorder->get_discount_type();

    $orderdata = array();
    $orderdata[] = array('item' => __('Order ID', 'wc_railticket'), 'value' => $bookingorder->get_order_id());
    $orderdata[] = array('item' => __('Name', 'wc_railticket'), 'value' => $bookingorder->get_customer_name());
    $orderdata[] = array('item' => __('Postcode', 'wc_railticket'), 'value' => $bookingorder->get_postcode());
    $orderdata[] = array('item' => __('Paid', 'wc_railticket'), 'value' => $bookingorder->is_paid(true));
    $orderdata[] = array('item' => __('Price', 'wc_railticket'), 'value' => $bookingorder->get_price(true));
    if ($discount) {
        $orderdata[] = array('item' => __('Discount Type Applied', 'wc_railticket'), 'value' => $discount->get_name(), 'style' => 'color:blue');
        if ($discount->show_notes()) {
            $orderdata[] = array('item' => $discount->get_note_type(),
                'value' => '<div style="text-align:center;">'.
                    '<span style="font-weight:bold;color:blue">Attention! Check this with the customer:<br /> '.
                    '<span style="font-weight:bold;color:red;font-size:x-large;">'.$bookingorder->get_discount_note().'</span></div>');
        } else {
            $orderdata[] = array('item' => __('Discount Validation', 'wc_railticket'), 'value' => __('Not applicable', 'wc_railticket'));
        }
    }
    $orderdata[] = array('item' => __('Price Breakdown', 'wc_railticket'), 'value' => $bookingorder->get_ticket_prices(true));
    $orderdata[] = array('item' => __('Supplement', 'wc_railticket'), 'value' => $bookingorder->get_supplement(true));
    $orderdata[] = array('item' => __('Tickets'), 'value' => $bookingorder->get_tickets(true));
    $orderdata[] = array('item' => __('Date'), 'value' => $bookingorder->get_date(true, true));
    $orderdata[] = array('item' => __('Journey Type'), 'value' => $bookingorder->get_journeytype(true));
    $orderdata[] = array('item' => __('Seats'), 'value' => $bookingorder->get_seats());
    $orderdata[] = array('item' => __('Wheelchair space requested'), 'value' => $bookingorder->priority_requested(true));
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
        'orderid' => $bookingorder->get_order_id(),
        'actionurl' => railticket_get_page_url(),
        'buttonstyle' => 'width:100%;',
        'otheritems' => $bookingorder->other_items()
    );

    if ($alldata['otheritems'] == false || count($alldata['otheritems']) == 0) {
        $alldata['otheritemsstyle'] = 'display:none';
    }

    return $alldata;
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
        $bk['returnto'] = 'order';
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
        echo "<p style='font-size:large;color:red;'>Invalid order number or no tickets were purchased with this order.</p>";
        railticket_summary_selector();
        return;
    }

    wp_register_script('railticket_script_mustache', plugins_url('wc-product-railticket/js/mustache.min.js'));
    wp_register_script('railticket_script_builder', plugins_url('wc-product-railticket/js/ticketeditor.js'));
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
    $bkdata->seats = $bookingorder->get_seats();
    $tripdata = array();
    $count = 1;
    $allstns = \wc_railticket\Station::get_stations($bookingorder->bookableday->timetable->get_revision());
    foreach ($bookingorder->get_bookings() as $booking) {
        $from = $booking->get_from_station();
        $to = $booking->get_to_station();

        $data = new \stdclass();
        $trip = new stdclass();
        $data->legnum = $count;
        $trip->num = $count;

        $trip->from = $from->get_name();
        $trip->to = $to->get_name();
        $trip->dep = $booking->get_dep_time(true);
        $trip->seats = $booking->get_bays(true);
        $tripdata[] = $trip;

        $data->fromstns = railticket_get_stnselect($from, $allstns);
        $data->tostns = railticket_get_stnselect($to, $allstns);
        $data->deps = railticket_get_depselect($booking->bookableday, $from, $to, $booking->get_dep_time(), $bookingorder->get_bookings());
        $data->bays = $booking->get_bays(true, true);
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
        'tripdata' => $tripdata,
        'notes' => $bookingorder->get_notes()
    );
    $template = $rtmustache->loadTemplate('editorder');
    echo $template->render($alldata);
    echo file_get_contents(dirname(__FILE__).'/templates/edit-templates.html');
}

function railticket_save_order_note() {
    $orderid = railticket_getpostfield('orderid');
    $bookingorder = \wc_railticket\BookingOrder::get_booking_order($orderid);
    if (!$bookingorder) {
        echo "Invalid order ID ".$orderid;
        return;
    }

    $notes = railticket_getpostfield('notes');
    $bookingorder->set_notes($notes);

    railticket_show_bookingorder($bookingorder);
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
        $data->legnum = $count;
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
        if ($bk->get_allocation_type() == 'seat') {
            $deps[$i]->seats = $capused->totalseatsmax." (".$capused->totalseats.")";
        } else {
            $deps[$i]->seats = $capused->totalseats;
        }
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

        // TODO Will bay allocation fail if one booking is moving onto the train the other booking is on before that second booking
        // is moved off? I'm not excluding any bookings here.

        if (!$nfrom->matches($ofrom) || !$nto->matches($oto) || $bookings[$i]->get_dep_time() != $legs[$i]->dep) {
            $ts = new \wc_railticket\TrainService($bookingorder->bookableday, $nfrom, $legs[$i]->dep, $nto);
            $capdata = $ts->get_capacity(false, $bookings[$i]->get_seats(), $bookings[$i]->get_priority());
            $bookings[$i]->set_dep($nfrom, $nto, $legs[$i]->dep, $capdata->bays);
        }
    }

    if ($notify) {
        $bookingorder->notify();
    }

    $edit->message = 'Order update saved';
    return $edit;
}

function railticket_overridebays() {
    $edit = new \stdclass();
    if (!current_user_can('admin_tickets')) {
        $edit->message = 'You do not have permission to do this.';
        return $edit;
    }

    $orderid = railticket_getpostfield('orderid');
    $notify = railticket_gettfpostfield('notify');
    $bookingorder = \wc_railticket\BookingOrder::get_booking_order($orderid);
    $legbays = json_decode(stripslashes($_REQUEST['legbays']));
    $bookings = $bookingorder->get_bookings();

    for ($i=0; $i < count($legbays); $i++) {
        $bookings[$i]->update_bays($legbays[$i]);
    }

    if ($notify) {
        $bookingorder->notify();
    }

    $seats = railticket_getpostfield('seats');
    if ($seats != $bookingorder->get_seats()) {
        for ($i=0; $i < count($legbays); $i++) {
            $bookings[$i]->update_seats($seats);
        }
    }

    $edit->message = 'Order bay update saved';
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

function railticket_fares() {
    global $rtmustache;
    wp_register_style('railticket_style', plugins_url('wc-product-railticket/ticketbuilder.css'));
    wp_enqueue_style('railticket_style');

    $pricerevisionid = railticket_getpostfield('pricerevision');
    if ($pricerevisionid == false) {
        $pricerevisionid = \wc_railticket\FareCalculator::get_last_revision_id();
    }
    $stnchoice = railticket_getpostfield('stn');
    $showdisabled = railticket_gettfpostfield('showdisabled');

    $farecalc = \wc_railticket\FareCalculator::get_fares($pricerevisionid); 
    $timetable = $farecalc->get_last_timetable();
    $alldata = new \stdclass();
    $alldata->stations = $timetable->get_stations(true);
    if ($stnchoice == false) {
        $stnchoice = reset($alldata->stations)->stnid;
    }

    if (array_key_exists('action', $_REQUEST)) {
        switch ($_REQUEST['action']) {
            case 'updatefare':
                railticket_updatefares($stnchoice, $farecalc);
                break;
            case 'addfare':
                railticket_addfare($stnchoice, $farecalc);
                break;
            case 'deletefare':
                $id = railticket_getpostfield('id');
                $farecalc->delete_fare($id);
                break;
        }
    }


    $alldata->actionurl = railticket_get_page_url();
    $alldata->farerevisions = railticket_get_all_pricerevisions($pricerevisionid);
    $alldata->pricerevision = $pricerevisionid;
    $alldata->showdisabled = $showdisabled;
    if ($showdisabled) {
        $alldata->showdisabledcheck = 'checked';
    }
    $alldata->fromstnid = $stnchoice;

    $stnchoice = \wc_railticket\Station::get_station($stnchoice, $timetable->get_revision());
    foreach ($alldata->stations as $id => $stn) {
        if ($stn->stnid == $stnchoice->get_stnid()) {
            $stn->selected = 'selected';
        }
    }
    $alldata->stationname = $stnchoice->get_name();
    $fares = $farecalc->get_tickets_from($stnchoice, $showdisabled);

    $alldata->fares = array_values($fares);
    $alldata->ids = array(); 
    foreach ($fares as $fare) {
        if ($fare->stationone == $stnchoice->get_stnid()) {
            $stnto = \wc_railticket\Station::get_station($fare->stationtwo, $timetable->get_revision());
        } else {
            $stnto = \wc_railticket\Station::get_station($fare->stationone, $timetable->get_revision());
        }
        $fare->tostation = $stnto->get_name();

        if ($fare->disabled) {
            $fare->disabled = 'checked';
        }

        if ($fare->special) {
            $fare->special = 'Y';
        } else {
            $fare->special = 'N';
        }

        if ($fare->guardonly) {
            $fare->guardonly = 'Y';
        } else {
            $fare->guardonly = 'N';
        }

        $fare->price = number_format($fare->price, 2);
        $fare->localprice = number_format($fare->localprice, 2);
        $alldata->ids[] = $fare->id;
    }
    $alldata->ids = implode(',', $alldata->ids);
    $alldata->tickettypes = array_values($farecalc->get_all_ticket_types());
    $alldata->journeytypes = $farecalc->get_all_journey_types();

    $template = $rtmustache->loadTemplate('fare_selector');
    echo $template->render($alldata);
}

function railticket_updatefares($stnchoice, $farecalc) {
    $ids = explode(',', railticket_getpostfield('ids'));

    foreach ($ids as $id) {
        $price = railticket_getpostfield('price_'.$id);
        $localprice = railticket_getpostfield('localprice_'.$id);
        $disabled = railticket_gettfpostfield('disabled_'.$id);
        $image = railticket_getpostfield('image_'.$id);

        $farecalc->update_fare($id, $price, $localprice, $disabled, $image);
    }
}

function railticket_addfare($stnchoice, $farecalc) {
    $stnfrom = railticket_getpostfield('n_stnfrom');
    $stnto = railticket_getpostfield('n_stnto');
    $tickettype = railticket_getpostfield('n_tickettype');
    $journeytype = railticket_getpostfield('n_journeytype');
    $price = railticket_getpostfield('n_price');
    $localprice = railticket_getpostfield('n_localprice');
    $disabled = railticket_gettfpostfield('n_disabled');
    $image = railticket_getpostfield('n_image');

    $s = $farecalc->add_fare($stnfrom, $stnto, $tickettype, $journeytype, $price, $localprice, $disabled, $image);
    if ($s) {
        return;
    }

    echo "<p style='color:red'>Error: A fare with this configuration already exists in this revision.</p>";
}

function railticket_travellers() {
    global $rtmustache;
    wp_register_style('railticket_style', plugins_url('wc-product-railticket/ticketbuilder.css'));
    wp_enqueue_style('railticket_style');

    if (array_key_exists('action', $_REQUEST)) {
        switch ($_REQUEST['action']) {
            case 'updatetra':
                railticket_update_travellers();
                break;
            case 'addtra':
                $code = railticket_getpostfield('code');
                $name = railticket_getpostfield('name');
                $description = railticket_getpostfield('description');
                $seats = railticket_getpostfield('seats');
                $guardonly = railticket_gettfpostfield('guardonly');
                $tkoption = railticket_gettfpostfield('guardonly');
                $res = \wc_railticket\FareCalculator::add_traveller($code, $name, $description, $seats, $guardonly, $tkoption);
                if (!$res) {
                    echo "<p style='color:red;font-weight:bold;'>".__("The code used must be unique", "wc_railticket")."</p>";
                }
                break;
            case 'deletetra':
                $id = railticket_getpostfield('id');
                \wc_railticket\FareCalculator::delete_traveller($id);
                wp_redirect(site_url().'/wp-admin/admin.php?page=railticket-travellers');
                break;
        }
    }

    $alldata = new \stdclass;
    $alldata->travellers = array_values(\wc_railticket\FareCalculator::get_all_travellers());
    $alldata->ids = array();
    foreach ($alldata->travellers as $tra) {
        if ($tra->guardonly) {
            $tra->guardonly = 'checked';
        } else {
            $tra->guardonly = '';
        }
        if ($tra->tkoption) {
            $tra->tkoption = 'checked';
        } else {
            $tra->tkoption = '';
        }

        $alldata->ids[] = $tra->id;
    }
    $alldata->ids = implode(',', $alldata->ids);
    $template = $rtmustache->loadTemplate('travellers');
    echo $template->render($alldata);
}

function railticket_update_travellers() {
    $ids = explode(',', railticket_getpostfield('ids'));

    foreach ($ids as $id) {
        $code = railticket_getpostfield('code_'.$id);
        $name = railticket_getpostfield('name_'.$id);
        $description = railticket_getpostfield('description_'.$id);
        $seats = railticket_getpostfield('seats_'.$id);
        $guardonly = railticket_gettfpostfield('guardonly_'.$id);
        $tkoption = railticket_gettfpostfield('tkoption_'.$id);
        \wc_railticket\FareCalculator::update_traveller($id, $name, $description, $seats, $guardonly, $tkoption);
    }
}

function railticket_tickets() {
    global $rtmustache;
    wp_register_style('railticket_style', plugins_url('wc-product-railticket/ticketbuilder.css'));
    wp_enqueue_style('railticket_style');

    $showhidden = railticket_gettfpostfield('showhidden');

    if (array_key_exists('action', $_REQUEST)) {
        switch ($_REQUEST['action']) {
            case 'updatetickets':
                railticket_update_tickettypes();
                break;
            case 'addticket':
                $code = railticket_getpostfield('code');
                $name = railticket_getpostfield('name');
                $description = railticket_getpostfield('description');
                $special = railticket_gettfpostfield('special');
                $guardonly = railticket_gettfpostfield('guardonly');
                $res = \wc_railticket\FareCalculator::add_ticket_type($code, $name, $description, $special, $guardonly);
                if (!$res) {
                    echo "<p style='color:red;font-weight:bold;'>".__("The code used must be unique", "wc_railticket")."</p>";
                }
                break;
            case 'deleteticket':
                $id = railticket_getpostfield('id');
                \wc_railticket\FareCalculator::delete_ticket_type($id);
                wp_redirect(site_url().'/wp-admin/admin.php?page=railticket-tickets');
                break;
            case 'downticket':
                $id = railticket_getpostfield('id');
                \wc_railticket\FareCalculator::move_ticket($id, 1, !$showhidden);
                wp_redirect(site_url().'/wp-admin/admin.php?page=railticket-tickets');
                break;
            case 'upticket':
                $id = railticket_getpostfield('id');
                \wc_railticket\FareCalculator::move_ticket($id, -1, !$showhidden);
                wp_redirect(site_url().'/wp-admin/admin.php?page=railticket-tickets');
                break;
        }
    }

    $alldata = new \stdclass;
    $alldata->ids = array();
    $alldata->showhidden = $showhidden;
    if ($alldata->showhidden == true) {
        $alldata->showhiddencheck = 'checked';
    }

    $travellers = $alldata->travellers = array_values(\wc_railticket\FareCalculator::get_all_travellers());
    $alldata->tickets = array_values(\wc_railticket\FareCalculator::get_all_ticket_types($showhidden));
    $alltickets = \wc_railticket\FareCalculator::get_all_ticket_types(true);
    foreach ($alldata->tickets as $ticket) {
        $alldata->ids[] = $ticket->id;

        if ($ticket->hidden) {
            $ticket->hidden = 'checked';
        }
        if ($ticket->guardonly) {
            $ticket->guardonly = 'checked';
        }
        if ($ticket->special) {
            $ticket->special = 'checked';
        }

        $ticket->depends = json_decode($ticket->depends);
        $ticket->depselect = array();
        foreach ($alltickets as $tkt) {
            $isdep = in_array($tkt->code, $ticket->depends);
            if ((!$isdep && $tkt->hidden) || $tkt->code == $ticket->code || ($tkt->sequence > $ticket->sequence && !$isdep) ) {
                // This ticket is hidden and is not a dependency of the ticket we are displaying
                // Also hide the ticket in it's own dependency list!
                // Finally hide higher lower priority tickets. You must only be dependent on tickets with a higher priority.
                continue;
            }
            $dp = new \stdclass();
            $dp->code = $tkt->code;
            $dp->name = $tkt->code;
            if ($isdep) {
                $dp->selected = 'selected';
            }
            $ticket->depselect[] = $dp;
        }

        $ticket->composition = json_decode($ticket->composition);
        $ticket->tcomp = array();
        for ($i=0; $i< count($travellers); $i++) {
            $traveller = $travellers[$i];
            $tr = new \stdclass();
            $tr->id = $ticket->id;
            $tr->code1 = $traveller->code;
            $tr->name1 = $traveller->name;
            $code = $traveller->code;
            if (property_exists($ticket->composition, $code)) {
                $tr->value1 = $ticket->composition->$code;
            } else {
                $tr->value1 = 0;
            }

            $i++;
            if ($i >= count($travellers)) {
                $ticket->tcomp[] = $tr;
                break;
            }
            $traveller = $travellers[$i];
            $tr->code2 = $traveller->code;
            $tr->name2 = $traveller->name;
            $code = $traveller->code;
            if (property_exists($ticket->composition, $code)) {
                $tr->value2 = $ticket->composition->$code;
            } else {
                $tr->value2 = 0;
            }
            $ticket->tcomp[] = $tr;
        }
    }

    reset($alldata->tickets)->showup = 'display:none';
    end($alldata->tickets)->showdown = 'display:none';

    $alldata->ids = implode(',', $alldata->ids);
    $template = $rtmustache->loadTemplate('tickettypes');
    echo $template->render($alldata);
}

function railticket_update_tickettypes() {
    $ids = explode(',', railticket_getpostfield('ids'));
    $alltravellers = \wc_railticket\FareCalculator::get_all_travellers();

    foreach ($ids as $id) {
        $name = railticket_getpostfield('name_'.$id);
        $description = railticket_getpostfield('description_'.$id);
        $special = railticket_gettfpostfield('special_'.$id);
        $guardonly = railticket_gettfpostfield('guardonly_'.$id);
        $hidden = railticket_gettfpostfield('hidden_'.$id);
        $composition = new \stdclass();
        foreach ($alltravellers as $tr) {
            $code = $tr->code;
            $value = railticket_getpostfield('composition_'.$id."_".$code);

            if ($value == 0) {
                continue;
            }
            $composition->$code = $value;
        }

        $depends = array();
        if (array_key_exists('depends_'.$id, $_REQUEST)) {
            foreach ($_REQUEST['depends_'.$id] as $dep) {
                $depends[] = sanitize_text_field($dep);
            }
         }

        \wc_railticket\FareCalculator::update_ticket_type($id, $name, $description, $special, $guardonly, $hidden, $composition, $depends);
    }
}

function railticket_coach_types() {
    global $rtmustache;
    wp_register_style('railticket_style', plugins_url('wc-product-railticket/ticketbuilder.css'));
    wp_enqueue_style('railticket_style');
    wp_register_script('railticket_script_mustache', plugins_url('wc-product-railticket/js/mustache.min.js'));
    wp_register_script('railticket_script_ct', plugins_url('wc-product-railticket/js/coachtypes.js'));
    wp_enqueue_script('railticket_script_mustache');
    wp_enqueue_script('railticket_script_ct');

    $showhidden = railticket_gettfpostfield('showhidden');

    if (array_key_exists('action', $_REQUEST)) {
        switch ($_REQUEST['action']) {
            case 'updatecoach':
                railticket_update_coaches();
                break;
            case 'addcoach':
                $code = railticket_getpostfield('code');
                $name = railticket_getpostfield('name');
                $capacity = railticket_getpostfield('capacity');
                $maxcapacity = railticket_getpostfield('maxcapacity');
                $priority = railticket_getpostfield('priority');
                $image = railticket_getpostfield('image');
                $res = \wc_railticket\CoachManager::add_coach($code, $name, $capacity, $priority, $maxcapacity, $image);
                if (!$res) {
                    echo "<p style='color:red;font-weight:bold;'>".__("The code used must be unique", "wc_railticket")."</p>";
                }
                break;
            case 'deletecoach':
                $id = railticket_getpostfield('id');
                \wc_railticket\CoachManager::delete_coach($id);
                wp_redirect(site_url().'/wp-admin/admin.php?page=railticket-coach-types');
                break;
        }
    }

    $alldata = new \stdclass();
    $alldata->ids = array();
    $alldata->showhidden = $showhidden;
    if ($alldata->showhidden == true) {
        $alldata->showhiddencheck = 'checked';
    }

    $alldata->coaches = array_values(\wc_railticket\CoachManager::get_all_coachset_data($showhidden, true));
    foreach ($alldata->coaches as $c) {
        $alldata->ids[] = $c->id;
        if ($c->hidden) {
            $c->hidden = 'checked';
        }
    }

    $alldata->ids = implode(',', $alldata->ids);
    $template = $rtmustache->loadTemplate('coachtypes');
    echo file_get_contents(dirname(__FILE__).'/templates/coachtypes-templates.html');
    echo $template->render($alldata);
}

function railticket_update_coaches() {
    $ids = explode(',', railticket_getpostfield('ids'));

    foreach ($ids as $id) {
        $name = railticket_getpostfield('name_'.$id);
        $capacity = railticket_getpostfield('capacity_'.$id);
        $maxcapacity = railticket_getpostfield('maxcapacity_'.$id);
        $priority = railticket_getpostfield('priority_'.$id);
        $image = railticket_getpostfield('image_'.$id);
        $hidden = railticket_gettfpostfield('hidden_'.$id);
        $composition = stripslashes(railticket_getpostfield('composition_'.$id));

        \wc_railticket\CoachManager::update_coach($id, $name, $capacity, $maxcapacity, $priority, $image, $hidden, $composition);
    }
}

function railticket_discount_codes() {
    global $rtmustache;
    wp_register_style('railticket_style', plugins_url('wc-product-railticket/ticketbuilder.css'));
    wp_enqueue_style('railticket_style');

    if (array_key_exists('action', $_REQUEST)) {
        switch ($_REQUEST['action']) {
            case 'updatediscountcodes':
                railticket_update_discountcodes();
                break;
            case 'adddiscountcode':
                $shortname = railticket_getpostfield('shortname');
                $code = railticket_getpostfield('code');
                $start = railticket_getpostfield('start');
                $end = railticket_getpostfield('end');
                $single = railticket_gettfpostfield('single');
                $disabled = railticket_gettfpostfield('disabled');
                $notes = railticket_getpostfield('note');
                $check = \wc_railticket\Discount::add_discount_code($shortname, $code, $start, $end, $single, $disabled, $notes);
                if (!$check) {
                    echo "<span style='color:red'>Duplicate discount code '".$code."' detected, cannot add.</span><br />";
                }
                break;
            case 'deletediscountcode':
                $id = railticket_getpostfield('id');
                \wc_railticket\Discount::delete_discount_code($id);
                break;
            case 'cleandiscountcode':
                \wc_railticket\Discount::clean_discount_codes($id);
        }
    }

    $alldata = new \stdclass();
    $alldata->dtypes = array_values(\wc_railticket\DiscountType::get_all_discount_type_mindata());
    $alldata->dcodes = array_values(\wc_railticket\Discount::get_all_discount_data());
    $alldata->ids = array();
    foreach ($alldata->dcodes as $dcode) {
       $alldata->ids[] = $dcode->id;
       if ($dcode->single == 1) {
           $dcode->single = 'checked';
       } else {
           $dcode->single = '';
       }
       if ($dcode->disabled == 1) {
           $dcode->disabled = 'checked';
       } else {
           $dcode->disabled = '';
       }
    }

    $alldata->ids = implode(',', $alldata->ids);
    $template = $rtmustache->loadTemplate('discountcodes');
    echo $template->render($alldata);
}

function railticket_update_discountcodes() {
    $ids = explode(',', railticket_getpostfield('ids'));

    foreach ($ids as $id) {
        $code = railticket_getpostfield('code_'.$id);
        $start = railticket_getpostfield('start_'.$id);
        $end = railticket_getpostfield('end_'.$id);
        $single = railticket_gettfpostfield('single_'.$id);
        $disabled = railticket_gettfpostfield('disabled_'.$id);
        $notes = railticket_getpostfield('notes_'.$id);

        $check = \wc_railticket\Discount::update_discount_code($id, $code, $start, $end, $single, $disabled, $notes);
        if (!$check) {
            echo "<span style='color:red'>Duplicate discount code '".$code."' detected, skipping.</span><br />";
        }
    }
}

function railticket_discount_types() {
    global $rtmustache;
    $alldata = new \stdclass();

    $template = $rtmustache->loadTemplate('discounttypes');
    echo $template->render($alldata);
}

function railticket_rebook($action) {
    $sure = railticket_gettfpostfield('sure');
    if (!$sure) {
        echo "<p>You were not sure you wanted to do this!</p>";
        return;
    }
    $dateofjourney = railticket_getpostfield('dateofjourney');
    $sortby = railticket_getpostfield('sortby');

    $bookableday = \wc_railticket\BookableDay::get_bookable_day($dateofjourney);
    if ($sortby == 'date') {
        $sort = "time, created ASC";
        $clever = false;
    } else {
        $sort = "time, priority DESC, seats DESC";
        $clever = false;
    }

    if ($action == 'rebookall') {
        $bookings = $bookableday->get_all_bookings($sort, true);
    } else {
        $deptime = railticket_getpostfield('dep');
        $from = railticket_getpostfield('from');
        $revision = railticket_getpostfield('ttrevision');
        $station = \wc_railticket\Station::get_station($from, $revision);
        $direction = railticket_getpostfield('direction');
        $bookings = $bookableday->get_bookings_from_station($station, $deptime, $direction, $sort);
    }

    railticket_rebook_bookings_bays($bookableday, $bookings, $clever);
}

function railticket_rebook_bookings_bays($bookableday, $bookings, $clever) {
    global $rtmustache;

    $isbookable = $bookableday->is_bookable();

    // Close bookings while we do this since it's potentially dangerous if somebody is trying to book onto this service
    if ($isbookable) {
        $bookableday->set_bookable(false);
    }

    $alldata = new \stdclass();
    $alldata->date = $bookableday->get_date(true);
    $alldata->dateofjourney = $bookableday->get_date();
    $alldata->bookings = array();

    // Delete the existing bookings bays
    for ($i=0; $i<count($bookings); $i++) {
       $alldata->bookings[$i] = new \stdclass();
       $alldata->bookings[$i]->oldbays = $bookings[$i]->get_bays(true);

       $bookings[$i]->delete_bays();
    }

    if ($clever) {
        // Clever version which tries to avoid ending up with the smallest groups in the largest bays if that's all we have left.
        // However, if the train is over booked, this tends to leave a larger group without a bay and manages to run out of space!
        $stop = 0;
        for ($i=0; $i<count($bookings); $i++) {
            $count = railticket_processrebookentry($bookableday, $alldata, $bookings, $i);
            if ($count == 1) {
                $stop = $i;
                break;
            }
        }

        for ($i=count($bookings)-1; $i>$stop; $i--) {
            railticket_processrebookentry($bookableday, $alldata, $bookings, $i);
        }
    } else {
        for ($i=0; $i<count($bookings); $i++) {
            $count = railticket_processrebookentry($bookableday, $alldata, $bookings, $i);
        }
    }

    // Reopen bookings
    if ($isbookable) {
        $bookableday->set_bookable(true);
    }

    $template = $rtmustache->loadTemplate('rebooking');
    echo $template->render($alldata);
}

function railticket_processrebookentry($bookableday, &$alldata, &$bookings, $i) {
    $from = $bookings[$i]->get_from_station();
    $to = $bookings[$i]->get_to_station();
    $ts = new \wc_railticket\TrainService($bookableday, $from, $bookings[$i]->get_dep_time(), $to);
    $capdata = $ts->get_capacity(false, $bookings[$i]->get_seats(), $bookings[$i]->get_priority());
    if (count($capdata->bays) == 0) {
        // We probably ran out of space. Allocate whatever is left if we can.
        $cap = $ts->get_inventory(false);
        $bookings[$i]->update_bays($cap->bays);
        $alldata->bookings[$i]->newbays = $bookings[$i]->get_bays(true);
    } else {
        $bookings[$i]->update_bays($capdata->bays);
        $alldata->bookings[$i]->newbays = $bookings[$i]->get_bays(true);
        if ($alldata->bookings[$i]->newbays != $alldata->bookings[$i]->oldbays) {
            $alldata->bookings[$i]->change = 'color:blue';
        }
    }

    $alldata->bookings[$i]->orderid = $bookings[$i]->get_order_id();
    $alldata->bookings[$i]->time = $bookings[$i]->get_dep_time(true);
    $alldata->bookings[$i]->fromstation = $from->get_name();
    $alldata->bookings[$i]->tostation = $to->get_name();
    $alldata->bookings[$i]->seats = $bookings[$i]->get_seats();
    $alldata->bookings[$i]->priority = $bookings[$i]->get_priority(true);

    $count = 0;
    foreach ($bookings[$i]->get_bays() as $bay) {
        $count += $bay->num;
    }

    return $count;
}

function railticket_manage_specials() {
    wp_register_style('railticket_style', plugins_url('wc-product-railticket/ticketbuilder.css'));
    wp_enqueue_style('railticket_style');

    if (array_key_exists('action', $_REQUEST)) {
        switch ($_REQUEST['action']) {
            case 'editspecial':
                railticket_show_edit_special();
                return;
            case 'addspecial':
            case 'updatespecial':
                railticket_update_special();
                break;
        }
    }
    railticket_show_special_summary();
}

function railticket_show_special_summary() {
    global $rtmustache;
    $currentyear = intval(date("Y"));

    if (array_key_exists('year', $_REQUEST)) {
        $chosenyear = sanitize_text_field($_REQUEST['year']);
    } else {
        $chosenyear = $currentyear;
    }

    $alldata = new \stdclass();
    $alldata->years = wc_railticket_getyearselect($currentyear);
    $alldata->actionurl = railticket_get_page_url();
    $alldata->specials = array();
    $specials = \wc_railticket\Special::get_specials_year($chosenyear);

    if ($specials) {
        foreach ($specials as $sp) {
            $item = new \stdclass();
            $item->id = $sp->get_id();
            $item->date = $sp->get_date(true);
            $item->name = $sp->get_name();
            $item->fromstation = $sp->get_from_station()->get_name();
            $item->tostation = $sp->get_to_station()->get_name();
            $item->onsale = $sp->on_sale(true);
            $alldata->specials[] = $item;
        }
    }

    $template = $rtmustache->loadTemplate('manage_specials');

    // new form
    $alldata->action = 'addspecial';
    $alldata->button = 'Add';
    $alldata->title = 'Add Special';
    $alldata->id = '-1';
    $today = new \DateTime();
    $railticket_timezone = new \DateTimeZone(get_option('timezone_string'));
    $today->setTimezone($railticket_timezone);
    $today->setTime(0,0,0);
    $timetable = \wc_railticket\Timetable::get_timetables()[0];
    $alldata->fromstation = $timetable->get_stations(true);
    $alldata->tostation = $timetable->get_stations(true);
    end($alldata->tostation)->selected = "selected";
    $alldata->colourdefault = 'checked';
    $alldata->backgrounddefault = 'checked';
    $alldata->tickettypes = \wc_railticket\FareCalculator::get_all_ticket_types();

    echo $template->render($alldata);
}

function railticket_show_edit_special() {
    global $rtmustache;

    $id = sanitize_text_field($_REQUEST['id']);
    $sp = \wc_railticket\Special::get_special($id);
    $timetable = \wc_railticket\Timetable::get_timetable_by_date($sp->get_date());

    $item = new \stdclass();
    $item->id = $sp->get_id();
    $item->date = $sp->get_date();
    $item->name = $sp->get_name();
    $item->description = $sp->get_description();
    $item->colour = $sp->get_colour();
    $item->background = $sp->get_background();
    if ($item->colour == '') {
        $item->colourdefault = 'checked';
    }
    if ($item->background == '') {
        $item->backgrounddefault = 'checked';
    }

    $item->fromstation = $timetable->get_stations(true);
    foreach ($item->fromstation as $fs) {
        if ($fs->stnid == $sp->get_from_station()->get_stnid()) {
            $fs->selected = 'selected';
        }
    }
    $item->tostation = $timetable->get_stations(true);
    foreach ($item->tostation as $fs) {
        if ($fs->stnid == $sp->get_to_station()->get_stnid()) {
            $fs->selected = 'selected';
        }
    }

    $alltickettypes = \wc_railticket\FareCalculator::get_all_ticket_types(true);
    $item->tickettypes = array();
    $tickettypes = $sp->get_ticket_types();
    foreach ($alltickettypes as $tt) {
        $hastt = in_array($tt->code, $tickettypes);
        if (!$hastt && $tt->hidden) {
            continue;
        }
        if ($hastt) {
            $tt->selected = 'selected';
        }
        $item->tickettypes[] = $tt;
    }

    if ($sp->on_sale()) {
        $item->onsale = 'checked';
    }

    $item->action = 'updatespecial';
    $item->button = 'Update';
    $item->title = 'Update Special';

    $template = $rtmustache->loadTemplate('edit_special');
    echo $template->render($item);
}

function railticket_update_special() {

    if (railticket_gettfpostfield('colourdefault') == 0) {
        $colour = railticket_getpostfield('colour');
    } else {
        $colour = '';
    }

    if (railticket_gettfpostfield('backgrounddefault') == 0) {
        $background = railticket_getpostfield('background');
    } else {
        $background = '';
    }

    $id = sanitize_text_field($_REQUEST['id']);
    if ($id == -1) {
        \wc_railticket\Special::add(
            railticket_getpostfield('name'),
            railticket_getpostfield('date'),
            railticket_getpostfield('description'),
            railticket_gettfpostfield('onsale'),
            substr($colour, 1),
            substr($background, 1),
            railticket_getpostfield('fromstation'),
            railticket_getpostfield('tostation'),
            $_REQUEST['tickettypes']
        );
    } else {
        $sp = \wc_railticket\Special::get_special($id);
        $sp->update(
            railticket_getpostfield('name'),
            railticket_getpostfield('date'),
            railticket_getpostfield('description'),
            railticket_gettfpostfield('onsale'),
            substr($colour, 1),
            substr($background, 1),
            railticket_getpostfield('fromstation'),
            railticket_getpostfield('tostation'),
            $_REQUEST['tickettypes']
        );
    }
}

function railticket_stats() {
    global $rtmustache;

    if (array_key_exists('action', $_REQUEST)) {
        switch($_REQUEST['action']) {
            case 'statsprocess':
                echo "<h5>Updating Stats</h5>";
                railticket_process_stats(true, railticket_getpostfield('maxdays'));
                break;
            //case 'statsdownload':
            //    railticket_get_stats(railticket_getpostfield('start'), railticket_getpostfield('end'));
            //    break;
        }
    }

    $alldata = new \stdclass();
    $alldata->adminurl = admin_url('admin-post.php');
    $template = $rtmustache->loadTemplate('stats');
    echo $template->render($alldata);
}

function railticket_get_stats() {
    global $wpdb;

    $start = railticket_getpostfield('start');
    $end = railticket_getpostfield('end');

    header('Content-Type: application/csv');
    header('Content-Disposition: attachment; filename="stats_'.$start.'_'.$end.'.csv";');
    header('Pragma: no-cache');
    $f = fopen('php://output', 'w');
    $lines = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_railticket_stats WHERE ".
        "date >= '".$start."' AND date <= '".$end."' ORDER BY DATE ASC");

    fputcsv($f, array('Date', 'Total Passengers', 'Total Orders', 'Online Orders',
        'Manual Orders', 'Revenue', 'Peak Loading', 'Pass. at 18:00', 'Pass. at 9:00'));
    foreach ($lines as $line) {
        unset($line->id);
        fputcsv($f, (array) $line);
    }

    fclose($f);
    exit;
}

function railticket_get_geo() {
    global $wpdb;

    $start = railticket_getpostfield('start');
    $end = railticket_getpostfield('end');
    $consolidation = railticket_getpostfield('consolidation');

    $field = '';
    $fname = '';
    switch ($consolidation) {
        case 'zone': $field = 'postcodezone'; $fname = 'Postcode Zone'; break;
        case 'first': $field = 'postcodefirst'; $fname = 'Postcode First Part'; break;
        case 'full': $field = 'postcodes'; $fname = 'Postcode'; break;
    }

    header('Content-Type: application/csv');
    header('Content-Disposition: attachment; filename="geostats_'.$field.'_'.$start.'_'.$end.'.csv";');
    header('Pragma: no-cache');
    $f = fopen('php://output', 'w');
    $lines = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_railticket_stats WHERE ".
        "date >= '".$start."' AND date <= '".$end."' ORDER BY DATE ASC");

    $totals = array();
    foreach ($lines as $line) {
        $data = json_decode($line->$field);
        if (!$data) {
            continue;
        }
        $data = (array) $data;
        foreach ($data as $k => $v) {
            if (array_key_exists($k, $totals)) {
                $totals[$k]->orders += $v->orders;
                $totals[$k]->seats += $v->seats;
                $totals[$k]->price += $v->price;
            } else {
                $totals[$k] = new \stdclass();
                $totals[$k]->orders = $v->orders;
                $totals[$k]->seats = $v->seats;
                $totals[$k]->price = $v->price;
            }
        }
    }

    arsort($totals);
    fputcsv($f, array($fname, 'Total Orders', 'Total Seats', 'Total Revenue'));
    foreach ($totals as $p => $v) {
        unset($line->id);
        fputcsv($f, array($p, $v->orders, $v->seats, $v->price));
    }

    fclose($f);
    exit;
}

function railticket_process_stats($info = false, $limit = 5) {
    global $wpdb;
    $nowdt = new \DateTime();
    $nowdt->setTimezone(new \DateTimeZone(get_option('timezone_string')));

    $toprocess = $wpdb->get_results("SELECT bk.id, bk.date FROM {$wpdb->prefix}wc_railticket_bookable bk ". 
        "LEFT join {$wpdb->prefix}wc_railticket_stats stats ON bk.date = stats.date ". 
        "WHERE stats.id IS NULL AND bk.date < '".$nowdt->format('Y-m-d')."' ORDER BY date ASC LIMIT ".$limit);

    foreach ($toprocess as $tp) {
        if ($info) {
            echo $tp->date."<br />";
        }
        $bk = \wc_railticket\BookableDay::get_bookable_day($tp->date);
        $stats = new \wc_railticket\StatsProcessor($bk);
        $stats->updateStats();
    }
}
