<?php

if (php_sapi_name() !== 'cli') {
 echo 'Run this script via the command line instead.';
 die;
}

require_once('../../../../wp-config.php');

$pass = DB_PASSWORD;
$table = DB_NAME;
$user = DB_USER;

$cmd = "/usr/bin/mysqldump -u$user -p$pass --no-data $table \\
$(/usr/bin/mysql  -u$user -p$pass -D $table -Bse \"show tables like 'wp\_wc\_railticket%'\") \\
--compatible=mysql40 \\
--skip-add-drop-table \\
--skip-triggers \\
--skip-comments --skip-add-locks --skip-disable-keys --skip-set-charset \\
--skip-quote-names \\
--no-create-db \\
| /usr/bin/grep -v '^\\/\*![0-9]\{5\}.*\\/;$'";

$rawsql = shell_exec($cmd); 

$lines = explode("\n", $rawsql);
$stmts = [];
$stmt = [];

foreach ($lines as $line) {
    $line = trim($line);

    if ( strlen($line) == 0 || str_starts_with($line, '/*') || str_starts_with($line, '--') || str_starts_with($line, 'DROP TABLE')) {
        continue;
    }

    //Field quoting is supposedly off in the mysqldump command, but it still insists on quoting some field names but not all. So remove here.
    $line = str_replace('`', '', $line);

    // You have to have two spaces between the PRIMARY KEY and ( for dbDelta.
    if ( str_starts_with($line, 'PRIMARY KEY (')) {
        $line = str_replace('PRIMARY KEY (', 'PRIMARY KEY  (', $line);
    }

    $line = str_replace('unsigned', 'UNSIGNED', $line);

    if ( str_starts_with($line, 'CREATE TABLE')) {
        if (count($stmt) > 0) {
            $stmts[] = $stmt;
        }
        $stmt = [];
        $stmt[] = str_replace('wp_', '".$wpdb->prefix."', $line);
    } else {
        $stmt[] = $line;
    }

} 

$stmts[] = $stmt;

$out = file_get_contents('template.php'); 

foreach ($stmts as $stmt) {

    if (strpos(reset($stmt), '_bak') !== false) {
        continue;
    }

    // Telling SQL Dump to not include create options strips AUTO_INCREMENT from the create statement, but including them adds
    // a lod of stuff we don't want onto the end of the statement. So skip the last line to remove those options and replace with what we
    // actually want.

    array_pop($stmt);
    //$stmt[] = ") \".\$charset_collate.\";\"";

    $line = "\$sql[] = \"".implode("\r\n    ", $stmt);
    $line = trim($line);
    $line .= ")\r\n\".\$charset_collate.\";\";\r\n\r\n";
    $out .= $line;
}

file_put_contents('sqlimport.php', $out);
