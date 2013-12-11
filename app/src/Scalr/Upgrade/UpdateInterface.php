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
     * Verifies whether database has specified table
     *
     * @param   string    $table A database table name
     * @return  bool      Returns true if table does exist.
     */
    public function hasTable($table);

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
     * Checks whether specified table column is referenced by foreign key
     *
     * @param   string     $referencedTable  The table name
     * @param   string     $referencedColumn The table column name
     * @param   string     $referencedSchema optional The name of the database. If it's omitted it will use
     *                                       schema that is specified in the config.
     */
    public function hasTableReferencedColumn($referencedTable, $referencedColumn, $referencedSchema = null);
}