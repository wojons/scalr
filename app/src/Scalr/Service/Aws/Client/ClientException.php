<?php
namespace Scalr\Service\Aws\Client;

use http\Client\Request;
use http\QueryString;
use Scalr\Service\Aws\DataType\ErrorData;
use Scalr\Service\AwsException;

/**
 * ClientException
 *
 * @author    Vitaliy Demidov   <vitaliy@scalr.com>
 * @since     23.09.2012
 */
class ClientException extends AwsException
{
    /**
     * @var ErrorData
     */
    protected $errorData;

    /**
     * API Action
     *
     * @var string
     */
    protected $apicall;


    public function __construct($message = null, $code = null, $previous = null)
    {
        if ($message instanceof ErrorData) {
            $this->errorData = $message;

            //Action is the AWS Action name
            $this->apicall = null;
            //We need to fetch Action name from the request if possible.
            if ($message->request instanceof Request) {
                if ($message->request->getRequestMethod() == "POST") {
                    $postfields = (new QueryString($message->request->getBody()))->toArray();
                    if (!empty($postfields['Action'])) {
                        $this->apicall = $postfields['Action'];
                    }
                }
            }
            //Trying to fetch Action from the backtrace
            if ($this->apicall === null) {
                foreach (debug_backtrace() as $arr) {
                    if (empty($arr['class']) ||
                        !preg_match("/\\\\Service\\\\Aws\\\\.+Api$/", $arr['class']) ||
                        $arr['type'] !== '->') {
                        continue;
                    }
                    $this->apicall = ucfirst($arr['function']);
                    break;
                }
            }

            if ($this->errorData->getCode() == ErrorData::ERR_REQUEST_LIMIT_EXCEEDED) {
                $this->errorData->message = $this->errorData->getMessage()
                  . " (Request number for the current session is " . $this->errorData->queryNumber . ")";
            }

            parent::__construct(
                sprintf(
                    'AWS Error.%s %s (RequestID: %s)',
                    ($this->apicall ? sprintf(" Request %s failed.", $this->apicall) : ''),
                    $this->errorData->getMessage(),
                    $this->errorData->getRequestId()
                ),
                $code,
                $previous
            );
        } else {
            parent::__construct($message, $code, $previous);
        }
    }

    /**
     * Gets ErrorData
     *
     * @return \Scalr\Service\Aws\DataType\ErrorData Returns ErrorData object
     */
    public function getErrorData()
    {
        return $this->errorData;
    }

    /**
     * Gets API Action name which causes error
     *
     * @return   string Returns API Action name which causes error
     */
    public function getApiCall()
    {
        return $this->apicall;
    }
}