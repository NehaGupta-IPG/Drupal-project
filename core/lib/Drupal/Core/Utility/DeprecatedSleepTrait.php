<?php

declare(strict_types=1);

namespace Drupal\Core\Utility;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;

/**
 * Trait that provides backwards compatibility for __sleep().
 *
 * This trait provides backwards compatibility for two separate use cases:
 * - Serializable core classes implement __serialize(). As a result, _sleep() or
 *   methods in contrib or custom classes that extend any of those classes will
 *   not run during serialization. Core classes can use this trait to make sure
 *   a subclass's __sleep() is invoked during serialization.
 * - Direct calls to __sleep(). This trait provides __sleep() for classes that
 *   have had it replaced by __serialize(), in case there are calls from
 *   subclasses or external code.
 *
 * @internal
 */
trait DeprecatedSleepTrait {

  /**
   * {@inheritdoc}
   */
  public function __serialize(): array {
    if (!$this->overridesSleep()) {
      return $this->doSerialize();
    }

    // If an extending class implements its own __sleep() method, make sure it
    // gets called, otherwise PHP will ignore __sleep() in any class that has
    // __serialize().
    @trigger_error('Implementing __sleep() in a subclass of ' . __CLASS__ . ' is deprecated in drupal:11.5.0 and is not supported in drupal:13.0.0. Implement __serialize() instead. See https://www.drupal.org/node/3578423', E_USER_DEPRECATED);
    // @phpstan-ignore method.deprecated
    $keys = $this->__sleep();
    // Read the properties from the object's full property table, as PHP does
    // for the names returned by a native __sleep(). Unlike a property read
    // from the scope of this class, that also finds private properties of a
    // subclass. Their keys stay mangled ("\0Class\0property"), exactly as PHP
    // writes them, so that __unserialize() can assign them in the scope of the
    // class that declares them.
    $vars = (array) $this;
    $map = [];
    foreach ($keys as $key) {
      // Mirror PHP's lookup order for a name returned by __sleep(): a public
      // or dynamic property, then a private property of the concrete class,
      // then a protected property.
      foreach ([$key, "\0" . static::class . "\0" . $key, "\0*\0" . $key] as $candidate) {
        if (array_key_exists($candidate, $vars)) {
          $map[$candidate] = $vars[$candidate];
          continue 2;
        }
      }
      // Not found in the property table, for example an uninitialized typed
      // property. Let a regular property read produce the usual error.
      $map[$key] = $this->$key;
    }
    return $map;
  }

  /**
   * {@inheritdoc}
   *
   * @deprecated in drupal:11.5.0 and is removed from drupal:13.0.0. Use
   *    __serialize() instead.
   * @see https://www.drupal.org/node/3578423
   */
  public function __sleep(): array {
    @trigger_error(__CLASS__ . '::__sleep() is deprecated in drupal:11.5.0 and is removed from drupal:13.0.0. Use __serialize() instead. See https://www.drupal.org/node/3578423', E_USER_DEPRECATED);
    return array_keys($this->doSerialize());
  }

  /**
   * Helper that handles serialization of the class.
   *
   * @return array
   *   Key-value map as expected to be returned from __unserialize().
   */
  abstract protected function doSerialize(): array;

  /**
   * Checks whether a class using this trait overrides __sleep().
   *
   * @return bool
   *   TRUE if the class overrides __sleep(), otherwise FALSE.
   */
  protected function overridesSleep(): bool {
    $reflection_trait = new \ReflectionClass(DependencySerializationTrait::class);
    $reflection_class = new \ReflectionClass($this);
    try {
      $reflection_trait_method = $reflection_trait->getMethod('__sleep');
      $reflection_class_method = $reflection_class->getMethod('__sleep');
      return $reflection_class_method->getFileName() !== $reflection_trait_method->getFileName();
    }
    catch (\ReflectionException) {
      // Do nothing on invalid methods.
    }
    return FALSE;
  }

}
