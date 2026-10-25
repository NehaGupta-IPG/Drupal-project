<?php

namespace Drupal\Core\EventSubscriber;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Default handling for JSON errors.
 */
class ExceptionJsonSubscriber extends HttpExceptionSubscriberBase {

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * {@inheritdoc}
   */
  protected function getHandledFormats() {
    return ['json', 'drupal_modal', 'drupal_dialog', 'drupal_ajax'];
  }

  /**
   * {@inheritdoc}
   */
  protected static function getPriority() {
    // This will fire after the most common HTML handler, since HTML requests
    // are still more common than JSON requests.
    return -75;
  }

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface|null $config_factory
   *   Config factory.
   */
  public function __construct(?ConfigFactoryInterface $config_factory = NULL) {
    if (!$config_factory) {
      @trigger_error(sprintf('Calling %s without the config factory is deprecated in drupal:11.1.0 and disallowed in drupal:12.0.0. See https://www.drupal.org/project/drupal/issues/3304981', __FUNCTION__), E_USER_DEPRECATED);
      $config_factory = \Drupal::configFactory();
    }
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function onException(ExceptionEvent $event) {
    $request = $event->getRequest();

    $format = $request->query->get(MainContentViewSubscriber::WRAPPER_FORMAT, $request->getRequestFormat());

    if (!in_array($format, $this->getHandledFormats())) {
      return;
    }
    $exception = $event->getThrowable();
    if (!$exception instanceof HttpException) {
      $error_level = $this->configFactory
        ->get('system.logging')->get('error_level') ?? ERROR_REPORTING_HIDE;
      $exception = new HttpException(
        Response::HTTP_INTERNAL_SERVER_ERROR,
        $error_level !== ERROR_REPORTING_HIDE
          ? $exception->getMessage()
          : 'Internal Server Error',
        $exception
      );
      $event->setThrowable($exception);
    }

    if (in_array($format, $this->getHandledFormats())) {
      $method = 'on' . $exception->getStatusCode();
      // Keep just the leading number of the status code to produce either a
      // 400 or a 500 method callback.
      $method_fallback = 'on' . substr($exception->getStatusCode(), 0, 1) . 'xx';
      if (method_exists($this, $method)) {
        $this->$method($event);
      }
      elseif (method_exists($this, $method_fallback)) {
        $this->$method_fallback($event);
      }
    }
  }

  /**
   * Handles 4xx exceptions on JSON formatted routes.
   *
   * @param \Symfony\Component\HttpKernel\Event\ExceptionEvent $event
   *   Exception event.
   */
  protected function on4xx(ExceptionEvent $event): void {
    $this->on4xx5xx($event);
  }

  /**
   * Handles 5xx exceptions on JSON formatted routes.
   *
   * @param \Symfony\Component\HttpKernel\Event\ExceptionEvent $event
   *   Exception event.
   */
  protected function on5xx(ExceptionEvent $event): void {
    $this->on4xx5xx($event);
  }

  /**
   * Handles all 4xx and 5xx errors for JSON formatted routes.
   *
   * @param \Symfony\Component\HttpKernel\Event\ExceptionEvent $event
   *   The event to process.
   */
  public function on4xx5xx(ExceptionEvent $event) {
    /** @var \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $exception */
    $exception = $event->getThrowable();

    // If the exception is cacheable, generate a cacheable response.
    if ($exception instanceof CacheableDependencyInterface) {
      $response = new CacheableJsonResponse(['message' => $event->getThrowable()->getMessage()], $exception->getStatusCode(), $exception->getHeaders());
      $response->addCacheableDependency($exception);
    }
    else {
      $response = new JsonResponse(['message' => $event->getThrowable()->getMessage()], $exception->getStatusCode(), $exception->getHeaders());
    }

    $event->setResponse($response);
  }

}
