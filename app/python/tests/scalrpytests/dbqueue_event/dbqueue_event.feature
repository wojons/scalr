Feature: DBQueueEvent

    Scenario: Ok
        White Rabbit stops system service 'mysql'
        White Rabbit starts system service 'mysql'
        White Rabbit has config '../../../tests/etc/config.yml'
        White Rabbit drops scalr_test database
        White Rabbit creates scalr_test database

        Database has webhook_history records
            | history_id                             | webhook_id                             | endpoint_id                            | status |
            | 'a0000000-0000-0000-0000-000000000001' | 'a0000000-0000-0000-0000-000000000001' | 'a0000000-0000-0000-0000-000000000001' | 0      |
            | 'a0000000-0000-0000-0000-000000000002' | 'a0000000-0000-0000-0000-000000000002' | 'a0000000-0000-0000-0000-000000000002' | 0      |

        Database has webhook_endpoints records
            | endpoint_id                            | url                   |
            | 'a0000000-0000-0000-0000-000000000001' | 'http://localhost:80' |
            | 'a0000000-0000-0000-0000-000000000002' | 'http://localhost:81' |

        White Rabbit starts wsgi server on port 80
        White Rabbit waits 1 seconds
        White Rabbit starts script with options '-c ../../../tests/etc/config.yml -v DEBUG'
        White Rabbit waits 3 seconds
        White Rabbit stops script with options '-c ../../../tests/etc/config.yml -v DEBUG'
        White Rabbit stops wsgi server on port 80

        White Rabbit checks webhook_history
            | history_id                             | status | response_code | handle_attempts | error_msg         |
            | 'a0000000-0000-0000-0000-000000000001' | 1      | 200           | 1               |                   |
            | 'a0000000-0000-0000-0000-000000000002' | 0      | NULL          | 1               | 'ConnectionError' |

    Scenario: HTTPS Ok
        White Rabbit stops system service 'mysql'
        White Rabbit starts system service 'mysql'
        White Rabbit has config '../../../tests/etc/config.yml'
        White Rabbit drops scalr_test database
        White Rabbit creates scalr_test database

        Database has webhook_history records
            | history_id                             | webhook_id                             | endpoint_id                            | status |
            | 'a0000000-0000-0000-0000-000000000001' | 'a0000000-0000-0000-0000-000000000001' | 'a0000000-0000-0000-0000-000000000001' | 0      |
            | 'a0000000-0000-0000-0000-000000000002' | 'a0000000-0000-0000-0000-000000000002' | 'a0000000-0000-0000-0000-000000000002' | 0      |

        Database has webhook_endpoints records
            | endpoint_id                            | url                      |
            | 'a0000000-0000-0000-0000-000000000001' | 'https://localhost:444'  |
            | 'a0000000-0000-0000-0000-000000000002' | 'https://localhost:8888' |

        White Rabbit starts https wsgi server on port 444
        White Rabbit waits 1 seconds
        White Rabbit starts script with options '-c ../../../tests/etc/config.yml --disable-ssl-verification -v DEBUG'
        White Rabbit waits 5 seconds
        White Rabbit stops script with options '-c ../../../tests/etc/config.yml -v DEBUG'
        White Rabbit stops wsgi server on port 444

        White Rabbit checks webhook_history
            | history_id                             | status | response_code | handle_attempts | error_msg         |
            | 'a0000000-0000-0000-0000-000000000001' | 1      | 200           | 1               |                   |
            | 'a0000000-0000-0000-0000-000000000002' | 0      | NULL          | 1               | 'ConnectionError' |

    Scenario: redirect Ok
        White Rabbit stops system service 'mysql'
        White Rabbit starts system service 'mysql'
        White Rabbit has config '../../../tests/etc/config.yml'
        White Rabbit drops scalr_test database
        White Rabbit creates scalr_test database

        Database has webhook_history records
            | history_id                             | webhook_id                             | endpoint_id                            | status |
            | 'a0000000-0000-0000-0000-000000000001' | 'a0000000-0000-0000-0000-000000000001' | 'a0000000-0000-0000-0000-000000000001' | 0      |
            | 'a0000000-0000-0000-0000-000000000002' | 'a0000000-0000-0000-0000-000000000002' | 'a0000000-0000-0000-0000-000000000002' | 0      |

        Database has webhook_endpoints records
            | endpoint_id                            | url                   |
            | 'a0000000-0000-0000-0000-000000000001' | 'http://localhost:80' |
            | 'a0000000-0000-0000-0000-000000000002' | 'http://localhost:81' |

        White Rabbit starts wsgi server on port 80 with redirect on port 8080
        White Rabbit starts wsgi server on port 8080
        White Rabbit waits 1 seconds
        White Rabbit starts script with options '-c ../../../tests/etc/config.yml -v DEBUG'
        White Rabbit waits 3 seconds
        White Rabbit stops script with options '-c ../../../tests/etc/config.yml -v DEBUG'
        White Rabbit stops wsgi server on port 80
        White Rabbit stops wsgi server on port 8080

        White Rabbit checks webhook_history
            | history_id                             | status | response_code | handle_attempts | error_msg         |
            | 'a0000000-0000-0000-0000-000000000001' | 1      | 200           | 1               |                   |
            | 'a0000000-0000-0000-0000-000000000002' | 0      | NULL          | 1               | 'ConnectionError' |

    Scenario: Not Ok
        White Rabbit stops system service 'mysql'
        White Rabbit starts system service 'mysql'
        White Rabbit has config '../../../tests/etc/config.yml'
        White Rabbit drops scalr_test database
        White Rabbit creates scalr_test database

        Database has webhook_history records
            | history_id                             | webhook_id                             | endpoint_id                            | status |
            | 'a0000000-0000-0000-0000-000000000001' | 'a0000000-0000-0000-0000-000000000001' | 'a0000000-0000-0000-0000-000000000001' | 0      |

        Database has webhook_endpoints records
            | endpoint_id                            | url                   |
            | 'a0000000-0000-0000-0000-000000000001' | 'http://localhost:80' |

        White Rabbit starts wsgi server with 500 response code on port 80
        White Rabbit waits 1 seconds
        White Rabbit starts script with options '-c ../../../tests/etc/config.yml -v DEBUG'
        White Rabbit waits 3 seconds

        White Rabbit checks webhook_history
            | history_id                             | status | response_code | handle_attempts | error_msg               |
            | 'a0000000-0000-0000-0000-000000000001' | 0      | 500           | 1               | 'Internal Server Error' |

        White Rabbit stops script with options '-c ../../../tests/etc/config.yml -v DEBUG'
        White Rabbit stops wsgi server with 500 response code on port 80


    Scenario: Ok after Not Ok
        White Rabbit stops system service 'mysql'
        White Rabbit starts system service 'mysql'
        White Rabbit has config '../../../tests/etc/config.yml'
        White Rabbit drops scalr_test database
        White Rabbit creates scalr_test database

        Database has webhook_history records
            | history_id                             | webhook_id                             | endpoint_id                            | status |
            | 'a0000000-0000-0000-0000-000000000001' | 'a0000000-0000-0000-0000-000000000001' | 'a0000000-0000-0000-0000-000000000001' | 0      |

        Database has webhook_endpoints records
            | endpoint_id                            | url                   |
            | 'a0000000-0000-0000-0000-000000000001' | 'http://localhost:80' |

        White Rabbit starts wsgi server with 500 response code on port 80
        White Rabbit waits 1 seconds
        White Rabbit starts script with options '-c ../../../tests/etc/config.yml -v DEBUG'
        White Rabbit waits 3 seconds

        White Rabbit checks webhook_history
            | history_id                             | status | response_code | handle_attempts | error_msg               |
            | 'a0000000-0000-0000-0000-000000000001' | 0      | 500           | 1               | 'Internal Server Error' |

        White Rabbit stops wsgi server with 500 response code on port 80

        White Rabbit waits 172 seconds

        White Rabbit checks webhook_history
            | history_id                             | status | response_code | handle_attempts | error_msg               |
            | 'a0000000-0000-0000-0000-000000000001' | 0      | 500           | 1               | 'Internal Server Error' |

        White Rabbit starts wsgi server on port 80
        White Rabbit waits 10 seconds

        White Rabbit checks webhook_history
            | history_id                             | status | response_code | handle_attempts | error_msg               |
            | 'a0000000-0000-0000-0000-000000000001' | 1      | 200           | 2               | 'Internal Server Error' |

        White Rabbit stops script with options '-c ../../../tests/etc/config.yml -v DEBUG'


    Scenario: Timeout
        White Rabbit stops system service 'mysql'
        White Rabbit starts system service 'mysql'
        White Rabbit has config '../../../tests/etc/config.yml'
        White Rabbit drops scalr_test database
        White Rabbit creates scalr_test database

        Database has webhook_history records
            | history_id                             | webhook_id                             | endpoint_id                            | status |
            | 'a0000000-0000-0000-0000-000000000001' | 'a0000000-0000-0000-0000-000000000001' | 'a0000000-0000-0000-0000-000000000001' | 0      |

        Database has webhook_endpoints records
            | endpoint_id                            | url                   |
            | 'a0000000-0000-0000-0000-000000000001' | 'http://localhost:80' |

        White Rabbit starts wsgi server with timeout on port 80
        White Rabbit waits 1 seconds
        White Rabbit starts script with options '-c ../../../tests/etc/config.yml -v DEBUG'
        White Rabbit waits 10 seconds

        White Rabbit checks webhook_history
            | history_id                             | status | response_code | handle_attempts | error_msg     |
            | 'a0000000-0000-0000-0000-000000000001' | 0      | NULL          | 1               | 'ReadTimeout' |

        White Rabbit waits 180 seconds

        White Rabbit checks webhook_history
            | history_id                             | status | response_code | handle_attempts | error_msg     |
            | 'a0000000-0000-0000-0000-000000000001' | 0      | NULL          | 2               | 'ReadTimeout' |

        White Rabbit waits 180 seconds

        White Rabbit checks webhook_history
            | history_id                             | status | response_code | handle_attempts | error_msg     |
            | 'a0000000-0000-0000-0000-000000000001' | 0      | NULL          | 2               | 'ReadTimeout' |

        White Rabbit waits 190 seconds

        White Rabbit checks webhook_history
            | history_id                             | status | response_code | handle_attempts | error_msg     |
            | 'a0000000-0000-0000-0000-000000000001' | 2      | NULL          | 3               | 'ReadTimeout' |

        White Rabbit stops script with options '-c ../../../tests/etc/config.yml -v DEBUG'
        White Rabbit stops wsgi server on port 80

