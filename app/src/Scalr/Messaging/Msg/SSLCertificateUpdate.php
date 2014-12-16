<?php

class Scalr_Messaging_Msg_SSLCertificateUpdate extends Scalr_Messaging_Msg {
    
    public $id,
        $privateKey,
        $certificate,
        $cacertificate;
    
    function __construct () {
        parent::__construct();
    }
}