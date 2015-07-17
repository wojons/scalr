Feature: Message sender

    Scenario: Test Ok
        White Rabbit stops system service 'mysql'
        White Rabbit starts system service 'mysql'
        White Rabbit has config '../../../tests/etc/config.yml'
        White Rabbit drops scalr_test database
        White Rabbit creates scalr_test database

        Database has messages records
            | messageid                              | status | handle_attempts | server_id                              | type | message_version | message_name |
            | 'b0000000-0000-0000-0000-000000000001' | 0      | 0               | 'a0000000-0000-0000-0000-000000000001' | 2    | 2               | ''           |
            | 'b0000000-0000-0000-0000-000000000002' | 0      | 0               | 'a0000000-0000-0000-0000-000000000001' | 2    | 2               | 'ExecScript' |
            | 'b0000000-0000-0000-0000-000000000003' | 0      | 0               | 'a0000000-0000-0000-0000-000000000001' | 1    | 2               | ''           |

        Database has servers records
            | server_id                              | farm_id | client_id | env_id | status    | index | remote_ip   |
            | 'a0000000-0000-0000-0000-000000000001' | 1       | 1         | 1      | 'running' | 1     | '127.0.0.1' |

        Database has server_properties records
            | server_id                              | name                  | value                                                      |
            | 'a0000000-0000-0000-0000-000000000001' | 'scalarizr.ctrl_port' | '8013'                                                     |
            | 'a0000000-0000-0000-0000-000000000001' | 'scalarizr.key'       | '8mYTcBxiE70DtXCBRjn7AMuTQNzBJJcTa5uFok24X40ePafq1gUyyg==' |

        White Rabbit starts wsgi server on port 8013
        White Rabbit waits 1 seconds
        White Rabbit starts script with options '-c ../../../tests/etc/config.yml -v DEBUG'
        White Rabbit waits 2 seconds
        White Rabbit stops script with options '-c ../../../tests/etc/config.yml -v DEBUG'
        White Rabbit stops wsgi server on port 8013

        White Rabbit checks messages table
            | messageid                              | status | handle_attempts |
            | 'b0000000-0000-0000-0000-000000000001' | 1      | 1               |
            | 'b0000000-0000-0000-0000-000000000003' | 0      | 0               |


    Scenario: Test scalarizr is not available
        White Rabbit stops system service 'mysql'
        White Rabbit starts system service 'mysql'
        White Rabbit has config '../../../tests/etc/config.yml'
        White Rabbit drops scalr_test database
        White Rabbit creates scalr_test database
        
        Database has messages records
          | messageid                              | status | handle_attempts  | server_id                              | type | message_version |
          | 'b0000000-0000-0000-0000-000000000001' | 0      | 0                | 'a0000000-0000-0000-0000-000000000001' | 2    | 2               |
        
        Database has servers records
          | server_id                              | farm_id | client_id | env_id | status    | index | remote_ip   |
          | 'a0000000-0000-0000-0000-000000000001' | 1       | 1         | 1      | 'running' | 1     | '127.0.0.1' |
        
        Database has server_properties records
          | server_id                              | name                  | value                                                      |
          | 'a0000000-0000-0000-0000-000000000001' | 'scalarizr.ctrl_port' | '8013'                                                     |
          | 'a0000000-0000-0000-0000-000000000001' | 'scalarizr.key'       | '8mYTcBxiE70DtXCBRjn7AMuTQNzBJJcTa5uFok24X40ePafq1gUyyg==' |
        
        White Rabbit waits 1 seconds
        White Rabbit starts script with options '-c ../../../tests/etc/config.yml -v DEBUG'
        White Rabbit waits 2 seconds
        White Rabbit stops script with options '-c ../../../tests/etc/config.yml -v DEBUG'
        
        White Rabbit checks messages table
          | messageid                              | status | handle_attempts |
          | 'b0000000-0000-0000-0000-000000000001' | 0      | 1               |
 
 
    Scenario: Test mysql 1
        White Rabbit stops system service 'mysql'
        White Rabbit starts system service 'mysql'
        White Rabbit has config '../../../tests/etc/config.yml'
        White Rabbit drops scalr_test database
        White Rabbit creates scalr_test database
        
        Database has messages records
          | messageid                              | status | handle_attempts  | server_id                              |
          | 'b0000000-0000-0000-0000-000000000001' | 0      | 0                | 'a0000000-0000-0000-0000-000000000001' |
        
        Database has servers records
          | server_id                              | farm_id | client_id | env_id | status    | index | remote_ip   |
          | 'a0000000-0000-0000-0000-000000000001' | 1       | 1         | 1      | 'running' | 1     | '127.0.0.1' |
        
        Database has server_properties records
          | server_id                              | name                  | value                                                      |
          | 'a0000000-0000-0000-0000-000000000001' | 'scalarizr.ctrl_port' | '8013'                                                     |
          | 'a0000000-0000-0000-0000-000000000001' | 'scalarizr.key'       | '8mYTcBxiE70DtXCBRjn7AMuTQNzBJJcTa5uFok24X40ePafq1gUyyg==' |
        
        White Rabbit starts wsgi server on port 8013
        White Rabbit stops system service 'mysql'
        White Rabbit waits 1 seconds
        White Rabbit starts script with options '-c ../../../tests/etc/config.yml -v DEBUG'
        White Rabbit waits 10 seconds
        White Rabbit starts system service 'mysql'
        White Rabbit waits 6 seconds
        White Rabbit stops script with options '-c ../../../tests/etc/config.yml -v DEBUG'
        White Rabbit stops wsgi server on port 8013
        
        White Rabbit checks messages table
          | messageid                              | status | handle_attempts |
          | 'b0000000-0000-0000-0000-000000000001' | 1      | 1               |
 
 
    Scenario: Test mysql 2
        White Rabbit stops system service 'mysql'
        White Rabbit starts system service 'mysql'
        White Rabbit has config '../../../tests/etc/config.yml'
        White Rabbit drops scalr_test database
        White Rabbit creates scalr_test database
        
        Database has messages records
          | messageid                              | status | handle_attempts  | server_id                              |
          | 'b0000000-0000-0000-0000-000000000001' | 0      | 1                | 'a0000000-0000-0000-0000-000000000001' |
        
        Database has servers records
          | server_id                              | farm_id | client_id | env_id | status    | index | remote_ip   |
          | 'a0000000-0000-0000-0000-000000000001' | 1       | 1         | 1      | 'running' | 1     | '127.0.0.1' |
        
        
        Database has server_properties records
          | server_id                              | name                  | value                                                      |
          | 'a0000000-0000-0000-0000-000000000001' | 'scalarizr.ctrl_port' | '8013'                                                     |
          | 'a0000000-0000-0000-0000-000000000001' | 'scalarizr.key'       | '8mYTcBxiE70DtXCBRjn7AMuTQNzBJJcTa5uFok24X40ePafq1gUyyg==' |
        
        White Rabbit starts wsgi server on port 8013
        White Rabbit waits 1 seconds
        White Rabbit starts script with options '-c ../../../tests/etc/config.yml -v DEBUG'
        White Rabbit waits 10 seconds
        White Rabbit stops system service 'mysql'
        White Rabbit waits 120 seconds
        White Rabbit starts system service 'mysql'
        White Rabbit waits 6 seconds
        White Rabbit stops script with options '-c ../../../tests/etc/config.yml -v DEBUG'
        White Rabbit stops wsgi server on port 8013
        
        White Rabbit checks messages table
          | messageid                              | status | handle_attempts |
          | 'b0000000-0000-0000-0000-000000000001' | 1      | 2               |
     
     
    Scenario: Test vpc 1
        White Rabbit stops system service 'mysql'
        White Rabbit starts system service 'mysql'
        White Rabbit has config '../../../tests/etc/config.yml'
        White Rabbit drops scalr_test database
        White Rabbit creates scalr_test database
        
        Database has messages records
          | messageid                              | status | handle_attempts  | server_id                              |
          | 'b0000000-0000-0000-0000-000000000001' | 0      | 0                | 'a0000000-0000-0000-0000-000000000001' |
          | 'b0000000-0000-0000-0000-000000000002' | 0      | 0                | 'a0000000-0000-0000-0000-000000000002' |
          | 'b0000000-0000-0000-0000-000000000003' | 0      | 0                | 'a0000000-0000-0000-0000-000000000003' |
        
        Database has servers records
          | server_id                              | farm_id | farm_roleid | client_id | env_id | status    | index | remote_ip   | local_ip    | platform |
          | 'a0000000-0000-0000-0000-000000000001' | 1       | 1           | 1         | 1      | 'running' | 1     | '127.0.0.1' | '127.0.0.1' | 'ec2'    |
          | 'a0000000-0000-0000-0000-000000000002' | 1       | 2           | 1         | 1      | 'running' | 1     | '127.0.0.1' | '127.0.0.1' | 'gce'    |
          | 'a0000000-0000-0000-0000-000000000003' | 1       | 3           | 1         | 1      | 'running' | 1     | NULL        | '127.0.0.1' | 'idcf'   |
        
        Database has server_properties records
          | server_id                              | name                  | value                                                      |
          | 'a0000000-0000-0000-0000-000000000001' | 'scalarizr.ctrl_port' | '8013'                                                     |
          | 'a0000000-0000-0000-0000-000000000001' | 'scalarizr.key'       | '8mYTcBxiE70DtXCBRjn7AMuTQNzBJJcTa5uFok24X40ePafq1gUyyg==' |
          | 'a0000000-0000-0000-0000-000000000002' | 'scalarizr.ctrl_port' | '8013'                                                     |
          | 'a0000000-0000-0000-0000-000000000002' | 'scalarizr.key'       | '8mYTcBxiE70DtXCBRjn7AMuTQNzBJJcTa5uFok24X40ePafq1gUyyg==' |
          | 'a0000000-0000-0000-0000-000000000003' | 'scalarizr.ctrl_port' | '8013'                                                     |
          | 'a0000000-0000-0000-0000-000000000003' | 'scalarizr.key'       | '8mYTcBxiE70DtXCBRjn7AMuTQNzBJJcTa5uFok24X40ePafq1gUyyg==' |
        
        Database has farm_settings records
          | farmid | name         | value |
          | 1      | 'ec2.vpc.id' | '5'   |
        
        Database has farm_role_settings records
          | farm_roleid | name                        | value |
          | 1           | 'router.scalr.farm_role_id' | '10'  |
        
        Database has farm_role_settings records
          | farm_roleid  | name            | value       |
          | 10           | 'router.vpc.ip' | '127.0.0.1' |
        
        White Rabbit starts wsgi server on port 8013
        White Rabbit waits 1 seconds
        White Rabbit starts script with options '-c ../../../tests/etc/config.yml -v DEBUG'
        White Rabbit waits 10 seconds
        White Rabbit stops script with options '-c ../../../tests/etc/config.yml -v DEBUG'
        White Rabbit stops wsgi server on port 8013
        
        White Rabbit checks messages table
          | messageid                              | status | handle_attempts |
          | 'b0000000-0000-0000-0000-000000000001' | 1      | 1               |
          | 'b0000000-0000-0000-0000-000000000002' | 1      | 1               |
          | 'b0000000-0000-0000-0000-000000000003' | 3      | 1               |
 
 
    Scenario: Test vpc 2
        White Rabbit stops system service 'mysql'
        White Rabbit starts system service 'mysql'
        White Rabbit has config '../../../tests/etc/config.yml'
        White Rabbit drops scalr_test database
        White Rabbit creates scalr_test database
        
        Database has messages records
          | messageid                              | status | handle_attempts  | server_id                              |
          | 'b0000000-0000-0000-0000-000000000001' | 0      | 0                | 'a0000000-0000-0000-0000-000000000001' |
          | 'b0000000-0000-0000-0000-000000000002' | 0      | 0                | 'a0000000-0000-0000-0000-000000000002' |
          | 'b0000000-0000-0000-0000-000000000003' | 0      | 0                | 'a0000000-0000-0000-0000-000000000003' |
        
        Database has servers records
          | server_id                              | farm_id | farm_roleid | client_id | env_id | status    | index | remote_ip   | local_ip    | platform |
          | 'a0000000-0000-0000-0000-000000000001' | 1       | 1           | 1         | 1      | 'running' | 1     | NULL        | '127.0.0.1' | 'ec2'    |
          | 'a0000000-0000-0000-0000-000000000002' | 1       | 2           | 1         | 1      | 'running' | 1     | '127.0.0.1' | '127.0.0.1' | 'gce'    |
          | 'a0000000-0000-0000-0000-000000000003' | 1       | 3           | 1         | 1      | 'running' | 1     | NULL        | '127.0.0.1' | 'idcf'   |
        
        Database has server_properties records
          | server_id                              | name                  | value                                                      |
          | 'a0000000-0000-0000-0000-000000000001' | 'scalarizr.ctrl_port' | '8013'                                                     |
          | 'a0000000-0000-0000-0000-000000000001' | 'scalarizr.key'       | '8mYTcBxiE70DtXCBRjn7AMuTQNzBJJcTa5uFok24X40ePafq1gUyyg==' |
          | 'a0000000-0000-0000-0000-000000000002' | 'scalarizr.ctrl_port' | '8013'                                                     |
          | 'a0000000-0000-0000-0000-000000000002' | 'scalarizr.key'       | '8mYTcBxiE70DtXCBRjn7AMuTQNzBJJcTa5uFok24X40ePafq1gUyyg==' |
          | 'a0000000-0000-0000-0000-000000000003' | 'scalarizr.ctrl_port' | '8013'                                                     |
          | 'a0000000-0000-0000-0000-000000000003' | 'scalarizr.key'       | '8mYTcBxiE70DtXCBRjn7AMuTQNzBJJcTa5uFok24X40ePafq1gUyyg==' |
        
        Database has farm_settings records
          | farmid | name         | value |
          | 1      | 'ec2.vpc.id' | '5'   |
        
        Database has farm_role_settings records
          | farm_roleid | name                        | value |
          | 1           | 'router.scalr.farm_role_id' | '10'  |
        
        Database has farm_role_settings records
          | farm_roleid  | name            | value       |
          | 10           | 'router.vpc.ip' | '127.0.0.1' |
        
        White Rabbit starts wsgi server on port 80
        White Rabbit starts wsgi server on port 8013
        White Rabbit waits 1 seconds
        White Rabbit starts script with options '-c ../../../tests/etc/config.yml -v DEBUG'
        White Rabbit waits 10 seconds
        White Rabbit stops script with options '-c ../../../tests/etc/config.yml -v DEBUG'
        White Rabbit stops wsgi server on port 80
        White Rabbit stops wsgi server on port 8013
        
        White Rabbit checks messages table
          | messageid                              | status | handle_attempts |
          | 'b0000000-0000-0000-0000-000000000002' | 1      | 1               |
          | 'b0000000-0000-0000-0000-000000000002' | 1      | 1               |
          | 'b0000000-0000-0000-0000-000000000003' | 3      | 1               |


    Scenario: Test Local setup Ok
        White Rabbit stops system service 'mysql'
        White Rabbit starts system service 'mysql'
        White Rabbit has config '../../../tests/etc/config-local.yml'
        White Rabbit drops scalr_test database
        White Rabbit creates scalr_test database

        Database has messages records
            | messageid                              | status | handle_attempts | server_id                              | type | message_version | message_name |
            | 'b0000000-0000-0000-0000-000000000001' | 0      | 0               | 'a0000000-0000-0000-0000-000000000001' | 2    | 2               | ''           |
            | 'b0000000-0000-0000-0000-000000000002' | 0      | 0               | 'a0000000-0000-0000-0000-000000000001' | 2    | 2               | 'ExecScript' |
            | 'b0000000-0000-0000-0000-000000000003' | 0      | 0               | 'a0000000-0000-0000-0000-000000000001' | 1    | 2               | ''           |

        Database has servers records
            | server_id                              | farm_id | client_id | env_id | status    | index | remote_ip   | local_ip    |
            | 'a0000000-0000-0000-0000-000000000001' | 1       | 1         | 1      | 'running' | 1     | NULL        | '127.0.0.1' |

        Database has server_properties records
            | server_id                              | name                  | value                                                      |
            | 'a0000000-0000-0000-0000-000000000001' | 'scalarizr.ctrl_port' | '8013'                                                     |
            | 'a0000000-0000-0000-0000-000000000001' | 'scalarizr.key'       | '8mYTcBxiE70DtXCBRjn7AMuTQNzBJJcTa5uFok24X40ePafq1gUyyg==' |

        White Rabbit starts wsgi server on port 8013
        White Rabbit waits 1 seconds
        White Rabbit starts script with options '-c ../../../tests/etc/config-local.yml -v DEBUG'
        White Rabbit waits 2 seconds
        White Rabbit stops script with options '-c ../../../tests/etc/config-local.yml -v DEBUG'
        White Rabbit stops wsgi server on port 8013

        White Rabbit checks messages table
            | messageid                              | status | handle_attempts |
            | 'b0000000-0000-0000-0000-000000000001' | 1      | 1               |
            | 'b0000000-0000-0000-0000-000000000003' | 0      | 0               |


    Scenario: Test scalarizr is not available 2
        White Rabbit stops system service 'mysql'
        White Rabbit starts system service 'mysql'
        White Rabbit has config '../../../tests/etc/config.yml'
        White Rabbit drops scalr_test database
        White Rabbit creates scalr_test database
        
        Database has messages records
          | messageid                              | status | handle_attempts  | server_id                              | type | message_version |
          | 'b0000000-0000-0000-0000-000000000001' | 0      | 0                | 'a0000000-0000-0000-0000-000000000001' | 2    | 2               |
        
        Database has servers records
          | server_id                              | farm_id | client_id | env_id | status    | index | remote_ip   |
          | 'a0000000-0000-0000-0000-000000000001' | 1       | 1         | 1      | 'running' | 1     | '127.0.0.1' |
        
        Database has server_properties records
          | server_id                              | name                  | value                                                      |
          | 'a0000000-0000-0000-0000-000000000001' | 'scalarizr.ctrl_port' | '8013'                                                     |
          | 'a0000000-0000-0000-0000-000000000001' | 'scalarizr.key'       | '8mYTcBxiE70DtXCBRjn7AMuTQNzBJJcTa5uFok24X40ePafq1gUyyg==' |
        
        White Rabbit waits 1 seconds
        White Rabbit starts script with options '-c ../../../tests/etc/config.yml -v DEBUG'
        White Rabbit waits 370 seconds
        White Rabbit stops script with options '-c ../../../tests/etc/config.yml -v DEBUG'
        
        White Rabbit checks messages table
          | messageid                              | status | handle_attempts |
          | 'b0000000-0000-0000-0000-000000000001' | 3      | 3               |


    Scenario: Perforamance test
        White Rabbit stops system service 'mysql'
        White Rabbit starts system service 'mysql'
        White Rabbit has config '../../../tests/etc/config.yml'
        White Rabbit drops scalr_test database
        White Rabbit creates scalr_test database
        
        Database has 1500 messages
        
        White Rabbit starts wsgi server on port 8013
        White Rabbit waits 1 seconds
        White Rabbit starts script with options '-c ../../../tests/etc/config.yml -v ERROR'
        White Rabbit waits 30 seconds
        White Rabbit stops script with options '-c ../../../tests/etc/config.yml -v ERROR'
        White Rabbit stops wsgi server on port 8013
        White Rabbit checks all messages has status 1
        
