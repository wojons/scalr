<?php

namespace Scalr\Service\Aws\Iam\Handler;

use Scalr\Service\Aws\Client\ClientException;
use Scalr\Service\Aws\Iam\AbstractIamHandler;
use Scalr\Service\Aws\Iam\DataType\ServerCertificateMetadataData;
use Scalr\Service\Aws\IamException;

/**
 * ServerCertificateHandler
 *
 * @author N.V.
 */
class ServerCertificateHandler extends AbstractIamHandler
{

    /**
     * Upload server certificate that have the specified path prefix
     *
     * @param   string   $certificateBody       The contents of the public key certificate in PEM-encoded format
     * @param   string   $privateKey            The contents of the private key in PEM-encoded format
     * @param   string   $serverCertificateName The name for the server certificate. The name of the certificate cannot contain any spaces
     * @param   string   $certificateChain      optional The contents of the certificate chain. This is typically a concatenation of the PEM-encoded public key certificates of the chain
     * @param   string   $pathPrefix            optional The path for the server certificate
     * @return  ServerCertificateMetadataData   Returns ServerCertificateMetadataData object on success or throws an exception
     * @throws  IamException
     * @throws  ClientException
     */
    public function upload($certificateBody, $privateKey, $serverCertificateName, $certificateChain = null, $pathPrefix = null)
    {
        return $this->getIam()->getApiHandler()->uploadServerCertificate(
            $certificateBody, $privateKey, $serverCertificateName, $certificateChain, $pathPrefix
        );
    }

    /**
     * List server certificates action
     *
     * Lists the server certificates that have the specified path prefix
     *
     * @param   string $pathPrefix  optional The path prefix for filtering the results
     * @param   string $marker      optional Set this parameter to the value of the Marker element in the response you just received.
     * @param   string $maxItems    optional Maximum number of the records you want in the response
     * @return  ServerCertificateMetadataList  Returns ServerCertificateMetadataList object on success or throws an exception
     * @throws  IamException
     * @throws  ClientException
     */
    public function describe($pathPrefix = null, $marker = null, $maxItems = null)
    {
        return $this->getIam()->getApiHandler()->listServerCertificates($pathPrefix, $marker, $maxItems);
    }

    /**
     * Delete server certificate action
     *
     * Deletes the specified server certificate
     * NOTE! If you are using a server certificate with Elastic Load Balancing, deleting the certificate could have implications for your application
     *
     * @param   string $serverCertificateName The name of the server certificate you want to delete.
     * @return  bool Returns TRUE if server certificate has been successfully removed.
     * @throws  IamException
     * @throws  ClientException
     */
    public function delete($serverCertificateName)
    {
        return $this->getIam()->getApiHandler()->deleteServerCertificate($serverCertificateName);
    }
}
