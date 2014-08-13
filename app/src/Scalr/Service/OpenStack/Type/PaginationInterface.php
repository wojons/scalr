<?php
namespace Scalr\Service\OpenStack\Type;

use Scalr\Service\OpenStack\Exception\RestClientException;

/**
 * PaginationInterface
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0 (21.01.2014)
 */
interface PaginationInterface
{
    /**
     * Checks whether result set has a reference to the previous page
     *
     * @return  bool   Returns true if restult set has a reference to the previous page
     */
    public function hasNextPage();

    /**
     * Checks whether result set has a reference to the next page
     *
     * @return  bool   Returns true if restult set has a reference to the next page
     */
    public function hasPreviousPage();

    /**
     * Gets the next page list
     *
     * @return  PaginationIterface|bool Returns the result set or false if there is no next page
     * @throws  RestClientException
     */
    public function getNextPage();

    /**
     * Gets the previous page list
     *
     * @return  PaginationIterface|bool Returns the result set or false if there is no previous page
     * @throws  RestClientException
     */
    public function getPreviousPage();
}