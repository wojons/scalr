<?php

namespace Scalr\Service\Aws\Iam\DataType;

use DateTime;
use Scalr\Service\Aws\Client\ClientException;
use Scalr\Service\Aws\Iam\AbstractIamDataType;
use Scalr\Service\Aws\IamException;

/**
 * Class ServerCertificateMetadataData
 *
 * The ServerCertificate data type contains information about an AWS Server Certificate.
 *
 * @author N.V.
 */
class ServerCertificateMetadataData extends AbstractIamDataType
{

    /**
     * The stable and unique string identifying the server certificate.
     *
     * Length constraints: Minimum length of 16. Maximum length of 32.
     *
     * @var string
     */
    public $serverCertificateId;

    /**
     * The name that identifies the server certificate.
     *
     * Length constraints: Minimum length of 1. Maximum length of 128.
     *
     * @var string
     */
    public $serverCertificateName;

    /**
     * The Amazon Resource Name (ARN) specifying the instance profile.
     *
     * Length constraints: Minimum length of 20. Maximum length of 2048.
     *
     * @var string
     */
    public $arn;

    /**
     * The date on which the certificate is set to expire.
     *
     * @var DateTime
     */
    public $expiration;

    /**
     * The date when the server certificate was uploaded.
     *
     * @var DateTime
     */
    public $uploadDate;

    /**
     * The path to the server certificate.
     *
     * Length constraints: Minimum length of 1. Maximum length of 512.
     *
     * @var string
     */
    public $path;

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws\Iam.AbstractIamDataType::throwExceptionIfNotInitialized()
     */
    protected function throwExceptionIfNotInitialized()
    {
        parent::throwExceptionIfNotInitialized();
        if ($this->serverCertificateName === null) {
            throw new IamException(sprintf(
                'serverCertificateName has not been initialized for the object %s yet.', get_class($this)
            ));
        }
        if ($this->serverCertificateId === null) {
            throw new IamException(sprintf(
                'serverCertificateId has not been initialized for the object %s yet.', get_class($this)
            ));
        }
    }

    /**
     * Deletes an server certificate
     *
     * @return  boolean Returns true on success or throws an exception
     * @throws  IamException
     * @throws  ClientException
     */
    public function delete()
    {
        $this->throwExceptionIfNotInitialized();
        return $this->getIam()->serverCertificate->delete($this->serverCertificateName);
    }
}