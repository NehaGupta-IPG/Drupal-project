<?php

declare(strict_types=1);

namespace Drupal\Core\Environment;

use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;

/**
 * Render context substitution.
 */
class RenderContextSubstitution implements SubstitutionInterface {

  use SubstitutionApplyTrait;

  /**
   * Constructs a new render context substitution.
   *
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   */
  public function __construct(
    protected readonly RendererInterface $renderer,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function executeInEnvironment(EnvironmentParameter $param, callable $callback): mixed {
    return $this->renderer->executeInRenderContext(new RenderContext(), $callback);
  }

}
