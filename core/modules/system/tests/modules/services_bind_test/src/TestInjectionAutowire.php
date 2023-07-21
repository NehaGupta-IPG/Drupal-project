<?php

namespace Drupal\services_bind_test;

/**
 * Class for services that will be injected.
 */
class TestInjectionAutowire {

  /**
   * Constructor.
   *
   * @param string $id
   *   Id to distinguish different instances.
   */
  public function __construct(
    public readonly string $id = 'default',
  ) {}

}
