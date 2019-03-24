DIST=.dist
APP_NAME=phyxo
APP_VERSION=$(shell grep "PHPWG_VERSION'," ./include/constants.php| cut -d"'" -f4)
SOURCE=./*
TARGET=../target

all:;
	@echo "make config or make dist"


dist: config admin_assets dist-tgz dist-zip


config: clean
	mkdir -p $(DIST)/$(APP_NAME)
	cp -pr *.php admin include install language templates config src \
	CHANGELOG.md LICENSE README.md $(DIST)/$(APP_NAME)/
	cp -p tools/index_prod.php $(DIST)/$(APP_NAME)/index.php
	cp -p tools/.htaccess $(DIST)/$(APP_NAME)/

	cp -p composer.* $(DIST)/$(APP_NAME)/
	composer install --no-dev -o -a -d $(DIST)/$(APP_NAME)
	rm -fr $(DIST)/$(APP_NAME)/config/packages/dev $(DIST)/$(APP_NAME)/config/packages/test $(DIST)/$(APP_NAME)/config/routes/dev
	rm -f $(DIST)/$(APP_NAME)/composer.* $(DIST)/$(APP_NAME)/symfony.lock $(DIST)/$(APP_NAME)/src/Log.php

	# remove doc and useless stuff
	find $(DIST)/$(APP_NAME)/vendor -path '*/.git/*'	\
		-o -path '*/Tests/*'				\
		-o -path '*/tests/*'				\
		-o -path '*/Test/*'				\
		-o -path '*/test/*'				\
		-o -path '*/docs/*'				\
		-o -path '*/doc/*'				\
		-o -path '*/demo/*'				\
		-o -path '*/documentation/*'			\
		-o -path '*/examples/*'	| xargs rm -fr ; 	\

	# empty dirs
	mkdir -p $(DIST)/$(APP_NAME)/_data $(DIST)/$(APP_NAME)/upload	\
	$(DIST)/$(APP_NAME)/galleries $(DIST)/$(APP_NAME)/local/config	\
	$(DIST)/$(APP_NAME)/themes $(DIST)/$(APP_NAME)/plugins		\
	$(DIST)/$(APP_NAME)/var/cache/prod $(DIST)/$(APP_NAME)/var/log

	# copy only distrib plugins and themes
	cp -pr themes/treflez $(DIST)/$(APP_NAME)/themes/

	find $(DIST) -name '*~' -exec rm \{\} \;
	find $(DIST) -name '.env*' -exec rm \{\} \;
	rm -fr $(DIST)/$(APP_NAME)/public
	rm -fr $(DIST)/$(APP_NAME)/vendor/atoum
	find ./$(DIST)/ -type d -name '.git' | xargs -r rm -rf
	find ./$(DIST)/ -type d -name '.svn' | xargs -r rm -rf
	find ./$(DIST)/ -type f -name '.*ignore' | xargs -r rm -rf

admin_assets:;
	cd $(DIST)/$(APP_NAME)/admin/theme ;	\
	npm ci ;				\
	npm run build ;				\
	rm -fr src node_modules webpack.config.js package.json package-lock.json

assets:;
	cd $(DIST)/$(APP_NAME)/themes/treflez ;	\
	npm ci ;				\
	npm run build ;				\
	rm -fr src node_modules webpack.config.js package.json package-lock.json

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

unit-tests:
	./bin/atoum

unit-tests-coverage:
	./bin/atoum -ebpc -c .atoum.coverage.php

chrome:
	google-chrome-unstable --disable-gpu --headless --remote-debugging-address=0.0.0.0 --remote-debugging-port=9222
