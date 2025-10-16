<?php

declare(strict_types=1);

namespace Drupal\Core\Template\Event;

use Drupal\Component\EventDispatcher\Event;

/**
 * Event that is fired when twig renders a template.
 */
final class TwigRenderTemplateEvent extends Event {

  public function __construct(
    protected readonly string $templateFile,
    protected readonly array $variables,
    protected string $renderedMarkup,
  ) {}

  /**
   * Gets the template file path.
   *
   * @return string
   *   The template that was used to create the rendered markup.
   */
  public function getTemplateFile(): string {
    return $this->templateFile;
  }

  /**
   * Gets variables.
   *
   * @return array
   *   The render array that was used to create the rendered markup.
   */
  public function getVariables(): array {
    return $this->variables;
  }

  /**
   * Gets rendered markup.
   *
   * @return string
   *   The results of running twig_render_template.
   */
  public function getRenderedMarkup(): string {
    return $this->renderedMarkup;
  }

  /**
   * Sets rendered markup.
   *
   * @param string $renderedMarkup
   *   The rendered markup.
   */
  public function setRenderedMarkup(string $renderedMarkup): void {
    $this->renderedMarkup = $renderedMarkup;
  }

}
