<?php

namespace Drupal\Core\Render\Element;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Provides an interface for render element plugins.
 *
 * Render element plugins allow modules to declare their own Render API element
 * types and specify the default values for the properties. The values returned
 * by the getInfo() method of the element plugin will be merged with the
 * properties specified in render arrays. Thus, you can specify defaults for any
 * Render API keys, in addition to those explicitly documented by
 * \Drupal\Core\Render\ElementInfoManagerInterface::getInfo().
 *
 * Some render elements are specifically form input elements; see
 * \Drupal\Core\Render\Element\FormElementInterface for more information.
 *
 * The public API of these objects must be designed with security in mind as
 * render elements process raw user input.
 *
 * @see \Drupal\Core\Render\ElementInfoManager
 * @see \Drupal\Core\Render\Attribute\RenderElement
 * @see \Drupal\Core\Render\Element\RenderElementBase
 * @see plugin_api
 *
 * @ingroup theme_render
 */
interface ElementInterface extends PluginInspectionInterface, RenderCallbackInterface {

  /**
   * Returns the element properties for this element.
   *
   * @return array
   *   An array of element properties. See
   *   \Drupal\Core\Render\ElementInfoManagerInterface::getInfo() for
   *   documentation of the standard properties of all elements, and the
   *   return value format.
   */
  public function getInfo();

  /**
   * Sets a form element's class attribute.
   *
   * Adds 'required' and 'error' classes as needed.
   *
   * @param array $element
   *   The form element.
   * @param array $class
   *   Array of new class names to be added.
   */
  public static function setAttributes(&$element, $class = []);

  /**
   * Returns a render array.
   *
   * @return array
   *   A render array. Make sure to take the return value as a reference.
   */
  public function &toRenderArray();


  /**
   * Return child elements.
   *
   * @return \Traversable
   *   Key will be children names, values are ElementInterface objects.
   */
  public function getChildren(): \Traversable;

  /**
   * Adds a child.
   *
   * @param int|string $name
   *   The name of the child. Can be an integer, too.
   * @param array|\Drupal\Core\Render\Element\ElementInterface $child
   *   A render array or a render object.
   *
   * @return $this
   *   The called object.
   */
  public function addChild(int|string $name, array|ElementInterface $child): static;

  /**
   * Removes a child.
   *
   * @param int|string $name
   *  The name of the child. Can be an integer, too.
   * @return $this
   *   The called object.
   */
  public function removeChild(int|string $name): static;

}
