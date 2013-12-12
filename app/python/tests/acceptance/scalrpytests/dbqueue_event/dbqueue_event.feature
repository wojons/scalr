
Feature: DBQueueEvent 

    Scenario: Prepare 
        Alice prepares homework

    Scenario: Mail 
        Alice prepares homework
        White Rabbit creates 10 events with Mail observer
        Alice starts 'dbqueue_event' daemon
        Alice waits 5 seconds
        Alice stops 'dbqueue_event' daemon
        Alice update events status as handled
        Alice waits 15 seconds
        White Rabbit gets right emails

    Scenario: REST
        Alice prepares homework
        White Rabbit creates 5 events with REST observer
        Alice starts wsgi server
        Alice starts 'dbqueue_event' daemon
        Alice waits 5 seconds
        Alice stops 'dbqueue_event' daemon
        Alice stops wsgi server
        Alice update events status as handled
        White Rabbit gets 5 REST requests

    Scenario: MySQL connection fail
        Alice prepares homework
        White Rabbit creates 20 events with Mail observer
        Alice starts 'dbqueue_event' daemon
        Alice stops 'mysql' service
        Alice waits 10 seconds
        Alice starts 'mysql' service
        Alice waits 20 seconds
        Alice stops 'dbqueue_event' daemon
        Alice update events status as handled
        White Rabbit gets right emails

    Scenario: Too many events
        Alice prepares homework
        White Rabbit creates 2500 events with REST observer
        White Rabbit creates 100 events with Mail observer
        White Rabbit creates 2500 events with REST observer
        Alice starts wsgi server
        Alice starts 'dbqueue_event' daemon
        Alice waits 90 seconds
        Alice stops 'dbqueue_event' daemon
        Alice stops wsgi server
        Alice update events status as handled
        White Rabbit gets 5000 REST requests
        White Rabbit gets right emails

