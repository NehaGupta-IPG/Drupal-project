<?php

namespace Drupal\serialization\EventSubscriber;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\CacheableResponse;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\EventSubscriber\HttpExceptionSubscriberBase;
use Drupal\Core\EventSubscriber\MainContentViewSubscriber;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Handles default error responses in serialization formats.
 */
class DefaultExceptionSubscriber extends HttpExceptionSubscriberBase {

  /**
   * The serializer.
   *
   * @var \Symfony\Component\Serializer\Serializer
   */
  protected $serializer;

  /**
   * The available serialization formats.
   *
   * @var array
   */
  protected $serializerFormats = [];

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * DefaultExceptionSubscriber constructor.
   *
   * @param \Symfony\Component\Serializer\SerializerInterface $serializer
   *   The serializer service.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Drupal\Core\Config\ConfigFactoryInterface|null $config_factory
   *   Config factory.
   */
  public function __construct(
    SerializerInterface $serializer,
    array $serializer_formats,
    ?ConfigFactoryInterface $config_factory = NULL,
  ) {
    $this->serializer = $serializer;
    $this->serializerFormats = $serializer_formats;
    if (!$config_factory) {
      @trigger_error(sprintf('Calling %s without the config factory is deprecated in drupal:11.1.0 and disallowed in drupal:12.0.0. See https://www.drupal.org/project/drupal/issues/3304981', __FUNCTION__), E_USER_DEPRECATED);
      $config_factory = \Drupal::configFactory();
    }
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  protected function getHandledFormats() {
    return $this->serializerFormats;
  }

  /**
   * {@inheritdoc}
   */
  protected static function getPriority() {
    // This will fire after the most common HTML handler, since HTML requests
    // are still more common than HTTP requests. But it has a higher priority
    // than \Drupal\Core\EventSubscriber\ExceptionJsonSubscriber::on4xx(), so
    // this also handles the 'json' format. Then all serialization formats
    // (::getHandledFormats()) are handled by this exception subscriber, which
    // results in better consistency.
    return -70;
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

  /**
   * Handles 4xx exceptions on routes with known serialization formats.
   *
   * @param \Symfony\Component\HttpKernel\Event\ExceptionEvent $event
   *   Exception event.
   */
  protected function on4xx(ExceptionEvent $event): void {
    $this->on4xx5xx($event);
  }

  /**
   * Handles 5xx exceptions on routes with known serialization formats.
   *
   * @param \Symfony\Component\HttpKernel\Event\ExceptionEvent $event
   *   Exception event.
   */
  protected function on5xx(ExceptionEvent $event): void {
    $this->on4xx5xx($event);
  }

  /**
   * Handles all 4xx and 5xx errors for exceptions.
   *
   * @param \Symfony\Component\HttpKernel\Event\ExceptionEvent $event
   *   The event to process.
   */
  protected function on4xx5xx(ExceptionEvent $event) {
    /** @var \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $exception */
    $exception = $event->getThrowable();
    $request = $event->getRequest();

    $format = $request->getRequestFormat();
    $content = ['message' => $exception->getMessage()];
    $encoded_content = $this->serializer->serialize($content, $format);
    $headers = $exception->getHeaders();

    // Add the MIME type from the request to send back in the header.
    $headers['Content-Type'] = $request->getMimeType($format);

    // If the exception is cacheable, generate a cacheable response.
    if ($exception instanceof CacheableDependencyInterface) {
      $response = new CacheableResponse($encoded_content, $exception->getStatusCode(), $headers);
      $response->addCacheableDependency($exception);
    }
    else {
      $response = new Response($encoded_content, $exception->getStatusCode(), $headers);
    }

    $event->setResponse($response);
  }

}
