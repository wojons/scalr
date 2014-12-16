<?php

namespace Scalr\Upgrade;

/**
 * UpdateInterface
 *
 * This class declares the interface for the update classes.
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    4.5.0 (09.10.2013)
 */
interface UpdateInterface
{
    /**
     * mysql database update type
     */
    const TYPE_MYSQL = 'mysql';

    /**
     * filesystem update type
     */
    const TYPE_FILESYSTEM = 'filesystem';

    /**
     * Checks whether this update is applied.
     *
     * Verifies whether current update has already been applied to this install.
     * This ensures avoiding the duplications. Implementation of this method should give
     * the definite answer to question "has been this update applied or not?".
     * This method may not be overridden from AbstractUpdate class.
     * In this case it will always return false.
     *
     * @param   int  $stage  optional The stage number
     * @return  bool Returns true if the update has already been applied.
     */
    public function isApplied($stage = null);

    /**
     * Validates an environment before it will try to apply the update.
     *
     * Validates current environment or inspects circumstances that is expected to be in the certain state
     * before the update is applied. This method may not be overridden from AbstractUpdate class
     * which means current update is always valid.
     *
     * @param   int  $stage  optional The stage number
     * @return  bool Returns true if the environment meets the requirements.
     */
    public function validateBefore($stage = null);

    /**
     * Performs upgrade literally.
     *
     * Implementation of this method performs update steps needs to be taken
     * to accomplish upgrade successfully.
     *
     * If there are any error during an execution of this scenario it must
     * throw an exception.
     *
     * @param   int  $stage  optional The stage number
     * @throws  \Exception
     */
    public function run($stage = null);

    /**
     * Gets current status of the update
     *
     * @return int|null   Returns current status of the update.
     */
    public function getStatus();

    /**
     * Sets the status of the update
     *
     * @param   int     $status  The status of the update.
     *                           It may be \Scalr\Upgrade\Entity\AbstractUpgradeEntity::STATUS_OK for example.
     * @return  AbstractUpdate
     */
    public function setStatus($status);

    /**
     * Gets update name.
     *
     * By default, update name is the class name if it isn't overridden.
     *
     * @return  string Returns update name.
     */
    public function getName();

    /**
     * Verifies whether specified table has index.
     *
     * @param   string     $table A database table name
     * @param   string     $index An index name
     * @return  bool       Returns true if index does exist.
     */
    public function hasTableIndex($table, $index);

    /**
     * Checks whether the database table has specified column.
     *
     * @param   string     $table   A database table name
     * @param   string     $column  A column name
     * @return  bool       Returns true if column does exist.
     */
    public function hasTableColumn($table, $column);

    /**
     * Checks whether the database table has specified column with given type
     *
     * @param   string    $table  The name of the database table
     * @param   string    $column The name of the column
     * @param   string    $type   A column type ("varchar(24)" for example or "datetime")
     * @param   string    $schema optional The name of the database. If it's omitted will be used schema
     *                                     that is specified in the config.
     * @return  bool      Returns true if specified column is defined with the given type
     */
    public function hasTableColumnType($table, $column, $type, $schema = null);

    /**
     * Checks whether the table column has specified default value
     *
     * @param   string    $table   The name of the database table
     * @param   string    $column  The name of the column
     * @param   string    $default Default value for the column definition
     * @param   string    $schema  optional The name of the database. If it's omitted will be used schema
     *                             that is specified in the config.
     * @return  bool      Returns  true if specified column is defined with the given type
     */
    public function hasTableColumnDefault($table, $column, $default, $schema = null);

    /**
     * Checks whether the table has auto incremented primary key
     *
     * @param   string    $table   The name of the database table
     * @param   string    $schema  optional The name of the database. If it's omitted will be used schema
     *                             that is specified in the config.
     * @return  bool      Returns  true if specified table has auto incremented primary key
     */
    public function hasTableAutoIncrement($table, $schema = null);

    /**
     * Gets column definition for the specified table and database shcema
     *
     * @param   string    $table   The name of the database table
     * @param   string    $column  The name of the column
     * @param   string    $schema  optional The name of the database. If it's omitted will be used schema
     *                             that is specified in the config.
     * @return  \Scalr\Model\Entity\InformationSchema\ColumnEntity  Returns column definition object
     */
    public function getTableColumnDefinition($table, $column, $schema = null);

    /**
     * Verifies whether database has specified table
     *
     * @param   string    $table     A database table name
     * @param   string    $database  optional The specified database or default
     * @return  bool      Returns true if table exists.
     */
    public function hasTable($table, $database = null);

    /**
     * Verifies whether specified database exists
     *
     * @param   string    $database
     * @return  bool      Returns true if databse exists
     */
    public function hasDatabase($database);

    /**
     * Check whether the database table has specified constraint
     *
     * @param   string     $constraintName The name of the constraint
     * @param   string     $table          The name of the database table
     * @param   string     $schema         optional The name of the database. If it's omitted will be used schema
     *                                     that is specified in the config.
     * @return  bool       Returns true if schema exists or false otherwise
     */
    public function hasTableForeignKey($constraintName, $table, $schema = null);

    /**
     * Gest specified referential constraint
     *
     * @param   string    $constraintName The name of the constraint
     * @param   string    $table          The name of the table
     * @param   string    $schema         optional The name of the database. If it's omitted will be used schema
     *                                    that is specified in the config.
     * @return  array     Returns referential constraint record
     */
    public function getTableConstraint($constraintName, $table, $schema = null);

    /**
     * Checks whether specified table column is referenced by foreign key
     *
     * @param   string     $referencedTable  The table name
     * @param   string     $referencedColumn The table column name
     * @param   string     $referencedSchema optional The name of the database. If it's omitted it will use
     *                                       schema that is specified in the config.
     */
    public function hasTableReferencedColumn($referencedTable, $referencedColumn, $referencedSchema = null);

    /**
     * This method returns true if upgrade process should ignore changes in the update script.
     *
     * This will prevent re-execution of the update if its content is changed.
     *
     * @return  bool      Returns false by default.
     */
    public function getIgnoreChanges();

    /**
     * Returns not false if this upgrade should be silently refused to excecute
     * without saving a status to database
     *
     * Upgrade will be performed only when it passes all conditions.
     *
     * @return   string|bool     Returns the reason
     */
    public function isRefused();
}