<?php

namespace ServicesSecond;

use ServicesPackage\TestService;

/**
 * A test service depending on a php package provided service.
 */
class SecondTestService {

  /**
   * The constructor.
   *
   * @param \ServicesPackage\TestService $testService
   *   Packages services can also depend on other packages.
   */
  public function __construct(
    protected readonly TestService $testService,
  ) {}

  /**
   * Get the configured service dependencies.
   *
   * @return array
   *   The services so that we can check if they were set up.
   */
  public function getConfiguredDependencies(): array {
    return array_filter($this->testService->getConfiguredDependencies());
  }

}
