<?php

final class SERVER_SNAPSHOT_CREATION_TYPE
{
    const EC2_EBS = 'ec2.ebs';
    const EC2_EBS_HVM = 'ec2.ebs-hvm';
    const EC2_WIN200X = 'ec2.win200X';
    const EC2_WIN2003 = 'ec2.win2003';
    const EC2_WIN2008 = 'ec2.win2008';
    const EC2_S3I = 'ec2.s3image';

    const RDS_SPT = 'rds.snapshot';

    const RS_CFILES = 'rs.cfiles';
    const GCE_STORAGE = 'gce.storage';
    const GCE_WINDOWS = 'gce.windows';

    const OSTACK_LINUX = 'ostack.linux';
    const OSTACK_WINDOWS = 'ostack.windows';

    const CSTACK_DEF = 'cstack.default';
    const CSTACK_WINDOWS = 'cs.windows';
}
