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
	find $(DIST)/vendor -path '*/.git/*'			\
		-o -path '*/Tests/*'				\
		-o -path '*/tests/*'				\
		-o -path '*/Test/*'				\
		-o -path '*/test/*'				\
		-o -path '*/docs/*'				\
		-o -path '*/doc/*'				\
		-o -path '*/demo/*'				\
		-o -path '*/documentation/*'			\
		-o -path '*/examples/*'	| xargs rm -fr ; 	\

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
	rm -fr $(DIST)/phyxo/admin/themes/default/node_modules
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
