#! /bin/sh
eval "$(ssh-agent -s)"
openssl aes-256-cbc -K $encrypted_a666911b0f49_key -iv $encrypted_a666911b0f49_iv -in id_rsa.enc -out id_rsa -d
chmod 600 id_rsa
ssh-add id_rsa
ssh-keyscan -H humanoids.be >> ~/.ssh/known_hosts
