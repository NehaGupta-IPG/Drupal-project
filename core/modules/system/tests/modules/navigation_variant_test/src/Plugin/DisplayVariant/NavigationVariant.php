<?php

declare(strict_types=1);

namespace Drupal\navigation_variant_test\Plugin\DisplayVariant;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Display\Attribute\DisplayVariant;
use Drupal\Core\Display\PageVariantInterface;
use Drupal\Core\Display\VariantBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a display variant that requires configuration.
 */
#[DisplayVariant(
  id: 'navigation_variant',
  admin_label: new TranslatableMarkup('Test display variant')
)]
final class NavigationVariant extends VariantBase implements PageVariantInterface, ContainerFactoryPluginInterface {

  /**
   * The render array representing the main page content.
   */
  protected array $mainContent = [];

  /**
   * The page title: a string (plain title) or a render array (formatted title).
   */
  protected string|MarkupInterface|array $title = '';

  /**
   * Constructs a new NavigationVariant.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private RouteMatchInterface $routeMatch,
    private BlockManagerInterface $blockManager,
    private BreadcrumbBuilderInterface $breadcrumbBuilder,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
      $container->get('plugin.manager.block'),
      $container->get('breadcrumb'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function setMainContent(array $main_content) {
    $this->mainContent = $main_content;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setTitle($title) {
    $this->title = $title;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $page = [];

    $page['messages'] = [
      '#type' => 'status_messages',
      '#include_fallback' => TRUE,
    ];

    $page['page_title'] = [
      '#type' => 'page_title',
      '#title' => $this->title,
    ];

    // \Drupal\Core\Menu\Plugin\Block\LocalActionsBlock
    $page['local_actions'] = $this->blockManager->createInstance('local_actions_block')->build();

    // \Drupal\Core\Menu\Plugin\Block\LocalTasksBlock
    $page['local_tasks'] = $this->blockManager->createInstance('local_tasks_block')->build();

    $page['breadcrumb'] = $this->breadcrumbBuilder->build($this->routeMatch)->toRenderable();

    $page['content'] = $this->mainContent;

    // Keys are variables of ['#theme' => 'page'].
    return ['content' => $page];
  }

}
