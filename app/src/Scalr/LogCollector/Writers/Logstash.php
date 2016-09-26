<?php

namespace Scalr\LogCollector\Writers;

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
            "@tags"      => $data["extra"]["tags"],
            "@fields"    => $data["extra"],
            "@timestamp" => $data["extra"]["timestamp"],
        ]);
    }
}
