Feature: Analytics Processing

    Scenario Outline: Calculate
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

        Database has client_environment_properties records
            | env_id | name                           | value                |
            | 1      | 'ec2.access_key'               | '1cePDEMoB84zhQ==\n' |
            | 1      | 'ec2.secret_key'               | 'xxlxmoD31LEi5Q==\n' |
            | 1      | 'ec2.is_enabled'               | '1'                  |
            | 1      | 'ec2.account_id'               | 'hTy6\n'             |

        Analytics database has poller_sessions records
            | sid                                | dtime                 | account_id | env_id | platform | cloud_location |
            | '00000000000000000000000000000001' | '2015-05-01 03:00:00' | 1          | 1      | 'ec2'    | 'us-west-1'    |
            | '00000000000000000000000000000002' | '2015-05-01 03:00:00' | 1          | 1      | 'ec2'    | 'us-west-1'    |
            | '00000000000000000000000000000003' | '2015-05-01 03:00:00' | 2          | 2      | 'ec2'    | 'us-west-2'    |

        Analytics database has managed records
            | sid                                | server_id                              | instance_type | os | instance_id |
            | '00000000000000000000000000000001' | '10000000-0000-0000-0000-000000000001' | 'm1.small'    | 0  | i-00000     |
            | '00000000000000000000000000000002' | '20000000-0000-0000-0000-000000000001' | 'm1.small'    | 0  | i-00001     |
            | '00000000000000000000000000000002' | '20000000-0000-0000-0000-000000000002' | 'm1.small'    | 0  | i-00002     |
            | '00000000000000000000000000000002' | '20000000-0000-0000-0000-000000000003' | 'm1.medium'   | 0  | i-00003     |
            | '00000000000000000000000000000003' | '30000000-0000-0000-0000-000000000001' | 'm1.small'    | 0  | i-00004     |

        Database has servers records
            | server_id                              | farm_id | farm_roleid | env_id | os_type | client_id |
            | '10000000-0000-0000-0000-000000000001' | 1       | 1           | 1      | 'linux' | 1         |
            | '20000000-0000-0000-0000-000000000001' | 1       | 1           | 1      | 'linux' | 1         |
            | '20000000-0000-0000-0000-000000000002' | 1       | 1           | 1      | 'linux' | 1         |
            | '20000000-0000-0000-0000-000000000003' | 2       | 2           | 1      | 'linux' | 1         |
            | '30000000-0000-0000-0000-000000000001' | 3       | 3           | 2      | 'linux' | 2         |

        Database has server_properties records
            | server_id                              | name              | value                                  |
            | '10000000-0000-0000-0000-000000000001' | 'ec2.instance-id' | 'i-00000'                              |
            | '20000000-0000-0000-0000-000000000001' | 'ec2.instance-id' | 'i-00001'                              |
            | '20000000-0000-0000-0000-000000000002' | 'ec2.instance-id' | 'i-00002'                              |
            | '20000000-0000-0000-0000-000000000003' | 'ec2.instance-id' | 'i-00003'                              |
            | '30000000-0000-0000-0000-000000000001' | 'ec2.instance-id' | 'i-00004'                              |
            | '10000000-0000-0000-0000-000000000001' | 'farm.project_id' | '00000000-0000-0000-0000-00000000000a' |
            | '10000000-0000-0000-0000-000000000001' | 'env.cc_id'       | '00000000-0000-0000-0000-00000000000b' |
            | '20000000-0000-0000-0000-000000000001' | 'farm.project_id' | '00000000-0000-0000-0000-00000000000a' |
            | '20000000-0000-0000-0000-000000000001' | 'env.cc_id'       | '00000000-0000-0000-0000-00000000000b' |
            | '20000000-0000-0000-0000-000000000002' | 'farm.project_id' | '00000000-0000-0000-0000-00000000000a' |
            | '20000000-0000-0000-0000-000000000002' | 'env.cc_id'       | '00000000-0000-0000-0000-00000000000b' |
            | '20000000-0000-0000-0000-000000000003' | 'farm.project_id' | '00000000-0000-0000-0000-00000000000a' |
            | '20000000-0000-0000-0000-000000000003' | 'env.cc_id'       | '00000000-0000-0000-0000-00000000000b' |
            | '30000000-0000-0000-0000-000000000001' | 'farm.project_id' | '00000000-0000-0000-0000-00000000000a' |
            | '30000000-0000-0000-0000-000000000001' | 'env.cc_id'       | '00000000-0000-0000-0000-00000000000b' |

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
            | price_id                           | applied      | platform  | cloud_location  | account_id |
            | '00000000000000000000000000000001' | '2015-05-01' | 'ec2'     | 'us-west-1'     | 0          |
            | '00000000000000000000000000000002' | '2015-05-01' | 'ec2'     | 'us-west-2'     | 0          |

        Analytics database has prices records
            | price_id                           | instance_type | os | cost |
            | '00000000000000000000000000000001' | 'm1.small'    | 0  | 1    |
            | '00000000000000000000000000000001' | 'm1.medium'   | 0  | 2    |
            | '00000000000000000000000000000001' | 'm1.medium'   | 1  | 2.5  |
            | '00000000000000000000000000000002' | 'm1.small'    | 0  | 1.5  |
            | '00000000000000000000000000000002' | 'm1.medium'   | 1  | 2.5  |

        Analytics database has quarterly_budget records
            | subject_type | subject_id                         | quarter | budget | cumulativespend | spentondate           |
            | 1            | '0000000000000000000000000000000b' | 2       | 2      | 0               | NULL                  |
            | 2            | '0000000000000000000000000000000a' | 2       | 10     | 14.5            | '2015-04-10 00:00:00' |

        White Rabbit starts Analytics Processing script with options '-c ../../../tests/etc/config.yml -v DEBUG'
        White Rabbit waits 15 seconds
        White Rabbit stops Analytics Processing script

        White Rabbit checks usage_h table
            | account_id | dtime                 | platform | cloud_location | instance_type | os | cc_id                                  | project_id                           | env_id | farm_id | farm_role_id | role_id | num | cost |
            | 1          | '2015-05-01 03:00:00' | 'ec2'    | 'us-west-1'    | 'm1.small'    | 0  | '00000000-0000-0000-0000-00000000000b' | 00000000-0000-0000-0000-00000000000a | 1      | 1       | 1            | 111     | 3   | 3    |
            | 1          | '2015-05-01 03:00:00' | 'ec2'    | 'us-west-1'    | 'm1.medium'   | 0  | '00000000-0000-0000-0000-00000000000b' | 00000000-0000-0000-0000-00000000000a | 1      | 2       | 2            | 111     | 1   | 2    |
            | 2          | '2015-05-01 03:00:00' | 'ec2'    | 'us-west-2'    | 'm1.small'    | 0  | '00000000-0000-0000-0000-00000000000b' | 00000000-0000-0000-0000-00000000000a | 2      | 3       | 3            | 111     | 1   | 1.5  |

        White Rabbit checks usage_d table
            | date          | platform | cc_id                                  | project_id                             | farm_id | cost |
            | '2015-05-01 ' | 'ec2'    | '00000000-0000-0000-0000-00000000000b' | '00000000-0000-0000-0000-00000000000a' | 1       | 3    |
            | '2015-05-01 ' | 'ec2'    | '00000000-0000-0000-0000-00000000000b' | '00000000-0000-0000-0000-00000000000a' | 2       | 2    |
            | '2015-05-01 ' | 'ec2'    | '00000000-0000-0000-0000-00000000000b' | '00000000-0000-0000-0000-00000000000a' | 3       | 1.5  |

        White Rabbit checks farm_usage_d table
            | date         | account_id | farm_role_id | cc_id                                  | project_id                             | platform | cloud_location | env_id | farm_id | role_id | cost        | min_usage | max_usage | usage_hours | working_hours |
            | '2015-05-01' | 1          |            1 | '00000000-0000-0000-0000-00000000000b' | '00000000-0000-0000-0000-00000000000a' | 'ec2'    | 'us-west-1'    |      1 |       1 |     111 | 3.000000000 |      0.00 |      3.00 |        3.00 |             1 |
            | '2015-05-01' | 1          |            2 | '00000000-0000-0000-0000-00000000000b' | '00000000-0000-0000-0000-00000000000a' | 'ec2'    | 'us-west-1'    |      1 |       2 |     111 | 2.000000000 |      0.00 |      1.00 |        1.00 |             1 |
            | '2015-05-01' | 2          |            3 | '00000000-0000-0000-0000-00000000000b' | '00000000-0000-0000-0000-00000000000a' | 'ec2'    | 'us-west-2'    |      2 |       3 |     111 | 1.500000000 |      0.00 |      1.00 |        1.00 |             1 |

        White Rabbit checks quarterly_budget table
            | subject_type | subject_id                             | quarter | cumulativespend | spentondate           |
            | 1            | '00000000-0000-0000-0000-00000000000b' | 2       | 6.5             | '2015-05-01 00:00:00' |
            | 2            | '00000000-0000-0000-0000-00000000000a' | 2       | 21              | '2015-04-10 00:00:00' |


    Scenario Outline: Recalculate

        Analytics database has new prices
            | price_id                           | instance_type | os | cost |
            | '00000000000000000000000000000001' | 'm1.small'     | 0  | 2    |
            | '00000000000000000000000000000001' | 'm1.medium'    | 0  | 4    |
            | '00000000000000000000000000000001' | 'm1.medium'    | 1  | 5    |
            | '00000000000000000000000000000002' | 'm1.small'     | 0  | 3    |
            | '00000000000000000000000000000002' | 'm1.medium'    | 1  | 5    |

        White Rabbit starts Analytics Processing script with options '-c ../../../tests/etc/config.yml -v DEBUG --recalculate --platform ec2 --date-from 2015-05-01'
        White Rabbit waits 15 seconds
        White Rabbit stops Analytics Processing script

        White Rabbit checks usage_h table
            | account_id | dtime                 | platform | cloud_location | instance_type | os | cc_id                                  | project_id                           | env_id | farm_id | farm_role_id | role_id | num | cost |
            | 1          | '2015-05-01 03:00:00' | 'ec2'    | 'us-west-1'    | 'm1.small'    | 0  | '00000000-0000-0000-0000-00000000000b' | 00000000-0000-0000-0000-00000000000a | 1      | 1       | 1            | 111     | 3   | 6.00 |
            | 1          | '2015-05-01 03:00:00' | 'ec2'    | 'us-west-1'    | 'm1.medium'   | 0  | '00000000-0000-0000-0000-00000000000b' | 00000000-0000-0000-0000-00000000000a | 1      | 2       | 2            | 111     | 1   | 4.00 |
            | 2          | '2015-05-01 03:00:00' | 'ec2'    | 'us-west-2'    | 'm1.small'    | 0  | '00000000-0000-0000-0000-00000000000b' | 00000000-0000-0000-0000-00000000000a | 2      | 3       | 3            | 111     | 1   | 3.00 |

        White Rabbit checks usage_d table
            | date         | platform | cc_id                                  | project_id                             | farm_id | cost |
            | '2015-05-01' | 'ec2'    | '00000000-0000-0000-0000-00000000000b' | '00000000-0000-0000-0000-00000000000a' | 1       | 6    |
            | '2015-05-01' | 'ec2'    | '00000000-0000-0000-0000-00000000000b' | '00000000-0000-0000-0000-00000000000a' | 2       | 4    |
            | '2015-05-01' | 'ec2'    | '00000000-0000-0000-0000-00000000000b' | '00000000-0000-0000-0000-00000000000a' | 3       | 3    |

        White Rabbit checks farm_usage_d table
            | date         | account_id | farm_role_id | cc_id                                  | project_id                             | platform | cloud_location | env_id | farm_id | role_id | cost        | min_usage | max_usage | usage_hours | working_hours |
            | '2015-05-01' |          1 |            1 | '00000000-0000-0000-0000-00000000000b' | '00000000-0000-0000-0000-00000000000a' | 'ec2'    | 'us-west-1'    |      1 |       1 |     111 | 6.000000000 |      0.00 |      3.00 |        3.00 |             1 |
            | '2015-05-01' |          1 |            2 | '00000000-0000-0000-0000-00000000000b' | '00000000-0000-0000-0000-00000000000a' | 'ec2'    | 'us-west-1'    |      1 |       2 |     111 | 4.000000000 |      0.00 |      1.00 |        1.00 |             1 |
            | '2015-05-01' |          2 |            3 | '00000000-0000-0000-0000-00000000000b' | '00000000-0000-0000-0000-00000000000a' | 'ec2'    | 'us-west-2'    |      2 |       3 |     111 | 3.000000000 |      0.00 |      1.00 |        1.00 |             1 |

        White Rabbit checks quarterly_budget table
            | subject_type | subject_id                             | quarter | cumulativespend | spentondate           |
            | 1            | '00000000-0000-0000-0000-00000000000b' | 2       | 13              | '2015-05-01 00:00:00' |
            | 2            | '00000000-0000-0000-0000-00000000000a' | 2       | 13              | '2015-05-01 00:00:00' |

 
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
            | env_id | name                           | value                |
            | 1      | 'ec2.access_key'               | '1cePDEMoB84zhQ==\n' |
            | 1      | 'ec2.secret_key'               | 'xxlxmoD31LEi5Q==\n' |
            | 1      | 'ec2.is_enabled'               | '1'                  |
            | 1      | 'ec2.account_id'               | 'hTy6\n'             |
            | 1      | 'ec2.detailed_billing.enabled' | '1'                  |
            | 1      | 'ec2.detailed_billing.bucket'  | 'bucket'             |

        Database has farm_roles records
            | id | role_id |
            | 1  | 111     |
            | 2  | 111     |
            | 3  | 111     |

        Database has servers records
            | server_id                              | farm_id | farm_roleid | os_type | client_id |
            | '10000000-0000-0000-0000-000000000001' | 1       | 1           | 'linux' | 1         |

        Database has server_properties records
            | server_id                              | name                | value                                  |
            | '10000000-0000-0000-0000-000000000001' | 'ec2.instance-id'   | 'i-00000'                              |
            | '10000000-0000-0000-0000-000000000001' | 'ec2.instance_type' | 'm1.small'                             |
            | '10000000-0000-0000-0000-000000000001' | 'farm.project_id'   | '00000000-0000-0000-0000-00000000000a' |
            | '10000000-0000-0000-0000-000000000001' | 'env.cc_id'         | '00000000-0000-0000-0000-00000000000b' |

        Database has client_environment_properties records
            | env_id | name    | value                                  |
            | 1      | 'cc_id' | '00000000-0000-0000-0000-00000000000b' |

        White Rabbit starts Analytics Processing script with options '-c ../../../tests/etc/config.yml -v DEBUG'
        White Rabbit waits 15 seconds
        White Rabbit stops Analytics Processing script

        White Rabbit checks usage_h table
            | account_id | dtime                 | platform | cloud_location | instance_type | os | cc_id                                  | project_id                           | env_id | farm_id | farm_role_id | role_id | num | cost  |
            | 1          | '2015-05-01 00:00:00' | 'ec2'    | 'us-east-1'    | 'm1.small'    | 0  | '00000000-0000-0000-0000-00000000000b' | 00000000-0000-0000-0000-00000000000a | 1      | 1       | 1            | 111     | 1   | 0.044 |
            | 1          | '2015-05-01 03:00:00' | 'ec2'    | 'us-east-1'    | 'm1.small'    | 0  | '00000000-0000-0000-0000-00000000000b' | 00000000-0000-0000-0000-00000000000a | 1      | 1       | 1            | 111     | 1   | 0.044 |

        White Rabbit checks usage_d table
            | date          | platform | cc_id                                  | project_id                             | farm_id | cost        |
            | '2015-05-01 ' | 'ec2'    | '00000000-0000-0000-0000-00000000000b' | '00000000-0000-0000-0000-00000000000a' | 1       | 0.088000000 |

        White Rabbit checks farm_usage_d table
            | date         | account_id | farm_role_id | cc_id                                  | project_id                             | platform | cloud_location | env_id | farm_id | role_id | cost        | min_usage | max_usage | usage_hours | working_hours |
            | '2015-05-01' | 1          |            1 | '00000000-0000-0000-0000-00000000000b' | '00000000-0000-0000-0000-00000000000a' | 'ec2'    | 'us-east-1'    |      1 |       1 |     111 | 0.088000000 |      0.00 |      1.00 |        2.00 |             2 |
