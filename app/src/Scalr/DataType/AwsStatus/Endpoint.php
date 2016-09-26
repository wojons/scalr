<?php

namespace Scalr\DataType\AwsStatus;

/**
 * Class Endpoint
 *
 * @author  N.V.
 */
class Endpoint
{

    /**
     * HTML markup
     *
     * @var array
     */
    public $compliance = [
        'us-east-1'      => [
            'name'   => 'NA_block',
            'filter' => ['N. Virginia', 'US Standard']
        ],
        'us-west-1'      => [
            'name'   => 'NA_block',
            'filter' => 'N. California'
        ],
        'us-west-2'      => [
            'name'   => 'NA_block',
            'filter' => 'Oregon'
        ],
        'sa-east-1'      => [
            'name'   => 'SA_block',
            'filter' => ''
        ],
        'eu-west-1'      => [
            'name'   => 'EU_block',
            'filter' => 'Frankfurt'
        ],
        'eu-central-1'   => [
            'name'   => 'EU_block',
            'filter' => 'Frankfurt'
        ],
        'ap-southeast-1' => [
            'name'   => 'AP_block',
            'filter' => 'Singapore'
        ],
        'ap-southeast-2' => [
            'name'   => 'AP_block',
            'filter' => 'Sydney'
        ],
        'ap-northeast-1' => [
            'name'   => 'AP_block',
            'filter' => 'Tokyo'
        ],
        'ap-northeast-2' => [
            'name'   => 'AP_block',
            'filter' => 'Seoul'
        ]
    ];

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
