<?php

declare(strict_types=1);

namespace Drupal\Tests\editor\Kernel;

use Drupal\editor\Entity\Editor;
use Drupal\filter\Entity\FilterFormat;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests integration with filter module.
 */
#[Group('editor')]
#[RunTestsInSeparateProcesses]
class EditorFilterIntegrationTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['filter', 'editor', 'editor_test'];

  /**
   * Tests text format removal or disabling.
   */
  public function testTextFormatIntegration(): void {
    // Create an arbitrary text format.
    $format = FilterFormat::create([
      'format' => $this->randomMachineName(),
      'name' => $this->randomString(),
    ]);
    $format->save();

    // Create a paired editor.
    Editor::create([
      'format' => $format->id(),
      'editor' => 'unicorn',
      'image_upload' => [
        'status' => FALSE,
      ],
    ])->save();

    // Disable the text format.
    $format->disable()->save();

    // The paired editor should be disabled too.
    $this->assertFalse(Editor::load($format->id())->status());

    // Re-enable the text format.
    $format->enable()->save();

    // The paired editor should be enabled too.
    $this->assertTrue(Editor::load($format->id())->status());

    // Completely remove the text format. Usually this cannot occur via UI, but
    // can be triggered from API.
    $format->delete();

    // The paired editor should be removed.
    $this->assertNull(Editor::load($format->id()));
  }

  /**
   * Tests that ::getFilterFormat() throws domain exception if format not set.
   */
  public function testEmptyFilterFormat(): void {
    $format = FilterFormat::create([
      'format' => mb_strtolower($this->randomMachineName()),
      'name' => $this->randomString(),
    ]);
    $format->save();
    $editor = Editor::create(['editor' => 'unicorn']);
    $editor->set('format', $format->id());
    $format = $editor->getFilterFormat();
    $this->assertInstanceOf(FilterFormat::class, $format);

    // With an invalid format, getFilterFormat will return NULL.
    $null_filter_editor = Editor::create(['editor' => 'unicorn', 'format' => 'invalid_format']);
    $this->assertEmpty($null_filter_editor->getFilterFormat());

    // Without an associated format, getFilterFormat will throw a domain
    // exception.
    $exception_editor = Editor::create(['editor' => 'unicorn']);
    $this->expectException(\DomainException::class);
    $this->expectExceptionMessage('You cannot call Drupal\editor\Entity\Editor::getFilterFormat on the editor "unicorn" since it does not have an assigned text format.');
    $exception_editor->getFilterFormat();
  }

}
