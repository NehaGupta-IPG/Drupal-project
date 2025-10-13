<?php

declare(strict_types=1);

namespace Drupal\Tests\field\Kernel\EntityReference\Views;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;

/**
 * Tests output limitation for views selection handlers.
 *
 * @group entity_reference
 */
class ViewsSelectionLimitTest extends KernelTestBase {

  use NodeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_reference_test',
    'field',
    'node',
    'system',
    'user',
    'views',
  ];

  /**
   * The view executable.
   */
  protected ViewExecutable $view;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig('entity_reference_test');
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');

    // Create 20 test nodes, all containing a known string sequence.
    $type = $this->randomMachineName();
    NodeType::create([
      'type' => $type,
      'name' => $this->randomString(),
    ])->save();
    for ($i = 0; $i < 20; $i++) {
      $this->createNode([
        'type' => $type,
        'title' => $this->randomString() . ' string to search for',
        'status' => NodeInterface::PUBLISHED,
      ]);
    }

    $this->view = Views::getView('test_entity_reference');
    $this->view->initDisplay();
  }

  /**
   * Tests that the Views selection limits the number of results correctly.
   */
  public function testLimitedOutput(): void {
    /** @var \Drupal\Core\Entity\EntityAutocompleteMatcherInterface $matcher */
    $matcher = $this->container->get('entity.autocomplete_matcher');
    $selection_settings = [
      'view' => [
        'view_name' => 'test_entity_reference',
        'display_name' => 'entity_reference_1',
        'arguments' => [],
      ],
    ];

    // Check default limit.
    $result = $matcher->getMatches('node', 'views', $selection_settings, 'string to search for');
    $this->assertCount(10, $result);

    // Check custom limit.
    $result = $matcher->getMatches('node', 'views', $selection_settings + ['match_limit' => 15], 'string to search for');
    $this->assertCount(15, $result);
  }

}
