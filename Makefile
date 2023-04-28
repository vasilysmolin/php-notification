include ./.env

setup-ci: env-prepare install lint

env-prepare:
	cp -n .env.example .env || true

install:
	composer install

lint:
	composer lint
