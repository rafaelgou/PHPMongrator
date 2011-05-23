<?php
/**
 * PHPMongration class
 * Executes migrations from PHP PDO databases to Mongo
 * 
 * Requires PHP Mongo Extension
 * @see http://www.mongodb.org/display/DOCS/PHP+Language+Center
 * @see http://www.mongodb.org/display/DOCS/Ubuntu+and+Debian+packages
 * @see http://www.php.net/manual/en/mongo.installation.php
 *
 * @author  Rafael Goulart <rafaelgou@gmail.com>
 * @license GPL3 http://www.gnu.org/licenses/lgpl-3.0.txt
 * @link    http://github/rafaelgou/phpmongration
 */
class PHPMongration
{
  protected
    $config          = null,
    $log             = array(),
    $log_dump_mode   = 'TXT',
    $log_dump_online = true,
    $debug           = false,
    $timestart       = null,
    $timeend         = null;

  /**
   * PHPMongrationDriver[DriverType]
   * @var PHPMongrationBaseDriver $driver
   */
  protected  $driver = null;

  static protected $log_levels = array('INFO', 'WARNING', 'FATAL', 'DEBUG');
  static protected $log_dump_modes = array('TXT', 'HTML', 'JSON');

  /**
   * Constructor
   * @param string $config_file Full path to config file
   */
  public function __construct($config_file=null, $debug=false, $log_dump_online=true)
  {
    $this->log("Starting PHPMongration");

    require_once dirname(__FILE__) . '/../vendor/sfYaml/lib/sfYaml.php';
    require_once dirname(__FILE__) . '/driver/PHPMongrationDriver.class.php';
    if ( null !== $config_file )
    {
      $this->loadConfig($config_file);
    }

    $this->driver = PHPMongrationDriver::create($this->config['source']['driver'], $this->config['source']);

    if ($debug) $this->setDebug(true);
    if ($debug) $this->setLogDumpOnline(true);
  }

  /**
   * Loads Config file
   * @param string $config_file Full path to config file
   */
  public function loadConfig($config_file)
  {
    // Loading config file
    try
    {
      if ( ! is_file($config_file) ) throw new Exception('File not found');
      $this->config = sfYaml::load($config_file);
      $this->log('Config file loaded: ' . $config_file);
    } catch (Exception $exc) {
      $this->log("Unable to load config file '$config_file': " .
              $exc->getMessage() . "\n" .
              $exc->getTraceAsString() . "\n", 'FATAL');
    }

    // Basic validation
    //TODO test $this->config array for simple parameters
  }

  /**
   * Returns the Driver Object
   * @return PHPMongrationDriver
   */
  public function getDriver()
  {
    return $this->driver;
  }



  /**
   * Logs a message on a debug level
   * @param string $message
   * @param string $level Log level (INFO, WARNING, FATAL, DEBUG)
   */
  public function log($message, $level='INFO')
  {
    if ( !in_array($level, self::$log_levels) )
    {
      $this->log('Invalid log level "' . $level . '"', 'FATAL');
      throw new Exception('Invalid log level "' . $level . '"');
      exit;
    }
    $this->log['level']    = $level;
    $this->log['datetime'] = $date = date('Y-m-d H:i:s');
    $this->log['message']  = $message;

    if ($this->debug)
    {

    }

    if ($this->log_dump_online)
    {
      echo "[$level] $date - $message\n";
    }


  }

  /**
   * Sets Debug on/off (true/false)
   *
   * @param boolean $active
   */
  public function setDebug($active=true)
  {
    $this->debug = (boolean) $active;
  }

