#! /bin/sh
set -x
$TRAVIS_BUILD_DIR/.travis/trust-server.sh
ssh humanoid@humanoids.be 'cd www/twitchbots && git pull && rm -rf cache && php composer.phar install --no-dev --optimize-autoloader'
