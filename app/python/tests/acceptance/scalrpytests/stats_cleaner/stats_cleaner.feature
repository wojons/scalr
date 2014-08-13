Feature: Clean

    Scenario: Prepare 
        I make prepare

    Scenario: Clean
        I make prepare
        I have 10 farms in database
        I have 10 farms for delete
        I start stats_cleaner daemon
        I wait 10 seconds
        I see right folders were deleted
        I see right folders were not deleted
