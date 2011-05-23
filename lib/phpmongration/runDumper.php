#!/usr/bin/php
<?php
/**
 * Basic bash script to run PHPMongrationDumper
 * Creates basic YAML config file for a Database
 * @author  Rafael Goulart <rafaelgou@gmail.com>
 * @license GPL3 http://www.gnu.org/licenses/lgpl-3.0.txt
 * @link    http://github/rafaelgou/phpmongration
 */
//require_once dirname(__FILE__) . '/../vendor/sfYaml/lib/sfYaml.php';
require_once dirname(__FILE__) . "/driver/PHPMongrationDriver.class.php";

fwrite(STDOUT,
      "PHPMongration Dumper" . PHP_EOL .
      "------------------------------------------------------" . PHP_EOL .
      "@author  Rafael Goulart <rafaelgou@gmail.com>" . PHP_EOL .
      "@license GPL3 http://www.gnu.org/licenses/lgpl-3.0.txt" . PHP_EOL .
      "@link    http://github/rafaelgou/phpmongration" . PHP_EOL .
      "------------------------------------------------------" . PHP_EOL);

fwrite(STDOUT, "Inform..." . PHP_EOL);

fwrite(STDOUT, "- the PHP-PDO driver [mysql for a while...]: " . PHP_EOL);
$driver = trim(fgets(STDIN));
//$driver = $driver != '' ? $driver : 'mysql';
$driver = 'mysql';

fwrite(STDOUT, "- the server [localhost as default]: " . PHP_EOL);
$server = trim(fgets(STDIN));
$server = trim($server) != '' ? $server : 'localhost';

fwrite(STDOUT, "- the database name: " . PHP_EOL);
$dbname = trim(fgets(STDIN));

fwrite(STDOUT, "- the database user: " . PHP_EOL);
$dbuser = trim(fgets(STDIN));

fwrite(STDOUT, "- the database password: " . PHP_EOL);
$dbpass = trim(fgets(STDIN));

fwrite(STDOUT, "- target YAML file name [default migration.{dbname}.yml]: " . PHP_EOL);
$filename = trim(fgets(STDIN));
$filename = trim($filename) != '' ? $filename : "migration.{$dbname}.yml";

$source_config = array(
  'dsn'      => "{$driver}:host={$server};dbname={$dbname}",
  'user'     => $dbuser,
  'password' => $dbpass,
  'options'  => array(
    "PDO::MYSQL_ATTR_INIT_COMMAND" => "SET NAMES utf8",
    "PDO::ATTR_PERSISTENT" => true
  )
);
$dumper = PHPMongrationDriver::create($driver, $source_config);

$file = fopen($filename, 'w');
fwrite($file, $dumper->dump());
fclose($file);

fwrite(STDOUT, "------------------------------------------------------" . PHP_EOL);
fwrite(STDOUT, "File dumped: $filename\n" . PHP_EOL);
