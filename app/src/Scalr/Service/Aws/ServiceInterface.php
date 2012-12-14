<?php
namespace Scalr\Service\Aws;

/**
 * ServiceInterface
 *
 * Descrbies common interface for all Amazon AWS services such as EC2, ELB
 *
 * @author    Vitaliy Demidov   <zend@i.ua>
 * @since     20.09.2012
 */
interface ServiceInterface
{

	/**
	 * Gets url for current region
	 *
	 * @return string Returns url for Query API for current region
	 */
	public function getUrl();

	/**
	 * Gets API Version that is being used in current version of the soft.
	 *
	 * @return  string  Returns current API Version in YYYYMMDD format
	 */
	public function getCurrentApiVersion();

	/**
	 * Gets API Version
	 *
	 * @return string  Returns API Version
	 */
	public function getApiVersion();

	/**
	 * Sets API version
	 *
	 * @param    string   $apiVersion  API Version
	 * @throws   ElbException
	 */
	public function setApiVersion($apiVersion);

	/**
	 * Gets available API versions.
	 *
	 * @return array  Returns array of available API versions.
	 */
	public function getAvailableApiVersions();

	/**
	 * Gets an EntityManager
	 *
	 * @return EntityManager
	 */
	public function getEntityManager();

	/**
	 * Gets a list of entities.
	 *
	 * Gets a list of allowed entities that handle API requests for associated objects.
	 *
	 * @return array Returns the names of the allowed entities.
	 */
	public function getAllowedEntities();

	/**
	 * Gets an Aws instance which is associated with this service.
	 *
	 * @return \Scalr\Service\Aws  Returns Aws instance which is associated with this service.
	 */
	public function getAws();
}