<?php

declare(strict_types=1);

namespace Drupal\Core\Theme\Style;

use Drupal\Component\Plugin\Discovery\DiscoveryInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Plugin\Discovery\ContainerDerivativeDiscoveryDecorator;
use Drupal\Core\Plugin\Discovery\YamlDiscovery;
use Drupal\Core\Plugin\Factory\ContainerFactory;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Theme\Style\Exception\StyleDefinitionErrorException;
use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator;

/**
 * Provides the default style plugin manager.
 *
 * An extension can define a style in an EXTENSION_NAME.styles.yml file
 * contained in the extension's base directory.
 * Each style must have a `label` and `options` property.
 *
 * @code
 * example_style:
 *   label: (string) The name of the style for display.
 *   options: (array) The list of options for the style. Each option must have a
 *     label property. Then optional properties are:
 *     - value: (string) The value of the option. The option key is value is not
 *         specified.
 *     - description: (string) The description of the option.
 *     - attribute: (string) The attribute to set on the element. `class` by
 *         default.
 *     - target: (string) Override the target of the style for the option.
 *     - modes: (array) The user preferences the option is adapted to. Each
 *         mode matches a CSS media feature. An unspecified mode means the
 *         option is adapted to any value of this user preference. Possible
 *         modes are:
 *         - color_scheme: `light` or `dark`. See `prefers-color-scheme`.
 *         - contrast: `more` or `less`. See `prefers-contrast`.
 *         - reduced_motion: `reduce`. See `prefers-reduced-motion`.
 *     - status: (string) Status of the option. Possible values are the same as
 *         for the style's status property.
 *
 *   # Optional values:
 *   enabled: (boolean) Set FALSE to disable the style discovering process.
 *   description: (string) The description of the style for display.
 *   category: (string) The category of the style for display. Default is
 *     `Other`.
 *   links: (array)
 *     - (string) The URL of a documentation page.
 *     - title: (string) The title of the link.
 *       url: (string) The URL of the link.
 *   weight: (int) The weight of the style for ordering. Default is `0`.
 *   status: (string) The status of the style. Possible values are:
 *     - `experimental`: The style is experimental.
 *     - `stable`: The style is stable. Default value.
 *     - `deprecated`: The style is deprecated.
 *     - `obsolete`: The style is obsoleted.
 *     Using a style or an option marked as `deprecated` or `obsolete` will log
 *     warnings.
 *   type: (string) The type of the style. Possible values are:
 *     - `theme`: The style is a theme and depending on target, its application is
 *         limited to specific elements.
 *     - `utility`: The style can be applied on anything. Default value.
 *   attribute: (string) The attribute to set on the element. Default is
 *     `class`.
 *   target: (string) The target of the style. Possible values are:
 *     - `html`: The style is applicable on the html tag only.
 *     - `body`: The style is applicable on the body tag only.
 *     - `meta`: When applied, the style will generate a meta tag.
 *     - `any`/empty string/not specified: The style is applicable on any tag.
 *         Default value.
 *   library: (string) Drupal library machine name to include.
 * @endcode
 * For example:
 * @code
 * background_color:
 *   enabled: true
 *   category: "Background"
 *   label: "Background color"
 *   description: "My description."
 *   links:
 *     - url: 'https://homepage.com/docs/#usage'
 *       title: 'Detailed documentation'
 *     - 'https://documentation.com/docs/'
 *   options:
 *     primary:
 *       label: "Primary"
 *       value: "bg-primary"
 *     secondary:
 *       label: "Secondary"
 *       value: "bg-secondary"
 *     dark:
 *       label: "Dark"
 *       value: "bg-dark"
 *       modes:
 *         color_scheme: "dark"
 *     ...
 *   library: "my_theme/my_lib"
 *   weight: 50
 * @endcode
 *
 * @see \Drupal\Core\Theme\Style\StyleDefinitionInterface
 * @see plugin_api
 *
 * @method \Drupal\Core\Theme\Style\StyleDefinitionInterface|null getDefinition($plugin_id, $exception_on_invalid = TRUE)
 * @method \Drupal\Core\Theme\Style\StyleDefinitionInterface[] getDefinitions()
 *
 * @internal
 *   The style API is experimental and is not meant for production use.
 *   See https://www.drupal.org/core/experimental for more information.
 */
class StylePluginManager extends DefaultPluginManager implements StylePluginManagerInterface {

  private const string SCHEMA_VALIDATE = 'core/assets/schemas/v1/style.schema.json';

  /**
   * The schema validator.
   *
   * This property will only be set if the validator library is available.
   *
   * @var \JsonSchema\Validator|null
   */
  private ?Validator $validator = NULL;

  public function __construct(
    ModuleHandlerInterface $module_handler,
    protected readonly ThemeHandlerInterface $themeHandler,
    CacheBackendInterface $cacheBackend,
    protected string $appRoot,
  ) {
    $this->moduleHandler = $module_handler;
    $this->factory = new ContainerFactory($this);
    $this->alterInfo('style');
    $this->setCacheBackend($cacheBackend, 'style', ['style_plugin']);
  }

