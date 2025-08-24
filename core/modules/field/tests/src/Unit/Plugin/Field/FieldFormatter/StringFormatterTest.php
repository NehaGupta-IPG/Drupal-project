<?php

declare(strict_types=1);

namespace Drupal\Tests\field\Unit\Plugin\Field\FieldFormatter;

use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Access\AccessResultForbidden;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\StringFormatter;
use Drupal\Core\Field\Plugin\Field\FieldType\StringItem;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Url;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;

/**
 * Tests the string field formatter.
 *
 * @group field
 *
 * @coversDefaultClass \Drupal\Core\Field\Plugin\Field\FieldFormatter\StringFormatter
 */
final class StringFormatterTest extends UnitTestCase {

  /**
   * Checks link visibility depending on link templates and access.
   *
   * @param bool $hasUrl
   *   Whether the entity type has a canonical link template.
   * @param string|null $accessClass
   *   The access result for the current user.
   * @param bool $expectIsLinkElement
   *   Whether to expect the text to be wrapped in a link element.
   *
   * @phpstan-param class-string<\Drupal\Core\Access\AccessResultInterface>|null $accessClass
   *
   * @dataProvider providerAccessLinkToEntity
   */
  public function testLinkToEntity(bool $hasUrl, ?string $accessClass, bool $expectIsLinkElement): void {
    $fieldDefinition = $this->prophesize(FieldDefinitionInterface::class);
    $entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $languageManager = $this->prophesize(LanguageManagerInterface::class);
    $fieldFormatter = new StringFormatter('foobar', [], $fieldDefinition->reveal(), [], 'TestLabel', 'default', [], $entityTypeManager->reveal(), $languageManager->reveal());
    $fieldFormatter->setSetting('link_to_entity', TRUE);

    $entityType = $this->prophesize(EntityTypeInterface::class);
    $entityType->hasLinkTemplate('canonical')->willReturn($hasUrl)->shouldBeCalledTimes(1);
    $entityType->hasLinkTemplate('revision')->willReturn(FALSE)->shouldBeCalledTimes($hasUrl ? 1 : 0);

    $entity = $this->prophesize(EntityInterface::class);
    $entity->isNew()->willReturn(FALSE);
    $entity->getEntityType()->willReturn($entityType->reveal());
    if ($hasUrl) {
      $url = $this->prophesize(Url::class);
      $url->access(NULL, TRUE)->willReturn(new $accessClass());
      $entity->toUrl('canonical')->willReturn($url);
    }

    $item = $this->getMockBuilder(StringItem::class)
      ->disableOriginalConstructor()
      ->onlyMethods([])
      ->getMock();
    $item->setValue(['value' => 'FooText']);

    $items = $this->prophesize(FieldItemListInterface::class);
    $items->getEntity()->willReturn($entity->reveal());
    $items->valid()->willReturn(TRUE, FALSE);
    $items->next();
    $items->rewind();
    $items->current()->willReturn($item);
    $items->key()->willReturn(0);

    $elements = $fieldFormatter->viewElements($items->reveal(), 'en');
    if ($expectIsLinkElement) {
      $this->assertEquals('link', $elements[0]['#type']);
      $this->assertEquals('FooText', $elements[0]['#title']['#context']['value']);
    }
    else {
      $this->assertEquals('inline_template', $elements[0]['#type']);
      $this->assertEquals('FooText', $elements[0]['#context']['value']);
    }
  }

  /**
   * Data provider.
   *
   * @return \Generator
   *   Test scenarios.
   */
  public static function providerAccessLinkToEntity(): \Generator {
    yield 'entity with no URL' => [
      FALSE,
      NULL,
      FALSE,
    ];
    yield 'entity with url, with access' => [
      TRUE,
      AccessResultAllowed::class,
      TRUE,
    ];
    yield 'entity with url, no access' => [
      TRUE,
      AccessResultForbidden::class,
      FALSE,
    ];
  }

  /**
   * Checks that an entity link links to the entity in the current language if the entity has no language.
   *
   * @param string $entityLanguageCode
   *   The language code that the test entity will be given.
   *
   * @dataProvider providerTestLinkToEntityPointsToCurrentLanguage
   */
  public function testLinkToEntityPointsToCurrentLanguage(string $entityLanguageCode): void {

    // Mock the language manager and the current language.
    $currentLanguage = $this->prophesize(LanguageInterface::class)->reveal();
    $languageManager = $this->prophesize(LanguageManagerInterface::class);
    $languageManager
      ->getCurrentLanguage()
      ->willReturn($currentLanguage);
    $languageManager
      ->isMultilingual()
      ->willReturn(TRUE);

    // Mock the entity type manager.
    $entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);

    // Mock an entity type.
    $entityType = $this->prophesize(EntityTypeInterface::class);
    $entityType->hasLinkTemplate('canonical')->willReturn(TRUE)->shouldBeCalledTimes(1);
    $entityType->hasLinkTemplate('revision')->willReturn(FALSE)->shouldBeCalledTimes(1);

    // Mock an entity with its own language which is also different from the current language.
    $entityLanguage = $this->prophesize(LanguageInterface::class);
    $entityLanguage->getId()->willReturn($entityLanguageCode);
    $entity = $this->prophesize(EntityInterface::class);
    $entity->isNew()->willReturn(FALSE);
    $entity->getEntityType()->willReturn($entityType->reveal());
    $entity->language()->willReturn($entityLanguage->reveal());
    $url = $this->prophesize(Url::class);
    $url->access(NULL, TRUE)->willReturn(new AccessResultAllowed());

    $urlOptionLanguage = NULL;
    $url->setOption('language', Argument::any())
      ->will(function ($args) use (&$urlOptionLanguage) {
        if ('language' === $args[0]) {
          $urlOptionLanguage = $args[1];
        }
        return $this;
      })
      ->shouldBeCalledTimes(1);

    $entity->toUrl('canonical')->willReturn($url->reveal());

    // Mock a field item list.
    $item = $this->getMockBuilder(StringItem::class)
      ->disableOriginalConstructor()
      ->onlyMethods([])
      ->getMock();
    $item->setValue(['value' => 'FooText']);
    $items = $this->prophesize(FieldItemListInterface::class);
    $items->getEntity()->willReturn($entity->reveal());
    $items->valid()->willReturn(TRUE, FALSE);
    $items->next();
    $items->rewind();
    $items->current()->willReturn($item);
    $items->key()->willReturn(0);

    // Build a render array using the string formatter in order to produce a link to the entity.
    $fieldDefinition = $this->prophesize(FieldDefinitionInterface::class);
    $fieldFormatter = new StringFormatter('foobar', [], $fieldDefinition->reveal(), [], 'TestLabel', 'default', [], $entityTypeManager->reveal(), $languageManager->reveal());
    $fieldFormatter->setSetting('link_to_entity', TRUE);
    $fieldFormatter->viewElements($items->reveal(), 'en');

    $this->assertSame($currentLanguage, $urlOptionLanguage);
  }

  /**
   * Provides entity languages that will produce a link to the entity in the current language.
   *
   * @return array[]
   */
  public static function providerTestLinkToEntityPointsToCurrentLanguage(): array {
    return [
      'Not specified' => ['und'],
      'Not applicable' => ['zxx'],
    ];
  }

}
