<?php

namespace Scalr\LogCollector;

/**
 * AuditLoggerRetrieveConfigurationInterface
 *
 * @author  Vlad Dobrovolskiy
 */
interface AuditLoggerRetrieveConfigurationInterface
{
    /**
     * Gets audit logger config params
     *
     * @return  AuditLoggerConfiguration  Returns audit logger config object
     */
    public function getAuditLoggerConfig();

}
