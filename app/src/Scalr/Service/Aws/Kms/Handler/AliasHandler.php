<?php
namespace Scalr\Service\Aws\Kms\Handler;

use Scalr\Service\Aws;
use Scalr\Service\Aws\Client\ClientException;
use Scalr\Service\Aws\Kms\DataType\AliasList;

/**
 * Alias hadler of KMS service
 *
 * @author    Vitaliy Demidov   <vitaliy@scalr.com>
 * @since     5.9  (24.06.2015)
 *
 * @method    \Scalr\Service\Aws\Kms\DataType\AliasList list()
 *            list($marker = null, $maxRecords = null)
 *            Lists all of the key aliases in the account.
 */
class AliasHandler extends Aws\Kms\AbstractKmsHandler
{
    /**
     * ListAliases API call
     *
     * Lists all of the key aliases in the account.
     *
     * @param   string   $marker     optional Set it to the value of the NextMarker in the response you just received
     * @param   int      $maxRecords optional Maximum number of keys you want listed in the response [1, 1000]
     * @return  AliasList
     * @throws  ClientException
     */
    private function _list($marker = null, $maxRecords = null)
    {
        return $this->kms->getApiHandler()->listAliases($marker, $maxRecords);
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
