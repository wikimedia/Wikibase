#! /bin/bash

set -x

PHPVERSION=`phpenv version-name`

if [ "${PHPVERSION}" = 'hhvm-nightly' ]
then
	PHPINI=/etc/hhvm/php.ini
	echo "hhvm.enable_zend_compat = true" >> $PHPINI
fi

originalDirectory=$(pwd)

cd ..

wget https://github.com/wikimedia/mediawiki/archive/$MW.tar.gz
tar -zxf $MW.tar.gz
mv mediawiki-$MW phase3

cd phase3
composer install --no-dev
wget https://phar.phpunit.de/phpunit.phar
chmod +x phpunit.phar
mv phpunit.phar tests/phpunit/

mysql -e 'create database its_a_mw;'
php maintenance/install.php --dbtype $DBTYPE --dbuser root --dbname its_a_mw --dbpath $(pwd) --pass nyan TravisWiki admin

cd extensions

if [ "$WB" != "repo" ]; then
	git clone https://gerrit.wikimedia.org/r/p/mediawiki/extensions/Scribunto.git --depth 1
fi
git clone https://gerrit.wikimedia.org/r/mediawiki/extensions/cldr --depth 1

cp -r $originalDirectory Wikibase

cd Wikibase

composer self-update
composer install --prefer-source
