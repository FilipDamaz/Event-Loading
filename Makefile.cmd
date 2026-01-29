@echo off
setlocal

if "%1"=="" goto usage

if /i "%1"=="run" goto run
if /i "%1"=="test" goto test
if /i "%1"=="test-fast" goto test_fast
if /i "%1"=="phpstan" goto phpstan
if /i "%1"=="phpfixer" goto phpfixer

goto usage

:run
call docker compose up --build
exit /b %errorlevel%

:test
call docker compose up -d db_test rabbitmq
call docker compose run --rm --no-deps -e "DATABASE_URL=postgresql://app:app@db_test:5432/app_test?serverVersion=16&charset=utf8" app php bin/reset-test-db.php
call docker compose run --rm --no-deps -e "DATABASE_URL=postgresql://app:app@db_test:5432/app_test?serverVersion=16&charset=utf8" app ./vendor/bin/phpunit
exit /b %errorlevel%

:test_fast
call docker compose up -d db_test rabbitmq
call docker compose run --rm --no-deps -e "DATABASE_URL=postgresql://app:app@db_test:5432/app_test?serverVersion=16&charset=utf8" app php bin/reset-test-db.php
call docker compose run --rm --no-deps -e "DATABASE_URL=postgresql://app:app@db_test:5432/app_test?serverVersion=16&charset=utf8" app ./vendor/bin/phpunit --testsuite=unit,functional
exit /b %errorlevel%

:phpstan
call docker compose run --rm app ./vendor/bin/phpstan analyse -c phpstan.neon --memory-limit=512M
exit /b %errorlevel%

:phpfixer
call docker compose run --rm app ./vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php
exit /b %errorlevel%

:usage
echo Usage: Makefile.cmd ^<run^|test^|test-fast^|phpstan^|phpfixer^>
exit /b 1
