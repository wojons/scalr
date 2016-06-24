<?php

namespace Scalr\LogCollector\Writers;

use Scalr\System\Http\Client\Request;

/**
 * Abstract Writer for 3rd party backends
 *
 * @author Constantine Karnacevych <c.karnacevych@scalr.com>
 */
abstract class AbstractWriter
{
    /**
     * How long to sleep between attempts
     */
    const BACKOFF_USLEEP = 300;

    /**
     * Max retry attempts to send the data
     */
    const MAX_RETRY_ATTEMPTS = 3;

    /**
     * Protocol to use
     *
     * @var string
     */
    protected $scheme;

    /**
     * Either path for local FS or hostname for remote connection
     *
     * @var string
     */
    protected $host;

    /**
     * Port to use if connected to a remote host
     *
     * @var int
     */
    protected $port;

    /**
     * How long to wait for reply from the remote end (seconds)
     *
     * @var int
     */
    protected $timeout;

    /**
     * Constructor. Instantiates the writer.
     *
     * @param string $proto   optional Protocol to use, e.g. http, tcp, udp. Optional.
     * @param string $path    optional Either a local FS path or hostname/IP for remote.
     * @param int    $port    optional Port to use if connected to a remote host.
     * @param int    $timeout optional How long to wait for reply from the remote end. (seconds)
     */
    public function __construct($proto = "http", $path = "localhost", $port = 8888, $timeout = 1)
    {
        $this->scheme  = $proto;
        $this->host    = $path;
        $this->port    = $port;
        $this->timeout = $timeout;
    }

    /**
     * Converts the data into backend acceptable format
     *
     * @param  array $data Prepared array of data to convert into JSON format
     * @return string A JSON formatted string
     */
    public function formatMessage(array $data)
    {
        return json_encode($data);
    }

    /**
     * Formats and sends the data to a backend using the suitable method depending on protocol
     *
     * @param  array $data Prepared array of data to send
     * @return boolean Indicates whether operation was successful
     */
    public function send(array $data)
    {
        if ($this->scheme === "http") {
            $res = $this->writeHttp($this->formatMessage($data));
        } else {
            $res = $this->writeSocket($this->formatMessage($data) . "\r\n");
        }

        return $res;
    }

    /**
     * Sleeps when an attempt to send data failed
     */
    protected function sleepWait()
    {
        usleep(static::BACKOFF_USLEEP);
    }

    /**
     * Sends HTTP request with specified params.
     *
     * @param   string   $path       optional Path URL part
     * @param   string   $body       optional Raw request POST content
     * @param   array    $queryData  optional Associative array of query-string params
     * @param   array    $headers    optional Associative array of request headers
     * @param   string   $method     optional Request method
     * @param   array    $options    optional Request options
     * @param   array    $postFields optional Associative array of post-fields
     * @return  \http\Client\Response
     */
    protected function sendRequest($path = '/', $body = '', $queryData = [], $headers = [], $method = "POST", $options = [], $postFields = [])
    {
        $request = new Request($method, $this->scheme . '://' . $this->host . ':' . $this->port . $path);

        $request->setOptions(array_merge([
            'timeout'  => $this->timeout,
            'protocol' => '1.1'
        ], $options));

        if (!empty($queryData)) {
            $request->addQuery($queryData);
        }

        if (!empty($body)) {
            $request->append($body);
        } else if (!empty($postFields)) {
            $request->append($postFields);
        }

        if (!empty($headers)) {
            $request->addHeaders(array_merge([
                "Accept"       => "*/*",
                "Content-Type" => "application/json; charset=utf-8",
                "Host"         => $this->host . ':' . $this->port,
                "Connection"   => "close"
            ], $headers));
        }

        return \Scalr::getContainer()->http->sendRequest($request);
    }

    /**
     * Sends data over HTTP protocol
     *
     * @param  string $message A prepared message for sending
     * @return boolean Indicates whether operation was successful
     */
    protected function writeHttp($message)
    {
        $response = $this->sendRequest('/', $message);

        $statusCode = $response->getResponseCode();

        return $statusCode > 199 && $statusCode < 300;
    }

    /**
     * Sends data over a socket, using one of TCP, UDP etc. protocols
     *
     * @param  string $message A prepared message for sending
     * @return boolean Indicates whether operation was successful
     */
    protected function writeSocket($message)
    {
        $ret = false;
        $length = strlen($message);
        $written = 0;
        $attempts = self::MAX_RETRY_ATTEMPTS;

        $socket = stream_socket_client(
            $this->scheme . "://" . $this->host . ":" . $this->port,
            $errno,
            $errmsg,
            $this->timeout,
            \STREAM_CLIENT_CONNECT | \STREAM_CLIENT_ASYNC_CONNECT
        );

        if ($socket !== false) {
            stream_set_timeout($socket, $this->timeout);
            stream_set_blocking($socket, false);
            stream_set_write_buffer($socket, 0);

            do {
                $bytes = fwrite($socket, $message);

                if (empty($bytes)) {
                    if (--$attempts === 0) {
                        trigger_error("Attempts writing to a backend exceeded", E_USER_WARNING);
                        break;
                    }

                    if ($bytes === false) {
                        trigger_error("Couldn't write. Probably the PIPE is broken or socket closed", E_USER_WARNING);
                        break;
                    } elseif ($bytes === "") {
                        trigger_error("Connection aborted", E_USER_WARNING);
                        break;
                    }

                    $errors = error_get_last();

                    if ($errors) {
                        if (isset($errors['message']) && strpos($errors['message'], 'errno=32 ') !== false) {
                            fclose($socket);
                            $socket = stream_socket_client(
                                $this->scheme . "://" . $this->host . ":" . $this->port,
                                $errno,
                                $errmsg,
                                $this->timeout,
                                \STREAM_CLIENT_CONNECT | \STREAM_CLIENT_ASYNC_CONNECT
                            );
                        } else if (isset($errors['message']) && strpos($errors['message'], 'errno=11 ') !== false) {
                            // ignore EAGAIN message
                        } else {
                            trigger_error("An unhandled error detected " . $errors['message'], E_USER_WARNING);
                        }
                    }

                    $this->sleepWait();
                } else {
                    $written += $bytes;

                    if ($written < $length) {
                        $message = substr($message, 0, $bytes);
                    } else {
                        $ret = true;
                    }
                }

            } while ($written < $length);

            fclose($socket);
        }

        return $ret;
    }

    /**
     * Writes data to a local file
     *
     * @param  string $message A prepared message for sending
     * @return boolean Indicates whether operation was successful
     */
    protected function writeFile($message)
    {
        return file_put_contents($this->scheme . "://" . $this->host, $message, FILE_APPEND) == strlen($message);
    }
}
