DIST=.dist
APP_NAME=phyxo
APP_VERSION=$(shell grep "PHPWG_VERSION'," ./include/constants.php| cut -d"'" -f4)
SOURCE=./*
TARGET=../target

all:;
	@echo "make config or make dist"


dist: config dist-tgz dist-zip


config: clean
	mkdir -p $(DIST)/$(APP_NAME)
	cp -pr *.php admin doc include install language src \
	CHANGELOG.md LICENSE README.md $(DIST)/$(APP_NAME)/

	cp -p composer.* $(DIST)/$(APP_NAME)/
	composer install --no-dev -o -d $(DIST)/$(APP_NAME)
	rm $(DIST)/$(APP_NAME)/composer.*

	# remove doc and useless stuff
	rm -fr $(DIST)/vendor/smarty/smarty/development					\
		$(DIST)/vendor/smarty/smarty/documentation				\
		$(DIST)/vendor/symfony/http-kernel/Tests				\
		$(DIST)/vendor/symfony/debug						\
		$(DIST)/vendor/symfony/routing/Tests					\
		$(DIST)/vendor/symfony/http-foundation/Tests/CookieTest.php		\
		$(DIST)/vendor/phpmailer/phpmailer/docs/DomainKeys_notes.txt		\
		$(DIST)/vendor/phpmailer/phpmailer/examples/styles/shCoreMDUltra.css	\
		$(DIST)/vendor/phpmailer/phpmailer/test/fakepopserver.sh		\
		$(DIST)/vendor/smarty/smarty/demo/templates/footer.tpl			\
		$(DIST)/vendor/guzzlehttp/guzzle/tests/MimetypesTest.php		\
		$(DIST)/vendor/guzzlehttp/ringphp/docs/index.rst			\
		$(DIST)/vendor/guzzlehttp/ringphp/tests/Client/MiddlewareTest.php	\
		$(DIST)/vendor/guzzlehttp/streams/tests/AsyncReadStreamTest.php		\
		$(DIST)/vendor/guzzlehttp/guzzle/docs/streams.rst			\
		$(DIST)/vendor/silex/silex/doc/cookbook/sub_requests.rst		\
		$(DIST)/vendor/silex/silex/tests/Silex/Tests/ControllerResolverTest.php	\
		$(DIST)/vendor/react/promise/tests/					\
		$(DIST)/vendor/tedivm/jshrink/tests/Resources/minify/output/144.js	\
		$(DIST)/vendor/pimple/pimple/tests/bootstrap.php

	rm -fr $(DIST)/$(APP_NAME)/admin/node_modules \
		$(DIST)/$(APP_NAME)/admin/bower_components

	# empty dirs
	mkdir -p $(DIST)/$(APP_NAME)/_data $(DIST)/$(APP_NAME)/upload \
	$(DIST)/$(APP_NAME)/galleries $(DIST)/$(APP_NAME)/local/config \
	$(DIST)/$(APP_NAME)/themes $(DIST)/$(APP_NAME)/plugins

	# copy only distrib plugins and themes
	cp -pr themes/clear \
		themes/dark \
		themes/default \
		themes/elegant \
		themes/Sylvia $(DIST)/$(APP_NAME)/themes/

	find $(DIST) -name '*~' -exec rm \{\} \;
	rm -fr $(DIST)/$(APP_NAME)/vendor/atoum
	find ./$(DIST)/ -type d -name '.git' | xargs -r rm -rf
	find ./$(DIST)/ -type d -name '.svn' | xargs -r rm -rf
	find ./$(DIST)/ -type f -name '.*ignore' | xargs -r rm -rf


dist-tgz: config
	cd $(DIST); \
	mkdir -p $(TARGET); \
	tar zcvf $(TARGET)/$(APP_NAME)-$(APP_VERSION).tgz $(APP_NAME) ; \
	cd ..


dist-zip: config
	cd $(DIST); \
	mkdir -p $(TARGET); \
	rm $(TARGET)/$(APP_NAME)-$(APP_VERSION).zip ; \
	zip -v -r9 $(TARGET)/$(APP_NAME)-$(APP_VERSION).zip $(APP_NAME) ; \
	cd ..


clean:
	rm -fr $(DIST)
