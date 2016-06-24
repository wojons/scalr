<?php

namespace Scalr\Server\Import;

use Scalr\Exception\ValidationErrorException;
use Scalr\Exception\ServerImportException;
use Scalr\Model\Entity;

/**
 * Server import interface
 *
 * @author  Igor Vodiasov <invar@scalr.com>
 * @since   5.11.5 (22.01.2016)
 */
interface ServerImportInterface
{
    /**
     * @param   string              $cloudInstanceId                CloudInstanceId of server
     * @param   array               $tags               optional    Additional tags [key=>value]
     * @return  Entity\Server       Return Server entity on success otherwise throw exception
     * @throws  ValidationErrorException
     * @throws  ServerImportException
     */
    public function import($cloudInstanceId, $tags = []);
}
