<?php

declare(strict_types=1);

namespace Drupal\http_kernel_test\HttpKernel;

use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Provides a test middleware.
 */
class TestMiddleware implements HttpKernelInterface {

  /**
   * The decorated kernel.
   *
   * @var \Symfony\Component\HttpKernel\HttpKernelInterface
   */
  protected $httpKernel;

  /**
   * An optional argument.
   *
   * @var mixed
   */
  protected $optionalArgument;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a new TestMiddleware object.
   *
   * @param \Symfony\Component\HttpKernel\HttpKernelInterface $http_kernel
   *   The decorated kernel.
   * @param mixed $optional_argument
   *   (optional) An optional argument.
   * @param \Drupal\Core\Messenger\MessengerInterface|null $messenger
   *  The messenger service.
   */
  public function __construct(HttpKernelInterface $http_kernel, $optional_argument = NULL, ?MessengerInterface $messenger = NULL) {
    $this->httpKernel = $http_kernel;
    $this->optionalArgument = $optional_argument;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public function handle(Request $request, $type = self::MAIN_REQUEST, $catch = TRUE): Response {
    $request->attributes->set('_hello', 'world');
    if ($request->attributes->has('_optional_argument')) {
      $request->attributes->set('_previous_optional_argument', $request->attributes->get('_optional_argument'));
    }
    elseif (isset($this->optionalArgument)) {
      $request->attributes->set('_optional_argument', $this->optionalArgument);
    }
    if ($this->messenger && $request->query->has('set_message')) {
      $this->messenger->addStatus($request->query->get('set_message'));
    }

    return $this->httpKernel->handle($request, $type, $catch);
  }

}
