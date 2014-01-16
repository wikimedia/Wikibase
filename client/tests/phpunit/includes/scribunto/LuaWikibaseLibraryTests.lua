--[[
	Integration tests for the mw.wikibase module

	@license GNU GPL v2+
	@author Marius Hoch < hoo@online.de >
]]

local testframework = require 'Module:TestFramework'

-- Integration tests

local function testGetEntityType()
	return type( mw.wikibase.getEntity() )
end

local function testGetEntityObjectType()
	return type( mw.wikibase.getEntityObject() )
end

local function testLabel()
	local entity = mw.wikibase.getEntityObject()
	return mw.wikibase.label( entity.id )
end

local function testSitelink()
	local entity = mw.wikibase.getEntityObject()
	return mw.wikibase.sitelink( entity.id )
end

local tests = {
	-- Integration tests

	{ name = 'mw.wikibase.getEntity (type)', func = testGetEntityType, type='ToString',
	  expect = { 'table' }
	},
	{ name = 'mw.wikibase.getEntityObject (type)', func = testGetEntityObjectType, type='ToString',
	  expect = { 'table' }
	},
	{ name = 'mw.wikibase.label', func = testLabel, type='ToString',
	  expect = { 'Lua Test Item' }
	},
	{ name = 'mw.wikibase.sitelink', func = testSitelink, type='ToString',
	  expect = { 'WikibaseClientLuaTest' }
	}
}

return testframework.getTestProvider( tests )
