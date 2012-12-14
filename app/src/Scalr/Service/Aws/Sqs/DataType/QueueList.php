<?php
namespace Scalr\Service\Aws\Sqs\DataType;

use Scalr\Service\Aws\Sqs\AbstractSqsListDataType;

/**
 * QueueList
 *
 * @author    Vitaliy Demidov   <zend@i.ua>
 * @since     06.11.2012
 */
class QueueList extends AbstractSqsListDataType
{

	/**
	 * Constructor
	 *
	 * @param array|QueueData  $aListData  QueueData List
	 */
	public function __construct($aListData = null)
	{

		parent::__construct(
			$aListData,
			'queueName',
			'Scalr\\Service\\Aws\\Sqs\\DataType\\QueueData'
		);
	}

	/**
	 * {@inheritdoc}
	 * @see Scalr\Service\Aws\DataType.ListDataType::getQueryArray()
	 */
	public function getQueryArray($uriParameterName = 'QueueName')
	{
		return parent::getQueryArray($uriParameterName);
	}

}