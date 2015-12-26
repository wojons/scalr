<?php

namespace Scalr\Util\Logger\Writers;

use Scalr\Util\Logger\AbstractWriter;

/**
 * A writer for the Logstash
 *
 * @author Constantine Karnacevych <c.karnacevych@scalr.com>
 */
class Logstash extends AbstractWriter
{
    /**
     * {@inheritdoc}
     */
    public function send(array $data)
    {
        return parent::send([
            "@source"    => "Scalr",
            "@type"      => $data['type'],
            "@tags"      => $data["extra"]["tags"],
            "@fields"    => $data["extra"],
            "@timestamp" => $data["timestamp"],
        ]);
    }
}
