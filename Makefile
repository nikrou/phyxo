DIST=.dist
APP_NAME=phyxo
CORE_VERSION=$(shell grep "core_version:" ./config/parameters.yaml| sed -e 's/.*: //')
ifneq (, $(findstring '-dev',$(CORE_VERSION)))
  APP_VERSION=$(CORE_VERSION)'-'$$(date +%Y%m%d%H%M%S)
else
  APP_VERSION=$(CORE_VERSION)
endif
SOURCE=./*
TARGET=../target
COMPOSER=composer

DEV_PATH=$(DIST)/$(APP_NAME)
ADMIN_THEME_PATH=admin/theme
PUBLIC_THEME_PATH=themes/treflez

ADMIN_MANIFEST=$(ADMIN_THEME_PATH)/build/manifest.json
PUBLIC_MANIFEST=$(PUBLIC_THEME_PATH)/build/manifest.json

.DEFAULT_GOAL := help
.PHONY: help

##
## Build phyxo
## -----------
##

config: clean ## prepare environment for building archive
	mkdir -p $(DIST)/$(APP_NAME)/bin
	cp -pr .env public include install templates translations config src \
	CHANGELOG.md LICENSE README.md $(DIST)/$(APP_NAME)/
	cp -p tools/.htaccess $(DIST)/$(APP_NAME)/

	cp -p composer.* symfony.lock $(DIST)/$(APP_NAME)/
	cp -p bin/console $(DIST)/$(APP_NAME)/bin/
	$(COMPOSER) install --no-dev -o -d $(DIST)/$(APP_NAME) --no-scripts

	rm -f $(DIST)/$(APP_NAME)/bin/phpunit $(DIST)/$(APP_NAME)/bin/simple-phpunit $(DIST)/$(APP_NAME)/phpunit.xml.dist
	rm -fr $(DIST)/$(APP_NAME)/config/packages/dev $(DIST)/$(APP_NAME)/config/packages/test
	rm -f $(DIST)/$(APP_NAME)/config/services_dev.yaml $(DIST)/$(APP_NAME)/config/services_test.yaml
	rm -f $(DIST)/$(APP_NAME)/src/Log.php $(DIST)/$(APP_NAME)/config/services_prod.yaml $(DIST)/$(APP_NAME)/config/parameters_test.yaml

	# remove doc and useless stuff
	find $(DIST)/$(APP_NAME)/vendor -path '*/.git/*' | xargs rm -fr ;

	rm -fr $(DIST)/$(APP_NAME)/vendor/symfony/*/Tests					\
		$(DIST)/$(APP_NAME)/vendor/symfony/var-dumper/Test				\
		$(DIST)/$(APP_NAME)/vendor/symfony/mime/Test					\
		$(DIST)/$(APP_NAME)/vendor/symfony/http-client-contracts/Test			\
		$(DIST)/$(APP_NAME)/vendor/symfony/validator/Test				\
		$(DIST)/$(APP_NAME)/vendor/symfony/mailer/Test					\
		$(DIST)/$(APP_NAME)/vendor/symfony/http-foundation/Test				\
		$(DIST)/$(APP_NAME)/vendor/symfony/framework-bundle/Test			\
		$(DIST)/$(APP_NAME)/vendor/symfony/service-contracts/Test			\
		$(DIST)/$(APP_NAME)/vendor/symfony/doctrine-bridge/Test				\
		$(DIST)/$(APP_NAME)/vendor/symfony/service-contracts/Test			\
		$(DIST)/$(APP_NAME)/vendor/doctrine/deprecations/test_fixtures			\
		$(DIST)/$(APP_NAME)/vendor/psr/log/Psr/Log/Test					\
		$(DIST)/$(APP_NAME)/vendor/twig/twig/src/Test					\
		$(DIST)/$(APP_NAME)/vendor/doctrine/deprecations/tests				\
		$(DIST)/$(APP_NAME)/vendor/laminas/laminas-eventmanager/src/Test		\
		$(DIST)/$(APP_NAME)/vendor/openpsa/universalfeedcreator/test			\
		$(DIST)/$(APP_NAME)/vendor/twig/twig/doc					\
		$(DIST)/$(APP_NAME)/vendor/imagine/imagine/src/resources			\
		$(DIST)/$(APP_NAME)/vendor/symfony/intl/Resources/data/transliterator/emoji/	\
		$(DIST)/$(APP_NAME)/vendor/symfony/intl/Resources/data/currencies		\
		$(DIST)/$(APP_NAME)/vendor/symfony/intl/Resources/data/timezones

	find $(DIST)/$(APP_NAME)/vendor/symfony/intl/Resources/data/locales/* | grep -v '(fr|en)' | xargs rm -fr ;

	# empty dirs
	mkdir -p $(DIST)/$(APP_NAME)/upload $(DIST)/$(APP_NAME)/media							\
	$(DIST)/$(APP_NAME)/local/config										\
	$(DIST)/$(APP_NAME)/themes $(DIST)/$(APP_NAME)/plugins								\
	$(DIST)/$(APP_NAME)/var/cache/prod $(DIST)/$(APP_NAME)/var/cache/install/prod $(DIST)/$(APP_NAME)/var/log

	# add empty files in emty dirs
	touch $(DIST)/$(APP_NAME)/upload/.gitkeep $(DIST)/$(APP_NAME)/media/.gitkeep								\
	$(DIST)/$(APP_NAME)/local/config/.gitkeep												\
	$(DIST)/$(APP_NAME)/themes/.gitkeep $(DIST)/$(APP_NAME)/plugins/.gitkeep								\
	$(DIST)/$(APP_NAME)/var/cache/prod/.gitkeep $(DIST)/$(APP_NAME)/var/cache/install/prod/.gitkeep $(DIST)/$(APP_NAME)/var/log/.gitkeep

	find $(DIST) -name '*~' -exec rm \{\} \;
	find $(DIST) -name '.env.local*' -o -name '.env.*.local' -exec rm \{\} \;
	echo 'APP_ENV=prod' > $(DIST)/$(APP_NAME)/.env.local

	rm -fr $(DIST)/$(APP_NAME)/vendor/symfony/phpunit-bridge
	find ./$(DIST)/ -type d -name '.git' | xargs -r rm -rf
	find ./$(DIST)/ -type f -name '.*ignore' | xargs -r rm -rf

config_assets:
	# copy only distrib plugins and themes
	cp -pr themes/treflez $(DIST)/$(APP_NAME)/themes/

	# copy admin theme
	cp -pr admin $(DIST)/$(APP_NAME)/

	# remove node_modules and other stuff for dev
	rm -fr $(DIST)/$(APP_NAME)/themes/treflez/src $(DIST)/$(APP_NAME)/themes/treflez/node_modules \
	 	$(DIST)/$(APP_NAME)/themes/treflez/webpack.config.js $(DIST)/$(APP_NAME)/themes/treflez/package.json \
	 	$(DIST)/$(APP_NAME)/themes/treflez/package-lock.json $(DIST)/$(APP_NAME)/themes/treflez/postcss.config.js \
	 	$(DIST)/$(APP_NAME)/admin/theme/src $(DIST)/$(APP_NAME)/admin/theme/node_modules \
	 	$(DIST)/$(APP_NAME)/admin/theme/webpack.config.js $(DIST)/$(APP_NAME)/admin/theme/package.json \
	 	$(DIST)/$(APP_NAME)/admin/theme/package-lock.json $(DIST)/$(APP_NAME)/admin/theme/postcss.config.js


build: config $(ADMIN_MANIFEST) $(PUBLIC_MANIFEST) config_assets ## build/prepare archives for Phyxo

dist: build dist-tgz dist-zip ## create compressed archives (zip and tgz) for Phyxo

dist-tgz: config $(ADMIN_MANIFEST) $(PUBLIC_MANIFEST) config_assets ## build tgz archive for Phyxo
	cd $(DIST); \
	mkdir -p $(TARGET); \
	tar zcvf $(TARGET)/$(APP_NAME)-$(APP_VERSION).tgz $(APP_NAME) ; \
	cd ..

dist-zip: config $(ADMIN_MANIFEST) $(PUBLIC_MANIFEST) config_assets ## build zip archive for Phyxo
	cd $(DIST); \
	mkdir -p $(TARGET); \
	rm -f $(TARGET)/$(APP_NAME)-$(APP_VERSION).zip ; \
	zip -v -r9 $(TARGET)/$(APP_NAME)-$(APP_VERSION).zip $(APP_NAME) ; \
	cd ..

version:
	@echo $(APP_VERSION)

##
## Assets
## -----------
##

# admin theme
build-admin-assets: $(ADMIN_MANIFEST) ## build admin theme

admin_js_files := $(wildcard admin/theme/src/*/*.js)
admin_scss_files := $(wildcard admin/theme/src/*/*.scss)

$(ADMIN_MANIFEST): $(admin_js_files) $(admin_scss_files) $(ADMIN_THEME_PATH)/webpack.config.js $(ADMIN_THEME_PATH)/node_modules
	cd $(ADMIN_THEME_PATH) ;		\
	npm run build ;				\
	cd -

$(ADMIN_THEME_PATH)/node_modules: $(ADMIN_THEME_PATH)/package-lock.json
	cd $(ADMIN_THEME_PATH) ;		\
	npm ci ;				\
	cd -


# public theme
build-public-assets: $(PUBLIC_MANIFEST) ## build public theme

public_js_files := $(wildcard themes/trelfez/src/*/*.js)
public_scss_files := $(wildcard themes/treflez/src/*/*.scss)

$(PUBLIC_MANIFEST): $(public_js_files) $(public_scss_files) $(PUBLIC_THEME_PATH)/webpack.config.js $(PUBLIC_THEME_PATH)/node_modules
	cd $(PUBLIC_THEME_PATH) ;		\
	npm run build ;				\
	cd -

$(PUBLIC_THEME_PATH)/node_modules: $(PUBLIC_THEME_PATH)/package-lock.json
	cd $(PUBLIC_THEME_PATH) ;		\
	npm ci ;				\
	cd -

##
## Development
## -----------
##

clean: ## clean dist directory
	@rm -fr $(DIST)

behat: ## execute behat tests
	@if test ! "$(DATABASE_URL)" = ""; then						\
		DATABASE_URL="$(DATABASE_URL)" ./bin/behat --stop-on-failure -v;	\
	else										\
		echo 'You must define DATABASE_URL';					\
	fi

unit-tests: ## execute unit tests
	@./bin/simple-phpunit --testdox

unit-tests-coverage: ## execute unit tests with coverage
	@./bin/simple-phpunit --testdox --coverage-html=coverage

chrome: ## launch google-chrome in headless mode to run behat tests
	@google-chrome-stable --disable-gpu --headless --remote-debugging-address=0.0.0.0 --remote-debugging-port=9222

update: ## update dependencies
	@$(COMPOSER) update

server:
    APP_ENV=test php -S 127.0.0.1:1080 -t .


help:
	@grep -E '(^[a-zA-Z0-9_-]+:.*?##.*$$)|(^##)' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "; printf "\n  \033[33mUsage:\033[0m\n    make \033[32m[target]\033[0m\n\n"}; {printf "  \033[32m%-30s\033[0m %s\n", $$1, $$2}' | sed -e 's/\[32m## /[33m/'
