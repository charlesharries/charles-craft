#!/bin/bash

VERSION=$(openssl rand -hex 4)
git checkout HEAD -- ./templates
git pull origin main
composer install
php craft migrate/all --interactive 0
php craft project-config/apply
grep -rl '{|{VERSION}|}' ./templates | xargs sed -i "s/{|{VERSION}|}/$VERSION/g"
