#!/usr/bin/php
<?php
/**
 * Basic php script to run PHPMongration
 * PHPMongration class
 * Executes migrations from PHP PDO databases to Mongo
 *
 * Require PHP-CLI extension
 * @see 
 *
 * @author  Rafael Goulart <rafaelgou@gmail.com>
 * @license GPL3 http://www.gnu.org/licenses/lgpl-3.0.txt
 * @link    http://github/rafaelgou/phpmongration
*/

require_once dirname(__FILE__).'/PHPMongration.class.php';

$debug           = true;
$log_dump_online = true;

//print_r($argc);
$mongration = new PHPMongration($argv[1], $debug, $log_dump_online);
$mongration->run();

