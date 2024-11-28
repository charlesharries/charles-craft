#!/bin/bash

VERSION=$(openssl rand -hex 4)
git checkout HEAD -- ./templates
git pull origin main
composer install
grep -rl '{|{VERSION}|}' ./templates | xargs sed -i "s/{|{VERSION}|}/$VERSION/g"
