<?php

declare(strict_types=1);

namespace Drupal\Tests\media_library\Functional;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\media_library\Plugin\Field\FieldWidget\MediaLibraryWidget;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\TestWith;

/**
 * Tests MediaLibraryWidget base field behavior.
 */
#[Group('media_library')]
#[RunTestsInSeparateProcesses]
final class MediaLibraryWidgetMessageTest extends UnitTestCase {

  /**
   * Tests the no-media-types messages for base fields.
   */
  #[TestWith([
    TRUE,
    'There are no allowed media types configured for this field. You might want to <a href="/admin/structure/media/add">create one</a>.',
  ])]
  #[TestWith([
    FALSE,
    'There are no allowed media types configured for this field. Contact the site administrator.',
  ])]
  public function testNoMediaTypesMessageForBaseField($can_create_media_types, $expected_message): void {
    $field_definition = $this->createMock(FieldDefinitionInterface::class);

    $field_definition->expects($this->never())
      ->method('getTargetEntityTypeId');

    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);

    $current_user = $this->createMock(AccountInterface::class);
    $current_user->expects($this->once())
      ->method('hasPermission')
      ->with('administer media types')
      ->willReturn($can_create_media_types);

    $module_handler = $this->createMock(ModuleHandlerInterface::class);
    $module_handler->expects($this->never())
      ->method('moduleExists');

    $url_generator = $this->createMock(UrlGeneratorInterface::class);
    $url_generator->method('generateFromRoute')
      ->willReturn('/admin/structure/media/add');
    $container = new ContainerBuilder();
    $container->set('url_generator', $url_generator);
    \Drupal::setContainer($container);

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
      $expected_message,
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
