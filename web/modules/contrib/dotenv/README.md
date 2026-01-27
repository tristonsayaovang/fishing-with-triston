# Dotenv

Integrates the Symfony Dotenv component with Drupal

### What's the Symfony Dotenv Component

> Symfony Dotenv parses .env files to make environment variables stored in them
accessible via $_SERVER or $_ENV.

[https://symfony.com/components/Dotenv](https://symfony.com/components/Dotenv)

### Why?

A `dotenv` file allows you to remove hardcoded credentials or config from your
code. For an extensive explanation on why this is a good thing, check out
[the _Config_ chapter of the Twelve-Factor App website](https://www.12factor.net/config).

For a full description of the module, visit the
[project page](https://www.drupal.org/project/dotenv).

Submit bug reports and feature suggestions, or track changes in the
[issue queue](https://www.drupal.org/project/issues/dotenv).


## Table of contents

- Requirements
- Installation
- Configuration
- Maintainers


## Requirements

- The 1.0.x & 1.1.x versions are identical and require a minimum of PHP 7.2 and
  Drupal 8.
- The 1.2.x versions require a minimum of PHP 8.0 and Drupal 10.


## Installation

Install as you would normally install a contributed Drupal module. For further
information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).


## Configuration

### Automatic

If your project has Drush installed, you can use the `drush dotenv:init` 
command to automatically set up .env in your project,

- creating a .env file with `APP_ENV` and your database credentials from 
  settings.php
- creating a .env.example file with all keys from the .env file, without files
- adding the .env file to .gitignore
- copying the load.environment.php file to your project and setting it to 
  autoload using Composer

### Manual

Copy the [load.environment.php](https://git.drupalcode.org/project/dotenv/-/blob/1.0.x/files/load.environment.php) file to your project root and add it to the [Composer autoload config](https://github.com/drupal-composer/drupal-project/blob/9.x/composer.json#L49). Afterwards, run `composer update --lock` to update the hash in composer.lock and `composer dump-autoload` to recreate the autoload files.

Add a `.env` file in the root of your project. It has to at least contain the 
`APP_ENV` environment variable:

```
APP_ENV=prod
```

### How does it work?

You can now add environment variables to your `.env` file and it will
automatically be available in the `$_ENV` global var.

You can use it in `settings.php`, in service providers or in other places
throughout your code. Some examples:

```php
// settings.php.

$databases['default']['default'] = [
  'database' => $_ENV['DB_DATABASE'],
  'username' => $_ENV['DB_USERNAME'],
  'password' => $_ENV['DB_PASSWORD'] ?? '',
  'prefix' => '',
  'host' => $_ENV['DB_HOST'] ?? 'localhost',
  'port' => $_ENV['DB_PORT'] ?? 3306,
  'namespace' => 'Drupal\\Core\\Database\\Driver\\mysql',
  'driver' => 'mysql',
];

$config['mandrill.settings'] = [
  'mandrill_api_key' => $_ENV['MANDRILL_API_KEY'],
];
```
```php
namespace Drupal\yourmodule;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderInterface;

/**
 * Adds parameters to the container.
 */
class YourmoduleServiceProvider implements ServiceProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    $container->setParameter('yourmodule.some_secret', $_ENV['SOME_SECRET']);
  }

}
```

On live environments, you should invoke `drush dotenv:dump` every time
your .env file changes. If you don't, the .env file will be loaded at every 
request, which will decrease the performance of your application.

You can use the `drush dotenv:dump` command to get debugging info about the 
scanned dotenv files and the loaded variables.

Read the [Symfony documentation](https://symfony.com/doc/current/configuration.html#configuring-environment-variables-in-env-files)
for more information.

### Usage with Drush

Drush has the ability to [read options from environment variables](https://www.drush.org/latest/using-drush-configuration/#environment-variables).

## Maintainers

- Dieter Holvoet - [DieterHolvoet](https://drupal.org/u/dieterholvoet)
- Robin.Houtevelts - [robinhoutevelts](https://www.drupal.org/u/robinhoutevelts)
