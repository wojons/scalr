<?php
namespace Scalr\Service\Aws\Kms\Handler;

use Scalr\Service\Aws;
use Scalr\Service\Aws\Client\ClientException;

/**
 * Grant hadler of KMS service
 *
 * @author    Vitaliy Demidov   <vitaliy@scalr.com>
 * @since     5.9  (24.06.2015)
 *
 * @method    \Scalr\Service\Aws\Kms\DataType\GrantList list()
 *            list($keyId, $marker = null, $maxRecords = null)
 *            List the grants for a specified key.
 */
class GrantHandler extends Aws\Kms\AbstractKmsHandler
{
    /**
     * ListGrants API call
     *
     * List the grants for a specified key.
     *
     * @param   string   $keyId      A unique identifier for the customer master key.
     *          This value can be a globally unique identifier or the fully specified ARN to a key.
     *          - Key ARN Example - arn:aws:kms:us-east-1:123456789012:key/12345678-1234-1234-1234-123456789012
     *          - Globally Unique Key ID Example - 12345678-1234-1234-1234-123456789012
     *
     * @param   string   $marker     optional Set it to the value of the NextMarker in the response you just received
     * @param   int      $maxRecords optional Maximum number of keys you want listed in the response [1, 1000]
     * @return  \Scalr\Service\Aws\Kms\DataType\GrantList
     * @throws  ClientException
     */
    private function _list($keyId, $marker = null, $maxRecords = null)
    {
        return $this->kms->getApiHandler()->listGrants($keyId, $marker, $maxRecords);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Service\Aws\AbstractServiceRelatedType::__call()
     */
    public function __call($name, $arguments)
    {
        if ($name == 'list') {
            return call_user_func_array([$this, '_list'], $arguments);
        } else {
            return parent::__call($name, $arguments);
        }
    }
}
