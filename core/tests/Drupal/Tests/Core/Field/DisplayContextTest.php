<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Field;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\DisplayContext;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Unit tests for the DisplayContext enum.
 *
 * @group Field
 */
class DisplayContextTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Set up a minimal container for BaseFieldDefinition::create() to work.
    $field_type_manager = $this->prophesize(FieldTypePluginManagerInterface::class);
    $field_type_manager->getDefaultStorageSettings('string')->willReturn([]);
    $field_type_manager->getDefaultFieldSettings('string')->willReturn([]);

    $container = new ContainerBuilder();
    $container->set('plugin.manager.field.field_type', $field_type_manager->reveal());
    \Drupal::setContainer($container);
  }

  /**
   * Tests DisplayContext enum values.
   */
  public function testDisplayContextEnum() {
    $this->assertEquals('view', DisplayContext::View->value);
    $this->assertEquals('form', DisplayContext::Form->value);
  }

  /**
   * Tests setDisplayConfigurable with enum values.
   */
  public function testSetDisplayConfigurableWithEnum() {
    $definition = BaseFieldDefinition::create('string');
    // Test with enum values.
    $definition->setDisplayConfigurable(DisplayContext::View, TRUE);
    $this->assertTrue($definition->isDisplayConfigurable('view'));
    $definition->setDisplayConfigurable(DisplayContext::Form, FALSE);
    $this->assertFalse($definition->isDisplayConfigurable('form'));
  }

  /**
   * Tests setDisplayOptions with enum values.
   */
  public function testSetDisplayOptionsWithEnum() {
    $definition = BaseFieldDefinition::create('string');
    $view_options = ['type' => 'string', 'weight' => 5];
    $form_options = ['type' => 'string_textfield', 'weight' => 10];
    // Test with enum values.
    $definition->setDisplayOptions(DisplayContext::View, $view_options);
    $definition->setDisplayOptions(DisplayContext::Form, $form_options);
    $this->assertEquals($view_options, $definition->getDisplayOptions('view'));
    $this->assertEquals($form_options, $definition->getDisplayOptions('form'));
  }

  /**
   * Tests backward compatibility with string values.
   */
  public function testBackwardCompatibilityWithStrings() {
    $definition = BaseFieldDefinition::create('string');
    // Test with string values (should still work)
    $definition->setDisplayConfigurable('view', TRUE);
    $this->assertTrue($definition->isDisplayConfigurable('view'));
    $options = ['type' => 'string', 'weight' => 5];
    $definition->setDisplayOptions('form', $options);
    $this->assertEquals($options, $definition->getDisplayOptions('form'));
  }

}
