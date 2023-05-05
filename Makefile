include ./.env

setup-ci: env-prepare install lint

env-prepare:
	cp -n .env.example .env || true

install:
	composer install

lint:
	composer lint

lint-fix:
	composer exec phpcbf -v

build-php:
	docker build -t kitman/php-native:$(branch) -f ./images/php/Dockerfile .
	docker push kitman/php-native:$(branch)
	docker build -t kitman/nginx-native:$(branch) -f ./images/nginx/Dockerfile .
	docker push kitman/nginx-native:$(branch)
