# -*- encoding : utf-8 -*-
# Wikidata UI tests
#
# Author:: Thiemo Mättig (thiemo.maettig@wikimedia.de)
# Author:: Tobias Gritschacher (tobias.gritschacher@wikimedia.de)
# License:: GNU GPL v2+
#
# tests for the Special:SetLabel page

Then /^Language input field should be there$/ do
  on(SpecialModifyTermPage).language_input_field?.should be_true
end

When /^I enter (.*) into the language input field$/ do |language_code|
  on(SpecialModifyTermPage).language_input_field = language_code
end
