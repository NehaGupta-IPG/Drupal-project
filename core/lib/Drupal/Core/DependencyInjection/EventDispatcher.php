<?php

namespace Drupal\Core\DependencyInjection;

use Symfony\Component\EventDispatcher\EventDispatcher as SymfonyEventDispatcher;

class EventDispatcher extends SymfonyEventDispatcher {

  public function setListeners(array $listeners): void {
    $dispatcher = new \ReflectionClass(SymfonyEventDispatcher::class);
    $dispatcher->getProperty('listeners')->setValue($this, $listeners);
    $dispatcher->getProperty('sorted')->setValue($this, []);
    $dispatcher->getProperty('optimized')->setValue($this, []);
  }

}
