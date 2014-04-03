# -*- encoding : utf-8 -*-
# Wikidata UI tests
#
# Author:: Thiemo Mättig (thiemo.maettig@wikimedia.de)
# License:: GNU GPL v2+
#
# page object for the Special:ModifyEntity page

class SpecialModifyEntityPage
  include PageObject

  page_url URL.repo_url("Special:ModifyEntity")

  p(:anonymous_edit_warning, :class => 'warning')
  p(:error_message, :class => 'error')
  text_field(:id_input_field, :id => 'wb-modifyentity-id')

end
