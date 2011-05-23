<?php
/**
 * PHPMongrationBaseDriver abstract class
 * Base class to database conncetors
 *
 * @author  Rafael Goulart <rafaelgou@gmail.com>
 * @license GPL3 http://www.gnu.org/licenses/lgpl-3.0.txt
 * @link    http://github/rafaelgou/phpmongration
 */
class PHPMongrationDriver
{
  private function __construtc() {}

  /**
   * Factory method
   * @param string $driver
   * @param array  $config
   * @return PHPMongrantioBaseDriver
   */
  static public function create($driver, $config)
  {
    require_once dirname(__FILE__) . "/PHPMongrationBaseDriver.class.php";

    $class = "PHPMongrationDriver" . ucfirst($driver);

    if ($class != "PHPMongrationDriver" && class_exists($class))
    {
      return new $class($config);
    } else {
      try
      {
        require_once dirname(__FILE__) . "/{$class}.class.php";
        if ($class != "PHPMongrationDriver" && class_exists($class))
        {
          return new $class($config);
        } else {
          throw new Exception(
                  "Unable to load class for driver: '{$driver}'\n",
                  500);
        }
      } catch (Exception $e) {
        echo $e->getMessage();
        exit;
      }
    }
  }
}