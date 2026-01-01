<?php

namespace Drupal\Core\Routing;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\CacheableResponseInterface;
use Drupal\Core\Render\AttachmentsInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;

final class ControllerWrapper {

  protected $controller;

  public function __construct(
    protected RendererInterface $renderer,
    callable $controller,
    protected array $arguments,
  ) {
    $this->controller = $controller;
  }

  /**
   * Wraps a controller execution in a render context.
   *
   * @param $arguments
   *   The arguments to pass to the controller.
   *
   * @return mixed
   *   The return value of the controller.
   *
   * @throws \LogicException
   *   When early rendering has occurred in a controller that returned a
   *   Response or domain object that cares about attachments or cacheability.
   *
   * @see \Symfony\Component\HttpKernel\HttpKernel::handleRaw()
   */
  public function wrapControllerExecutionInRenderContext(...$arguments) {
    $context = new RenderContext();

    $controller = $this->controller;
    // Now call the actual controller, just like HttpKernel does.
    $response = $this->renderer->executeInRenderContext($context, static fn() => call_user_func_array($controller, $arguments));

    // If early rendering happened, i.e. if code in the controller called
    // RendererInterface::render() outside of a render context, then the
    // bubbleable metadata for that is stored in the current render context.
    if (!$context->isEmpty()) {
      /** @var \Drupal\Core\Render\BubbleableMetadata $early_rendering_bubbleable_metadata */
      $early_rendering_bubbleable_metadata = $context->pop();

      // If a render array or AjaxResponse is returned by the controller, merge
      // the "lost" bubbleable metadata.
      if (is_array($response)) {
        BubbleableMetadata::createFromRenderArray($response)
          ->merge($early_rendering_bubbleable_metadata)
          ->applyTo($response);
      }
      elseif ($response instanceof AjaxResponse) {
        $response->addAttachments($early_rendering_bubbleable_metadata->getAttachments());
        // @todo Make AjaxResponse cacheable in
        //   https://www.drupal.org/node/956186. Meanwhile, allow contrib
        //   subclasses to be.
        if ($response instanceof CacheableResponseInterface) {
          $response->addCacheableDependency($early_rendering_bubbleable_metadata);
        }
      }
      elseif ($response instanceof CacheableResponseInterface) {
        $response->addCacheableDependency($early_rendering_bubbleable_metadata);
      }
      // If a non-Ajax Response or domain object is returned and it cares about
      // attachments or cacheability, then throw an exception: early rendering
      // is not permitted in that case. It is the developer's responsibility
      // to not use early rendering.
      elseif ($response instanceof AttachmentsInterface || $response instanceof CacheableDependencyInterface) {
        throw new \LogicException(sprintf('The controller result claims to be providing relevant cache metadata, but leaked metadata was detected. Ensure you are not rendering content too early. Returned object class: %s.', get_class($response)));
      }
      else {
        // A Response or domain object is returned that does not care about
        // attachments nor cacheability; for instance, a RedirectResponse. It is
        // safe to discard any early rendering metadata.
      }
    }

    return $response;
  }

  public function getArguments(): array {
    return $this->arguments;
  }

}
