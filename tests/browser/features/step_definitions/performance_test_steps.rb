# -*- encoding : utf-8 -*-
# Wikidata item tests
#
# License:: GNU GPL v2+
#
# steps for the performance test

items_under_test = {}

Given(/^Entity (.+) defined in (.+) exists$/) do |pagename, data_file|
  wb_api = MediawikiApi::Wikidata::WikidataClient.new URL.repo_api
  item_under_test = wb_api.search_entities(pagename, 'en', 'item')['search'][0]

  unless item_under_test
    items = JSON.parse(IO.read(data_file))
    item_under_test = on(EntityPage).create_entity_and_properties(items)
  end

  items_under_test[pagename] = item_under_test
end

When(/^I load the huge item (.+)$/) do |pagename|
  visit(ItemPage) do |page|
    page.navigate_to items_under_test[pagename]['url']
    page.wait_for_entity_to_load
  end
end
