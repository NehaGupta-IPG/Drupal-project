<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Theme\Style;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Theme\Style\Exception\StyleDefinitionErrorException;
use Drupal\Core\Theme\Style\StyleYamlDiscovery;
use Drupal\Tests\UnitTestCase;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Drupal\Core\Theme\Style\StyleYamlDiscovery.
 */
#[CoversClass(StyleYamlDiscovery::class)]
#[Group('style')]
class StyleYamlDiscoveryTest extends UnitTestCase {

  /**
   * Test that the type is set from the file name.
   */
  public function testGetDefinitions(): void {
    vfsStream::setup('root');
    vfsStream::create([
      'module_a' => [
        'module_a.themes.yml' => <<<'EOS'
theme_a:
  label: 'Theme A'
EOS,
        'module_a.utilities.yml' => <<<'EOS'
utility_a:
  label: 'Utility A'
EOS,
      ],
      'module_b' => [
        // The type declared in the file is ignored.
        'module_b.utilities.yml' => <<<'EOS'
utility_b:
  label: 'Utility B'
  type: 'theme'
EOS,
        // Files with other names are ignored.
        'module_b.styles.yml' => <<<'EOS'
style_b:
  label: 'Style B'
EOS,
      ],
    ]);

    $discovery = new StyleYamlDiscovery([
      'module_a' => vfsStream::url('root/module_a'),
      'module_b' => vfsStream::url('root/module_b'),
    ]);
    $definitions = $discovery->getDefinitions();

    $this->assertEquals([
      'theme_a' => [
        'label' => 'Theme A',
        'type' => 'theme',
        'provider' => 'module_a',
        'id' => 'theme_a',
      ],
      'utility_a' => [
        'label' => 'Utility A',
        'type' => 'utility',
        'provider' => 'module_a',
        'id' => 'utility_a',
      ],
      'utility_b' => [
        'label' => 'Utility B',
        'type' => 'utility',
        'provider' => 'module_b',
        'id' => 'utility_b',
      ],
    ], $definitions);
  }

  /**
   * Test that translatable properties apply to all the types.
   */
  public function testAddTranslatableProperty(): void {
    vfsStream::setup('root');
    vfsStream::create([
      'module_a' => [
        'module_a.themes.yml' => <<<'EOS'
theme_a:
  label: 'Theme A'
EOS,
        'module_a.utilities.yml' => <<<'EOS'
utility_a:
  label: 'Utility A'
EOS,
      ],
    ]);

    $discovery = new StyleYamlDiscovery([
      'module_a' => vfsStream::url('root/module_a'),
    ]);
    $discovery->addTranslatableProperty('label');
    $definitions = $discovery->getDefinitions();

    $this->assertInstanceOf(TranslatableMarkup::class, $definitions['theme_a']['label']);
    $this->assertInstanceOf(TranslatableMarkup::class, $definitions['utility_a']['label']);
  }

  /**
   * Test that a style ID declared for several types throws an exception.
   */
  public function testDuplicateIdAcrossTypes(): void {
    vfsStream::setup('root');
    vfsStream::create([
      'module_a' => [
        'module_a.themes.yml' => <<<'EOS'
duplicate:
  label: 'Theme'
EOS,
      ],
      'module_b' => [
        'module_b.utilities.yml' => <<<'EOS'
duplicate:
  label: 'Utility'
EOS,
      ],
    ]);

    $discovery = new StyleYamlDiscovery([
      'module_a' => vfsStream::url('root/module_a'),
      'module_b' => vfsStream::url('root/module_b'),
    ]);

    $this->expectException(StyleDefinitionErrorException::class);
    $this->expectExceptionMessage('Style "duplicate" is declared as theme by module_a and as utility by module_b.');
    $discovery->getDefinitions();
  }

}
