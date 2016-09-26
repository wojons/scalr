<?php

final class EC2_SERVER_PROPERTIES extends SERVER_PROPERTIES
{
    const AMIID			= 'ec2.ami-id';
    const ARIID			= 'ec2.ari-id';
    const AKIID			= 'ec2.aki-id';
    const INSTANCE_ID	= 'ec2.instance-id';
    const AVAIL_ZONE	= 'ec2.avail-zone';
    const REGION		= 'ec2.region';

    const IS_LOCKED                 = 'ec2.is_locked';
    const IS_LOCKED_LAST_CHECK_TIME = 'ec2.is_locked_last_check_time';

    const VPC_ID    = 'ec2.vpc-id';
    const SUBNET_ID = 'ec2.subnet-id';
}
