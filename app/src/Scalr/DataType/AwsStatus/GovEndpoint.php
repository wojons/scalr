<?php

namespace Scalr\DataType\AwsStatus;

/**
 * Class GovEndpoint
 * @author  N.V.
 */
class GovEndpoint extends Endpoint
{

    /**
     * HTML markup
     *
     * @var array
     */
    public $compliance = array(
        'us-gov-west-1' => array(
            'name'   => 'GC_block',
            'filter' => 'GovCloud'
        )
    );

    /**
     * Endpoint URL
     *
     * @var string
     */
    public $statUrl = 'http://status.aws.amazon.com/govcloud';

    /**
     * Cache file name
     *
     * @var string
     */
    public $cacheFile = 'aws.status.gov.cxml';
}
