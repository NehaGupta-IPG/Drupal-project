<?php

namespace Drupal\views\Plugin\Menu;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Menu\MenuLinkBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\views\ViewExecutableFactory;

/**
 * Defines menu links provided by views.
 *
 * @see \Drupal\views\Plugin\Derivative\ViewsMenuLink
 */
class ViewsMenuLink extends MenuLinkBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  protected $overrideAllowed = [
    'menu_name' => 1,
    'parent' => 1,
    'weight' => 1,
    'expanded' => 1,
    'enabled' => 1,
    'title' => 1,
    'description' => 1,
  ];

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The view executable factory.
   *
   * @var \Drupal\views\ViewExecutableFactory
   */
  protected $viewExecutableFactory;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The view executable of the menu link.
   *
   * @var \Drupal\views\ViewExecutable
   */
  protected $view;

  /**
   * Constructs a new ViewsMenuLink.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\views\ViewExecutableFactory $view_executable_factory
   *   The view executable factory.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, ViewExecutableFactory $view_executable_factory, ConfigFactoryInterface $config_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $entity_type_manager;
    $this->viewExecutableFactory = $view_executable_factory;
    $this->configFactory = $config_factory;
  }

  /**
   * Initializes the proper view.
   *
   * @return \Drupal\views\ViewExecutable
   *   The view executable.
   */
  public function loadView() {
    if (empty($this->view)) {
      $metadata = $this->getMetaData();
      $view_id = $metadata['view_id'];
      $display_id = $metadata['display_id'];
      $view_entity = $this->entityTypeManager->getStorage('view')->load($view_id);
      $view = $this->viewExecutableFactory->get($view_entity);
      $view->setDisplay($display_id);
      $view->initDisplay();
      $this->view = $view;
    }
    return $this->view;
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    // @todo Get the translated value from the config without instantiating the
    //   view. https://www.drupal.org/node/2310379
    return $this->loadView()->display_handler->getOption('menu')['title'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->loadView()->display_handler->getOption('menu')['description'];
  }

  /**
   * {@inheritdoc}
   */
  public function isExpanded() {
    return (bool) $this->loadView()->display_handler->getOption('menu')['expanded'];
  }

  /**
   * {@inheritdoc}
   */
  public function updateLink(array $new_definition_values, $persist) {
    $overrides = array_intersect_key($new_definition_values, $this->overrideAllowed);
    // Update the definition.
    $this->pluginDefinition = $overrides + $this->pluginDefinition;
    if ($persist) {
      $metadata = $this->getMetaData();
      $view_id = $metadata['view_id'];
      $display_id = $metadata['display_id'];
      $view = $this->entityTypeManager->getStorage('view')->load($view_id);

      $config_name = $view->getConfigDependencyName();
      $config_path = 'display.' . $display_id . '.display_options.menu';

      // Get settings in the original language.
      $config = $this->configFactory->getEditable($config_name);
      $options = $config->get($config_path);

      // Just save the title to the original view.
      $changed = FALSE;
      foreach ($overrides as $key => $new_definition_value) {
        if (empty($options[$key]) || $options[$key] != $new_definition_value) {
          $options[$key] = $new_definition_value;
          $changed = TRUE;
        }
      }
      if ($changed) {
        // @todo Improve this to not trigger a full rebuild of everything, if we
        //   just changed some properties. https://www.drupal.org/node/2310389
        $config->set($config_path, $options);
        $config->save();
      }
    }
    return $this->pluginDefinition;
  }

  /**
   * {@inheritdoc}
   */
  public function isDeletable() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteLink() {
  }

}
