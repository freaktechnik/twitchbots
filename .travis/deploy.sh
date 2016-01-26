#! /bin/sh
$HOME/.travis/trust-server.sh
ssh humanoid@humanoids.be 'cd www/twitchbots && git pull && rm -rf cache && php composer.phar install --no-dev --optimize-autoloader'
