#! /bin/bash
set -x
source $TRAVIS_BUILD_DIR/.travis/push.sh
source $TRAVIS_BUILD_DIR/.travis/trust-server.sh
deploy "lab/twitchbots"
