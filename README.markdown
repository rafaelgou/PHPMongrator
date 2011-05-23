# PHPMigration

PHP Classes and CLI scripts to migrate from RDBMs (such as MySQL) to MongoDB

## Motivation

As I started using MongoDB, I had not found tools to migrate data from RDBMs to MongoDB.
So I decided to make this simple but useful tool.

Initial support to only MySQL, but anyone can extends PHPMongrationDriver to support
any PDO supported RDBMs (and with some efforts, even other non-PDO RDBMs).

## Features

- Runs migration through a config file using YAML format (very simple to edit)
- To start the config, has a php-cli dumper that reads the de RDBM and create
  a config file to begin
- Support to datatype mapping (really imports date, boolean, integer, float and string BSON types)
- Support to simple foreign keys relations
- Support to many to many foreing keys relations (not automatic dumped, but easyl configured by hand if you want)
- Foreing keys converted to MongoDB DBRef or not (see config file defatul->use_dbref)
- Easy ignore tables to import

## Requirements

- PDO driver for the desired RDBM (current only supports MySQL)
- PHP Cli extension
  see: <http://php.net/cli>
  or for Debian/Ubuntu users:

    apt-get install php5-cli

- PHP MongoDB extension
  see: <http://www.mongodb.org/display/DOCS/PHP+Language+Center>
  or see: <http://www.php.net/manual/en/mongo.installation.php>
  or for Debian/Ubuntu users:

    apt-get -y install python-software-properties
    add-apt-repository ppa:chris-lea/mongodb-drivers
    apt-get update
    apt-get install php5-mongo

## Install

Get the last version on Github Downloads

    wget -c https://github.com/downloads/rafaelgou/PHPMongrator/PHPMongration_1.0.0.tar.gz
    tar xzvf PHPMongration_1.0.0.tar.gz

## TODO
- Implement keep or not source pk columns on target database
  (default->keep_source_pk_columns and tables->[?]->keep_source_pk_columns
- Implement Embeddeds (tables->[?]->embeddeds)
- More debug info

## Dumper (runDumper.php)

Used to dump a basic config based on the RDBM. Current support only to MySQL.

runDumper.php is a interactive command line script. Run like this:

    cd PHPMongrantion
    lib/phpmongration/runDumper.php

and the script asks for the source info:

    PHPMongration Dumper
    ------------------------------------------------------
    @author  Rafael Goulart <rafaelgou@gmail.com>
    @license GPL3 http://www.gnu.org/licenses/lgpl-3.0.txt
    @link    http://github/rafaelgou/phpmongration
    ------------------------------------------------------
    Inform...
    - the PHP-PDO driver [mysql for a while...]:

    - the server [localhost as default]:

    - the database name:
    yourdatabase
    - the database user:
    youruser
    - the database password:
    yourpassword
    - target YAML file name [default migration.{dbname}.yml]:

the file `migration.yourdatabase.yml` now is avaliable in the current directory.

## Config File

The config file can be as large as the number of tables and columns of the database.
A sample file with full explanation is avaliable on base directory: `migration.sample.yml`
Read to understand the options.

    # Default config
    default:
    
      # Keep or not source pk columns on target database
      # TODO !! Not implemented yet!
      keep_source_pk_columns: false
    
      # Default typecasting. Options:
      # tostring  => all unknow types are imported as string
      # discover  => try to discover the type if possible
      # TODO Mysql ok, e outros?
      typecasting: tostring
    
      # Use or not MongoDBRef
      # if false, just store plain ID
      use_dbrefs: true
    
      # Drop database before import
      # REALLY DANGER!!!
      drop_database_first: false
    
      # Tables to not import - usefull to ignore relation tables 
      # that will be referenced or embbeded
      # use [ ] and not { } !!
      ignore_tables: [ ]
    
    # Database source, in a PDO Format, plus "driver" infor
    source:
      driver:   'mysql'
      dsn:      'mysql:host=localhost;dbname=sample'
      user:     'sampleuser'
      password: 'samplepassword'
      options:
       "PDO::MYSQL_ATTR_INIT_COMMAND": "SET NAMES utf8"
       "PDO::ATTR_PERSISTENT": true
    
    # Target database, MongoDB format
    # but always inform "database" separated
    target:
      server:   'mongodb://localhost:27017'
      database: 'sampledb'
    # optional, if not set, not used
    #    user:     'sampleuser'
    #    password: 'samplepassword'
      options:
        persist: true
    #      replicaSet: false
    
    tables:
    
      # This is a sample table 'blog'
      blog:
        # target to migrate data. If ommited, default to table name
        collection_target: blog
    
        # Primary key column(s). If
        # just on colunm
        pk_columns: id
        # multiple pk columns
        #pk_columns: []
    
        # Keep or not source pk columns on target database (overrides default)
        # TODO !! Not implemented yet!
        keep_source_pk_columns: false
     
        # Columns to ignore on migration
        # none
        ignore_columns: ~
        # or
        #ignore_columns: [birthday,obs]
    
        # Mapping columns names and type
        # Format:
        # source_column_name: {name: target_column_name, source_type: date, target_type: date}
        # If a field is ommited will be ignored
        columns_map:
          author_name: { target_name: authorname, source_type: string, target_type: string }
          date_publish: { target_name: datepublish, source_type: date, target_type: date }
    
        # Embbeding
        # TODO !! Not implemented yet!
        embeddeds: ~

        # One to One Referencing
        one_references:
          author_id: { table: author, pk: id }
    
        # One to Many Referencing
        many_references:
          readers:
            reference_table: blog_readers
            in_id: blog_id
            out_id: blog_id
            out_table: reader
            out_table_id: id

## Migration (runMongration.php)

Used to run the migration. Needs the full path to the YAML config file as parameter

runMongration.php is a interactive command line script. Run like this:

    cd PHPMongrantion
    lib/phpmongration/runDumper.php /FULL_PATH_TO_CONFIG_FILE/migration.yourdatabaes.yml
