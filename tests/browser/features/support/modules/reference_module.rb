# -*- encoding : utf-8 -*-
# Wikidata UI tests
#
# Author:: Tobias Gritschacher (tobias.gritschacher@wikimedia.de)
# License:: GNU GPL v2+
#
# module for reference page object

module ReferencePage
  include PageObject
  # references UI elements
  div(:referenceContainer, class: "wb-statement-references")
  div(:referenceHeading, class: "wb-statement-references-heading")
  a(:referenceHeadingToggleLink, css: ".wb-statement-references-heading a")
  div(:referenceEditHeading, xpath: "//div[contains(@class, 'wb-referenceview')]/div[contains(@class, 'wb-snaklistview-heading')]")
  div(:referenceListItems, xpath: "//div[contains(@class, 'wb-statement-references')]/div[contains(@class, 'wb-listview')]")
  div(:reference1Property, xpath: "//div[contains(@class, 'wb-referenceview')][1]/div[contains(@class, 'wb-listview')]/div[contains(@class, 'wb-snakview')][1]/div[contains(@class, 'wb-snak-property-container')]/div")
  div(:reference1Property2, xpath: "//div[contains(@class, 'wb-referenceview')][1]/div[contains(@class, 'wb-listview')]/div[contains(@class, 'wb-snakview')][2]/div[contains(@class, 'wb-snak-property-container')]/div")
  div(:reference2Property, xpath: "//div[contains(@class, 'wb-referenceview')][2]/div[contains(@class, 'wb-listview')]/div[contains(@class, 'wb-snakview')]/div[contains(@class, 'wb-snak-property-container')]/div")
  div(:reference3Property, xpath: "//div[contains(@class, 'wb-referenceview')][3]/div[contains(@class, 'wb-listview')]/div[contains(@class, 'wb-snakview')]/div[contains(@class, 'wb-snak-property-container')]/div")
  a(:reference1PropertyLink, xpath: "//div[contains(@class, 'wb-referenceview')][1]/div[contains(@class, 'wb-listview')]/div[contains(@class, 'wb-snakview')][1]/div[contains(@class, 'wb-snak-property-container')]/div/a")
  a(:reference1PropertyLink2, xpath: "//div[contains(@class, 'wb-referenceview')][1]/div[contains(@class, 'wb-listview')]/div[contains(@class, 'wb-snakview')][2]/div[contains(@class, 'wb-snak-property-container')]/div/a")
  div(:reference1Value, xpath: "//div[contains(@class, 'wb-referenceview')][1]/div[contains(@class, 'wb-listview')]/div[contains(@class, 'wb-snakview')][1]/div[contains(@class, 'wb-snak-value-container')]/div[contains(@class, 'wb-snak-value')]/div/div")
  div(:reference1Value2, xpath: "//div[contains(@class, 'wb-referenceview')][1]/div[contains(@class, 'wb-listview')]/div[contains(@class, 'wb-snakview')][2]/div[contains(@class, 'wb-snak-value-container')]/div[contains(@class, 'wb-snak-value')]/div/div")
  div(:reference2Value, xpath: "//div[contains(@class, 'wb-referenceview')][2]/div[contains(@class, 'wb-listview')]/div[contains(@class, 'wb-snakview')]/div[contains(@class, 'wb-snak-value-container')]/div[contains(@class, 'wb-snak-value')]/div/div")
  div(:reference3Value, xpath: "//div[contains(@class, 'wb-referenceview')][3]/div[contains(@class, 'wb-listview')]/div[contains(@class, 'wb-snakview')]/div[contains(@class, 'wb-snak-value-container')]/div[contains(@class, 'wb-snak-value')]/div/div")
  a(:reference1ValueLink, xpath: "//div[contains(@class, 'wb-referenceview')][1]/div[contains(@class, 'wb-listview')]/div[contains(@class, 'wb-snakview')][1]/div[contains(@class, 'wb-snak-value-container')]/div[contains(@class, 'wb-snak-value')]/div/div/a")
  a(:reference1ValueLink2, xpath: "//div[contains(@class, 'wb-referenceview')][1]/div[contains(@class, 'wb-listview')]/div[contains(@class, 'wb-snakview')][2]/div[contains(@class, 'wb-snak-value-container')]/div[contains(@class, 'wb-snak-value')]/div/div/a")
  # TODO: could this lead to problems? for CM & item type properties there is an additional "a" element around the textbox; this is not the case for string type properies
  #textarea(:referenceValueInput, xpath: "//div[contains(@class, 'valueview-ineditmode')]/div/a/textarea[contains(@class, 'valueview-input')]")
  textarea(:referenceValueInput, xpath: "//div[contains(@class, 'wb-claimlistview')]//textarea[contains(@class, 'valueview-input')]", index: 0)
  textarea(:referenceValueInput2, xpath: "//div[contains(@class, 'wb-claimlistview')]//textarea[contains(@class, 'valueview-input')]", index: 1)
  a(:saveReference, xpath: "//div[contains(@class, 'wb-referenceview')]/span[contains(@class, 'wb-edittoolbar')]/span/span/span[contains(@class, 'wb-ui-toolbar-editgroup-ineditmode')]/span/a[text()='save']")
  a(:cancelReference, xpath: "//div[contains(@class, 'wb-referenceview')]/span[contains(@class, 'wb-edittoolbar')]/span/span/span[contains(@class, 'wb-ui-toolbar-editgroup-ineditmode')]/span/a[text()='cancel']")
  a(:removeReference, xpath: "//div[contains(@class, 'wb-referenceview')]/span[contains(@class, 'wb-edittoolbar')]/span/span/span[contains(@class, 'wb-ui-toolbar-editgroup-ineditmode')]/span/a[text()='remove']")
  a(:removeReferenceLine1, xpath: "//div[contains(@class, 'wb-referenceview')]/div[contains(@class, 'wb-snaklistview-listview')]/div[contains(@class, 'wb-snakview')][1]/span[contains(@class, 'wb-removetoolbar')]/div/span/span/a[text()='remove']")
  a(:removeReferenceLine2, xpath: "//div[contains(@class, 'wb-referenceview')]/div[contains(@class, 'wb-snaklistview-listview')]/div[contains(@class, 'wb-snakview')][2]/span[contains(@class, 'wb-removetoolbar')]/div/span/span/a[text()='remove']")
  a(:addReferenceLine, xpath: "//div[contains(@class, 'wb-referenceview')]/span[contains(@class, 'wb-addtoolbar')]/div/span/span/a[text()='add']")
  a(:addReferenceToFirstClaim, xpath: "//div[contains(@class, 'wb-statement-references-container')][1]/div[contains(@class, 'wb-statement-references')]/span[contains(@class, 'wb-addtoolbar')]/div/span/span/a")
  a(:editReference1, xpath: "//div[contains(@class, 'wb-referenceview')][1]/span[contains(@class, 'wb-edittoolbar')]/span/span/span/span/a[text()='edit']")

  def wait_for_reference_value_box
    wait_until do
      self.referenceValueInput?
    end
  end

  def toggle_reference_section
    referenceHeadingToggleLink
    sleep 0.5
    wait_until do
      referenceListItems_element.visible?
    end
  end

  def add_reference_to_first_claim(property, value)
    addReferenceToFirstClaim
    self.entitySelectorInput = property
    ajax_wait
    wait_for_entity_selector_list
    wait_for_reference_value_box
    self.referenceValueInput = value
    ajax_wait
    saveReference
    ajax_wait
    wait_for_statement_request_finished
  end

end
