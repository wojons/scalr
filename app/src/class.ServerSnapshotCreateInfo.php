<?php

    class ServerSnapshotCreateInfo
    {
        /**
         *
         * @var DBServer
         */
        public $DBServer;

        public $roleName;
        public $replaceType;
        public $object;
        public $description;
        public $rootBlockDeviceProperties;

        public function __construct(DBServer $DBServer, $roleName, $replaceType, $object = 'role', $description = '', $rootBlockDeviceProperties = [])
        {
            $this->DBServer = $DBServer;
            $this->roleName = $roleName;
            $this->replaceType = $replaceType;
            $this->object = $object;
            $this->description = $description;
            $this->rootBlockDeviceProperties = $rootBlockDeviceProperties;
        }
    }

?>