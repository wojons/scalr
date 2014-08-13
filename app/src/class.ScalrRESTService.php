<?php

use Scalr\Exception\Http\BadRequestException;
use Scalr\Exception\Http\ForbiddenException;

abstract class ScalrRESTService
{
    const HASH_ALGO = 'SHA1';

    protected $Request;

    /**
     * Arguments
     * @var array
     */
    protected $Args;

    /**
     * @var \ADODB_mysqli
     */
    protected $DB;

    protected $Logger;

    public function __construct()
    {
        $this->DB = \Scalr::getDb();
        $this->Logger = Logger::getLogger(__CLASS__);
    }

    /**
     * Set request data
     * @param $request
     * @return void
     */
    public function SetRequest($request)
    {
        $this->Request = $request;
        $this->Args = array_change_key_case($request, CASE_LOWER);
    }

    protected function GetArg($name)
    {
        return $this->Args[strtolower($name)];
    }

    /**
     * Verify Calling Instance
     * @return DBServer
     */
    protected function GetCallingInstance()
    {
        if (empty($_SERVER['HTTP_X_SIGNATURE']))
            throw new BadRequestException("ami-scripts roles cannot execute scripts anymore. Please upgrade your roles to scalarizr: http://scalr.net/blog/announcements/ami-scripts/");
        else
            return $this->ValidateRequestBySignature($_SERVER['HTTP_X_SIGNATURE'], $_SERVER['HTTP_DATE'], $_SERVER['HTTP_X_SERVER_ID']);
    }

    protected function ValidateRequestByFarmHash($farmid, $instanceid, $authhash)
    {
        try {
            $DBFarm = DBFarm::LoadByID($farmid);
            $DBServer = DBServer::LoadByPropertyValue(EC2_SERVER_PROPERTIES::INSTANCE_ID, $instanceid);
        } catch (Exception $e) {
            if (!$DBServer) {
                throw new BadRequestException(sprintf(
                    "Cannot verify the instance you are making request from. "
                  . "Make sure that farmid, instance-id and auth-hash parameters are specified."
                ));
            }
        }

        if ($DBFarm->Hash != $authhash || $DBFarm->ID != $DBServer->farmId) {
            throw new BadRequestException(sprintf(
                "Cannot verify the instance you are making request from. "
              . "Make sure that farmid (%s), instance-id (%s) and auth-hash (%s) parameters are valid.",
                $farmid, $instanceid, $authhash
            ));
        }

        return $DBServer;
    }

    protected function ValidateRequestBySignature($signature, $timestamp, $serverid)
    {
        ksort($this->Request);
        $string_to_sign = "";
        foreach ($this->Request as $k=>$v)
            $string_to_sign.= "{$k}{$v}";

        $string_to_sign .= $timestamp;

        try {
            $DBServer = DBServer::LoadByID($serverid);
        } catch (Exception $e) {
            if (stristr($e->getMessage(), 'not found in database')) {
                throw new ForbiddenException($e->getMessage());
            }
            throw $e;
        }

        $auth_key = $DBServer->GetKey(true);

        $valid_sign = base64_encode(hash_hmac(self::HASH_ALGO, $string_to_sign, $auth_key, 1));
        if ($valid_sign != $signature)
            throw new ForbiddenException("Signature doesn't match");

        return $DBServer;
    }
}
