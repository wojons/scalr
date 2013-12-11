<?php

require __DIR__ . "/../../src/prepend.inc.php";

$config = Scalr::getContainer()->config;

if ($config->defined('scalr.load_statistics.connections.plotter.host') &&
    $config('scalr.load_statistics.connections.plotter.host') != '') {

    // @TODO: move to cross-origin http request http://www.w3.org/TR/cors/ https://code.google.com/p/html5security/wiki/CrossOriginRequestSecurity
    // when we switch to load_statistics python server

    $conf = Scalr::config('scalr.load_statistics.connections.plotter');
    $metrics = array(
        'CPUSNMP' => 'cpu',
        'LASNMP' => 'la',
        'NETSNMP' => 'net',
        'ServersNum' => 'snum',
        'MEMSNMP' => 'mem'
    );

    if (stristr($_REQUEST['role'], "INSTANCE_")) {
        $ar = explode('_', $_REQUEST['role']);
        $farmRoleId = $ar[1];
        $index = $ar[2];
    } else {
        if ($_REQUEST['role'] == 'FARM') {
            $farmRoleId = null;
        } else {
            $farmRoleId = $_REQUEST['role'];
        }
        $index = null;
    }

    $params = array(
        'farmId' => $_REQUEST['farmid'],
        'farmRoleId' => $farmRoleId,
        'index' => $index,
        'period' => $_REQUEST['graph_type'],
        'metric' => $metrics[$_REQUEST['watchername']]
    );

    if (! $conf['port'])
        $conf['port'] = 8080;

    header('url: ' . "{$conf['host']}:{$conf['port']}/load_statistics?" . http_build_query($params));
    print file_get_contents("{$conf['host']}:{$conf['port']}/load_statistics?" . http_build_query($params));
} else {
    // use old version
    $_REQUEST['role_name'] = $_REQUEST['role'];

    $STATS_URL = 'http://monitoring.scalr.net';

    print str_replace('"type":"ok"', '"success":true', @file_get_contents("{$STATS_URL}/server/statistics.php?".http_build_query($_REQUEST)));
}