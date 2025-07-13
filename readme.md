# GeoVault

> [!CAUTION]
> This is in a very early state and should not be used by anyone yet. 
> Mostly because it would expose all your location data without any
> protection.
 
## Pre-Amble

The idea here is to create an application that is (for now) API-compatible
to [Aaron Parecki's excellent Compass](https://github.com/aaronpk/Compass) -
both in the web api and the data storage. The push functionality 
(webhooks and micropub) and the pretty map overview have lower priorities for now.

The plan is to always keep this one on the latest Symfony and PHP versions. Given that
I tend to lose interest in projects pretty quickly, I hope I can automate a lot of that 
with the help of tools like the dependabot.

## Local development

This project uses [DDEV](https://ddev.com/) - after checking it out, you'll have to copy
the `.env.local` into the `app` directory and start the whole thing with `ddev start` 

`ddev composer install` and `ddev console doctrine:migrations:migrate` will install all the
dependencies and get the database going.

You can run 
[rector](https://getrector.com/), 
[PHP Coding Standards Fixer](https://cs.symfony.com/), 
[PHPStan](https://phpstan.org/) and [PHPUnit](https://phpunit.de/index.html) 
with `ddev rector`, `ddev phpcs`, `ddev phpstan` and `ddev phpunit` respectively.

## Deployment

:rotating_light: DO NOT DO THIS YET :rotating_light: 

Seriously. Don't even think about it.