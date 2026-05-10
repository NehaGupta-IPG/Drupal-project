<?php

declare(strict_types=1);

namespace Drupal\Core\DependencyInjection;

use Drupal\Component\FileCache\FileCacheFactory;
use Drupal\Component\FileCache\FileCacheInterface;
use Drupal\Component\Serialization\Exception\InvalidDataTypeException;
use Drupal\Core\DependencyInjection\SymfonyConfigShim\FileLocatorInterface as DrupalFileLocatorInterface;
use Drupal\Core\DependencyInjection\SymfonyConfigShim\Loader\FileLoader as DrupalFileLoader;
use Drupal\Core\Serialization\Yaml;
use Symfony\Component\Config\FileLocatorInterface as SymfonyFileLocatorInterface;
use Symfony\Component\Config\Loader\FileLoader as SymfonyFileLoader;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader as SymfonyYamlFileLoader;

if (!interface_exists(SymfonyFileLocatorInterface::class)) {
  class_alias(DrupalFileLocatorInterface::class, SymfonyFileLocatorInterface::class);
}
if (!class_exists(SymfonyFileLoader::class)) {
  class_alias(DrupalFileLoader::class, SymfonyFileLoader::class);
}

/**
 * YamlFileLoader loads YAML files service definitions.
 *
 * Thin subclass of Symfony's YamlFileLoader that:
 * - Decodes YAML through Drupal's Yaml component (so Drupal's YAML hooks
 *   apply) and caches the parsed array per file via FileCache.
 * - Auto-injects a `_provider` tag on every service derived from the file's
 *   basename, and makes services public by default.
 *
 * @see \Symfony\Component\DependencyInjection\Loader\YamlFileLoader
 */
class YamlFileLoader extends SymfonyYamlFileLoader {

  /**
   * Per-file cache for parsed YAML payloads, keyed by file path and mtime.
   */
  protected FileCacheInterface $fileCache;

  public function __construct(ContainerBuilder $container) {
    $locator = new class() implements SymfonyFileLocatorInterface {

      /**
       * {@inheritdoc}
       */
      public function locate(string $name, ?string $currentPath = NULL, bool $first = TRUE): string|array {
        return $name;
      }

    };
    parent::__construct($container, $locator);
    $this->fileCache = FileCacheFactory::get('container_yaml_loader');
  }

  /**
   * {@inheritdoc}
   */
  protected function loadFile(string $file): ?array {
    $content = $this->fileCache->get($file);
    if ($content !== NULL) {
      return $content;
    }
    if (!stream_is_local($file)) {
      throw new InvalidArgumentException(sprintf('This is not a local file "%s".', $file));
    }
    if (!file_exists($file)) {
      throw new InvalidArgumentException(sprintf('The service file "%s" is not valid.', $file));
    }
    try {
      $content = Yaml::decode(file_get_contents($file));
    }
    catch (InvalidDataTypeException $e) {
      throw new InvalidArgumentException(sprintf('The file "%s" does not contain valid YAML: %s', $file, $e->getMessage()));
    }
    $content = $this->injectDefaults($content, $file);
    $this->fileCache->set($file, $content);
    return $content;
  }

  /**
   * Injects Drupal's per-file defaults into the parsed YAML payload.
   */
  protected function injectDefaults(?array $content, string $file): ?array {
    if (!is_array($content) || !isset($content['services']) || !is_array($content['services'])) {
      return $content;
    }

    if (isset($content['imports'])) {
      throw new InvalidArgumentException(sprintf('The "imports" key is not supported in "%s".', $file));
    }

    foreach ($content['services'] as &$service) {
      if (is_array($service) && (isset($service['resource']) || isset($service['namespace']))) {
        throw new InvalidArgumentException(sprintf('PSR-4 service prototypes ("resource"/"namespace") are not supported in "%s".', $file));
      }
      // Drupal services historically declared `deprecated:` as a single
      // string (the message) and omitted package/version. Symfony 8's
      // upstream YamlFileLoader requires both, so normalize the shape
      // before the parent processes it.
      if (is_array($service) && isset($service['deprecated'])) {
        $deprecation = is_array($service['deprecated']) ? $service['deprecated'] : ['message' => $service['deprecated']];
        $deprecation += ['package' => '', 'version' => ''];
        $service['deprecated'] = $deprecation;
      }
    }

    $provider = $content['_provider'] ?? explode('.', basename($file), 2)[0];
    unset($content['_provider']);

    $defaults = $content['services']['_defaults'] ?? [];
    // If _defaults or tags are malformed, let the parent raise the error.
    if (!is_array($defaults) || (isset($defaults['tags']) && !is_array($defaults['tags']))) {
      return $content;
    }
    $defaults['public'] ??= TRUE;
    $defaults['tags'][] = ['name' => '_provider', 'provider' => $provider];
    $content['services']['_defaults'] = $defaults;
    return $content;
  }

}
