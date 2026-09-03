<?php

declare(strict_types=1);

namespace Drupal\http_kernel_test\Controller;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\DrupalKernelInterface;
use Drupal\Core\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * A test controller.
 */
class TestController implements ContainerInjectionInterface {
  use AutowireTrait;

  public function __construct(protected DrupalKernelInterface $kernel) {
  }

  /**
   * Return an empty response.
   */
  #[Route(
    path: '/http-kernel-test',
    name: 'http_kernel_test.empty',
    requirements: ['_access' => 'TRUE'],
  )]
  #[Route(
    path: '/http-kernel-test-sub-sub-request',
    name: 'http_kernel_test.sub_sub_request',
    requirements: ['_access' => 'TRUE'],
  )]
  public function get() {
    return new Response();
  }

  /**
   * Return an empty response.
   */
  #[Route(
    path: '/http-kernel-test-sub-request',
    name: 'http_kernel_test.sub_request',
    requirements: ['_access' => 'TRUE'],
  )]
  public function subRequest(): Response {
    $sub_request = Request::create('/http-kernel-test-sub-sub-request');
    return $this->kernel->handle($sub_request, KernelInterface::SUB_REQUEST);
  }

}
