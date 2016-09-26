<?php

namespace Scalr\Service\Azure\Services\Compute\DataType;

use Scalr\Service\Azure\DataType\AbstractDataType;

/**
 * SshData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.8.6
 *
 * @property  \Scalr\Service\Azure\Services\Compute\DataType\PublicKeyList   $publicKeys Specifies the collection of SSH public keys (i.e. sshKey)
 *
 */
class SshData extends AbstractDataType
{
    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = ['publicKeys'];

    /**
     * Constructor
     *
     * @param   array|PublicKeyList    $publicKeys    Specifies the collection of SSH public keys (i.e. sshKey)
     */
    public function __construct($publicKeys)
    {
        $this->setPublicKeys($publicKeys);
    }

    /**
     * Sets PublicKeyList
     *
     * @param   array|PublicKeyList $publicKeys  Specifies the collection of SSH public keys (i.e. sshKey)
     * @return  SshData
     */
    public function setPublicKeys($publicKeys = null)
    {
        if (!($publicKeys instanceof PublicKeyList)) {
            $publicKeyList = new PublicKeyList();

            foreach ($publicKeys as $publicKey) {
                if (!($publicKey instanceof PublicKeyData)) {
                    $publicKeyData = PublicKeyData::initArray($publicKey);
                } else {
                    $publicKeyData = $publicKey;
                }

                $publicKeyList->append($publicKeyData);
            }
        } else {
            $publicKeyList = $publicKeys;
        }

        return $this->__call(__FUNCTION__, [$publicKeyList]);
    }

}