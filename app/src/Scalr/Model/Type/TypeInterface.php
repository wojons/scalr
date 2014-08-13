<?php
namespace Scalr\Model\Type;

/**
 * TypeInterface
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0 (07.02.2014)
 */
interface TypeInterface
{
    /**
     * Gets function which is used to select column
     *
     * @return  string
     */
    public function sel();

    /**
     * Gets function which is used to find a record
     *
     * @return  string
     */
    public function wh();

    /**
     * Convert value to use in database statement
     *
     * @param   mixed   $value  The php value
     * @return  string  Retuns the value to use in database statement
     */
    public function toDb($value);

    /**
     * Convert value from database value
     *
     * @param   string  $value The database value
     * @return  mixed Returns php value
     */
    public function toPhp($value);
}