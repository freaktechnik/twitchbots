#! /bin/sh
eval "$(ssh-agent -s)"
openssl aes-256-cbc -K $encrypted_a666911b0f49_key -iv $encrypted_a666911b0f49_iv -in $HOME/.travis/id_rsa.enc -out $HOME/.travis/id_rsa -d
chmod 600 $HOME/.travis/id_rsa
ssh-add $HOME/.travis/id_rsa
ssh-keyscan -H humanoids.be >> ~/.ssh/known_hosts
