Feature: Analytics Poller

    Scenario Outline: Poller
        White Rabbit stops system service 'mysql'
        White Rabbit starts system service 'mysql'
        White Rabbit has config '../../../tests/etc/config.yml'
        White Rabbit drops scalr_test database
        White Rabbit creates scalr_test database
        White Rabbit drops analytics_test database
        White Rabbit creates analytics_test database

        Database has clients records
            | id | status      |
            | 1  | 'Active'    |
            | 2  | 'Suspended' |
    
        Database has client_environments records
            | id | client_id | status     |
            | 1  | 1         | 'Active'   |
            | 2  | 1         | 'Inactive' |
            | 3  | 2         | 'Active'   |

        Database has client_environment_properties records
            | env_id | name             | value                |
            | 1      | 'ec2.access_key' | '1cePDEMoB84zhQ==\n' |
            | 1      | 'ec2.secret_key' | 'xxlxmoD31LEi5Q==\n' |
            | 1      | 'ec2.is_enabled' | '1'                  |
            | 1      | 'ec2.account_id' | 'hTy6\n'             |

        Database has servers records
            | server_id                              | client_id | env_id | platform | cloud_location | os_type   |
            | '00000000-0000-0000-0000-000000000000' | 1         | 1      | 'ec2'    | 'us-east-1'    | 'linux'   |
            | '00000000-0000-0000-0000-000000000001' | 1         | 1      | 'ocs'    | 'xxxxxxxxx'    | 'linux'   |
            | '00000000-0000-0000-0000-000000000002' | 1         | 1      | 'ec2'    | 'us-west-1'    | 'windows' |
            | '00000000-0000-0000-0000-000000000003' | 1         | 2      | 'ec2'    | 'us-east-1'    | 'linux'   |

        Database has server_properties records
            | server_id                              | name              | value     |
            | '00000000-0000-0000-0000-000000000000' | 'ec2.instance-id' | 'i-00000' |
            | '00000000-0000-0000-0000-000000000002' | 'ec2.instance-id' | 'i-00002' |
            | '00000000-0000-0000-0000-000000000003' | 'ec2.instance-id' | 'i-00003' |

        White Rabbit starts Analytics Poller script with options '-c ../../../tests/etc/config.yml -v DEBUG'
        White Rabbit waits 2 seconds
        White Rabbit stops Analytics Poller script

        White Rabbit checks poller_sessions table
            | account_id | env_id | dtime                 | platform | url | cloud_location | cloud_account |
            | 1          | 1      | '2015-05-01 01:00:01' | 'ec2'    | ''  | 'us-east-1'    | '123'         |
            | 1          | 1      | '2015-05-01 01:00:01' | 'ec2'    | ''  | 'us-west-1'    | '123'         |

        White Rabbit checks managed table
            | server_id                              | instance_type | os |
            | '00000000-0000-0000-0000-000000000000' | 'm1.small'    | 0  |
            | '00000000-0000-0000-0000-000000000002' | 'm1.medium'   | 1  |

        #White Rabbit checks notmanaged table
            #| instance_id | instance_type | os |
            #| 'i-00001'   | 'm1.small'    | 0  |
            #| 'i-00003'   | 'm1.small'    | 0  |
