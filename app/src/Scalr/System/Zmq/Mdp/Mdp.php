<?php
namespace Scalr\System\Zmq\Mdp;

/**
 * Majordomo Protocol
 *
 * The Majordomo Protocol (MDP) defines a reliable service-oriented request-reply dialog
 * between a set of client applications, a broker and a set of worker applications.
 * MDP covers presence, heartbeating, and service-oriented request-reply processing.
 *
 * @since 5.0 (05.09.2014)
 */
class Mdp
{

    // Heartbeat interval, msecs
    const HEARTBEAT_DELAY = 5000;

    // Reliability parameter, 3 - 5 is reasonable.
    const HEARTBEAT_LIVENESS = 3;

    /**
     * This is the version of MDP/Client
     */
    const CLIENT = 'MDPC01';

    /**
     * This is the version of the MDP/Worker
     */
    const WORKER = 'MDPW01';

    //Worker commands
    const WORKER_READY = "\001";
    const WORKER_REQUEST = "\002";
    const WORKER_REPLY = "\003";
    const WORKER_HEARTBEAT = "\004";
    const WORKER_DISCONNECT = "\005";

    public static $cmdname = [
        "\001" => "WORKER_READY",
        "\002" => "WORKER_REQUEST",
        "\003" => "WORKER_REPLY",
        "\004" => "WORKER_HEARTBEAT",
        "\005" => "WORKER_DISCONNECT",
    ];
}