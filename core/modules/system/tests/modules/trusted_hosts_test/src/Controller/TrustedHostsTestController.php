<?php

declare(strict_types=1);

namespace Drupal\trusted_hosts_test\Controller;

use Drupal\Core\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides a test controller for testing the trusted hosts setting.
 */
class TrustedHostsTestController {

  /**
   * Creates a fake request and prints out its host.
   */
  #[Route(
    path: '/trusted-hosts-test/fake-request',
    name: 'trusted_hosts_test.fake_request',
    requirements: ['_access' => 'TRUE'],
  )]
  public function fakeRequestHost() {
    $request = Request::create('/');
    return ['#markup' => 'Host: ' . $request->getHost()];
  }

  /**
   * Creates a fake request and prints out the class name of the specified bag.
   */
  #[Route(
    path: '/trusted-hosts-test/bag-type/{bag}',
    name: 'trusted_hosts_test.bag_type',
    requirements: ['_access' => 'TRUE'],
  )]
  public function bagType($bag) {
    $request = Request::create('/');
    return ['#markup' => 'Type: ' . get_class($request->$bag)];
  }

}
