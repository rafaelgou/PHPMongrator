<?php
/**
 * PHPMongrationBaseDriver abstract class
 * Base class to database conncetors
 *
 * @author  Rafael Goulart <rafaelgou@gmail.com>
 * @license GPL3 http://www.gnu.org/licenses/lgpl-3.0.txt
 * @link    http://github/rafaelgou/phpmongration
 */
abstract class PHPMongrationBaseDriver
{
  protected 
    $driver           = null,
    $source_config    = array(),
    $tables_definition = array(),
    $keys_definition  = array(),
    $data_type_map    = array(),
    $pk               = array(),
    $dump_data        = array(),
    $target_database  = '',
    $migration_config = null;
  
  /**
   * PDO Connection
   * @var PDO $source_conn
   */
  protected $source_conn = null;

  /**
   * Mongo
   * @var Mongo $target_conn
   */
  protected $target_conn = null;

  abstract protected function setDefaultTargetDatabase();

  abstract protected function loadTablesDefinition();

  abstract protected function loadKeysDefinition();

  abstract protected function loadDataTypeMap();

  abstract public function getTableQueryResult($table_config);

  abstract protected function getManyReferences($reference_config);

  /**
   * Constructor
   * @param array $config
   */
  public function __construct($config)
  {
    $this->source_conn = new PDO(
            $config['dsn'],
            $config['user'],
            $config['password'],
            $config['options']
            );
    $this->source_config = array_merge(array('driver'=>$this->driver), $config);
  }

  /**
   * Prepares and Dumps the Database definition to YAML config file
   * @return string
   */
  public function dump ()
  {
    $this->dumpPrepare();
    return $this->dumpToYaml($this->dump_data);
  }

  /**
   * Generate Dumped yml file from an array
   * @param  array $config
   * @return string
   */
  protected function dumpToYaml($config)
  {
    require_once dirname(__FILE__).'/../../vendor/sfYaml/lib/sfYaml.php';
    return sfYaml::dump($config, 4);
  }

  /**
   * Returns Target Mongo Connection
   * crates if not avaliable
   * @return Mongo
   */
  protected function getTargetConn()
  {
    if ( null === $this->target_conn )
    {
      if ( isset($this->migration_config['target']) )
      {
        $userpass = (
                      isset ($this->migration_config['target']['username'])
                      && isset ($this->migration_config['target']['password'])
                    )
                    ? $this->migration_config['target']['username'] . ':' .
                      $this->migration_config['target']['password'] . '@'
                    : '';
        $this->target_conn = new Mongo(
                $this->migration_config['target']['server'] .
                $userpass . '/' . $this->migration_config['target']['database']);
      }
    }
    return $this->target_conn;
  }

  /**
   * Returns Source PDO Connection
   * @return PDO
   */
  protected function getSourceConn()
  {
    return $this->source_conn;
  }

  /**
   * Returns MongoCollection
   * @param string $collection_name
   * @return MongoCollection
   */
  protected function getCollection($collection_name)
  {
    $database_name   = $this->target_database;
    return $this->getTargetConn()->$database_name->$collection_name;
  }