  /**
   * Runs migration
   */
  public function run ()
  {
    // TODO
    //$this->timestart = now();

    // It could run directly, but there's no log...
    //$this->getDriver()->runMigration($this->config);

    $this->getDriver()->setMigrationConfig($this->config);

    if (true === $this->config['default']['drop_database_first'])
    {
      $this->getDriver()->dropTargetDatabase();
      $this->log('Target database dropped');
    }

    $this->log('');
    $this->log('Starting TABLE MIGRATION');
    $this->log('------------------------------------------------');
    foreach ($this->config['tables'] as $table_name => $table_config)
    {
      $ignore_tables = isset($this->config['default']['ignore_tables'])
                       ? $this->config['default']['ignore_tables']
                       : array();
      if ( ! in_array($table_name,$ignore_tables))
      {
        $this->log('Start migration for table: ' .$table_name);
        $this->getDriver()->runTableMigration($table_config);
        $this->log('Successful migration table: ' .$table_name);
      } else {
        $this->log('Table Ignored (in default->ignore_tables): ' . $table_name, 'WARNING');
      }
    }

    $this->log('');
    $this->log('Starting ONE REFERENCES MIGRATION');
    $this->log('------------------------------------------------');
    foreach ($this->config['tables'] as $table_name => $table_config)
    {
      $ignore_tables = isset($this->config['default']['ignore_tables'])
                       ? $this->config['default']['ignore_tables']
                       : array();
      if (
           ! in_array($table_name,$ignore_tables)
           && isset($table_config['one_references'])
           && count($table_config['one_references']) != 0
          )
      {
        $this->log('Start one reference migration for table: ' .$table_name);
        $this->getDriver()->runOneReferencesMigration($table_config);
        $this->log('Successful one reference migration table: ' .$table_name);
      }
    }

    $this->log('');
    $this->log('Starting MANY REFERENCES MIGRATION');
    $this->log('------------------------------------------------');
    foreach ($this->config['tables'] as $table_name => $table_config)
    {
      $ignore_tables = isset($this->config['default']['ignore_tables'])
                       ? $this->config['default']['ignore_tables']
                       : array();
      if (
           ! in_array($table_name,$ignore_tables)
           && isset($table_config['many_references'])
           && count($table_config['many_references']) != 0
          )
      {
        $this->log('Start many reference migration for table: ' .$table_name);
        $this->getDriver()->runManyReferencesMigration($table_config);
        $this->log('Successful many reference migration table: ' .$table_name);
      }
    }


    //$this->timeend   = now();

//    $message = 'Started at ' . date('Y-m-d H:i:s',$this->timestart) . ' - ' .
//               'Finished at ' . date('Y-m-d H:i:s',$this->timeend) . ' - elapsed time: ' .
//               ($this->timeend - $this->timestart);
//    $this->log($message, 'INFO');
  }

  /**
   * Sets de Log Dump Mode
   * Avaliable modes: TXT HTML JSON
   * Default TXT
   *
   * @param string $dump_mode
   */
  public function setLogDumpMode($dump_mode='TXT')
  {
    if ( !in_array($dump_mode, self::$log_dump_modes) )
    {
      $this->log('Invalid log dump mode "' . $dump_mode . '"', 'FATAL');
      $this->logDump();
      exit;
    }
  }

  /**
   * Sets Log Dump Online on/off (true/false)
   * Default false
   *
   * @param boolean $active
   */
  public function setLogDumpOnline($active=false)
  {
    $this->log_dump_online = (boolean) $active;
  }

  /**
   * Returns the log dumped into a string
   * 
   * @return string The dump
   */
  public function logDump()
  {
    $log_dump = '';
    switch ($this->log_dump_mode)
    {
      case 'HTML':
        $log_dump = '<table>';
        foreach ($this->log as $log)
        {
          $log_dump .= "<tr><td>{$log['level']}</td><td>{$log['datetime']}</td><td>{$log['message']}</td><tr>" . PHP_EOL;
        }
        $log_dump .= '</table>';
        break;
      case 'JSON':
        $log_dump = json_encode($this->log);
        break;
      case 'TXT':
      default:
        $log_dump = '';
        foreach ($this->log as $log)
        {
          $log_dump .= "[{$log['level']}] {$log['datetime']} {$log['message']}" . PHP_EOL;
        }
        break;
    }
    return $log_dump;
  }

  /**
   * 
   *
   */
  protected function writeLogFile()
  {
    // TODO
  }
}
