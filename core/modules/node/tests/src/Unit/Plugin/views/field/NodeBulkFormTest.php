<?php

declare(strict_types=1);

namespace Drupal\Tests\node\Unit\Plugin\views\field;

use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\ViewExecutable;
use Drupal\views\ViewEntityInterface;
use Drupal\views\ViewsData;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\system\ActionConfigEntityInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\ResettableStackedRouteMatchInterface;
use Drupal\node\Plugin\views\field\NodeBulkForm;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Drupal\node\Plugin\views\field\NodeBulkForm.
 */
#[CoversClass(NodeBulkForm::class)]
#[Group('node')]
class NodeBulkFormTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    parent::tearDown();
    $container = new ContainerBuilder();
    \Drupal::setContainer($container);
  }

  /**
   * Tests the constructor assignment of actions.
   */
  public function testConstructor(): void {
    $actions = [];

    for ($i = 1; $i <= 2; $i++) {
      $action = $this->createMock(ActionConfigEntityInterface::class);
      $action->expects($this->any())
        ->method('getType')
        ->willReturn('node');
      $actions[$i] = $action;
    }

    $action = $this->createMock(ActionConfigEntityInterface::class);
    $action->expects($this->any())
      ->method('getType')
      ->willReturn('user');
    $actions[] = $action;

    $entity_storage = $this->createMock(EntityStorageInterface::class);
    $entity_storage->expects($this->any())
      ->method('loadMultiple')
      ->willReturn($actions);

    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->expects($this->once())
      ->method('getStorage')
      ->with('action')
      ->willReturn($entity_storage);

    $entity_repository = $this->createMock(EntityRepositoryInterface::class);

    $language_manager = $this->createMock(LanguageManagerInterface::class);

    $messenger = $this->createMock(MessengerInterface::class);

    $route_match = $this->createMock(ResettableStackedRouteMatchInterface::class);

    $views_data = $this->getMockBuilder(ViewsData::class)
      ->disableOriginalConstructor()
      ->getMock();
    $views_data->expects($this->any())
      ->method('get')
      ->with('node')
      ->willReturn(['table' => ['entity type' => 'node']]);
    $container = new ContainerBuilder();
    $container->set('views.views_data', $views_data);
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);

    $storage = $this->createMock(ViewEntityInterface::class);
    $storage->expects($this->any())
      ->method('get')
      ->with('base_table')
      ->willReturn('node');

    $executable = $this->getMockBuilder(ViewExecutable::class)
      ->disableOriginalConstructor()
      ->getMock();
    $executable->storage = $storage;

    $display = $this->getMockBuilder(DisplayPluginBase::class)
      ->disableOriginalConstructor()
      ->getMock();

    $definition['title'] = '';
    $options = [];

    $node_bulk_form = new NodeBulkForm([], 'node_bulk_form', $definition, $entity_type_manager, $language_manager, $messenger, $entity_repository, $route_match);
    $node_bulk_form->init($executable, $display, $options);

    $reflected_actions = (new \ReflectionObject($node_bulk_form))->getProperty('actions');
    $this->assertEquals(array_slice($actions, 0, -1, TRUE), $reflected_actions->getValue($node_bulk_form));
  }

}
