# Wikidata UI tests
#
# Author:: Tobias Gritschacher (tobias.gritschacher@wikimedia.de)
# License:: GNU GPL v2+
#
# feature definition for item type statements tests

@wikidata.beta.wmflabs.org
Feature: Creating statements of type item

  Background:
    Given I am on an item page
      And The copyright warning has been dismissed
      And Anonymous edit warnings are disabled

  @repo_login @modify_entity
  Scenario Outline: Adding a statement of type item
    Given I have the following properties with datatype:
      | itemprop | wikibase-item |
    Given I have the following items:
      | item1 |
    When I click the statement add button
      And I select the property itemprop
      And I enter the label of item <item> as statement value
      And I <save>
    Then Statement add button should be there
    And Statement cancel button should not be there
    And Statement save button should not be there
    And Entity selector input element should not be there
    And Statement value input element should not be there
    And Statement edit button for claim 1 in group 1 should be there
    And Statement name of group 1 should be the label of itemprop
    And Statement value of claim 1 in group 1 should be the label of item <item>

  Examples:
    | item  | save                            |
    | item1 | click the statement save button |
