# Twitch Bot Directory
[![Build Status](https://travis-ci.org/freaktechnik/twitchbots.svg?branch=mini)](https://travis-ci.org/freaktechnik/twitchbots) [![codecov](https://codecov.io/gh/freaktechnik/twitchbots/branch/master/graph/badge.svg)](https://codecov.io/gh/freaktechnik/twitchbots) [![Dependency Status](https://dependencyci.com/github/freaktechnik/twitchbots/badge)](https://dependencyci.com/github/freaktechnik/twitchbots)

A directory of Twitch chat bot accounts.

This is the code used for [twitchbots.info](https://twitchbots.info).

Thanks to [Mini2](https://github.com/panique/mini2) for the great base template.

## License
The code in this repo is licensed under the MIT license.

## Used APIs
Thanks to the following APIs, the service can try to curate the list as best as it can on its own:

 - [Twitch API](https://github.com/justintv/Twitch-API) *needs a Client-ID saved as `clinet-ID` in the config table*
 - [3v.fi Twitch Mod Check API](https://twitchstuff.3v.fi/modlookup/docs) *Needs a 3v-ua from the config table*

## Set up

### Database
The SQL table layout is currently not public, but can be
reverse-engineered from the code (look at the unit tests setup.php, or sometimes
as a comment in the table wrapper class).

### PHP Dependencies
To install all the dependencies get [Composer](https://getcomposer.org/download)
and then run `composer install` or `php composer.phar install`, depending on how
you installed it. This will then download all the deps and set them up.

### Cron Job
It is recommended to set up a cron job that runs `php Mini/console.php check`
every so often. The job removes any bots whose account has since been deleted on
twitch. You can also split it out to make fewer API requests in one job.

### Configuration
There are two configurations: one is a config.php in a folder called "lib" in the
root of the project and contains all configuration needed before the DB connection
is established. Almost all values defined by it can be found in the index.php files.

Then there is the config table, which holds key/value pairs for configuration.
You should manually set the following:
 - 28_URL: URL to crawl for ModBot.
 - checks_per_day: Number of bot pruning runs per day. Needs to be at least 1,
   recommended are at least one per hour.
 - client-ID: [Twitch Client-ID](https://dev.twitch.tv/docs#client-id)
 - 3v-ua: User Agent for the Mod Checker API
 - auth0_domain, auth0_redirectUrl, auth0_clientId, auth0_clientSecret: auth0 configuration

### Pitfalls
#### Crawlers
All TypeCrawlers currently rely on the type ID. This means your DeepBots have to
have id 22, ModBots 28 and Pajbots 44. I hope to fix this in the future.

#### Submissions
Submissions may fail if you do not have an attached piwik instance. The code is
currently not very well optimized toward a dynamic piwik instance or omitting it.

### Run tests
After installing Composer you can run the unit tests:
```bash
php vendor/bin/phpunit --bootstrap vendor/autload.php test
```

You might want to customize the `phpunit.xml` to match the database credentials
of your system. The unit tests expect a database called `twitchbots`.
