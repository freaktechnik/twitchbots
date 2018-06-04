#! /bin/sh
set -x
source $TRAVIS_BUILD_DIR/.travis/trust-server.sh
ssh $DEPLOY_USER@$DEPLOY_HOST 'cd www/twitchbots && git pull && rm -rf cache && php71 composer.phar install --no-dev --optimize-autoloader'
