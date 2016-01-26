#! /bin/sh
set -x
source $TRAVIS_BUILD_DIR/.travis/trust-server.sh
ssh humanoid@humanoids.be 'cd www/twitchbots && git pull && rm -rf cache && php70 composer.phar install --no-dev --optimize-autoloader'
