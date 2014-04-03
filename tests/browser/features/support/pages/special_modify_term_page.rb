# -*- encoding : utf-8 -*-
# Wikidata UI tests
#
# Author:: Thiemo Mättig (thiemo.maettig@wikimedia.de)
# License:: GNU GPL v2+
#
# page object for the Special:ModifyTerm page

class SpecialModifyTermPage < SpecialModifyEntityPage
  include PageObject

  page_url URL.repo_url("Special:ModifyTerm")

  text_field(:language_input_field, :id => 'wb-modifyterm-language')
  text_field(:label_input_field, :id => 'wb-modifyterm-value')

end
