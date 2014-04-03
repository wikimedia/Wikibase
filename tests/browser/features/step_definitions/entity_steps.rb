# -*- encoding : utf-8 -*-
# Wikidata UI tests
#
# Author:: Tobias Gritschacher (tobias.gritschacher@wikimedia.de)
# License:: GNU GPL v2+
#
# basic steps for entities

Given /^I am logged in to the repo$/ do
  visit(RepoLoginPage).login_with(ENV["WB_REPO_USERNAME"], ENV["WB_REPO_PASSWORD"])
end

Given /^I am not logged in to the repo$/ do
  visit(RepoLogoutPage)
end

Given /^I am on an item page$/ do
  step 'I have an item to test'
  step 'I am on the page of the item to test'
end

Given /^I have an item to test$/ do
  step 'I have an item with label "' + generate_random_string(8) + '" and description "' + generate_random_string(20) + '"'
end

Given /^I have an item with empty label and description$/ do
  step 'I have an item with label "" and description ""'
end

Given /^I have an item with label "([^"]*)"$/ do |label|
  step 'I have an item with label "' + label + '" and description "' + generate_random_string(20) + '"'
end

Given /^I have an item with label "(.*)" and description "(.*)"$/ do |label,description|
  item_data = '{"labels":{"en":{"language":"en","value":"' + label + '"}},"descriptions":{"en":{"language":"en","value":"' + description + '"}}}'
  wb_api = WikibaseAPI::Gateway.new(URL.repo_api)
  @item_under_test = wb_api.wb_create_entity(item_data, "item")
end

Given /^I am on the page of the item to test$/ do
  on(ItemPage).navigate_to_entity @item_under_test["url"]
end

Given /^There are properties with the following handles and datatypes:$/ do |props|
  wb_api = WikibaseAPI::Gateway.new(URL.repo_api)
  wb_api.login(ENV["WB_REPO_USERNAME"], ENV["WB_REPO_PASSWORD"])
  @properties = wb_api.wb_create_properties(props.raw)
end

Given /^I have the following items:$/ do |handles|
  wb_api = WikibaseAPI::Gateway.new(URL.repo_api)
  @items = wb_api.wb_create_items(handles.raw)
end

Given /^The copyright warning has been dismissed$/ do
  on(ItemPage).set_copyright_ack_cookie
end

Given /^Anonymous edit warnings are disabled$/ do
  on(ItemPage).set_noanonymouseditwarning_cookie
end

Given /^I am on an item page with empty label and description$/ do
  step 'I have an item with empty label and description'
  step 'I am on the page of the item to test'
end

Given /^The following sitelinks do not exist:$/ do |sitelinks|
  wb_api = WikibaseAPI::Gateway.new(URL.repo_api)
  sitelinks.raw.each do |sitelink|
    wb_api.wb_remove_sitelink(sitelink[0], sitelink[1]).should be_true
  end
end

Then /^An error message should be displayed$/ do
  on(ItemPage).wb_error_div?.should be_true
end

When /^I reload the page$/ do
  @browser.refresh
  on(ItemPage).wait_for_entity_to_load
end
