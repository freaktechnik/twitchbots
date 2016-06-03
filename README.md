# Twitch Bot Directory
[![Build Status](https://travis-ci.org/freaktechnik/twitchbots.svg?branch=mini)](https://travis-ci.org/freaktechnik/twitchbots)

A directory of Twitch chat bot accounts.

This is the code used for [twitchbots.info](https://twitchbots.info).

Thanks to [Mini2](https://github.com/panique/mini2) for the great base template.

## License
The code in this repo is licensed under the MIT license.

## Used APIs
Thanks to the following APIs, the service can try to curate the list as best as it can on its own:

 - [Twitch API](https://github.com/justintv/Twitch-API) *needs a CLient-ID saved as `clinet-ID` in the config table*
 - [Twitch Sword Counter](https://twitchstuff.3v.fi/mods/) API

## Set up

The SQL table layout is currently not public, but can be
reverse-engineered from the code (look at the unit tests, hinthint).

To install all the dependencies get [Composer](https://getcomposer.org/download)
and then run `composer install` or `php composer.phar install`, depending on how
you installed it. This will then download all the deps and set them up.

It is recommended to set up a cronjob that runs `php Mini/console.php check`
every so often. The job removes any bots whose account has since been deleted on
twitch.

### Run tests
After installing Composer you can run the unit tests:
```bash
php vendor/bin/phpunit --bootstrap vendor/autload.php test
```

You might want to customize the `phpunit.xml` to match the database credentials
of your system. The unit tests expect a database called `twitchbots`.
