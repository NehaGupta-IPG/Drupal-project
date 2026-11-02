<?php

declare(strict_types=1);

namespace Drupal\KernelTests;

use Drupal\Component\Render\FormattableMarkup;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Provides routes for testing the setting of session data.
 */
trait SessionTestRoutesTrait {

  /**
   * Prints the stored session value to the screen.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming request.
   *
   * @return string[]
   *   The render array for the response.
   */
  #[Route(
    path: '/session-test/get',
    name: 'session_test.get',
    requirements: ['_access' => 'TRUE'],
    options: ['no_cache' => TRUE],
    defaults: ['_title' => 'Session value'],
  )]
  public function routeGetSessionValue(Request $request): array {
    $value = $request->getSession()->get('session_test_value');
    return empty($value)
      ? []
      : ['#markup' => new FormattableMarkup('The current value of the stored session variable is: %val', ['%val' => $value])];
  }

  /**
   * Stores a value in 'session_test_value' session attribute.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param string $test_value
   *   A session value.
   */
  #[Route(
    path: '/session-test/set/{test_value}',
    name: 'session_test.set',
    requirements: ['_access' => 'TRUE'],
    options: [
      'no_cache' => TRUE,
      'converters' => [
        'test_value' => '\s+',
      ],
    ],
    defaults: ['_title' => 'Set session value'],
  )]
  public function routeSetSessionValue(Request $request, $test_value): array {
    $request->getSession()->set('session_test_value', $test_value);

    return ['#markup' => new FormattableMarkup('The current value of the stored session variable has been set to %val', ['%val' => $test_value])];
  }

}