  /**
   * Sets the validator service if available.
   *
   * @param \JsonSchema\Validator|null $validator
   *   The JSON Validator class.
   */
  public function setValidator(?Validator $validator = NULL): void {
    if (NULL !== $validator) {
      $this->validator = $validator;
      return;
    }
    if (class_exists(Validator::class)) {
      $this->validator = new Validator();
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getDiscovery(): DiscoveryInterface {
    if (!$this->discovery) {
      $discovery = new YamlDiscovery('styles', $this->moduleHandler->getModuleDirectories() + $this->themeHandler->getThemeDirectories());
      $discovery->addTranslatableProperty('label');
      $discovery->addTranslatableProperty('description');
      $discovery->addTranslatableProperty('category');

      $discovery = new ContainerDerivativeDiscoveryDecorator($discovery);
      $this->discovery = $discovery;
    }

    return $this->discovery;
  }

  /**
   * {@inheritdoc}
   */
  public function getCategories() {
    // Fetch all categories from definitions and remove duplicates.
    $categories = \array_unique(\array_values(\array_map(static function (StyleDefinitionInterface $definition) {
      return $definition->getCategory();
    }, $this->getDefinitions())));
    \natcasesort($categories);
    return $categories;
  }

  /**
   * {@inheritdoc}
   */
  public function getSortedDefinitions(?array $definitions = NULL, string $label_key = 'label'): array {
    $definitions = $definitions ?? $this->getDefinitions();

    \uasort($definitions, static function (StyleDefinitionInterface $item1, StyleDefinitionInterface $item2) {
      // Sort by weight.
      $weight = $item1->getWeight() <=> $item2->getWeight();
      if ($weight != 0) {
        return $weight;
      }

      // Sort by category.
      $category1 = $item1->getCategory();
      if ($category1 instanceof TranslatableMarkup) {
        $category1 = $category1->render();
      }
      $category2 = $item2->getCategory();
      if ($category2 instanceof TranslatableMarkup) {
        $category2 = $category2->render();
      }
      if ($category1 != $category2) {
        return \strnatcasecmp($category1, $category2);
      }

      // Sort by label ignoring parenthesis.
      $label1 = $item1->getLabel();
      if ($label1 instanceof TranslatableMarkup) {
        $label1 = $label1->render();
      }
      $label2 = $item2->getLabel();
      if ($label2 instanceof TranslatableMarkup) {
        $label2 = $label2->render();
      }
      // Ignore parenthesis.
      $label1 = \str_replace(['(', ')'], '', $label1);
      $label2 = \str_replace(['(', ')'], '', $label2);
      if ($label1 != $label2) {
        return \strnatcasecmp($label1, $label2);
      }

      // Sort by plugin ID.
      // In case the plugin ID starts with an underscore.
      $id1 = \str_replace('_', '', $item1->id());
      $id2 = \str_replace('_', '', $item2->id());
      return \strnatcasecmp($id1, $id2);
    });

    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupedDefinitions(?array $definitions = NULL, string $label_key = 'label'): array {
    $definitions = $this->getSortedDefinitions($definitions ?? $this->getDefinitions(), $label_key);
    $grouped_definitions = [];
    foreach ($definitions as $id => $definition) {
      $grouped_definitions[(string) $definition->getCategory()][$id] = $definition;
    }
    return $grouped_definitions;
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-ignore-next-line
   */
  protected function alterDefinitions(&$definitions) {
    parent::alterDefinitions($definitions);

    /** @var \Drupal\Core\Theme\Style\StyleDefinitionInterface[] $definitions */
    foreach ($definitions as $definition_key => $definition) {
      if (!$definition->isEnabled()) {
        unset($definitions[$definition_key]);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function processDefinition(&$definition, $plugin_id): void {
    parent::processDefinition($definition, $plugin_id);
    /** @var array<string, mixed> $definition */

    /** @var string $plugin_id */
    if (preg_match('@[^a-z0-9_]@', $plugin_id)) {
      throw new StyleDefinitionErrorException(\sprintf('Invalid style id in: %s, name: %s must contain only lowercase letters, numbers, and underscores.', $definition['provider'], $plugin_id));
    }

    if (empty($definition['id'])) {
      throw new StyleDefinitionErrorException(\sprintf('Style plugin property (%s) definition "id" is required.', $plugin_id));
    }

    $this->validateDefinition($definition);

    $definition = new StyleDefinition($definition);
  }

  /**
   * {@inheritdoc}
   */
  protected function providerExists($provider): bool {
    /** @var string $provider */
    return $this->moduleHandler->moduleExists($provider) || $this->themeHandler->themeExists($provider);
  }

  /**
   * Validates a definition against the JSON schema specification.
   *
   * @param array $definition
   *   The definition to check.
   *
   * @return bool
   *   FALSE if the response failed validation, otherwise TRUE.
   *
   * @throws \Drupal\Core\Theme\Style\Exception\StyleDefinitionErrorException
   *   Thrown when the definition is not valid.
   */
  protected function validateDefinition(array $definition): bool {
    // If the validator isn't set, then the validation library is not installed.
    if (!$this->validator) {
      return TRUE;
    }

    $schema_ref = sprintf(
      'file://%s/%s',
      $this->appRoot,
      self::SCHEMA_VALIDATE
    );
    $schema = (object) ['$ref' => $schema_ref];

    $definition_object = Validator::arrayToObjectRecursive($definition);

    $this->validator->validate($definition_object, $schema, Constraint::CHECK_MODE_COERCE_TYPES);

    if ($this->validator->isValid()) {
      return TRUE;
    }

    $message_parts = array_map(
      static fn (array $error): string => sprintf("[%s] %s", $error['property'], $error['message']),
      $this->validator->getErrors()
    );
    $message = implode(", ", $message_parts);

    throw new StyleDefinitionErrorException(
      sprintf(
        '%s:%s Error in definition `%s`:%s',
        $definition['provider'],
        $definition['id'],
        $definition_object->id,
        $message
      )
    );
  }

}
