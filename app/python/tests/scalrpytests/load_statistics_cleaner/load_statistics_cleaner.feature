Feature: Load statistics cleaner

    Scenario: Clean them all
        White Rabbit stops system service 'mysql'
        White Rabbit starts system service 'mysql'
        White Rabbit has config '../../../tests/etc/config.yml'
        White Rabbit drops scalr_test database
        White Rabbit creates scalr_test database

        White Rabbit has 50 farms in database
        White Rabbit has 100 farms for delete
        White Rabbit starts script with options '-c ../../../tests/etc/config.yml -v DEBUG'
        White Rabbit waits 10 seconds
        White Rabbit stops script with options '-c ../../../tests/etc/config.yml -v DEBUG'
        White Rabbit sees right folders were deleted
        White Rabbit sees right folders were not deleted

