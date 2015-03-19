Feature: Analytics processing

    Scenario Outline: Calculate
        White Rabbit stops system service 'mysql'
        White Rabbit starts system service 'mysql'
        White Rabbit has config '../../../tests/etc/config.yml'
        White Rabbit drops scalr_test database
        White Rabbit creates scalr_test database
        White Rabbit drops analytics_test database
        White Rabbit creates analytics_test database

        Analytics database has poller_sessions records
            | sid                                | account_id | env_id | platform | cloud_location |
            | '00000000000000000000000000000001' | 1          | 1      | 'ec2'    | 'us-west-1'    |
            | '00000000000000000000000000000002' | 1          | 1      | 'ec2'    | 'us-west-1'    |
            | '00000000000000000000000000000003' | 2          | 2      | 'ec2'    | 'us-west-2'    |

        Analytics database has managed records
            | sid                                | server_id                              | instance_type | os | instance_id |
            | '00000000000000000000000000000001' | '10000000-0000-0000-0000-000000000001' | 'm1.small'    | 0  | i-00000     |
            | '00000000000000000000000000000002' | '20000000-0000-0000-0000-000000000001' | 'm1.small'    | 0  | i-00001     |
            | '00000000000000000000000000000002' | '20000000-0000-0000-0000-000000000002' | 'm1.small'    | 0  | i-00002     |
            | '00000000000000000000000000000002' | '20000000-0000-0000-0000-000000000003' | 'm1.medium'   | 0  | i-00003     |
            | '00000000000000000000000000000003' | '30000000-0000-0000-0000-000000000001' | 'm1.small'    | 0  | i-00004     |

        Analytics database has notmanaged records
            | sid                                | instance_id | instance_type | os |
            | '00000000000000000000000000000001' | 'i-10000'   | 'm1.small'    | 0  |
            | '00000000000000000000000000000002' | 'i-10001'   | 'm1.small'    | 0  |
            | '00000000000000000000000000000003' | 'i-10002'   | 'm1.medium'   | 1  |

        Database has servers records
            | server_id                              | farm_id | farm_roleid |
            | '10000000-0000-0000-0000-000000000001' | 1       | 1           |
            | '20000000-0000-0000-0000-000000000001' | 1       | 1           |
            | '20000000-0000-0000-0000-000000000002' | 1       | 1           |
            | '20000000-0000-0000-0000-000000000003' | 2       | 2           |
            | '30000000-0000-0000-0000-000000000001' | 3       | 3           |

        Database has server_properties records
            | server_id                              | name              | value                                  |
            | '10000000-0000-0000-0000-000000000001' | 'ec2.instance-id' | 'i-00000'                              |
            | '20000000-0000-0000-0000-000000000001' | 'ec2.instance-id' | 'i-00001'                              |
            | '20000000-0000-0000-0000-000000000002' | 'ec2.instance-id' | 'i-00002'                              |
            | '20000000-0000-0000-0000-000000000003' | 'ec2.instance-id' | 'i-00003'                              |
            | '30000000-0000-0000-0000-000000000001' | 'ec2.instance-id' | 'i-00004'                              |
            | '10000000-0000-0000-0000-000000000001' | 'farm.project_id' | '00000000-0000-0000-0000-00000000000a' |
            | '10000000-0000-0000-0000-000000000001' | 'env.cc_id      ' | '00000000-0000-0000-0000-00000000000b' |
            | '20000000-0000-0000-0000-000000000001' | 'farm.project_id' | '00000000-0000-0000-0000-00000000000a' |
            | '20000000-0000-0000-0000-000000000001' | 'env.cc_id      ' | '00000000-0000-0000-0000-00000000000b' |
            | '20000000-0000-0000-0000-000000000002' | 'farm.project_id' | '00000000-0000-0000-0000-00000000000a' |
            | '20000000-0000-0000-0000-000000000002' | 'env.cc_id      ' | '00000000-0000-0000-0000-00000000000b' |
            | '20000000-0000-0000-0000-000000000003' | 'farm.project_id' | '00000000-0000-0000-0000-00000000000a' |
            | '20000000-0000-0000-0000-000000000003' | 'env.cc_id      ' | '00000000-0000-0000-0000-00000000000b' |
            | '30000000-0000-0000-0000-000000000001' | 'farm.project_id' | '00000000-0000-0000-0000-00000000000a' |
            | '30000000-0000-0000-0000-000000000001' | 'env.cc_id      ' | '00000000-0000-0000-0000-00000000000b' |

        Database has client_environment_properties records
            | env_id | name    | value                                  |
            | 1      | 'cc_id' | '00000000-0000-0000-0000-00000000000b' |
            | 2      | 'cc_id' | '00000000-0000-0000-0000-00000000000b' |

        Analytics database has price_history records
            | price_id                           | platform  | cloud_location  | account_id |
            | '00000000000000000000000000000001' | 'ec2'     | 'us-west-1'     | 0          |
            | '00000000000000000000000000000002' | 'ec2'     | 'us-west-2'     | 0          |

        Analytics database has prices records
            | price_id                           | instance_type | os | cost |
            | '00000000000000000000000000000001' | 'm1.small'    | 0  | 1    |
            | '00000000000000000000000000000001' | 'm1.medium'   | 0  | 2    |
            | '00000000000000000000000000000001' | 'm1.medium'   | 1  | 2.5  |
            | '00000000000000000000000000000002' | 'm1.small'    | 0  | 1.5  |
            | '00000000000000000000000000000002' | 'm1.medium'   | 1  | 2.5  |

        Analytics database has quarterly_budget records
            | subject_type | subject_id                         | quarter | budget | cumulativespend | spentondate           |
            | 1            | '0000000000000000000000000000000b' | 3       | 2      | 0               | NULL                  |
            | 2            | '0000000000000000000000000000000a' | 3       | 10     | 14.5            | '2014-11-10 00:00:00' |

        White Rabbit starts script with options '-c ../../../tests/etc/config.yml -v DEBUG'
        White Rabbit waits 5 seconds
        White Rabbit stops script with options '-c ../../../tests/etc/config.yml -v DEBUG'

        White Rabbit checks usage_h table
            | account_id | platform | cloud_location | instance_type | os | cc_id                                  | project_id                           | env_id | farm_id | farm_role_id | num | cost |
            | 1          | 'ec2'    | 'us-west-1'    | 'm1.small'    | 0  | '00000000-0000-0000-0000-00000000000b' | 00000000-0000-0000-0000-00000000000a | 1      | 1       | 1            | 3   | 3    |
            | 1          | 'ec2'    | 'us-west-1'    | 'm1.medium'   | 0  | '00000000-0000-0000-0000-00000000000b' | 00000000-0000-0000-0000-00000000000a | 1      | 2       | 2            | 1   | 2    |
            | 2          | 'ec2'    | 'us-west-2'    | 'm1.small'    | 0  | '00000000-0000-0000-0000-00000000000b' | 00000000-0000-0000-0000-00000000000a | 2      | 3       | 3            | 1   | 1.5  |

        White Rabbit checks nm_usage_h table
            | platform | cloud_location | instance_type | os | cc_id                                  | env_id | num | cost |
            | 'ec2'    | 'us-west-1'    | 'm1.small'    | 0  | '00000000-0000-0000-0000-00000000000b' | 1      | 2   | 2    |
            | 'ec2'    | 'us-west-2'    | 'm1.medium'   | 1  | '00000000-0000-0000-0000-00000000000b' | 2      | 1   | 2.5  |

        White Rabbit checks usage_d table
            | platform | cc_id                                  | project_id                             | farm_id | cost |
            | 'ec2'    | '00000000-0000-0000-0000-00000000000b' | '00000000-0000-0000-0000-00000000000a' | 1       | 3    |
            | 'ec2'    | '00000000-0000-0000-0000-00000000000b' | '00000000-0000-0000-0000-00000000000a' | 2       | 2    |
            | 'ec2'    | '00000000-0000-0000-0000-00000000000b' | '00000000-0000-0000-0000-00000000000a' | 3       | 1.5  |

        White Rabbit checks nm_usage_d table
            | platform | cc_id                                  | env_id | cost |
            | 'ec2'    | '00000000-0000-0000-0000-00000000000b' | 1      | 2    |
            | 'ec2'    | '00000000-0000-0000-0000-00000000000b' | 2      | 2.5  |

        White Rabbit checks farm_usage_d table
            | account_id | farm_role_id | instance_type | cc_id                                  | project_id                             | platform | cloud_location | env_id | farm_id | role_id | cost     | min_instances | max_instances | instance_hours | working_hours |
            |          1 |            1 | 'm1.small'    | '00000000-0000-0000-0000-00000000000b' | '00000000-0000-0000-0000-00000000000a' | 'ec2'    | 'us-west-1'    |      1 |       1 |       0 | 3.000000 |             0 |             3 |              3 |             1 |
            |          1 |            2 | 'm1.medium'   | '00000000-0000-0000-0000-00000000000b' | '00000000-0000-0000-0000-00000000000a' | 'ec2'    | 'us-west-1'    |      1 |       2 |       0 | 2.000000 |             0 |             1 |              1 |             1 |
            |          2 |            3 | 'm1.small'    | '00000000-0000-0000-0000-00000000000b' | '00000000-0000-0000-0000-00000000000a' | 'ec2'    | 'us-west-2'    |      2 |       3 |       0 | 1.500000 |             0 |             1 |              1 |             1 |

        #White Rabbit checks quarterly_budget table
        #    | subject_type | subject_id                             | quarter | cumulativespend | spentondate           |
        #    | 1            | '00000000-0000-0000-0000-00000000000b' | 3       | 6.5             |                       |
        #    | 2            | '00000000-0000-0000-0000-00000000000a' | 3       | 21              | '2014-09-30 00:00:00' |


    Scenario Outline: Recalculate

        Analytics database has new prices
            | price_id                           | instance_type | os | cost |
            | '00000000000000000000000000000001' | 'm1.small'     | 0  | 2    |
            | '00000000000000000000000000000001' | 'm1.medium'    | 0  | 4    |
            | '00000000000000000000000000000001' | 'm1.medium'    | 1  | 5    |
            | '00000000000000000000000000000002' | 'm1.small'     | 0  | 3    |
            | '00000000000000000000000000000002' | 'm1.medium'    | 1  | 5    |

        White Rabbit starts script with options '-c ../../../tests/etc/config.yml -v DEBUG --recalculate --platform ec2 --date-from 2015-03-01'
        White Rabbit waits 10 seconds
        White Rabbit stops script with options '-c ../../../tests/etc/config.yml -v DEBUG'

        White Rabbit checks usage_h table
            | account_id | platform | cloud_location | instance_type | os | cc_id                                  | project_id                           | env_id | farm_id | farm_role_id | num | cost |
            | 1          | 'ec2'    | 'us-west-1'    | 'm1.small'    | 0  | '00000000-0000-0000-0000-00000000000b' | 00000000-0000-0000-0000-00000000000a | 1      | 1       | 1            | 3   | 6    |
            | 1          | 'ec2'    | 'us-west-1'    | 'm1.medium'   | 0  | '00000000-0000-0000-0000-00000000000b' | 00000000-0000-0000-0000-00000000000a | 1      | 2       | 2            | 1   | 4    |
            | 2          | 'ec2'    | 'us-west-2'    | 'm1.small'    | 0  | '00000000-0000-0000-0000-00000000000b' | 00000000-0000-0000-0000-00000000000a | 2      | 3       | 3            | 1   | 3    |

        White Rabbit checks nm_usage_h table
            | platform | cloud_location | instance_type | os | cc_id                                  | env_id | num | cost |
            | 'ec2'    | 'us-west-1'    | 'm1.small'    | 0  | '00000000-0000-0000-0000-00000000000b' | 1      | 2   | 4    |
            | 'ec2'    | 'us-west-2'    | 'm1.medium'   | 1  | '00000000-0000-0000-0000-00000000000b' | 2      | 1   | 5    |

        White Rabbit checks usage_d table
            | platform | cc_id                                  | project_id                             | farm_id | cost |
            | 'ec2'    | '00000000-0000-0000-0000-00000000000b' | '00000000-0000-0000-0000-00000000000a' | 1       | 6    |
            | 'ec2'    | '00000000-0000-0000-0000-00000000000b' | '00000000-0000-0000-0000-00000000000a' | 2       | 4    |
            | 'ec2'    | '00000000-0000-0000-0000-00000000000b' | '00000000-0000-0000-0000-00000000000a' | 3       | 3    |

        White Rabbit checks nm_usage_d table
            | platform | cc_id                                  | env_id | cost |
            | 'ec2'    | '00000000-0000-0000-0000-00000000000b' | 1      | 4    |
            | 'ec2'    | '00000000-0000-0000-0000-00000000000b' | 2      | 5    |

        White Rabbit checks farm_usage_d table
            | account_id | farm_role_id | instance_type | cc_id                                  | project_id                             | platform | cloud_location | env_id | farm_id | role_id | cost     | min_instances | max_instances | instance_hours | working_hours |
            |          1 |            1 | 'm1.small'    | '00000000-0000-0000-0000-00000000000b' | '00000000-0000-0000-0000-00000000000a' | 'ec2'    | 'us-west-1'    |      1 |       1 |       0 | 6.000000 |             0 |             3 |              3 |             1 |
            |          1 |            2 | 'm1.medium'   | '00000000-0000-0000-0000-00000000000b' | '00000000-0000-0000-0000-00000000000a' | 'ec2'    | 'us-west-1'    |      1 |       2 |       0 | 4.000000 |             0 |             1 |              1 |             1 |
            |          2 |            3 | 'm1.small'    | '00000000-0000-0000-0000-00000000000b' | '00000000-0000-0000-0000-00000000000a' | 'ec2'    | 'us-west-2'    |      2 |       3 |       0 | 3.000000 |             0 |             1 |              1 |             1 |

        #White Rabbit checks quarterly_budget table
        #    | subject_type | subject_id                             | quarter | cumulativespend | spentondate |
        #    | 1            | '00000000-0000-0000-0000-00000000000b' | 3       | 13              |             |
        #    | 2            | '00000000-0000-0000-0000-00000000000a' | 3       | 13              |             |
        
