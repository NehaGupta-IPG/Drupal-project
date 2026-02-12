<?php

namespace Drupal\views\Plugin\Derivative;

use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides block plugin definitions for all Views exposed filters.
 *
 * @see \Drupal\views\Plugin\Block\ViewsExposedFilterBlock
 */
class ViewsExposedFilterBlock implements ContainerDeriverInterface {

  use StringTranslationTrait;

  /**
   * List of derivative definitions.
   *
   * @var array
   */
  protected $derivatives = [];

  /**
   * The view storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $viewStorage;

  /**
   * The base plugin ID that the derivative is for.
   *
   * @var string
   */
  protected $basePluginId;

  /**
   * Constructs a ViewsExposedFilterBlock object.
   *
   * @param string $base_plugin_id
   *   The base plugin ID.
   * @param \Drupal\Core\Entity\EntityStorageInterface $view_storage
   *   The entity storage to load views.
   */
  public function __construct($base_plugin_id, EntityStorageInterface $view_storage) {
    $this->basePluginId = $base_plugin_id;
    $this->viewStorage = $view_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $base_plugin_id,
      $container->get('entity_type.manager')->getStorage('view')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinition($derivative_id, $base_plugin_definition) {
    if (!empty($this->derivatives) && !empty($this->derivatives[$derivative_id])) {
      return $this->derivatives[$derivative_id];
    }
    $this->getDerivativeDefinitions($base_plugin_definition);
    return $this->derivatives[$derivative_id];
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    // Check all Views for displays with an exposed filter block.
    foreach ($this->viewStorage->loadMultiple() as $view) {
      // Do not return results for disabled views.
      if (!$view->status()) {
        continue;
      }
      $executable = $view->getExecutable();
      $executable->initDisplay();
      foreach ($executable->displayHandlers as $display) {
        if (isset($display) && $display->getOption('exposed_block')) {
          // Add a block definition for the block.
          if ($display->usesExposedFormInBlock()) {
            $delta = $view->id() . '-' . $display->display['id'];

            // Allow translators to control the punctuation. Plugin
            // definitions get cached, so use TranslatableMarkup() instead of
            // $this->t() to avoid double escaping when $admin_label is rendered
            // during requests that use the cached definition.
            $block_description = $display->getOption('block_description');
            if ($block_description) {
              $admin_label = new TranslatableMarkup('Exposed form: @view: @block_description', [
                '@view' => $view->label(),
                '@block_description' => $block_description,
              ]);
            }
            else {
              $admin_label = new TranslatableMarkup('Exposed form: @view: @display', [
                '@view' => $view->label(),
                '@display' => $display->display['display_title'],
              ]);
            }

            $this->derivatives[$delta] = [
              'admin_label' => $admin_label,
              'config_dependencies' => [
                'config' => [
                  $view->getConfigDependencyName(),
                ],
              ],
            ];
            $this->derivatives[$delta] += $base_plugin_definition;
          }
        }
      }
    }
    return $this->derivatives;
  }

}
