Feature: Scalarizr update service

    Scenario: Test Ok
        White Rabbit stops system service 'mysql'
        White Rabbit starts system service 'mysql'
        White Rabbit has config '../../../tests/etc/config.yml'
        White Rabbit drops scalr_test database
        White Rabbit creates scalr_test database

        Database has servers records
            | server_id                              | farm_id | farm_roleid | status    | index | remote_ip   | local_ip    | platform |
            | 'a0000000-0000-0000-0000-000000000001' | 1       | 1           | 'running' | 1     | '127.0.0.1' | NULL        | 'ec2'    |
            | 'a0000000-0000-0000-0000-000000000002' | 2       | 2           | 'running' | 1     | NULL        | '127.0.0.1' | 'ec2'    |

        Database has server_properties records
            | server_id                              | name                  | value                                                      |
            | 'a0000000-0000-0000-0000-000000000001' | 'scalarizr.updc_port' | '8008'                                                     |
            | 'a0000000-0000-0000-0000-000000000001' | 'scalarizr.key'       | '8mYTcBxiE70DtXCBRjn7AMuTQNzBJJcTa5uFok24X40ePafq1gUyyg==' |
            | 'a0000000-0000-0000-0000-000000000001' | 'scalarizr.version'   | '2.7.7' |
            | 'a0000000-0000-0000-0000-000000000002' | 'scalarizr.updc_port' | '8008'                                                     |
            | 'a0000000-0000-0000-0000-000000000002' | 'scalarizr.key'       | '9mYTcBxiE70DtXCBRjn7AMuTQNzBJJcTa5uFok24X40ePafq1gUyyg==' |
            | 'a0000000-0000-0000-0000-000000000002' | 'scalarizr.version'   | '2.7.8' |

        Database has farm_settings records
            | farmid | name               | value   |
            | 1      | 'szr.upd.schedule' | '* * *' |
            | 2      | 'ec2.vpc.id'       | '5'     |
        
        Database has farm_roles records
            | id | role_id |
            | 1  | 11      |
            | 2  | 22      |

        Database has roles records
            | id | os_id          |
            | 11 | 'ubuntu-12-04' |
            | 22 | 'ubuntu-12-04' |

        Database has farm_role_settings records
            | farm_roleid | name                        | value       |
            | 1           | 'scheduled_on'              | ''          |
            | 2           | 'router.scalr.farm_role_id' | '10'        |
            | 2           | 'base.upd.schedule'         | '* * *'     |
            | 2           | 'scheduled_on'              | ''          |
            | 10          | 'router.vpc.ip'             | '127.0.0.1' |

        White Rabbit starts scalarizr update client on port 8008
        White Rabbit starts vpc router
        White Rabbit waits 1 seconds
        White Rabbit starts script with options '-c ../../../tests/etc/config.yml -v DEBUG'
        White Rabbit waits 30 seconds
        White Rabbit stops script with options '-c ../../../tests/etc/config.yml -v DEBUG'
        White Rabbit stops scalarizr update client on port 8008
        White Rabbit stops vpc router
        White Rabbit checks server with server_id 'a0000000-0000-0000-0000-000000000001' was updated
        White Rabbit checks server with server_id 'a0000000-0000-0000-0000-000000000002' was updated


    Scenario: Test StriderCD
        White Rabbit stops system service 'mysql'
        White Rabbit starts system service 'mysql'
        White Rabbit has config '../../../tests/etc/config-stridercd.yml'
        White Rabbit drops scalr_test database
        White Rabbit creates scalr_test database

        Database has servers records
            | server_id                              | farm_id | farm_roleid | status    | index | remote_ip   | local_ip    | platform |
            | 'a0000000-0000-0000-0000-000000000001' | 1       | 1           | 'running' | 1     | '127.0.0.1' | NULL        | 'ec2'    |

        Database has server_properties records
            | server_id                              | name                  | value                                                      |
            | 'a0000000-0000-0000-0000-000000000001' | 'scalarizr.updc_port' | '8008'                                                     |
            | 'a0000000-0000-0000-0000-000000000001' | 'scalarizr.key'       | '8mYTcBxiE70DtXCBRjn7AMuTQNzBJJcTa5uFok24X40ePafq1gUyyg==' |
            | 'a0000000-0000-0000-0000-000000000001' | 'scalarizr.version'   | '2.7.7' |

        Database has farm_settings records
            | farmid | name               | value   |
            | 1      | 'szr.upd.schedule' | '* * *' |

        Database has farm_roles records
            | id | role_id |
            | 1  | 11      |
            | 2  | 22      |

        Database has roles records
            | id | os_id          |
            | 11 | 'ubuntu-12-04' |
            | 22 | 'ubuntu-12-04' |

        Database has farm_role_settings records
            | farm_roleid | name            | value       |
            | 1           | 'scheduled_on'  | ''          |
            | 10          | 'router.vpc.ip' | '127.0.0.1' |

        White Rabbit starts scalarizr update client on port 8008
        White Rabbit starts vpc router
        White Rabbit waits 1 seconds
        White Rabbit starts script with options '-c ../../../tests/etc/config-stridercd.yml -v DEBUG'
        White Rabbit waits 30 seconds
        White Rabbit stops script with options '-c ../../../tests/etc/config-stridercd.yml -v DEBUG'
        White Rabbit stops scalarizr update client on port 8008
        White Rabbit stops vpc router
        White Rabbit checks server with server_id 'a0000000-0000-0000-0000-000000000001' was updated


    Scenario: Test Not Ok
        White Rabbit stops system service 'mysql'
        White Rabbit starts system service 'mysql'
        White Rabbit has config '../../../tests/etc/config.yml'
        White Rabbit drops scalr_test database
        White Rabbit creates scalr_test database

        Database has servers records
            | server_id                              | farm_id | farm_roleid | status    | index | remote_ip   | local_ip    | platform |
            | 'a0000000-0000-0000-0000-000000000001' | 1       | 1           | 'running' | 1     | '127.0.0.1' | NULL        | 'ec2'    |

        Database has server_properties records
            | server_id                              | name                  | value                                                      |
            | 'a0000000-0000-0000-0000-000000000001' | 'scalarizr.updc_port' | '8008'                                                     |
            | 'a0000000-0000-0000-0000-000000000001' | 'scalarizr.key'       | '8mYTcBxiE70DtXCBRjn7AMuTQNzBJJcTa5uFok24X40ePafq1gUyyg==' |
            | 'a0000000-0000-0000-0000-000000000001' | 'scalarizr.version'   | '2.7.7' |

        Database has farm_settings records
            | farmid | name               | value   |
            | 1      | 'szr.upd.schedule' | '* * *' |
        
        Database has farm_roles records
            | id | role_id |
            | 1  | 11      |
            | 2  | 22      |

        Database has roles records
            | id | os_id          |
            | 11 | 'ubuntu-12-04' |
            | 22 | 'ubuntu-12-04' |

        Database has farm_role_settings records
            | farm_roleid | name                        | value   |
            | 1           | 'scheduled_on'              | ''      |

        White Rabbit starts failed scalarizr update client on port 8008
        White Rabbit waits 1 seconds
        White Rabbit starts script with options '-c ../../../tests/etc/config.yml -v DEBUG'
        White Rabbit waits 50 seconds
        White Rabbit stops script with options '-c ../../../tests/etc/config.yml -v DEBUG'
        White Rabbit stops scalarizr update client on port 8008
        White Rabbit checks server with server_id 'a0000000-0000-0000-0000-000000000001' was updated 0 times

