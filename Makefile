DC := docker compose
RUN := $(DC) run --rm app
PHPUNIT := $(RUN) ./vendor/bin/phpunit
PHPSTAN := $(RUN) ./vendor/bin/phpstan
PHPCSFIXER := $(RUN) ./vendor/bin/php-cs-fixer

.PHONY: run install test test-unit test-functional phpstan phpfixer phpfixer-dry

run:
	$(DC) up --build

install:
	$(RUN) composer install

test: test-unit test-functional

test-unit:
	$(PHPUNIT) --testsuite=unit

test-functional:
	$(PHPUNIT) --testsuite=functional

phpstan:
	$(PHPSTAN) analyse -c phpstan.neon

phpfixer:
	$(PHPCSFIXER) fix --config=.php-cs-fixer.php

phpfixer-dry:
	$(PHPCSFIXER) fix --config=.php-cs-fixer.php --dry-run --diff