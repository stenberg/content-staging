Feature: create a new batch

	Scenario: successfully creating a new batch
		Given I am signed in
		And I am on the create batch page

		When I set the batch title to <title>
		And I include the <post> post in the batch
		And I include the <post> post to the batch
		And I click the Save Batch button

		Then I should get a message telling me that the batch was successfully saved
		And the batch title should be <title>
		And the <post> post should be selected
		And the <post> post should be selected
