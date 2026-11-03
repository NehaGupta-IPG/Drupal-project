<?php

declare(strict_types=1);

namespace Drupal\Tests\block\Kernel;

use Drupal\Core\Template\Attribute;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests compatibility of the deprecated Block template path.
 *
 * @todo Remove in Drupal 13 together with the compatibility template.
 */
#[Group('block')]
#[RunTestsInSeparateProcesses]
class BlockTemplateCompatibilityTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'block'];

  /**
   * Tests including and extending the deprecated template with context.
   */
  #[DataProvider('providerTemplates')]
  #[IgnoreDeprecations]
  public function testLegacyTemplate(string $template, string $expected_content, ?string $additional_content = NULL): void {
    $this->container->get('theme_installer')->install(['stark']);
    $this->config('system.theme')->set('default', 'stark')->save();

    $this->expectUserDeprecationMessageMatches('/' . preg_quote('@block/block.html.twig is deprecated in drupal:12.0.0 and is removed from drupal:13.0.0. Use @system/block.html.twig instead. See https://www.drupal.org/node/3003610', '/') . '/');
    $output = $this->container->get('twig')->createTemplate($template)->render([
      'attributes' => new Attribute(['id' => 'legacy-block']),
      'title_attributes' => new Attribute(['class' => ['block-title']]),
      'label' => 'Example <label>',
      'content' => 'Example <content>',
    ]);
    $this->assertStringContainsString('<div id="legacy-block">', $output);
    $this->assertStringContainsString('<h2 class="block-title">Example &lt;label&gt;</h2>', $output);
    $this->assertStringContainsString($expected_content, $output);
    if ($additional_content !== NULL) {
      $this->assertStringContainsString($additional_content, $output);
    }
  }

  /**
   * Provides templates that include or extend the deprecated Block template.
   */
  public static function providerTemplates(): array {
    return [
      'include' => [
        "{% include '@block/block.html.twig' %}",
        'Example &lt;content&gt;',
      ],
      'extends' => [
        "{% extends '@block/block.html.twig' %}\n"
          . "{% block content %}Overridden: {{ content }}{% endblock %}",
        'Overridden: Example &lt;content&gt;',
      ],
      'extends with parent' => [
        "{% extends '@block/block.html.twig' %}\n"
          . "{% block content %}{{ parent() }} Additional content.{% endblock %}",
        'Example &lt;content&gt;',
        'Additional content.',
      ],
    ];
  }

}
