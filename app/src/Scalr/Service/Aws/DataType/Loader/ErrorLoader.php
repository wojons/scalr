<?php
namespace Scalr\Service\Aws\DataType\Loader;

use Scalr\Service\Aws\Client\ClientException;
use Scalr\Service\Aws\DataType\ErrorData;
use Scalr\Service\Aws\LoaderException;
use Scalr\Service\Aws\LoaderInterface;

/**
 * Error Loader.
 *
 * Loads ErrorData.
 *
 * @author    Vitaliy Demidov   <zend@i.ua>
 * @since     03.10.2012
 */
class ErrorLoader implements LoaderInterface
{

	/**
	 * ErrorData object
	 *
	 * @var ErrorData
	 */
	private $result;

	/**
	 * {@inheritdoc}
	 * @see Scalr\Service\Aws.LoaderInterface::getResult()
	 */
	public function getResult()
	{
		return $this->result;
	}

	/**
	 * {@inheritdoc}
	 * @see Scalr\Service\Aws.LoaderInterface::load()
	 * @return ErrorData Returns ErrorData object
	 */
	public function load($xml)
	{
		$this->result = new ErrorData();
		/* @var $simpleXmlElement \SimpleXmlElement */
		$simpleXmlElement = simplexml_load_string($xml);
		if (!isset($simpleXmlElement->Error)) {
			if (isset($simpleXmlElement->Code) && isset($simpleXmlElement->Message)) {
				//Workaround for the S3 service error
				$error = $simpleXmlElement;
			} elseif (strpos($xml, 'Service Unavailable') !== false) {
				throw new ClientException('Service Unavailable');
			} else {
				throw new LoaderException('Unexpected XML for the ErrorResponse: ' . $xml);
			}
		} else {
			$error = $simpleXmlElement->Error;
		}
		/* @var $error \SimpleXmlElement */
		$this->result->type = isset($error->Type) ? (string)$error->Type : null;
		$this->result->code = (string)$error->Code;
		$this->result->message = (string)$error->Message;
		$this->result->requestId = isset($simpleXmlElement->RequestId) ? (string)$simpleXmlElement->RequestId : null;

		return $this->result;
	}
}