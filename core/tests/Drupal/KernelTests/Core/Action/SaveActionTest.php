<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Action;

use Drupal\Core\Action\Plugin\Action\Derivative\EntityChangedActionDeriver;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Drupal\entity_test\Entity\EntityTestMulChanged;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Mocks\TestMockedTime;
use Drupal\system\Entity\Action;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Save Action.
 */
#[Group('Action')]
class SaveActionTest extends KernelTestBase implements ServiceModifierInterface {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'entity_test', 'user'];

  /**
   * The mocked time service.
   *
   * @var \Drupal\Mocks\TestMockedTime
   */
  protected $time;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('entity_test_mul_changed');

    $this->time = $this->container->get('datetime.time');
    $this->time->setRequestTime(10_000);
  }

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $service_definition = $container->getDefinition('datetime.time');
    $service_definition->setClass(TestMockedTime::class);
  }

  /**
   * Tests get derivative definitions.
   *
   * @legacy-covers \Drupal\Core\Action\Plugin\Action\Derivative\EntityChangedActionDeriver::getDerivativeDefinitions
   */
  public function testGetDerivativeDefinitions(): void {
    $deriver = new EntityChangedActionDeriver(\Drupal::entityTypeManager(), \Drupal::translation());
    $definitions = $deriver->getDerivativeDefinitions([
      'action_label' => 'Save',
    ]);
    $this->assertEquals([
      'type' => 'entity_test_mul_changed',
      'label' => 'Save test entity - multiple changed and data table',
      'action_label' => 'Save',
    ], $definitions['entity_test_mul_changed']);
  }

  /**
   * Tests save action.
   *
   * @legacy-covers \Drupal\Core\Action\Plugin\Action\SaveAction::execute
   */
  public function testSaveAction(): void {
    $entity = EntityTestMulChanged::create(['name' => 'test']);
    $entity->save();
    $saved_time = $entity->getChangedTime();

    // Advance the current time.
    $this->time->incrementRequestTime();

    $action = Action::create([
      'id' => 'entity_save_action',
      'plugin' => 'entity:save_action:entity_test_mul_changed',
    ]);
    $action->save();
    $action->execute([$entity]);
    $this->assertNotSame($saved_time, $entity->getChangedTime());
    $this->assertSame(['module' => ['entity_test']], $action->getDependencies());
  }

}