  /**
   * Prepares config file to be dumped
   */
  public function dumpPrepare()
  {
    $this->setDefaultTargetDatabase();
    $this->loadTablesDefinition();
    $this->loadKeysDefinition();
    $this->loadDataTypeMap();
    
    $this->dump_data['default'] = array(
      'keep_source_pk_columns' => false,
      'typecasting'            => 'tostring',
      'drop_database_first'    => false,
      'use_dbrefs'             => true,
      'ignore_tables'          => array(),
    );

    $this->dump_data['source'] = $this->source_config;

    $this->dump_data['target'] = array(
      'server'   => 'mongodb://localhost:27017',
      'database' => $this->target_database,
      'options'  => array('persist' => true),
    );

    $tables = array();

    foreach ($this->tables_definition as $table_name => $table_data)
    {
      $tables[$table_name] = array(
        'table_name' =>  $table_name,
        'collection_target' => $table_name,
        'pk' => ( ! isset($this->pk[$table_name]) ) ? null : (
                ( count($this->pk[$table_name]) == 1 ) ? $this->pk[$table_name][0] : $this->pk[$table_name]  ),
        'keep_source_pk_columns' =>  $this->dump_data['default']['keep_source_pk_columns'],
        'ignore_columns' => array(),
        'embeddeds' => array(),
      );

      foreach($table_data as $table_field)
      {
        $type = explode('(',$table_field['Type']);
        $type = trim($type[0]);

        if ( isset($this->data_type_map[$table_field['Type']]) )
        {
          $target_type = $this->data_type_map[$table_field['Type']];
        } elseif ( isset($this->data_type_map[$type]) ) {
          $target_type = $this->data_type_map[$type];
        } else {
          $target_type = 'string';
        }

        $tables[$table_name]['columns_map'][$table_field['Field']] = array(
          'target_name' => $table_field['Field'],
          'source_type' => $table_field['Type'],
          'target_type' => $target_type,
        );

        if (isset($this->keys_definition[$table_name]))
        {
          foreach ($this->keys_definition[$table_name] as $key_name => $key)
          {
            if ($table_name != $key['table'])
              $tables[$table_name]['one_references'][$key_name] = $key;
          }
        }
        /*
        $tables[$table_name]['many_references'] = array(
          'field_name' => array(
            'reference_table' => '',
            'in_id'           => '',
            'out_id'          => '',
         ),
        );
        */
        // TODO is possible, is interesting?
        $tables[$table_name]['many_references'] = array();

      }
    }
    $this->dump_data['tables'] = $tables;
  }

  /**
   * Sets the migration config array
   * @param array $migration_config
   */
  public function setMigrationConfig($migration_config=null)
  {
    $this->migration_config = $migration_config;
    $this->target_database  = $this->migration_config['target']['database'];
  }

  /**
   * Runs full migration
   * @param array $migration_config
   */
  public function runMigration($migration_config=null)
  {
    if ( null !== $migration_config) $this->setMigrationConfig($migration_config);

    if (true === $this->migration_config['default']['drop_database_first']) $this->dropTargetDatabase ();

    foreach ($this->migration_config['tables'] as $table_name => $table_config)
    {
      $ignore_tables = isset($this->migration_config['default']['ignore_tables'])
                       ? $this->config['default']['ignore_tables']
                       : array();
      if ( ! in_array($table_name,$ignore_tables))
        $this->runTableMigration($table_config);
    }

    foreach ($this->migration_config['tables'] as $table_name => $table_config)
    {
      $ignore_tables = isset($this->migration_config['default']['ignore_tables'])
                       ? $this->config['default']['ignore_tables']
                       : array();
      if (
           ! in_array($table_name,$ignore_tables)
           && isset($table_config['one_references'])
           && count($table_config['one_references']) != 0
         )

        $this->runOneReferencesMigration($table_config);
    }

    foreach ($this->migration_config['tables'] as $table_name => $table_config)
    {
      $ignore_tables = isset($this->migration_config['default']['ignore_tables'])
                       ? $this->config['default']['ignore_tables']
                       : array();
      if (
           ! in_array($table_name,$ignore_tables)
           && isset($table_config['many_references'])
           && count($table_config['many_references']) != 0
         )
        $this->runManyReferencesMigration($table_config);
    }

  }

  /**
   * Runs Table Migration
   * @param array $table_config
   */
  public function runTableMigration($table_config)
  {
    $collection = $this->getCollection($table_config['collection_target']);

    $result = $this->getTableQueryResult($table_config);

    while ($row = $result->fetch(PDO::FETCH_ASSOC))
    {
      foreach($row as $k => $r)
      {
        if (in_array($k, $table_config['ignore_columns'])) continue;

        $row[$k] = utf8_encode ($r);
        switch ($table_config['columns_map'][$k]['target_type'])
        {

          case 'boolean':
            $row[$k] = (boolean) $r;
            break;

          case 'integer':
            $row[$k] = (int) $r;
            break;

          case 'double':
            $row[$k] = (float) $r;
            break;

          case 'date':
            $row[$k] = new MongoDate(strtotime($r));
            break;

          default:
          case 'string':
            $r = utf8_encode($r);
            $row[$k] = (string) $r;
            break;
            break;
        }
      }
      $collection->insert($row);
    }

  }

