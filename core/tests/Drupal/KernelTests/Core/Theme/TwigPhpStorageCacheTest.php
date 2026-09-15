<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Theme;

use Drupal\Component\PhpStorage\PhpStorageInterface;
use Drupal\Core\PhpStorage\PhpStorageFactory;
use Drupal\Core\Template\TwigEnvironment;
use Drupal\Core\Template\TwigPhpStorageCache;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Twig cache storage backed by the compiled file's mtime.
 *
 * @see \Drupal\Core\Template\TwigPhpStorageCache
 */
#[Group('Twig')]
#[RunTestsInSeparateProcesses]
class TwigPhpStorageCacheTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * Tests that getTimestamp() returns the compiled file's mtime.
   */
  public function testGetTimestampReturnsCompiledFileMtime(): void {
    /** @var \Drupal\Core\Template\TwigEnvironment $environment */
    $environment = \Drupal::service('twig');
    $cache = $environment->getCache();

    $name = 'core/modules/system/templates/container.html.twig';
    $class = $environment->getTemplateClass($name);
    $key = $cache->generateKey($name, $class);
    $cache->write($key, '<?php // test');

    $timestamp = $cache->getTimestamp($key);
    $this->assertGreaterThan(0, $timestamp);

    $path = PhpStorageFactory::get('twig')->getFullPath($key);
    clearstatcache(TRUE, $path);
    $this->assertSame(filemtime($path), $timestamp);
  }

  /**
   * Tests that getTimestamp() returns 0 for a key that was never written.
   */
  public function testGetTimestampForMissingTemplate(): void {
    /** @var \Drupal\Core\Template\TwigEnvironment $environment */
    $environment = \Drupal::service('twig');
    $cache = $environment->getCache();

    $name = 'core/modules/system/templates/container.html.twig';
    $class = $environment->getTemplateClass($name);
    $key = $cache->generateKey($name, $class);

    $this->assertSame(0, $cache->getTimestamp($key));
  }

  /**
   * Tests that getTimestamp() returns 0 when the storage denies file access.
   *
   * \Drupal\Component\PhpStorage\PhpStorageInterface::getFullPath() returns
   * FALSE for implementations that prevent access to the file.
   */
  public function testGetTimestampWhenStorageDeniesAccess(): void {
    $storage = $this->createStub(PhpStorageInterface::class);
    $storage->method('getFullPath')->willReturn(FALSE);

    $cache = new class(\Drupal::cache('default'), 'prefix', $storage) extends TwigPhpStorageCache {

      public function __construct($cache, $twig_cache_prefix, PhpStorageInterface $storage) {
        parent::__construct($cache, $twig_cache_prefix);
        $this->storage = $storage;
      }

    };

    $this->assertSame(0, $cache->getTimestamp('any-key'));
  }

  /**
   * Tests that write() does not create a 'twig:' entry in the cache bin.
   */
  public function testNoCacheEntryIsWritten(): void {
    /** @var \Drupal\Core\Template\TwigEnvironment $environment */
    $environment = \Drupal::service('twig');
    $cache = $environment->getCache();

    $name = 'core/modules/system/templates/container.html.twig';
    $class = $environment->getTemplateClass($name);
    $key = $cache->generateKey($name, $class);
    $cache->write($key, '<?php // test');

    $this->assertFalse(\Drupal::cache('default')->get('twig:' . $key));
  }

  /**
   * Tests that a freshly compiled template is not recompiled.
   *
   * With auto-reload enabled, a template that has just been compiled must be
   * considered fresh, so that a subsequent request does not recompile it.
   *
   * Note: building a second \Drupal\Core\Template\TwigEnvironment instance to
   * simulate a following request is not a viable way to assert this, because
   * \Twig\Environment::loadTemplate() short-circuits via class_exists() as
   * soon as the template class has been declared once in this PHP process,
   * bypassing the cache freshness check entirely.
   * #[RunTestsInSeparateProcesses] only isolates between test methods, not
   * within one, so that shortcut cannot be avoided here. Asserting
   * isTemplateFresh() directly is the honest equivalent within a single
   * kernel test.
   */
  public function testAutoReloadDoesNotRecompile(): void {
    $container = \Drupal::getContainer();
    $options = [
      'cache' => TRUE,
      'debug' => TRUE,
      'auto_reload' => TRUE,
    ];
    $environment = new TwigEnvironment(
      $container->getParameter('app.root'),
      $container->get('cache.default'),
      $container->getParameter('twig_extension_hash'),
      $container->get('state'),
      \Drupal::service('twig')->getLoader(),
      $options
    );

    $name = 'core/modules/system/templates/container.html.twig';
    $environment->load($name);

    $cache = $environment->getCache();
    $class = $environment->getTemplateClass($name);
    $key = $cache->generateKey($name, $class);

    $this->assertTrue($environment->isTemplateFresh($name, $cache->getTimestamp($key)));
  }

}
