<?php

class Scalr_Net_Scalarizr_Services_System extends Scalr_Net_Scalarizr_Client
{
    public function __construct(DBServer $dbServer, $port = 8010) {
        $this->namespace = "system";
        parent::__construct($dbServer, $port);
    }

    public function getHostname() {
        return $this->request("get_hostname")->result;
    }

    public function setHostname($hostname) {
        $params = new stdClass();
        $params->hostname = $hostname;

        return $this->request("set_hostname", $params)->result;
    }

    public function callAuthShutdownHook() {
        return $this->request("call_auth_shutdown_hook")->result;
    }

    public function scalingMetrics() {
        return $this->request("scaling_metrics")->result;
    }

    public function blockDevices() {
        return $this->request("block_devices")->result;
    }

    public function statvfs(array $mountpoints) {
        $params = new stdClass();
        $params->mpoints = $mountpoints;

        return $this->request("statvfs", $params)->result;
    }

    public function loadAverage()
    {
        return $this->request("load_average")->result;
    }

    public function memInfo()
    {
        return $this->request("mem_info")->result;
    }

    public function cpuStat()
    {
        return $this->request("cpu_stat")->result;
    }

    public function reboot()
    {
        return $this->request("reboot")->result;
    }

    public function dist()
    {
        return $this->request("dist")->result;
    }

    public function mounts()
    {
        return $this->request("mounts")->result;
    }

    public function getScriptLogs($executionId)
    {
        $params = new stdClass();
        $params->execScriptId = $executionId;

        return $this->request("get_script_logs", $params)->result;
    }

    public function getLog()
    {
        return $this->request("get_log")->result;
    }

    public function getDebugLog()
    {
        return $this->request("get_debug_log")->result;
    }

    public function executeScripts(array $scripts, array $globalVariables, $eventName, $roleName, $async = true)
    {
        $params = new stdClass();
        $params->scripts = $scripts;
        $params->global_variables = $globalVariables;
        $params->eventName = $eventName;
        //$params->roleName = $roleName;
        $params->async = $async;

        return $this->request("execute_scripts", $params)->result;
    }
}