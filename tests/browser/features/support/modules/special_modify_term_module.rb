# -*- encoding : utf-8 -*-
# Wikidata UI tests
#
# Author:: Thiemo Mättig
# License:: GNU GPL v2+
#
# module for the Special:ModifyTerm page

module SpecialModifyTermModule
  include PageObject
  include SpecialModifyEntityModule

  text_field(:language_input_field, id: 'wb-modifyterm-language')
  text_field(:term_input_field, id: 'wb-modifyterm-value')
end
