<?php

namespace Drupal\Core\Display;

/**
 * Provides an interface for PageDisplayVariant plugins.
 *
 * Page display variants are a specific type of DisplayVariant, intended for
 * "pages", which always have some main content to be rendered. Hence page
 * display variants may choose to render that main content in a certain way:
 * decorated in a certain way, laid out in a certain way, et cetera.
 *
 * For example, the \Drupal\block\Plugin\DisplayVariant\FullPageVariant page
 * display variant is used by the Block module to control regions and output
 * blocks placed in those regions.
 *
 * @see \Drupal\Core\Display\Attribute\DisplayVariant
 * @see \Drupal\Core\Display\VariantBase
 * @see \Drupal\Core\Display\VariantManager
 * @see plugin_api
 */
interface PageVariantInterface extends VariantInterface {

  /**
   * Sets the main content for the page being rendered.
   *
   * @param array $main_content
   *   The render array representing the main content.
   *
   * @return $this
   */
  public function setMainContent(array $main_content);

  /**
   * Sets the title for the page being rendered.
   *
   * @param string|\Stringable $title
   *   The page title to set. This can be either:
   *   - A plain string.
   *   - An object implementing \Stringable, such as
   *     \Drupal\Core\StringTranslation\TranslatableMarkup or
   *     \Drupal\Component\Render\MarkupInterface.
   *   These objects will be automatically cast to a string during rendering.
   *
   * @return $this
   *   The called object for method chaining.
   *
   * @see \Drupal\Core\Controller\TitleResolverInterface::getTitle()
   * @see \Drupal\Core\Render\MainContent\HtmlRenderer::prepare()
   */
  public function setTitle($title);

}
