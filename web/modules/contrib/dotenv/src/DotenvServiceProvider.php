<?php

namespace Drupal\dotenv;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use DrupalFinder\DrupalFinder;
use DrupalFinder\DrupalFinderComposerRuntime;

/**
 * The dotenv service provider.
 *
 * Sets container parameters for configuring the Symfony Console commands.
 */
class DotenvServiceProvider implements ServiceModifierInterface {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    if (!$container->hasParameter('dotenv.project_dir')) {
      if (class_exists(DrupalFinderComposerRuntime::class)) {
        $drupalFinder = new DrupalFinderComposerRuntime();
      }
      else {
        $drupalFinder = new DrupalFinder();
        $drupalFinder->locateRoot(__DIR__);
      }
      $container->setParameter('dotenv.project_dir', $drupalFinder->getComposerRoot());
    }
    if (!$container->hasParameter('dotenv.environment')) {
      $container->setParameter('dotenv.environment', $_ENV['APP_ENV'] ?? 'prod');
    }
  }

}
