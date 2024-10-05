<?php

/**
 * Plugin Name: Heritage railway ticket sales and booking for Woocommerce
 * Plugin URI:   * Plugin URI:  https://github.com/tim1mw/railtimetable
 * Description: Designed for use by Heritage railways, this booking plugin allows for sales based on a standard timetable and one off specials.
 * Author:      Tim Williams, AutoTrain (tmw@autotrain.org)
 * Author URI:  https://github.com/tim1mw/
 * Version:     0.0.4
 * Text Domain: railtimetable
 * License:     GPL3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Requires Plugins: woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
    return;
}

define('SINGLE_TICKET', 0);
define('RETURN_TICKET', 1);
define('SPECIAL_TICKET', 2);

require_once('Mustache/Autoloader.php');
Mustache_Autoloader::register();
$rtmustache = new Mustache_Engine(array(
   'loader' => new Mustache_Loader_FilesystemLoader(dirname(__FILE__).'/templates') 
));
spl_autoload_register('wc_railticket_autoloader');

require_once('raillib.php');
require_once('woolib.php');
require_once('editlib.php');

// Install the DB
register_activation_hook( __FILE__, 'railticket_create_db' );
add_action( 'upgrader_process_complete', 'railticket_create_db', 10, 2 );

function wc_railticket_autoloader($class) {
    $namespace = 'wc_railticket';
    if (strpos($class, $namespace) !== 0) {
        return;
    }
 
    $class = str_replace($namespace, '', $class);
    $class = str_replace('\\', DIRECTORY_SEPARATOR, $class) . '.php';
 
    $directory = dirname(__FILE__);
    $path = $directory . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . $class;

    if (file_exists($path)) {
        require_once($path);
    }
}
