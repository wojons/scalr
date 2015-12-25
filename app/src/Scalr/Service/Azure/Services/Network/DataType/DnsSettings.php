<?php

namespace Scalr\Service\Azure\Services\Network\DataType;

use Scalr\Service\Azure\DataType\AbstractDataType;

/**
 * DnsSettings
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.8.6
 *
 */
class DnsSettings extends AbstractDataType
{
    /**
     * The concatenation of the domain name label and the regionalized DNS zone make up the fully qualified domain name associated with the public IP address.
     * If a domain name label is specified, an A DNS record is created for the public IP in the Microsoft Azure DNS system.
     *
     * @var string
     */
    public $domainNameLabel;

    /**
     * Fully qualified domain name of the A DNS record associated with the public IP.
     * This is the concatenation of the domainNameLabel and the regionalized DNS zone
     *
     * @var string
     */
    public $fqdn;

    /**
     * A fully qualified domain name that resolves to this public IP address.
     * If the reverseFqdn is specified, then a PTR DNS record is created pointing from the IP address in the in-addr.arpa domain to the reverse FQDN.
     *
     * @var int
     */
    public $reverseFqdn;

}