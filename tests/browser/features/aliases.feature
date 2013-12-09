# Wikidata UI tests
#
# Author:: Tobias Gritschacher (tobias.gritschacher@wikimedia.de)
# License:: GNU GPL v2+
#
# feature definition for item aliases tests

@wikidata.beta.wmflabs.org
Feature: Edit aliases

  Background:
    Given I am on an item page
      And The copyright warning has been dismissed
      And Anonymous edit warnings are disabled

  @ui_only
  Scenario: Aliases UI has all required elements
    Then Aliases UI should be there
      And Aliases add button should be there
      And Aliases edit button should not be there
      And Aliases list should be empty

  @ui_only
  Scenario: Click add button
    When I click the aliases add button
    Then New alias input field should be there
      And Aliases add button should not be there
      And Aliases edit button should not be there
      And Aliases cancel button should be there
      And Aliases save button should be disabled
      And Aliases help field should be there

  @ui_only
  Scenario: Type new alias
    When I click the aliases add button
      And I enter "alias123" as new aliases
    Then Aliases cancel button should be there
      And Aliases save button should be there
      And Modified alias input field should be there
      And New alias input field should be there

  @ui_only
  Scenario Outline: Cancel aliases
    When I click the aliases add button
      And I enter "alias123" as new aliases
      And I <cancel>
    Then Aliases add button should be there
      And Aliases save button should not be there
      And Aliases edit button should not be there
      And Aliases cancel button should not be there
      And New alias input field should not be there
      And Aliases list should be empty

    Examples:
      | cancel |
      | click the aliases cancel button |
      | press the ESC key in the new alias input field |

  @save_aliases @modify_entity
  Scenario Outline: Save alias
    When I click the aliases add button
      And I enter "alias123" as new aliases
      And I <save>
    Then Aliases list should not be empty
      And Aliases add button should not be there
      And Aliases cancel button should not be there
      And Aliases save button should not be there
      And Aliases edit button should be there
      And There should be 1 aliases in the list
      And List of aliases should be "alias123"

    Examples:
      | save |
      | click the aliases save button |
      | press the RETURN key in the new alias input field |

  @save_aliases @modify_entity
  Scenario Outline: Save alias and reload
    When I click the aliases add button
      And I enter "alias123" as new aliases
      And I <save>
      And I reload the page
    Then Aliases edit button should be there
      And There should be 1 aliases in the list
      And List of aliases should be "alias123"

    Examples:
      | save |
      | click the aliases save button |
      | press the RETURN key in the new alias input field |

  @save_aliases @modify_entity
  Scenario: Save multiple aliases
    When I click the aliases add button
      And I enter "alias1", "alias2", "alias3" as new aliases
      And I click the aliases save button
    Then Aliases list should not be empty
      And There should be 3 aliases in the list
      And List of aliases should be "alias1", "alias2", "alias3"

  @save_aliases @modify_entity
  Scenario: Remove alias
    When I click the aliases add button
      And I enter "alias1", "alias2" as new aliases
      And I click the aliases save button
      And I click the aliases edit button
      And I click the remove first alias button
      And I click the aliases save button
    Then List of aliases should be "alias2"
      And There should be 1 aliases in the list

  @save_aliases @modify_entity
  Scenario: Remove all aliases
    When I click the aliases add button
      And I enter "alias1", "alias2" as new aliases
      And I click the aliases save button
      And I click the aliases edit button
      And I click the remove first alias button
      And I click the remove first alias button
      And I click the aliases save button
    Then Aliases list should be empty
      And Aliases add button should be there

  @save_aliases @modify_entity
  Scenario: Remove all aliases and reload
    When I click the aliases add button
      And I enter "alias1", "alias2" as new aliases
      And I click the aliases save button
      And I click the aliases edit button
      And I click the remove first alias button
      And I click the remove first alias button
      And I click the aliases save button
      And I reload the page
    Then Aliases list should be empty
      And Aliases add button should be there

  @ui_only
  Scenario: Edit aliases UI
    When I click the aliases add button
      And I enter "alias123" as new aliases
      And I click the aliases save button
      And I click the aliases edit button
    Then New alias input field should be there
      And First alias input field should contain alias123
      And Aliases save button should be disabled
      And Aliases cancel button should be there
      And First remove alias button should be there

  @save_aliases @modify_entity
  Scenario: Edit multiple aliases
    When I click the aliases add button
      And I enter "alias1", "alias2" as new aliases
      And I click the aliases save button
      And I click the aliases edit button
      And I enter "alias3", "alias4" as new aliases
      And I click the remove first alias button
      And I change the first alias to alias5
      And I click the aliases save button
    Then There should be 3 aliases in the list
      And List of aliases should be "alias5", "alias3", "alias4"

  @ui_only
  Scenario: Duplicated aliases detection
    When I click the aliases add button
      And I enter "alias1", "alias2", "alias1" as new aliases
    Then Duplicate alias input field should be there
      And Aliases save button should be there
      And Aliases cancel button should be there

  @ui_only
  Scenario: Duplicated aliases resolve
    When I click the aliases add button
      And I enter "alias1", "alias2", "alias1" as new aliases
      And I click the remove first alias button
    Then Duplicate alias input field should not be there

  @save_aliases @modify_entity
  Scenario: Save duplicated aliases
    When I click the aliases add button
      And I enter "alias1", "alias2", "alias1" as new aliases
      And I click the aliases save button
    Then There should be 2 aliases in the list
      And List of aliases should be "alias1", "alias2"

  @save_aliases @modify_entity
  Scenario Outline: Special inputs for aliases
    When I click the aliases add button
      And I enter "<alias>" as new aliases
      And I click the aliases save button
    Then There should be 1 aliases in the list
      And List of aliases should be "<alias_expected>"

    Examples:
      | alias | alias_expected |
      | 0 | 0 |
      |    norm   a lize   me    | norm a lize me |
      | <script>$('body').empty();</script> | <script>$('body').empty();</script> |

  @save_aliases
  Scenario: Too long input for alias
    When I click the aliases add button
      And I enter "loooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooong" as new aliases
      And I click the aliases save button
    Then An error message should be displayed

  @save_aliases @modify_entity @bugfix
  Scenario: Zombie alias bugfix
    When I click the aliases add button
      And I enter "zombie" as new aliases
      And I click the aliases save button
      And I reload the page
      And I click the aliases edit button
      And I click the remove first alias button
      And I click the aliases save button
      And I click the aliases add button
      And I enter "alias123" as new aliases
      And I click the aliases save button
    Then There should be 1 aliases in the list
      And List of aliases should be "alias123"

  @ui_only @bugfix
  Scenario: Bugfix for addbutton appearing when it should not
    When I click the aliases add button
      And I click the aliases cancel button
      And I click the aliases add button
    Then Aliases add button should not be there
