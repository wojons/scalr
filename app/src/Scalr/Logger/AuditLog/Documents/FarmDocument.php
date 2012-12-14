<?php

namespace Scalr\Logger\AuditLog\Documents;

/**
 * Farm document
 *
 * @author   Vitaliy Demidov   <zend@i.ua>
 * @since    31.10.2012
 */
class FarmDocument extends AbstractAuditLogDocument
{
	/**
	 * Farmid
	 *
	 * @var int
	 */
	private $farmid;

	/**
	 * Farm name
	 *
	 * @var string
	 */
	private $name;

	public function __sleep()
	{
		return array('farmid', 'name');
	}

	/**
	 * Convenient constructor
	 *
	 * @param   int        $farmid optional A farm id
	 * @param   int        $name   optional A farm name
	 */
	public function __construct($farmid = null, $name = null)
	{
		parent::__construct();
		$this
			->setFarmid($farmid)
			->setName($name)
		;
	}

	/**
	 * Gets a new document by DBFarm object
	 *
	 * @param   \DBFarm      $dbfarm DBFarm object
	 * @return  FarmDocument Returns new FarmDocument
	 */
	public static function createFromDBFarm (\DBFarm $dbfarm)
	{
		return new self ($dbfarm->ID, $dbfarm->Name);
	}

	/**
	 * Gets farm id
	 *
	 * @return   int Returns farmid
	 */
	public function getFarmid()
	{
		return $this->farmid;
	}

	/**
	 * Gets farm name
	 *
	 * @return   string Returns farm name
	 */
	public function getName()
	{
		return $this->name;
	}

	/**
	 * Sets farm id.
	 *
	 * @param   int   $farmid  A farm id
	 * @return  FarmDocument Returns Farm document
	 */
	public function setFarmid($farmid)
	{
		$this->farmid = $farmid;

		return $this;
	}

	/**
	 * Sets farm name.
	 *
	 * @param   string   $name  A farm name
	 * @return  FarmDocument Returns Farm document
	 */
	public function setName($name)
	{
		$this->name = $name;
	}
}