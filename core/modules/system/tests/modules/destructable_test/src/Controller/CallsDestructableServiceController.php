<?php

declare(strict_types=1);

namespace Drupal\destructable_test\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\destructable_test\Destructable;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;

/**
 * Controller to instantiate the destructable service.
 */
final class CallsDestructableServiceController extends ControllerBase {

  /**
   * Destructable service.
   *
   * @var \Drupal\destructable_test\Destructable
   */
  protected $destructable;

  public function __construct(Destructable $destructable) {
    $this->destructable = $destructable;
  }

  /**
   * Render callback.
   *
   * @param string $semaphore
   *   The semaphore query parameter.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Response.
   */
  public function render(#[MapQueryParameter] string $semaphore): Response {
    $this->destructable->setSemaphore($semaphore);
    return new Response('This is a longer-ish string of content to send to the client, to invoke any trivial transfer buffers both on the server and client side.');
  }

}
