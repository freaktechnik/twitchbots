#! /bin/sh
eval "$(ssh-agent -s)"
openssl aes-256-cbc -K $encrypted_a666911b0f49_key -iv $encrypted_a666911b0f49_iv -in $TRAVIS_BUILD_DIR/.travis/id_rsa.enc -out $TRAVIS_BUILD_DIR/.travis/id_rsa -d
chmod 600 $TRAVIS_BUILD_DIR/.travis/id_rsa
ssh-add $TRAVIS_BUILD_DIR/.travis/id_rsa
echo "$DEPLOY_HOST $SERVER_PUBLIC_KEY" >> $HOME/.ssh/known_hosts
cat $HOME/.ssh/known_hosts
