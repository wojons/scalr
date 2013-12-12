
Feature: DNSManager

    Scenario: Prepare 
        Alice prepares homework

    Scenario: Test dns zone creating
        Alice prepares homework
        Alice clears zones files dir
        White Rabbit inserts 10 dns zones with 'Pending create' status in test database
        White Rabbit inserts 10 dns zones with 'Pending update' status in test database
        Alice starts 'dns_manager' daemon
        Alice waits 4 seconds
        Alice stops 'dns_manager' daemon
        White Rabbit checks zones files
        White Rabbit checks test database has been updated

    Scenario: Test dns zones deleting
        Alice prepares homework
        Alice clears zones files dir
        White Rabbit inserts 10 dns zones with 'Active' status in test database
        White Rabbit inserts 10 dns zones with 'Inactive' status in test database
        White Rabbit inserts 10 dns zones with 'Pending create' status in test database
        White Rabbit inserts 10 dns zones with 'Pending update' status in test database
        Alice starts 'dns_manager' daemon
        Alice waits 5 seconds
        White Rabbit changes 'Active' status of dns zones to 'Pending delete'
        Alice waits 6 seconds
        Alice stops 'dns_manager' daemon
        White Rabbit checks zones files
        White Rabbit checks test database has been updated

    Scenario: MySQL connection failed
        Alice prepares homework
        White Rabbit inserts 50 dns zones with 'Active' status in test database
        White Rabbit inserts 50 dns zones with 'Inactive' status in test database
        White Rabbit inserts 50 dns zones with 'Pending create' status in test database
        White Rabbit inserts 50 dns zones with 'Pending update' status in test database
        Alice starts 'dns_manager' daemon
        Alice waits 2 seconds
        Alice stops 'mysql' service
        Alice waits 15 seconds
        Alice starts 'mysql' service
        Alice waits 5 seconds
        White Rabbit changes 'Active' status of dns zones to 'Pending delete'
        Alice waits 30 seconds
        Alice stops 'dns_manager' daemon
        White Rabbit checks zones files
        White Rabbit checks test database has been updated

    Scenario: Monkey
        Alice prepares homework
        White Rabbit inserts 1000 wrong dns zones with 'Random' status in test database
        Alice starts 'dns_manager' daemon
        Alice waits 5 seconds
        Alice stops 'mysql' service
        Alice waits 15 seconds
        Alice starts 'mysql' service
        White Rabbit changes status of all dns zones to 'Random'
        Alice waits 60 seconds
        Alice stops 'dns_manager' daemon


