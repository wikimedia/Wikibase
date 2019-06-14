- - - -
Introduction to Wikibase
====================

[![Build Status](https://travis-ci.org/wikimedia/mediawiki-extensions-Wikibase.svg)](http://travis-ci.org/wikimedia/mediawiki-extensions-Wikibase/)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/wikimedia/mediawiki-extensions-Wikibase/badges/quality-score.png)](https://scrutinizer-ci.com/g/wikimedia/mediawiki-extensions-Wikibase/)

The Wikibase.git package is part of the [Wikibase software](http://wikiba.se/) and consists of
three interconnected extensions:

* Wikibase Repository (in the directory repo)
* Wikibase Client (in the directory client)
* WikibaseLib (in the directory lib)

These extensions allow for the creation, maintenance, dissemination, and usage of structured data
in MediaWiki.

The repo is the extension for the repository. It allows the creation and maintenance of structured
data. This is being used on [wikidata.org](https://www.wikidata.org).

The client is the extension for the client. It allows several MediaWiki instances to use data provided
by a Wikidata instance. Usually, you would not use them in a single wiki. This is being used on the
Wikipedias.

The lib bundles common code that is used by both the client and the repo.

## Install

This package contains three interrelated MediaWiki extensions:

* Wikibase (in the subdirectory repo)
* WikibaseLib (in the subdirectory lib)
* Wikibase Client (in the subdirectory client)

If you are running Wikibase with hhvm, you need to enable [zend compat](http://docs.hhvm.com/hhvm/configuration/INI-settings#feature-flags)
in your php.ini:

```
hhvm.enable_zend_compat = true
```

Wikibase depends on various libraries such as [DataValues](https://github.com/DataValues/) components,
and uses [Composer](http://getcomposer.org/) to make it easy to install and manage those.

Once you have Wikibase in your MediaWiki extensions directory, add the `composer.json` of Wikibase to `composer.local.json` at the root of your mediawiki folder, as documented in [MediaWiki's Composer documentation](https://www.mediawiki.org/wiki/Composer#Using_composer-merge-plugin).

It should now look similar to:
```
{
  "extra": {
    "merge-plugin": {
       "include": [
         "extensions/Wikibase/composer.json"
       ]
    }
  }
}
```


Then, in the root of your mediawiki folder, run:
```bash
composer install
```

If you already ran `composer install` during the installation of MediaWiki, run instead:
```bash
composer update
```


> When using ways to combine MediaWiki with the extension folders (e.g. symlinks or docker volumes) please make sure that the folders are available to composer in the same structure they are available to the webserver, too.

This will install both Wikibase Client and Repo together on the same wiki.

If you want to only have one or the other, then set `$wgEnableWikibaseRepo = false` or
`$wgEnableWikibaseClient` to false for the one you don't want to enable.

Wikibase also depends on several JavaScript libraries. They are included in this repository as submodules.
To fetch files of these libraries, you might need to run, in the Wikibase extension folder, the following command:
```bash
git submodule update --init
```

### Development

Wikibase uses tools to ensure the quality of software developed. To invoke these tools, inside the Wikibase folder, run

```bash
composer install
composer run-script test
```

> As this uses development dependencies and custom configuration, executing it from the MediaWiki root folder (via `composer run-script test extensions/Wikibase`) will not work satisfactorily

## The Wikibase software

These extensions are part of the [Wikibase software](http://wikiba.se/) created by the Wikidata team
for the [Wikidata project](https://meta.wikimedia.org/wiki/Special:MyLanguage/Wikidata).

The Wikidata project uses the Wikibase software on [its website](https://www.wikidata.org).
