# ![](public/favicon.png) Twitch Bot Directory

[![Build Status](https://travis-ci.com/freaktechnik/twitchbots.svg?branch=master)](https://travis-ci.com/freaktechnik/twitchbots) [![codecov](https://codecov.io/gh/freaktechnik/twitchbots/branch/master/graph/badge.svg)](https://codecov.io/gh/freaktechnik/twitchbots)

A directory of Twitch chat bot accounts.

This is the code used for [twitchbots.info](https://twitchbots.info).

Thanks to [Mini2](https://github.com/panique/mini2) for the great base template.

## License

The code in this repo is licensed under the MIT license.

## Used APIs

Thanks to the following APIs, the service can try to curate the list as best as it can on its own:

- [Twitch API](https://github.com/justintv/Twitch-API)
- [3v.fi Twitch Mod Check API](https://t.3v.fi/modlookup/docs)
- FrankerFaceZ bots list
- BetterTTV channel bots list

See [Configuration](#configuration) for details on required credentials for the respective APIs.

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
- client-secret: Twitch client secret
- 3v-ua: User Agent for the Mod Checker API
- auth0_domain, auth0_redirectUrl, auth0_clientId, auth0_clientSecret: auth0 configuration

### Pitfalls

#### Crawlers

All TypeCrawlers currently rely on the type ID. This means your DeepBots have to
have id 22, ModBots 28 and Pajbots 44. I hope to fix this in the future.

### Run tests

After installing Composer you can run the unit tests:

```bash
php vendor/bin/phpunit --bootstrap vendor/autload.php test
```

You might want to customize the `phpunit.xml` to match the database credentials
of your system. The unit tests expect a database called `twitchbots`.

## Contributors

Thanks goes to these wonderful people ([emoji key](https://github.com/kentcdodds/all-contributors#emoji-key)):

<!-- ALL-CONTRIBUTORS-LIST:START - Do not remove or modify this section -->
<!-- prettier-ignore -->
| [<img src="https://avatars0.githubusercontent.com/u/640949?v=4" width="100px;"/><br /><sub><b>Martin Giger</b></sub>](https://humanoids.be)<br />[💻](https://github.com/freaktechnik/twitchbots/commits?author=freaktechnik "Code") [🐛](https://github.com/freaktechnik/twitchbots/issues?q=author%3Afreaktechnik "Bug reports") [🚇](#infra-freaktechnik "Infrastructure (Hosting, Build-Tools, etc)") [📖](https://github.com/freaktechnik/twitchbots/commits?author=freaktechnik "Documentation") [💡](#example-freaktechnik "Examples") | [<img src="https://avatars3.githubusercontent.com/u/8625450?v=4" width="100px;"/><br /><sub><b>nD00rn</b></sub>](https://github.com/nD00rn)<br />[📖](https://github.com/freaktechnik/twitchbots/commits?author=nD00rn "Documentation") | [<img src="https://fiverr-res.cloudinary.com/t_profile_original,q_auto,f_auto/attachments/profile/photo/7430d3f11c372d8fcae51674276bfa51-1503422143339/9ea8d44f-b3d4-41c4-ac8b-cd4038d95adc.jpg" width="100px;"/><br /><sub><b>Luis Muñiz</b></sub>](https://www.linkedin.com/in/luismuniz1)<br />[🎨](#design-luism03 "Design") | [<img src="https://avatars1.githubusercontent.com/u/3787686?v=4" width="100px;"/><br /><sub><b>CBenni</b></sub>](https://github.com/CBenni)<br />[🤔](#ideas-CBenni "Ideas, Planning, & Feedback") |
| :---: | :---: | :---: | :---: |
<!-- ALL-CONTRIBUTORS-LIST:END -->

This project follows the [all-contributors](https://github.com/kentcdodds/all-contributors) specification. Contributions of any kind welcome!