  /**
   * Runs One References Migration
   * @param array $table_config
   */
  public function runOneReferencesMigration($table_config)
  {
    if (isset($table_config['one_references']))
    {
      foreach ($table_config['one_references'] as $reference_name => $reference_config)
      {
        $references = $this->getMongoIdsFromPK(
                $this->getCollectionTarget($reference_config['table']),
                $this->getFieldTargetName($reference_config['table'], $reference_config['fk']));
        foreach ($references as $pk_key => $_id)
        {
          $ref = $this->migration_config['default']['use_dbrefs']
                 ? MongoDBRef::create(
                     $this->getCollectionTarget($reference_config['table']),
                     $_id
                   )
                 : $_id;
          $query = array($this->getFieldTargetName($table_config['collection_target'], $reference_name) => $pk_key);
          $set = array('$set' =>
              array( $this->getFieldTargetName($table_config['collection_target'], $reference_name) => $ref )
              );
          $this->getCollection($table_config['collection_target'])
                  ->update($query, $set, array("multiple" => true));
        }
      }
    }
  }

  /**
   * Runs Many References Migration
   * @param array $table_config
   */
  public function runManyReferencesMigration($table_config)
  {
    if (isset($table_config['many_references']))
    {
      foreach ($table_config['many_references'] as $reference_name => $reference_config)
      {
        $references = $this->getManyReferences($reference_config);
        $refs = array();

        $out_mongo_ids = $this->getMongoIdsFromPK(
                $this->getCollectionTarget($reference_config['out_table']),
                $reference_config['out_table_id']
                );

        foreach ($references as $in_id => $out_ids)
        {
          foreach ($out_ids as $out_id)
          {
            $refs[$in_id][] = $this->migration_config['default']['use_dbrefs']
                              ? MongoDBRef::create(
                                  $this->getCollectionTarget($reference_config['out_table']),
                                  $out_mongo_ids[$out_id]
                                )
                              : $out_mongo_ids[$out_id];
          }
/*
    many_references:
      sf_guard_groups:
        reference_table: sf_guard_user_group
        in_id: user_id
        out_id: group_id
        out_table: sf_guard_group
        out_table_id: id
 */
          $query = array( $table_config['pk'] => $in_id);
          $set = array('$set' =>
              array( $reference_name => $refs[$in_id] )
              );
          $this->getCollection($table_config['collection_target'])
                  ->update($query, $set);
        }
      }
    }
  }











  /**
   * Drops Target Database
   */
  public function dropTargetDatabase()
  {
    $this->getTargetConn()->dropDB($this->migration_config['target']['database']);
  }


  /**
   * Finds MongoId from original Primary Key value
   * @param string $collection
   * @param string $pk_id
   * @param string $pk_value
   * @return MongoId
   */
  public function getMongoIdFromPK($collection, $pk_id, $pk_value)
  {
   $row = $this->getCollection($collection)->findOne(
            array( $pk_id => $pk_value)
            );
   return $row->_id;
  }

  /**
   * Returns pk_id => MongoIds relation
   * @param string $collection
   * @param string $pk_id
   * @return array pk_value => MongoId
   */
  public function getMongoIdsFromPK($collection_name, $pk_id)
  {
   $result = $this->getCollection($collection_name)->find(array(),array('_id',$pk_id));
   $return = array();
   foreach ($result as $row)
   {
     $return[$row[$pk_id]] = $row['_id'];
   }
   return $return;
  }

  /**
   * Returns Collection Target from a table
   * @param string $table_name
   * @return string
   */
  protected function getCollectionTarget($table_name)
  {
    return $this->migration_config['tables'][$table_name]['collection_target'];
  }

  /**
   * Returs Field Target Name from a Table/Field Name
   * @param string $table_name
   * @param string $field_name
   * @return string
   */
  protected function getFieldTargetName($table_name, $field_name)
  {
    return $this->migration_config['tables'][$table_name]['columns_map'][$field_name]['target_name'];
  }

}