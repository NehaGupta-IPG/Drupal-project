<?php

declare(strict_types=1);

namespace Drupal\basic_auth_test;

use Drupal\Core\PageCache\ResponsePolicyInterface;
use Drupal\Core\State\StateInterface;

/**
 * Provides routes for HTTP Basic Authentication testing.
 */
class BasicAuthTestController {

  /**
   * The state storage service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  private $state;

  /**
   * The page cache kill switch service.
   *
   * @var \Drupal\Core\PageCache\ResponsePolicy\ResponsePolicyInterface
   */
  private $pageCacheKillSwitch;

  /**
   * Constructs a new BasicAuthTestController object.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state storage service.
   * @param \Drupal\Core\PageCache\ResponsePolicy\ResponsePolicyInterface $pageCacheKillSwitch
   *   The page cache kill switch service.
   */
  public function __construct(StateInterface $state, ResponsePolicyInterface $pageCacheKillSwitch) {
    $this->state = $state;
    $this->pageCacheKillSwitch = $pageCacheKillSwitch;
  }

  /**
   * @see \Drupal\basic_auth\Tests\Authentication\BasicAuthTest::testControllerNotCalledBeforeAuth()
   */
  public function modifyState() {
    $this->state->set('basic_auth_test.state.controller_executed', TRUE);
    return ['#markup' => 'Done'];
  }

  /**
   * @see \Drupal\basic_auth\Tests\Authentication\BasicAuthTest::testControllerNotCalledBeforeAuth()
   */
  public function readState() {
    // Mark this page as being uncacheable.
    $this->pageCacheKillSwitch->trigger();

    return [
      '#markup' => $this->state->get('basic_auth_test.state.controller_executed') ? 'yep' : 'nope',
      '#cache' => [
        'max-age' => 0,
      ],
    ];
  }

}
