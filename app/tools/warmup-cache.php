<?php

require_once __DIR__ . '/../src/prepend.inc.php';

use Scalr\Model\Entity\CloudLocation;

//Cleans out cloud locations cache
CloudLocation::warmUp();