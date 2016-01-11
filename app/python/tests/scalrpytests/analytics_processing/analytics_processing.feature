Feature: Analytics Processing

    Scenario Outline: Calculate poller data
        White Rabbit stops system service 'mysql'
        White Rabbit starts system service 'mysql'
        White Rabbit has config '../../../tests/etc/config.yml'
        White Rabbit drops scalr_test database
        White Rabbit creates scalr_test database
        White Rabbit drops analytics_test database
        White Rabbit creates analytics_test database

        Database has clients records
            | id | status     |
            | 1  | 'Active'   |
    
        Database has client_environments records
            | id | client_id | status     |
            | 1  | 1         | 'Active'   |

        Analytics database has poller_sessions records
            | sid                                  | dtime                 | account_id | env_id | platform | cloud_location |
            | 00000000-0000-0000-0000-000000000001 | '2015-05-01 05:00:00' | 1          | 1      | 'ec2'    | 'us-west-1'    |
            | 00000000-0000-0000-0000-000000000002 | '2015-05-01 05:00:00' | 1          | 1      | 'ec2'    | 'us-west-1'    |
            | 00000000-0000-0000-0000-000000000003 | '2015-05-01 05:00:00' | 2          | 2      | 'ec2'    | 'us-west-2'    |

        Analytics database has managed records
            | sid                                  | server_id                            | instance_type | os | instance_id |
            | 00000000-0000-0000-0000-000000000001 | 10000000-0000-0000-0000-000000000001 | 'm1.small'    | 0  | i-00000000  |
            | 00000000-0000-0000-0000-000000000002 | 20000000-0000-0000-0000-000000000001 | 'm1.small'    | 0  | i-00000001  |
            | 00000000-0000-0000-0000-000000000002 | 20000000-0000-0000-0000-000000000002 | 'm1.small'    | 0  | i-00000002  |
            | 00000000-0000-0000-0000-000000000002 | 20000000-0000-0000-0000-000000000003 | 'm1.medium'   | 1  | i-00000003  |
            | 00000000-0000-0000-0000-000000000003 | 30000000-0000-0000-0000-000000000001 | 'm1.small'    | 0  | i-00000004  |

        Database has servers records
            | server_id                              | farm_id | farm_roleid | env_id | os_type   | client_id |
            | '10000000-0000-0000-0000-000000000001' | 1       | 1           | 1      | 'linux'   | 1         |
            | '20000000-0000-0000-0000-000000000001' | 1       | 1           | 1      | 'linux'   | 1         |
            | '20000000-0000-0000-0000-000000000002' | 1       | 1           | 1      | 'windows' | 1         |
            | '20000000-0000-0000-0000-000000000003' | 2       | 2           | 1      | 'linux'   | 1         |
            | '30000000-0000-0000-0000-000000000001' | 3       | 3           | 2      | 'linux'   | 2         |

        Database has servers_history records
            | server_id                              | farm_id | cloud_server_id | env_id | client_id | project_id                           | cc_id                                | farm_roleid | role_id |
            | '10000000-0000-0000-0000-000000000001' | 1       | 'i-00000000'    | 1      | 1         | 00000000-0000-0000-0000-00000000000a | 00000000-0000-0000-0000-00000000000b | 1            | 111     |
            | '20000000-0000-0000-0000-000000000001' | 1       | 'i-00000001'    | 1      | 1         | 00000000-0000-0000-0000-00000000000a | 00000000-0000-0000-0000-00000000000b | 1            | 111     |
            | '20000000-0000-0000-0000-000000000002' | 1       | 'i-00000002'    | 1      | 1         | 00000000-0000-0000-0000-00000000000a | 00000000-0000-0000-0000-00000000000b | 1            | 111     |
            | '20000000-0000-0000-0000-000000000003' | 2       | 'i-00000003'    | 1      | 1         | 00000000-0000-0000-0000-00000000000a | 00000000-0000-0000-0000-00000000000b | 2            | 111     |
            | '30000000-0000-0000-0000-000000000001' | 3       | 'i-00000004'    | 2      | 2         | 00000000-0000-0000-0000-00000000000a | 00000000-0000-0000-0000-00000000000b | 3            | 111     |

        Database has farm_roles records
            | id | role_id |
            | 1  | 111     |
            | 2  | 111     |
            | 3  | 111     |

        Database has client_environment_properties records
            | env_id | name    | value                                  |
            | 1      | 'cc_id' | '00000000-0000-0000-0000-00000000000b' |
            | 2      | 'cc_id' | '00000000-0000-0000-0000-00000000000b' |

        Analytics database has price_history records
            | price_id                             | applied      | platform  | cloud_location  | account_id |
            | 00000000-0000-0000-0000-000000000001 | '2015-05-01' | 'ec2'     | 'us-west-1'     | 0          |
            | 00000000-0000-0000-0000-000000000002 | '2015-05-01' | 'ec2'     | 'us-west-2'     | 0          |

        Analytics database has prices records
            | price_id                             | instance_type | os | cost |
            | 00000000-0000-0000-0000-000000000001 | 'm1.small'    | 0  | 1    |
            | 00000000-0000-0000-0000-000000000001 | 'm1.medium'   | 0  | 2    |
            | 00000000-0000-0000-0000-000000000001 | 'm1.medium'   | 1  | 2.5  |
            | 00000000-0000-0000-0000-000000000002 | 'm1.small'    | 0  | 1.5  |
            | 00000000-0000-0000-0000-000000000002 | 'm1.medium'   | 1  | 2.5  |

        Analytics database has quarterly_budget records
            | year   | subject_type | subject_id                           | quarter | budget | cumulativespend | spentondate           |
            | '2015' | 1            | 00000000-0000-0000-0000-00000000000b | 2       | 2      | 0               | NULL                  |
            | '2015' | 2            | 00000000-0000-0000-0000-00000000000a | 2       | 10     | 14.5            | '2015-04-10 00:00:00' |

        White Rabbit starts Analytics Processing script with options '-c ../../../tests/etc/config.yml -v DEBUG'
        White Rabbit waits 20 seconds
        White Rabbit stops Analytics Processing script

        White Rabbit checks usage_h table
            | account_id | dtime                 | platform | cloud_location | usage_types.cost_distr_type | usage_types.name | usage_items.name | os | cc_id                                | project_id                           | env_id | farm_id | farm_role_id | role_id | num  | cost        |
            | 1          | '2015-05-01 05:00:00' | 'ec2'    | 'us-west-1'    | 1                           | 'BoxUsage'       | 'm1.small'       | 0  | 00000000-0000-0000-0000-00000000000b | 00000000-0000-0000-0000-00000000000a | 1      | 1       | 1            | 111     | 3.00 | 3.000000000 |
            | 1          | '2015-05-01 05:00:00' | 'ec2'    | 'us-west-1'    | 1                           | 'BoxUsage'       | 'm1.medium'      | 1  | 00000000-0000-0000-0000-00000000000b | 00000000-0000-0000-0000-00000000000a | 1      | 2       | 2            | 111     | 1.00 | 2.500000000 |
            | 2          | '2015-05-01 05:00:00' | 'ec2'    | 'us-west-2'    | 1                           | 'BoxUsage'       | 'm1.small'       | 0  | 00000000-0000-0000-0000-00000000000b | 00000000-0000-0000-0000-00000000000a | 2      | 3       | 3            | 111     | 1.00 | 1.500000000 |

        White Rabbit checks usage_d table
            | date          | platform | cc_id                                | project_id                           | farm_id | cost        |
            | '2015-05-01 ' | 'ec2'    | 00000000-0000-0000-0000-00000000000b | 00000000-0000-0000-0000-00000000000a | 1       | 3.000000000 |
            | '2015-05-01 ' | 'ec2'    | 00000000-0000-0000-0000-00000000000b | 00000000-0000-0000-0000-00000000000a | 2       | 2.500000000 |
            | '2015-05-01 ' | 'ec2'    | 00000000-0000-0000-0000-00000000000b | 00000000-0000-0000-0000-00000000000a | 3       | 1.500000000 |

        White Rabbit checks farm_usage_d table
            | date         | account_id | farm_role_id | cc_id                                | project_id                           | platform | cloud_location | env_id | farm_id | role_id | cost        | min_usage | max_usage | usage_hours | working_hours |
            | '2015-05-01' | 1          |            1 | 00000000-0000-0000-0000-00000000000b | 00000000-0000-0000-0000-00000000000a | 'ec2'    | 'us-west-1'    |      1 |       1 |     111 | 3.000000000 |      0.00 |      3.00 |        3.00 |             1 |
            | '2015-05-01' | 1          |            2 | 00000000-0000-0000-0000-00000000000b | 00000000-0000-0000-0000-00000000000a | 'ec2'    | 'us-west-1'    |      1 |       2 |     111 | 2.500000000 |      0.00 |      1.00 |        1.00 |             1 |
            | '2015-05-01' | 2          |            3 | 00000000-0000-0000-0000-00000000000b | 00000000-0000-0000-0000-00000000000a | 'ec2'    | 'us-west-2'    |      2 |       3 |     111 | 1.500000000 |      0.00 |      1.00 |        1.00 |             1 |

        White Rabbit checks quarterly_budget table
            | year   | subject_type | subject_id                           | quarter | budget | cumulativespend | spentondate           |
            | '2015' | 1            | 00000000-0000-0000-0000-00000000000b | 2       | 2      | 7               | '2015-05-01 00:00:00' |
            | '2015' | 2            | 00000000-0000-0000-0000-00000000000a | 2       | 10     | 21.5            | '2015-04-10 00:00:00' |


    Scenario Outline: Recalculate poller data

        Analytics database has new prices
            | price_id                             | instance_type | os | cost |
            | 00000000-00000-0000-0000-00000000001 | 'm1.small'    | 0  | 2    |
            | 00000000-00000-0000-0000-00000000001 | 'm1.medium'   | 0  | 4    |
            | 00000000-00000-0000-0000-00000000001 | 'm1.medium'   | 1  | 5    |
            | 00000000-00000-0000-0000-00000000002 | 'm1.small'    | 0  | 3    |
            | 00000000-00000-0000-0000-00000000002 | 'm1.medium'   | 1  | 5    |

        White Rabbit starts Analytics Processing script with options '-c ../../../tests/etc/config.yml -v DEBUG --recalculate --billing poller --date-from 2015-05-01'
        White Rabbit waits 20 seconds
        White Rabbit stops Analytics Processing script

        White Rabbit checks usage_h table
            | account_id | dtime                 | platform | cloud_location | usage_types.cost_distr_type | usage_types.name | usage_items.name | os | cc_id                                | project_id                           | env_id | farm_id | farm_role_id | role_id | num  | cost        |
            | 1          | '2015-05-01 05:00:00' | 'ec2'    | 'us-west-1'    | 1                           | 'BoxUsage'       | 'm1.small'       | 0  | 00000000-0000-0000-0000-00000000000b | 00000000-0000-0000-0000-00000000000a | 1      | 1       | 1            | 111     | 3.00 | 6.000000000 |
            | 1          | '2015-05-01 05:00:00' | 'ec2'    | 'us-west-1'    | 1                           | 'BoxUsage'       | 'm1.medium'      | 1  | 00000000-0000-0000-0000-00000000000b | 00000000-0000-0000-0000-00000000000a | 1      | 2       | 2            | 111     | 1.00 | 5.000000000 |
            | 2          | '2015-05-01 05:00:00' | 'ec2'    | 'us-west-2'    | 1                           | 'BoxUsage'       | 'm1.small'       | 0  | 00000000-0000-0000-0000-00000000000b | 00000000-0000-0000-0000-00000000000a | 2      | 3       | 3            | 111     | 1.00 | 3.000000000 |

        White Rabbit checks usage_d table
            | date         | platform | cc_id                                | project_id                           | farm_id | cost |
            | '2015-05-01' | 'ec2'    | 00000000-0000-0000-0000-00000000000b | 00000000-0000-0000-0000-00000000000a | 1       | 6    |
            | '2015-05-01' | 'ec2'    | 00000000-0000-0000-0000-00000000000b | 00000000-0000-0000-0000-00000000000a | 2       | 5    |
            | '2015-05-01' | 'ec2'    | 00000000-0000-0000-0000-00000000000b | 00000000-0000-0000-0000-00000000000a | 3       | 3    |

        White Rabbit checks farm_usage_d table
            | date         | account_id | farm_role_id | cc_id                                | project_id                           | platform | cloud_location | env_id | farm_id | role_id | cost        | min_usage | max_usage | usage_hours | working_hours |
            | '2015-05-01' |          1 |            1 | 00000000-0000-0000-0000-00000000000b | 00000000-0000-0000-0000-00000000000a | 'ec2'    | 'us-west-1'    |      1 |       1 |     111 | 6.000000000 |      0.00 |      3.00 |        3.00 |             1 |
            | '2015-05-01' |          1 |            2 | 00000000-0000-0000-0000-00000000000b | 00000000-0000-0000-0000-00000000000a | 'ec2'    | 'us-west-1'    |      1 |       2 |     111 | 5.000000000 |      0.00 |      1.00 |        1.00 |             1 |
            | '2015-05-01' |          2 |            3 | 00000000-0000-0000-0000-00000000000b | 00000000-0000-0000-0000-00000000000a | 'ec2'    | 'us-west-2'    |      2 |       3 |     111 | 3.000000000 |      0.00 |      1.00 |        1.00 |             1 |

        White Rabbit checks quarterly_budget table
            | year   | subject_type | subject_id                           | quarter | cumulativespend | spentondate           |
            | '2015' | 1            | 00000000-0000-0000-0000-00000000000b | 2       | 14              | '2015-05-01 00:00:00' |
            | '2015' | 2            | 00000000-0000-0000-0000-00000000000a | 2       | 14              | '2015-05-01 00:00:00' |


    Scenario Outline: Amazon detailed billing
        White Rabbit stops system service 'mysql'
        White Rabbit starts system service 'mysql'
        White Rabbit has config '../../../tests/etc/config.yml'
        White Rabbit drops scalr_test database
        White Rabbit creates scalr_test database
        White Rabbit drops analytics_test database
        White Rabbit creates analytics_test database

        Database has clients records
            | id  | status     |
            | 1   | 'Active'   |
    
        Database has client_environments records
            | id | client_id | status     |
            | 1  | 1         | 'Active'   |

        Database has client_environment_properties records
            | env_id | name                           | value |
            | 1      | 'ec2.is_enabled'               | '1'   |

        Database has environment_cloud_credentials records
            | env_id | cloud | cloud_credentials_id |
            | 1      | 'ec2' | '111'                |

        Database has cloud_credentials records
            | id    | account_id | env_id | cloud |
            | '111' | 1          | 1      | 'ec2' |

        Database has cloud_credentials_properties records
            | cloud_credentials_id | name                       | value                |
            | '111'                | 'access_key'               | '1cePDEMoB84zhQ==\n' |
            | '111'                | 'secret_key'               | 'xxlxmoD31LEi5Q==\n' |
            | '111'                | 'account_id'               | '123'                |
            | '111'                | 'detailed_billing.enabled' | '1'                  |
            | '111'                | 'detailed_billing.bucket'  | 'bucket'             |

        Database has farm_roles records
            | id | role_id |
            | 1  | 111     |

        Database has servers records
            | server_id                              | farm_id | farm_roleid | type       | os_type | client_id | platform | env_id |
            | '10000000-0000-0000-0000-000000000001' | 1       | 1           | 'm1.small' | 'linux' | 1         | 'ec2'    | 1      |
            | '10000000-0000-0000-0000-000000000010' | 1       | 1           | 'm1.small' | 'linux' | 1         | 'ec2'    | 1      |

        Database has servers_history records
            | server_id                              | cloud_server_id | project_id                           | cc_id                                | role_id |
            | '10000000-0000-0000-0000-000000000001' | 'i-00000000'    | 00000000-0000-0000-0000-00000000000a | 00000000-0000-0000-0000-00000000000b | 111     |
            | '10000000-0000-0000-0000-000000000010' | 'i-00000002'    | 00000000-0000-0000-0000-00000000000a | 00000000-0000-0000-0000-00000000000b | 111     |

        Database has client_environment_properties records
            | env_id | name    | value                                  |
            | 1      | 'cc_id' | '00000000-0000-0000-0000-00000000000b' |

        White Rabbit starts Analytics Processing script with options '-c ../../../tests/etc/config.yml -v DEBUG'
        White Rabbit waits 5 seconds
        White Rabbit stops Analytics Processing script

        White Rabbit checks usage_h table
            | account_id | dtime                 | platform | cloud_location | usage_types.cost_distr_type | usage_types.name | usage_items.name | os | cc_id                                | project_id                           | env_id | farm_id | farm_role_id | role_id | num  | cost        |
            | 1          | '2015-05-01 00:00:00' | 'ec2'    | 'us-east-1'    | 1                           | 'BoxUsage'       |'m1.small'        | 0  | 00000000-0000-0000-0000-00000000000b | 00000000-0000-0000-0000-00000000000a | 1      | 1       | 1            | 111     | 1.00 | 0.044000000 |
            | 1          | '2015-05-01 03:00:00' | 'ec2'    | 'us-east-1'    | 1                           | 'BoxUsage'       |'m1.small'        | 0  | 00000000-0000-0000-0000-00000000000b | 00000000-0000-0000-0000-00000000000a | 1      | 1       | 1            | 111     | 2.00 | 0.088000000 |

        White Rabbit checks usage_d table
            | date          | platform | cc_id                                | project_id                           | farm_id | cost        |
            | '2015-05-01 ' | 'ec2'    | 00000000-0000-0000-0000-00000000000b | 00000000-0000-0000-0000-00000000000a | 1       | 0.132000000 |

        White Rabbit checks farm_usage_d table
            | date         | account_id | farm_role_id | cc_id                                | project_id                           | platform | cloud_location | env_id | farm_id | role_id | cost        | min_usage | max_usage | usage_hours | working_hours |
            | '2015-05-01' | 1          |            1 | 00000000-0000-0000-0000-00000000000b | 00000000-0000-0000-0000-00000000000a | 'ec2'    | 'us-east-1'    |      1 |       1 |     111 | 0.132000000 |      0.00 |      2.00 |        3.00 |             2 |


    Scenario Outline: Amazon detailed billing file not in bucket
        White Rabbit stops system service 'mysql'
        White Rabbit starts system service 'mysql'
        White Rabbit has config '../../../tests/etc/config.yml'
        White Rabbit drops scalr_test database
        White Rabbit creates scalr_test database
        White Rabbit drops analytics_test database
        White Rabbit creates analytics_test database

        Database has clients records
            | id  | status     |
            | 1   | 'Active'   |
    
        Database has client_environments records
            | id | client_id | status     |
            | 1  | 1         | 'Active'   |

        Database has client_environment_properties records
            | env_id | name                           | value |
            | 1      | 'ec2.is_enabled'               | '1'   |

        Database has environment_cloud_credentials records
            | env_id | cloud | cloud_credentials_id |
            | 1      | 'ec2' | '111'                |

        Database has cloud_credentials records
            | id    | account_id | env_id | cloud |
            | '111' | 1          | 1      | 'ec2' |

        Database has cloud_credentials_properties records
            | cloud_credentials_id | name          | value                 |
            | '111'                | 'access_key'  | '1cePDEMoB84zhQ==\n'  |
            | '111'                | 'secret_key'  | 'xxlxmoD31LEi5Q==\n'  |
            | '111'                | 'account_id'  | '123'                 |
            | '111'                | 'detailed_billing.enabled' | '1'      |
            | '111'                | 'detailed_billing.bucket'  | 'bucket' |

        Database has farm_roles records
            | id | role_id |
            | 1  | 111     |

        Database has servers records
            | server_id                              | farm_id | farm_roleid | type       | os_type | client_id |
            | '10000000-0000-0000-0000-000000000001' | 1       | 1           | 'm1.small' | 'linux' | 1         |

        Database has servers_history records
            | server_id                              | cloud_server_id | project_id                           | cc_id                                | role_id |
            | '10000000-0000-0000-0000-000000000001' | 'i-00000000'    | 00000000-0000-0000-0000-00000000000a | 00000000-0000-0000-0000-00000000000b | 111     |

        Database has client_environment_properties records
            | env_id | name    | value                                  |
            | 1      | 'cc_id' | '00000000-0000-0000-0000-00000000000b' |

        Mock download AWS billing file not in bucket
        White Rabbit starts Analytics Processing script with options '-c ../../../tests/etc/config.yml -v DEBUG'
        White Rabbit waits 20 seconds
        White Rabbit stops Analytics Processing script

        White Rabbit checks usage_h table
            | account_id | dtime | platform | cloud_location | instance_type | os | cc_id | project_id | env_id | farm_id | farm_role_id | role_id | num | cost  |

        White Rabbit checks usage_d table
            | date | platform | cc_id | project_id | farm_id | cost |

        White Rabbit checks farm_usage_d table
            | date | account_id | farm_role_id | cc_id | project_id | platform | cloud_location | env_id | farm_id | role_id | cost | min_usage | max_usage | usage_hours | working_hours |


    Scenario Outline: Amazon detailed billing file not ok
        White Rabbit stops system service 'mysql'
        White Rabbit starts system service 'mysql'
        White Rabbit has config '../../../tests/etc/config.yml'
        White Rabbit drops scalr_test database
        White Rabbit creates scalr_test database
        White Rabbit drops analytics_test database
        White Rabbit creates analytics_test database

        Database has clients records
            | id  | status     |
            | 1   | 'Active'   |
    
        Database has client_environments records
            | id | client_id | status     |
            | 1  | 1         | 'Active'   |

        Database has client_environment_properties records
            | env_id | name                           | value |
            | 1      | 'ec2.is_enabled'               | '1'   |

        Database has environment_cloud_credentials records
            | env_id | cloud | cloud_credentials_id |
            | 1      | 'ec2' | '111'                |

        Database has cloud_credentials records
            | id    | account_id | env_id | cloud |
            | '111' | 1          | 1      | 'ec2' |

        Database has cloud_credentials_properties records
            | cloud_credentials_id | name          | value                 |
            | '111'                | 'access_key'  | '1cePDEMoB84zhQ==\n'  |
            | '111'                | 'secret_key'  | 'xxlxmoD31LEi5Q==\n'  |
            | '111'                | 'account_id'  | '123'                 |
            | '111'                | 'detailed_billing.enabled' | '1'      |
            | '111'                | 'detailed_billing.bucket'  | 'bucket' |

        Database has farm_roles records
            | id | role_id |
            | 1  | 111     |

        Database has servers records
            | server_id                              | farm_id | farm_roleid | type       | os_type | client_id |
            | '10000000-0000-0000-0000-000000000001' | 1       | 1           | 'm1.small' | 'linux' | 1         |

        Database has servers_history records
            | server_id                              | cloud_server_id | project_id                           | cc_id                                | role_id |
            | '10000000-0000-0000-0000-000000000001' | 'i-00000000'    | 00000000-0000-0000-0000-00000000000a | 00000000-0000-0000-0000-00000000000b | 111     |

        Database has client_environment_properties records
            | env_id | name    | value                                  |
            | 1      | 'cc_id' | '00000000-0000-0000-0000-00000000000b' |

        Mock download AWS billing file not ok
        White Rabbit starts Analytics Processing script with options '-c ../../../tests/etc/config.yml -v DEBUG'
        White Rabbit waits 20 seconds
        White Rabbit stops Analytics Processing script

        White Rabbit checks usage_h table
            | account_id | dtime | platform | cloud_location | instance_type | os | cc_id | project_id | env_id | farm_id | farm_role_id | role_id | num | cost  |

        White Rabbit checks usage_d table
            | date | platform | cc_id  | project_id | farm_id | cost |

        White Rabbit checks farm_usage_d table
            | date | account_id | farm_role_id | cc_id | project_id | platform | cloud_location | env_id | farm_id | role_id | cost | min_usage | max_usage | usage_hours | working_hours |

 
    Scenario Outline: Amazon detailed billing with custom PayerAccount
        White Rabbit stops system service 'mysql'
        White Rabbit starts system service 'mysql'
        White Rabbit has config '../../../tests/etc/config.yml'
        White Rabbit drops scalr_test database
        White Rabbit creates scalr_test database
        White Rabbit drops analytics_test database
        White Rabbit creates analytics_test database

        Database has clients records
            | id  | status     |
            | 1   | 'Active'   |
    
        Database has client_environments records
            | id | client_id | status     |
            | 1  | 1         | 'Active'   |
            | 2  | 1         | 'Active'   |

        Database has client_environment_properties records
            | env_id | name                                 | value    |
            | 1      | 'ec2.is_enabled'                     | '1'      |
            | 2      | 'ec2.is_enabled'                     | '1'      |

        Database has environment_cloud_credentials records
            | env_id | cloud | cloud_credentials_id |
            | 1      | 'ec2' | '111'                |
            | 2      | 'ec2' | '222'                |

        Database has cloud_credentials records
            | id    | account_id | env_id | cloud |
            | '111' | 1          | 1      | 'ec2' |
            | '222' | 1          | 2      | 'ec2' |

        Database has cloud_credentials_properties records
            | cloud_credentials_id | name                             | value                |
            | '111'                | 'access_key'                     | '1cePDEMoB84zhQ==\n' |
            | '111'                | 'secret_key'                     | 'xxlxmoD31LEi5Q==\n' |
            | '111'                | 'account_id'                     | '123'                |
            | '111'                | 'detailed_billing.enabled'       | '1'                  |
            | '111'                | 'detailed_billing.bucket'        | 'bucket'             |
            | '111'                | 'detailed_billing.payer_account' | '333'                |
            | '222'                | 'access_key'                     | '2cePDEMoB84zhQ==\n' |
            | '222'                | 'secret_key'                     | 'yxlxmoD31LEi5Q==\n' |
            | '222'                | 'account_id'                     | '333'                |

        Database has farm_roles records
            | id | role_id |
            | 1  | 111     |

        Database has servers records
            | server_id                              | farm_id | farm_roleid | type       | os_type | client_id |
            | '10000000-0000-0000-0000-000000000001' | 1       | 1           | 'm1.small' | 'linux' | 1         |

        Database has servers_history records
            | server_id                              | cloud_server_id | project_id                           | cc_id                                | role_id |
            | '10000000-0000-0000-0000-000000000001' | 'i-00000000'    | 00000000-0000-0000-0000-00000000000a | 00000000-0000-0000-0000-00000000000b | 111     |

        Database has client_environment_properties records
            | env_id | name    | value                                  |
            | 1      | 'cc_id' | '00000000-0000-0000-0000-00000000000b' |

        White Rabbit starts Analytics Processing script with options '-c ../../../tests/etc/config.yml -v DEBUG'
        White Rabbit waits 20 seconds
        White Rabbit stops Analytics Processing script

        White Rabbit checks usage_h table
            | account_id | dtime                 | platform | cloud_location |usage_types.cost_distr_type | usage_types.name | usage_items.name | os | cc_id                                | project_id                           | env_id | farm_id | farm_role_id | role_id | num  | cost        |
            | 1          | '2015-05-01 01:00:00' | 'ec2'    | 'us-east-1'    |1                           | 'BoxUsage'       | 'm1.small'       | 0  | 00000000-0000-0000-0000-00000000000b | 00000000-0000-0000-0000-00000000000a | 1      | 1       | 1            | 111     | 1.00 | 0.088000000 |

        White Rabbit checks usage_d table
            | date          | platform | cc_id                                  | project_id                             | farm_id | cost        |
            | '2015-05-01 ' | 'ec2'    | '00000000-0000-0000-0000-00000000000b' | '00000000-0000-0000-0000-00000000000a' | 1       | 0.088000000 |

        White Rabbit checks farm_usage_d table
            | date         | account_id | farm_role_id | cc_id                                | project_id                           | platform | cloud_location | env_id | farm_id | role_id | cost        | min_usage | max_usage | usage_hours | working_hours |
            | '2015-05-01' | 1          |            1 | 00000000-0000-0000-0000-00000000000b | 00000000-0000-0000-0000-00000000000a | 'ec2'    | 'us-east-1'    |      1 |       1 |     111 | 0.088000000 |      0.00 |      1.00 |        1.00 |             1 |


    Scenario Outline: Azure billing
        White Rabbit stops system service 'mysql'
        White Rabbit starts system service 'mysql'
        White Rabbit has config '../../../tests/etc/config.yml'
        White Rabbit drops scalr_test database
        White Rabbit creates scalr_test database
        White Rabbit drops analytics_test database
        White Rabbit creates analytics_test database

        Database has clients records
            | id  | status     |
            | 1   | 'Active'   |
    
        Database has client_environments records
            | id | client_id | status     |
            | 1  | 1         | 'Active'   |

        Database has client_environment_properties records
            | env_id | name                    | value                                  |
            | 1      | 'azure.is_enabled'      | '1'                                    |
            | 1      | 'cc_id'                 | '00000000-0000-0000-0000-00000000000b' |

        Database has environment_cloud_credentials records
            | env_id | cloud   | cloud_credentials_id |
            | 1      | 'azure' | '111'                |

        Database has cloud_credentials records
            | id    | account_id | env_id | cloud   |
            | '111' | 1          | 1      | 'azure' |

        Database has cloud_credentials_properties records
            | cloud_credentials_id | name              | value                |
            | '111'                | 'tenant_name'     | 'wLvxmyMxlUFAsMw=\n' |
            | '111'                | 'subscription_id' | 'subscription_id'    |

        Database has farm_roles records
            | id | role_id |
            | 1  | 111     |

        Database has servers records
            | server_id                              | env_id | farm_id | farm_roleid | os_type | client_id |
            | '10000000-0000-0000-0000-000000000001' | 1      | 1       | 1           | 'linux' | 1         |

        Database has servers_history records
            | server_id                              | cloud_server_id                        | project_id                           | cc_id                                | role_id |
            | '10000000-0000-0000-0000-000000000001' | '10000000-0000-0000-0000-000000000001' | 00000000-0000-0000-0000-00000000000a | 00000000-0000-0000-0000-00000000000b | 111     |

        White Rabbit starts Analytics Processing script with options '-c ../../../tests/etc/config.yml -v DEBUG'
        White Rabbit waits 20 seconds
        White Rabbit stops Analytics Processing script

        White Rabbit checks usage_h table
            | account_id | dtime                 | platform | cloud_location | usage_types.cost_distr_type | usage_types.name | usage_items.name  | os | cc_id                                  | project_id                           | env_id | farm_id | farm_role_id | role_id | num  | cost        |
            | 1          | '2015-04-30 08:00:00' | 'azure'  | 'eastus'       | 1                           | 'BoxUsage'       | 'Basic_A0'        | 0  | '00000000-0000-0000-0000-00000000000b' | 00000000-0000-0000-0000-00000000000a | 1      | 1       | 1            | 111     | 1.00 | 0.000198000 |

        White Rabbit checks usage_d table
            | date          | platform | cc_id                                  | project_id                             | farm_id | cost        |
            | '2015-04-30 ' | 'azure'  | '00000000-0000-0000-0000-00000000000b' | '00000000-0000-0000-0000-00000000000a' | 1       | 0.000198000 |

        White Rabbit checks farm_usage_d table
            | date         | account_id | farm_role_id | cc_id                                | project_id                           | platform | cloud_location | env_id | farm_id | role_id | cost        | min_usage | max_usage | usage_hours | working_hours |
            | '2015-04-30' | 1          |            1 | 00000000-0000-0000-0000-00000000000b | 00000000-0000-0000-0000-00000000000a | 'azure'  | 'eastus'       |      1 |       1 |     111 | 0.000198000 |      0.00 |      1.00 |        1.00 |             1 |
