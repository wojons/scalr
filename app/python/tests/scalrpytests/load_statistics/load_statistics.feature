Feature: Load statistics

    Scenario: Poller
        White Rabbit stops system service 'mysql'
        White Rabbit starts system service 'mysql'
        White Rabbit has config '../../../tests/etc/config.yml'
        White Rabbit drops scalr_test database
        White Rabbit creates scalr_test database
        
        Database has clients records
          | id | status     |
          | 1  | 'Active'   |
    
        Database has client_environments records
          | id | client_id | status     |
          | 1  | 1         | 'Active'   |
    
        Database has farms records
          | id | clientid | env_id |
          | 1  | 1        | 1      |
        
        Database has servers records
          | server_id                              | farm_id | client_id | env_id | status       | index | remote_ip   | os_type |
          | 'a0000000-0000-0000-0000-000000000001' | 1       | 1         | 1      | 'Running'    | 1     | '127.0.0.1' | 'linux' |
    
        Database has server_properties records
          | server_id                              | name                  | value                                                      |
          | 'a0000000-0000-0000-0000-000000000001' | 'scalarizr.api_port'  | '8010'                                                     |
          | 'a0000000-0000-0000-0000-000000000001' | 'scalarizr.key'       | '8mYTcBxiE70DtXCBRjn7AMuTQNzBJJcTa5uFok24X40ePafq1gUyyg==' |
    
        White Rabbit starts api server on port 8010
        White Rabbit starts api server on port 80
        White Rabbit starts rrdcached service
        White Rabbit starts script with options '-c ../../../tests/etc/config.yml -v DEBUG --poller --daemon'
        White Rabbit waits 5 seconds
        White Rabbit checks rrdcached
        White Rabbit waits 5 seconds
        White Rabbit checks rrd files
        White Rabbit stops script with options '-c ../../../tests/etc/config.yml -v DEBUG'
        White Rabbit stops rrdcached service
        White Rabbit stops api server on port 8010
        White Rabbit stops api server on port 80

     Scenario: Test Plotter
        White Rabbit stops system service 'mysql'
        White Rabbit starts system service 'mysql'
        White Rabbit has config '../../../tests/etc/config.yml'
        White Rabbit drops scalr_test database
        White Rabbit creates scalr_test database

        Database has clients records
          | id | status     |
          | 1  | 'Active'   |
    
        Database has client_environments records
          | id | client_id | status     |
          | 1  | 1         | 'Active'   |
    
        Database has farms records
          | id | clientid | env_id | hash |
          | 1  | 1        | 1      | 1111 |
        
        Database has servers records
          | server_id                              | farm_id | client_id | env_id | status       | index | remote_ip   |
          | 'a0000000-0000-0000-0000-000000000001' | 1       | 1         | 1      | 'Running'    | 1     | '127.0.0.1' |
    
        Database has server_properties records
          | server_id                              | name                  | value                                                      |
          | 'a0000000-0000-0000-0000-000000000001' | 'scalarizr.api_port'  | '8010'                                                     |
          | 'a0000000-0000-0000-0000-000000000001' | 'scalarizr.key'       | '8mYTcBxiE70DtXCBRjn7AMuTQNzBJJcTa5uFok24X40ePafq1gUyyg==' |
    
        White Rabbit starts rrdcached service
        White Rabbit starts api server on port 8010
        White Rabbit starts api server on port 80
        White Rabbit starts script with options '-c ../../../tests/etc/config.yml -v DEBUG --poller --plotter --daemon'
        White Rabbit waits 5 seconds
        White Rabbit checks plotter
        White Rabbit waits 5 seconds
        White Rabbit sends request to plotter 'load_statistics?hash=1111&farmId=1&period=daily&metrics=snum'
        White Rabbit stops script with options '-c ../../../tests/etc/config.yml -v DEBUG'
        White Rabbit stops api server on port 8010
        White Rabbit stops api server on port 80
        White Rabbit stops rrdcached service

     Scenario: Test Plotter 2
        White Rabbit stops system service 'mysql'
        White Rabbit starts system service 'mysql'
        White Rabbit has config '../../../tests/etc/config-load-statistics-bind-port.yml'
        White Rabbit drops scalr_test database
        White Rabbit creates scalr_test database

        Database has clients records
          | id | status     |
          | 1  | 'Active'   |
    
        Database has client_environments records
          | id | client_id | status     |
          | 1  | 1         | 'Active'   |
    
        Database has farms records
          | id | clientid | env_id | hash |
          | 1  | 1        | 1      | 1111 |
        
        Database has servers records
          | server_id                              | farm_id | client_id | env_id | status       | index | remote_ip   |
          | 'a0000000-0000-0000-0000-000000000001' | 1       | 1         | 1      | 'Running'    | 1     | '127.0.0.1' |
    
        Database has server_properties records
          | server_id                              | name                  | value                                                      |
          | 'a0000000-0000-0000-0000-000000000001' | 'scalarizr.api_port'  | '8010'                                                     |
          | 'a0000000-0000-0000-0000-000000000001' | 'scalarizr.key'       | '8mYTcBxiE70DtXCBRjn7AMuTQNzBJJcTa5uFok24X40ePafq1gUyyg==' |
    
        White Rabbit starts rrdcached service
        White Rabbit starts api server on port 8010
        White Rabbit starts api server on port 80
        White Rabbit starts script with options '-c ../../../tests/etc/config-load-statistics-bind-port.yml -v DEBUG --poller --plotter --daemon'
        White Rabbit waits 5 seconds
        White Rabbit checks plotter
        White Rabbit waits 5 seconds
        White Rabbit sends request to plotter 'load_statistics?hash=1111&farmId=1&period=daily&metrics=snum'
        White Rabbit stops script with options '-c ../../../tests/etc/config-load-statistics-bind-port.yml -v DEBUG'
        White Rabbit stops api server on port 8010
        White Rabbit stops api server on port 80
        White Rabbit stops rrdcached service

