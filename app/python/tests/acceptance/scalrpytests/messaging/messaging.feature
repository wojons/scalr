Feature: Messaging 

    Scenario: Prepare 
        I make prepare

    Scenario: Delivery Ok
        I make prepare
        I have 10 messages with status 0 and type 'out'
        I start wsgi server
        I start messaging daemon
        I wait 8 seconds
        I stop messaging daemon
        I stop wsgi server
        I see right messages were delivered

    Scenario: VPC delivery Ok
        I make prepare
        I have 20 vpc messages with status 0 and type 'out'
        I start wsgi server
        I start messaging daemon
        I wait 8 seconds
        I stop messaging daemon
        I stop wsgi server
        I see right messages were delivered
 
    Scenario: Delivery Failed
        I make prepare
        I have 20 messages with status 1 and type 'out'
        I have 2 messages with status 0 and type 'out'
        I have 2 messages with status 0 and type 'in'
        I have 2 vpc messages with status 0 and type 'out'
        I have 2 vpc messages with status 0 and type 'in'
        I start messaging daemon
        I wait 10 seconds
        I stop messaging daemon
        I see right messages have 1 handle_attempts

    Scenario: MySQL connection fail
        I make prepare
        I have 500 messages with status 0 and type 'out'
        I start wsgi server
        I start messaging daemon
        I stop 'mysql' service
        I wait 15 seconds
        I start 'mysql' service
        I wait 30 seconds
        I stop messaging daemon
        I stop wsgi server
        I see right messages were delivered

    Scenario: Too many messages
        I make prepare
        I have 5000 messages with status 0 and type 'out'
        I start wsgi server
        I start messaging daemon
        I wait 100 seconds
        I stop messaging daemon
        I stop wsgi server
        I see right messages were delivered

    Scenario: Wrong data
        I make prepare
        I have 1000 wrong messages with status 0 and type 'out'
        I start wsgi server
        I start messaging daemon
        I wait 30 seconds
        I stop messaging daemon
        I stop wsgi server
