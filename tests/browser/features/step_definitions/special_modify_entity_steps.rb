# -*- encoding : utf-8 -*-
# Wikidata UI tests
#
# Author:: Thiemo Mättig (thiemo.maettig@wikimedia.de)
# Author:: Tobias Gritschacher (tobias.gritschacher@wikimedia.de)
# License:: GNU GPL v2+
#
# tests for the Special:SetLabel page

When /^I enter (.+) in the ID input field$/ do |value|
  on(SpecialModifyEntityPage).id_input_field = value
end

When /^I enter the ID of item (.*) into the ID input field$/ do |item_handle|
  step "I enter " + @items[item_handle]['id'] + " in the ID input field"
end

Then /^Anonymous edit warning should be there$/ do
  on(SpecialModifyEntityPage).anonymous_edit_warning?.should be_true
end

Then /^Anonymous edit warning should not be there$/ do
  on(SpecialModifyEntityPage).anonymous_edit_warning?.should be_false
end

Then /^An error message should be displayed on the special page$/ do
  on(SpecialModifyEntityPage).error_message?.should be_true
end

Then /^ID input field should be there$/ do
  on(SpecialModifyEntityPage).id_input_field?.should be_true
end
