<?php
namespace Scalr\Service\Aws\Client;

use Scalr\Service\Aws\DataType\ErrorData;
use Scalr\Service\AwsException;

/**
 * ClientException
 *
 * @author    Vitaliy Demidov   <zend@i.ua>
 * @since     23.09.2012
 */
class ClientException extends AwsException
{
	/**
	 * @var ErrorData
	 */
	protected $errorData;

	public function __construct ($message = null, $code = null, $previous = null)
	{
		if ($message instanceof ErrorData) {
			$this->errorData = $message;
			parent::__construct('AWS Error. ' . $this->errorData->getMessage(), $code, $previous);
			return;
		}
		parent::__construct($message, $code, $previous);
	}

	/**
	 * Gets ErrorData
	 *
	 * @return \Scalr\Service\Aws\DataType\ErrorData Returns ErrorData object
	 */
	public function getErrorData ()
	{
		return $this->errorData;
	}
}