#! /bin/sh
eval "$(ssh-agent -s)"
openssl aes-256-cbc -K $encrypted_a666911b0f49_key -iv $encrypted_a666911b0f49_iv -in .travis/id_rsa.enc -out .travis/id_rsa -d
chmod 600 .travis/id_rsa
ssh-add .travis/id_rsa
ssh-keyscan -H humanoids.be >> ~/.ssh/known_hosts
# TODO update vendor deps
ssh humanoid@humanoids.be 'cd www/twitchbots && git pull && rm -rf cache'
