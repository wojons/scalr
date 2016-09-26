<?php

use Scalr\Exception\Http\BadRequestException;

abstract class ScalrEnvironment extends ScalrRESTService
{
    const LATEST_VERSION = '2015-04-10';

    /**
     * @var DBServer
     */
    protected $DBServer;
    
    public $debugObject = null;

    /**
     * Query Environment object and return result;
     */
    public function Query($operation, array $args)
    {
        $this->debugObject = new stdClass();
        
        $this->SetRequest($args);

        // Get Method name by operation
        $method_name = str_replace(" ", "", ucwords(str_replace("-", " ", $operation)));

        // Check method
        if (method_exists($this, $method_name)) {
            // Call method
            try {
                $this->DBServer = $this->GetCallingInstance();

                $result = call_user_func(array($this, $method_name));
                if ($result instanceof DOMDocument) {
                    header("Content-Type: text/xml");
                    return $result->saveXML();
                } else if (is_object($result)) {
                    header("Content-Type: application/json");
                    
                    $retval = new stdClass();
                    $retval->result = new stdClass();
                    
                    $serializer = Scalr_Messaging_JsonSerializer::getInstance();
                    $serializer->walkSerialize($result, $retval->result, 'underScope');
                    
                    return json_encode($retval->result);
                } else {
                    throw new Exception(sprintf("%s:%s() returns invalid response. DOMDocument expected.",
                        get_class($this),
                        $method_name
                    ));
                }
            } catch (\Scalr\Exception\Http\HttpException $e) {
                throw $e;
            } catch (DOMException $e) {
                throw $e;
            } catch (Exception $e) {
                throw new Exception(sprintf(_("Cannot retrieve environment by operation '%s': %s"),
                    $operation,
                    $e->getMessage()
                ));
            }
        } else {
            throw new BadRequestException(sprintf("Operation '%s' is not supported", $operation));
        }
    }

    /**
     * Create Base DOMDocument for response
     * @return DOMDocument
     */
    protected function CreateResponse()
    {
        $DOMDocument = new DOMDocument('1.0', 'utf-8');
        $DOMDocument->loadXML('<response></response>');

        return $DOMDocument;
    }
}
