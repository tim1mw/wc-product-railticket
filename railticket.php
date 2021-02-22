<?php

/**
 * Plugin Name: WooCommerce Ticket Product Type
 * Description: Works with railtimetable
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
