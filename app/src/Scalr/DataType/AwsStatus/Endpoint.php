<?php

namespace Scalr\DataType\AwsStatus;

/**
 * Class Endpoint
 * @author  N.V.
 */
class Endpoint
{

    /**
     * HTML markup
     *
     * @var array
     */
    public $compliance = array(
        'us-east-1'      => array(
            'name'   => 'NA_block',
            'filter' => array(
                'N. Virginia',
                'US Standard'
            )
        ),
        'us-west-1'      => array(
            'name'   => 'NA_block',
            'filter' => 'N. California'
        ),
        'us-west-2'      => array(
            'name'   => 'NA_block',
            'filter' => 'Oregon'
        ),
        'sa-east-1'      => array(
            'name'   => 'SA_block',
            'filter' => ''
        ),
        'eu-west-1'      => array(
            'name'   => 'EU_block',
            'filter' => 'Frankfurt'
        ),
        'eu-central-1'   => array(
            'name'   => 'EU_block',
            'filter' => 'Frankfurt'
        ),
        'ap-southeast-1' => array(
            'name'   => 'AP_block',
            'filter' => 'Singapore'
        ),
        'ap-southeast-2' => array(
            'name'   => 'AP_block',
            'filter' => 'Sydney'
        ),
        'ap-northeast-1' => array(
            'name'   => 'AP_block',
            'filter' => 'Tokyo'
        )
    );

    /**
     * Endpoint URL
     *
     * @var string
     */
    public $statUrl = 'http://status.aws.amazon.com';

    /**
     * Cache file name
     *
     * @var string
     */
    public $cacheFile = 'aws.status.cxml';
}
