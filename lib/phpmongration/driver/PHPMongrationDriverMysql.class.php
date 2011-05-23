<?php
/**
 * PHPMongrationBaseDriver abstract class
 * Base class to database conncetors
 *
 * @author  Rafael Goulart <rafaelgou@gmail.com>
 * @license GPL3 http://www.gnu.org/licenses/lgpl-3.0.txt
 * @link    http://github/rafaelgou/phpmongration
 */
class PHPMongrationDriverMysql extends PHPMongrationBaseDriver
{
  protected $driver = 'mysql';

  protected function setDefaultTargetDatabase()
  {
    $source = explode(';',$this->source_config['dsn']);
    $source = explode('=', $source[1]);
    $this->target_database = $source[1];
  }

  protected function loadTablesDefinition()
  {
    $tables_result = $this->getSourceConn()->query('SHOW TABLES');

    $tables = $tables_result->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table)
    {
      $describe_result = $this->source_conn->query('DESCRIBE ' . $table);

      //$conn = new PDO; $q = $conn->query();; $q->fetch($fetch_style)

      while ($describe = $describe_result->fetch(PDO::FETCH_ASSOC))
      {
        $this->tables_definition[$table][$describe['Field']] = $describe;
        if ($describe['Field'] == 'PRI') $this->pk[] = $describe['Field'];
      }
    }
  }

  protected function loadKeysDefinition()
  {
    $db_schema = 'prime';
    
    $key_result = $this->source_conn->query("
      SELECT *
      FROM  INFORMATION_SCHEMA.KEY_COLUMN_USAGE
      WHERE CONSTRAINT_SCHEMA = '$db_schema'
        AND TABLE_SCHEMA      = '$db_schema'
      ");

    while ($key = $key_result->fetch(PDO::FETCH_ASSOC))
    {
      $keys[$key['TABLE_NAME']][] = $key;
    }

    foreach ($this->tables_definition as $table => $table_data)
    {
      if (isset($keys[$table]))
      {
        foreach ($keys[$table] as $key)
        {
          if ($key['CONSTRAINT_NAME'] == 'PRIMARY')
          {
            $this->pk[$table][] = $key['COLUMN_NAME'];
          } elseif ( null !== $key['REFERENCED_TABLE_NAME'] ) {
            $this->keys_definition[$table][$key['COLUMN_NAME']] = array(
              'table' => $key['REFERENCED_TABLE_NAME'],
              'fk'    => $key['REFERENCED_COLUMN_NAME'],
            );
          }
        }
      }
    }
  }

  protected function loadDataTypeMap()
  {
    //  string, integer, boolean, double, null, array, and object
    $this->data_type_map = array(
      'char'       => 'string',
      'varchar'    => 'string',
      'tinytext'   => 'string',
      'text'       => 'string',
      'mediumtext' => 'string',
      'longtext'   => 'string',
      'enum'       => 'string',
      'set'        => 'string',

      // Not used for a while
      'blob'       => 'string',
      'mediumblob' => 'string',
      'longblob'   => 'string',

      'tinyint(1)' => 'boolean',
      'tinyint'    => 'integer',
      'smallint'   => 'integer',
      'mediumint'  => 'integer',
      'int'        => 'integer',
      'bigint'     => 'integer',
      'float'      => 'double',
      'double'     => 'double',
      'decimal'    => 'double',

      'date'       => 'date',
      'datetime'   => 'date',
      'timestamp'  => 'date',
      'time'       => 'date',
    );
  }

  public function getTableQueryResult($table_config)
  {
    $fields_name = array_keys($table_config['columns_map']);
    $sql = "SELECT " . implode(',',$fields_name) . " FROM " . $table_config['table_name'];
    return $this->getSourceConn()->query($sql);
  }

  protected function getManyReferences($reference_config)
  {
    $sql = "
      SELECT {$reference_config['in_id']} as in_id, {$reference_config['out_id']} as out_id
      FROM   {$reference_config['reference_table']}
    ";

    $result = $this->getSourceConn()->query($sql);
    $references = array();
    while ($row = $result->fetch(PDO::FETCH_ASSOC))
    {
      $references[$row['in_id']][] = $row['out_id'];
    }
    return $references;
  }

}