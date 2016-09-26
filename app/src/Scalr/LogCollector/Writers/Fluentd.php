<?php

namespace Scalr\LogCollector\Writers;

/**
 * A writer for the FluentD
 *
 * @author Constantine Karnacevych <c.karnacevych@scalr.com>
 */
class Fluentd extends AbstractWriter
{

    /**
     * {@inheritdoc}
     * @see AbstractWriter::send()
     */
    public function send(array $data)
    {
        if ($this->scheme === "http") {
            return $this->writeHttp($data);
        } elseif (in_array($this->scheme, ["tcp", "udp", "udg", "unix"])) {
            return parent::send([
                (empty($data["tag"]) ? "" : $data["tag"] . ".") . $data["message"],
                $data["extra"]["timestamp"],
                $data["extra"],
            ]);
        } else {
            return parent::send($data);
        }
    }

    /**
     * {@inheritdoc}
     * @see AbstractWriter::send()
     */
    protected function writeHttp($message)
    {
        $message['tag'] = isset($message['tag']) ? "{$message['tag']}." : '';

        $response = $this->sendRequest(
            "/" . $message["tag"] . $message["message"],
            '',
            ['json' => json_encode($message['extra'])]
        );

        $statusCode = $response->getResponseCode();

        return $statusCode > 199 && $statusCode < 300;
    }
}
