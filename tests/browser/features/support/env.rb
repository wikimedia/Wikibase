# -*- encoding : utf-8 -*-
# Wikidata UI tests
#
# Author:: Tobias Gritschacher (tobias.gritschacher@wikimedia.de)
# License:: GNU GPL v2+
#
# Reused and modified from https://github.com/wikimedia/qa-browsertests/blob/master/features/support/env.rb
#
# setup & bootstrapping

# before all
require 'mediawiki_selenium/cucumber'
require 'mediawiki_selenium/pages'
require 'mediawiki_selenium/step_definitions'
require 'mediawiki_selenium'
require 'mediawiki_api/wikidata'
require 'net/http'
require 'active_support/all'
require 'require_all'

if not ENV['guess_config']
  if File.exist?('config/config.yml')
    config = YAML.load_file('config/config.yml')
    config.each do |k, v|
      unless ENV["#{k}"]
        ENV["#{k}"] = "#{v}"
      end
    end
  else
    abort('Could not find config file. Please make sure there is a config/config.yml!')
  end
else
  #WIKIDATA_REPO_URL = 'http://wikidata.beta.wmflabs.org/wiki/'
  #WIKIDATA_REPO_API = 'http://wikidata.beta.wmflabs.org/w/api.php'
  #WB_REPO_USERNAME
  #ITEM_NAMESPACE = ''
  #PROPERTY_NAMESPACE = 'Property:'
  #ITEM_ID_PREFIX = 'Q'
  #PROPERTY_ID_PREFIX = 'P'
  #ENV['LANGUAGE_CODE'] = 'en'
end

require_all 'features/support/modules'
require_all 'features/support/pages'
require_all 'features/support/utils'

Before('@repo_login') do
  abort('WB_REPO_USERNAME environment variable is not defined! Please export a value for that variable before proceeding.') unless ENV['WB_REPO_USERNAME']
  abort('WB_REPO_PASSWORD environment variable is not defined! Please export a value for that variable before proceeding.') unless ENV['WB_REPO_PASSWORD']
end

PageObject.default_element_wait = 10 # increased to avoid fails on jenkins

unless (env_no = ENV['TEST_ENV_NUMBER'].to_i).zero?
  sleep env_no * 4 # sleep time to give webdriver time to setup
end
