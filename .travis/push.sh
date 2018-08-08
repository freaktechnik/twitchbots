#! /bin/sh

if [ -z ${DEPLOY_HOST + x} ]
then
    echo "No deploy config found"
    exit 1
fi

deploy () {
    ssh $DEPLOY_USER@$DEPLOY_HOST 'cd www/$1 && git pull && rm -rf cache && php71 composer.phar install --no-dev --optimize-autoloader'
}
