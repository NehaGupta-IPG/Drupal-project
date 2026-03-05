<?php

declare(strict_types=1);

namespace Drupal\Tests\user\Unit\Plugin\Derivative;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\Plugin\Derivative\UserLocalTask;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the local tasks deriver class.
 */
#[CoversClass(UserLocalTask::class)]
#[Group('user')]
class UserLocalTaskTest extends UnitTestCase {

  /**
   * The local tasks deriver.
   *
   * @var \Drupal\user\Plugin\Derivative\UserLocalTask
   */
  protected $deriver;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $prophecy = $this->prophesize(EntityTypeInterface::class);
    $prophecy->hasLinkTemplate('entity-permissions-form')->willReturn(FALSE);
    $entity_no_link_template = $prophecy->reveal();

    $prophecy = $this->prophesize(EntityTypeInterface::class);
    $prophecy->hasLinkTemplate('entity-permissions-form')->willReturn(TRUE);
    $prophecy->getBundleOf()->willReturn(NULL);
    $prophecy->entityClassImplements(ConfigEntityInterface::class)->willReturn(TRUE);
    $prophecy->hasLinkTemplate('edit-form')->willReturn(FALSE);
    $entity_no_bundle_of_no_edit_form = $prophecy->reveal();

    $prophecy = $this->prophesize(EntityTypeInterface::class);
    $prophecy->hasLinkTemplate('entity-permissions-form')->willReturn(TRUE);
    $prophecy->getBundleOf()->willReturn(NULL);
    $prophecy->entityClassImplements(ConfigEntityInterface::class)->willReturn(TRUE);
    $prophecy->hasLinkTemplate('edit-form')->willReturn(TRUE);
    $entity_no_bundle_of = $prophecy->reveal();

    $prophecy = $this->prophesize(EntityTypeInterface::class);
    $prophecy->hasLinkTemplate('entity-permissions-form')->willReturn(TRUE);
    $prophecy->getBundleOf()->willReturn('content_entity_type_id');
    $entity_bundle_of = $prophecy->reveal();

    $prophecy = $this->prophesize(EntityTypeInterface::class);
    $prophecy->hasLinkTemplate('entity-permissions-form')->willReturn(FALSE);
    $prophecy->get('field_ui_base_route')->willReturn('field_ui.base_route');
    $content_entity_type = $prophecy->reveal();

    $prophecy = $this->prophesize(EntityTypeManagerInterface::class);
    $prophecy->getDefinitions()->willReturn([
      'entity_no_link_template_id' => $entity_no_link_template,
      'entity_no_bundle_of_id_no_edit_form' => $entity_no_bundle_of_no_edit_form,
      'entity_no_bundle_of' => $entity_no_bundle_of,
      'entity_bundle_of_id' => $entity_bundle_of,
      'content_entity_type_id' => $content_entity_type,
    ]);
    $entity_type_manager = $prophecy->reveal();

    $this->deriver = new UserLocalTask($entity_type_manager, $this->getStringTranslationStub());
  }

  /**
   * Tests the derivatives generated for local tasks.
   *
   * @legacy-covers \Drupal\user\Plugin\Derivative\UserLocalTask::getDerivativeDefinitions
   */
  public function testGetDerivativeDefinitions(): void {
    $expected = [
      'permissions_entity_bundle_of_id' => [
        'route_name' => 'entity.entity_bundle_of_id.entity_permissions_form',
        'weight' => 10,
        'title' => $this->getStringTranslationStub()->translate('Manage permissions'),
        'base_route' => 'field_ui.base_route',
      ],
      'permissions_entity_no_bundle_of' => [
        'route_name' => 'entity.entity_no_bundle_of.entity_permissions_form',
        'weight' => 10,
        'title' => $this->getStringTranslationStub()->translate('Manage permissions'),
        'base_route' => 'entity.entity_no_bundle_of.edit_form',
      ],
    ];
    $this->assertEquals($expected, $this->deriver->getDerivativeDefinitions([]));
  }

}
