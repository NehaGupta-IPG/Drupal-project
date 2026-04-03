<?php

declare(strict_types=1);

namespace Drupal\Tests\media_library\Functional;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\media_library\Plugin\Field\FieldWidget\MediaLibraryWidget;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests MediaLibraryWidget base field behavior.
 */
#[Group('media_library')]
#[RunTestsInSeparateProcesses]
final class MediaLibraryWidgetMessageTest extends UnitTestCase {

  /**
   * Tests the default no-media-types message for base fields.
   */
  public function testNoMediaTypesMessageForBaseField(): void {
    $field_definition = $this->createMock(FieldDefinitionInterface::class);

    $field_definition->expects($this->never())
      ->method('getTargetEntityTypeId');

    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);

    $current_user = $this->createMock(AccountInterface::class);
    $current_user->expects($this->never())
      ->method('hasPermission');

    $module_handler = $this->createMock(ModuleHandlerInterface::class);
    $module_handler->expects($this->never())
      ->method('moduleExists');

    $widget = new TestMediaLibraryWidget(
      'media_library_widget',
      [],
      $field_definition,
      [],
      [],
      $entity_type_manager,
      $current_user,
      $module_handler,
    );
    $widget->setStringTranslation($this->getStringTranslationStub());

    $this->assertSame(
      'There are no allowed media types configured for this field. Contact the site administrator.',
      (string) $widget->getNoMediaTypesAvailableMessagePublic()
    );
  }

}

/**
 * Testable subclass exposing protected methods.
 */
final class TestMediaLibraryWidget extends MediaLibraryWidget {

  /**
   * Exposes the protected method for testing.
   */
  public function getNoMediaTypesAvailableMessagePublic(): MarkupInterface {
    return $this->getNoMediaTypesAvailableMessage();
  }

}
