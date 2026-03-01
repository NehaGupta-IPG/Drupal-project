<?php

namespace Drupal\Composer\Plugin\Scaffold;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\Plugin\Capability\CommandProvider;
use Composer\Plugin\Capable;
use Composer\Plugin\CommandEvent;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Composer\Semver\Constraint\Constraint;
use Composer\Util\Filesystem;
use Composer\Util\Platform;
use Drupal\Composer\Plugin\Scaffold\CommandProvider as ScaffoldCommandProvider;
use Drupal\Core\Boot\PreBootstrapPath;
use Symfony\Component\Filesystem\Path;

/**
 * Composer plugin for handling drupal scaffold.
 *
 * @internal
 */
class Plugin implements PluginInterface, EventSubscriberInterface, Capable {

  /**
   * The Composer service.
   *
   * @var \Composer\Composer
   */
  protected $composer;

  /**
   * Composer's I/O service.
   *
   * @var \Composer\IO\IOInterface
   */
  protected $io;

  /**
   * The Composer Scaffold handler.
   *
   * @var \Drupal\Composer\Plugin\Scaffold\Handler
   */
  protected $handler;

  /**
   * Record whether the 'require' command was called.
   *
   * @var bool
   */
  protected $requireWasCalled;

  /**
   * Comment.
   *
   * @param \Composer\Composer $composer
   *   Composer.
   * @param \Composer\IO\IOInterface $io
   *   IO.
   *
   * @return string|null
   *   Return.
   */
  public static function getCorePath(Composer $composer, IOInterface $io): ?string {
    $core = $composer
      ->getRepositoryManager()
      ->getLocalRepository()
      ->findPackage('drupal/core', '*');
    if (!$core) {
      // This can only happen in testing scenarios.
      $io->write('This is not a Drupal install, Drupal autoloader skipped.');
      return '';
    }
    return $composer
      ->getInstallationManager()
      ->getInstallPath($core);
  }

  /**
   * {@inheritdoc}
   */
  public function activate(Composer $composer, IOInterface $io) {
    $this->composer = $composer;
    $this->io = $io;
    $this->requireWasCalled = FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function deactivate(Composer $composer, IOInterface $io) {
  }

  /**
   * {@inheritdoc}
   */
  public function uninstall(Composer $composer, IOInterface $io) {
  }

  /**
   * {@inheritdoc}
   */
  public function getCapabilities() {
    return [CommandProvider::class => ScaffoldCommandProvider::class];
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // Important note: We only instantiate our handler on "post" events.
    return [
      ScriptEvents::POST_UPDATE_CMD => 'postCmd',
      ScriptEvents::POST_INSTALL_CMD => 'postCmd',
      PackageEvents::POST_PACKAGE_INSTALL => 'postPackage',
      PluginEvents::COMMAND => 'onCommand',
      ScriptEvents::PRE_AUTOLOAD_DUMP => 'preAutoloadDump',
      ScriptEvents::POST_AUTOLOAD_DUMP => 'postAutoloadDump',
    ];
  }

  /**
   * Post command event callback.
   *
   * @param \Composer\Script\Event $event
   *   The Composer event.
   */
  public function postCmd(Event $event) {
    $this->handler()->scaffold();
  }

  /**
   * Post package event behavior.
   *
   * @param \Composer\Installer\PackageEvent $event
   *   Composer package event sent on install/update/remove.
   */
  public function postPackage(PackageEvent $event) {
    $this->handler()->onPostPackageEvent($event);
  }

  /**
   * Pre command event callback.
   *
   * @param \Composer\Plugin\CommandEvent $event
   *   The Composer command event.
   */
  public function onCommand(CommandEvent $event) {
    if ($event->getCommandName() == 'require') {
      if ($this->handler) {
        throw new \Error('Core Scaffold Plugin handler instantiated too early. See https://www.drupal.org/project/drupal/issues/3104922');
      }
      $this->requireWasCalled = TRUE;
    }
  }

  /**
   * Instantiates the handler object upon demand.
   *
   * It is dangerous to update a Composer plugin if it loads any classes prior
   * to the `composer update` operation, and later tries to use them in a
   * post-update hook.
   */
  protected function handler() {
    if (!$this->handler) {
      $this->handler = new Handler($this->composer, $this->io);
      // On instantiation of our handler, notify it if the 'require' command
      // was executed.
      if ($this->requireWasCalled) {
        $this->handler->requireWasCalled();
      }
    }
    return $this->handler;
  }

  /**
   * Add vendor classes to Composer's static classmap.
   *
   * @param \Composer\Script\Event $event
   *   The event.
   */
  public static function preAutoloadDump(Event $event): void {
    // Get the configured vendor directory.
    $composer = $event->getComposer();
    $vendor_dir = $composer->getConfig()->get('vendor-dir');

    // We need the root_package package so we can add our classmaps to its
    // loader.
    $package = $composer->getPackage();
    // We need the local repository so that we can query and see if it's likely
    // that our files are present there.
    $repository = $composer->getRepositoryManager()->getLocalRepository();
    // This is, essentially, a null constraint. We only care whether the package
    // is present in the vendor directory yet, but findPackage() requires it.
    $constraint = new Constraint('>', '');
    // It's possible that there is no classmap specified in a custom project
    // composer.json file. We need one so we can optimize lookup for some of our
    // dependencies.
    $autoload = $package->getAutoload();
    $autoload['classmap'] ??= [];
    // Check for packages used prior to the default classloader being able to
    // use APCu and optimize them if they're present.
    // @see \Drupal\Core\DrupalKernel::boot()
    if ($repository->findPackage('symfony/http-foundation', $constraint)) {
      $autoload['classmap'] = array_merge($autoload['classmap'], [
        $vendor_dir . '/symfony/http-foundation/Request.php',
        $vendor_dir . '/symfony/http-foundation/RequestStack.php',
        $vendor_dir . '/symfony/http-foundation/ParameterBag.php',
        $vendor_dir . '/symfony/http-foundation/FileBag.php',
        $vendor_dir . '/symfony/http-foundation/ServerBag.php',
        $vendor_dir . '/symfony/http-foundation/HeaderBag.php',
        $vendor_dir . '/symfony/http-foundation/HeaderUtils.php',
      ]);
    }
    if ($repository->findPackage('symfony/http-kernel', $constraint)) {
      $autoload['classmap'] = array_merge($autoload['classmap'], [
        $vendor_dir . '/symfony/http-kernel/HttpKernel.php',
        $vendor_dir . '/symfony/http-kernel/HttpKernelInterface.php',
        $vendor_dir . '/symfony/http-kernel/TerminableInterface.php',
      ]);
    }
    if ($repository->findPackage('symfony/dependency-injection', $constraint)) {
      $autoload['classmap'][] = $vendor_dir . '/symfony/dependency-injection/ContainerInterface.php';
    }
    if ($repository->findPackage('psr/container', $constraint)) {
      $autoload['classmap'][] = $vendor_dir . '/psr/container/src/ContainerInterface.php';
    }
    $autoload['classmap'] = array_merge(static::generatePreBootstrapContainerDefinition($composer), $autoload['classmap']);
    $autoload['classmap'][] = static::writeDrupalClass($vendor_dir, 'DrupalInstalled.php', DrupalInstalledTemplate::getCode($package, $repository));

    $package->setAutoload($autoload);
  }

  /**
   * Generate the bootstrap map.
   *
   * @param \Composer\Composer $composer
   *   The composer object.
   *
   * @return array
   *   Paths to classes to be added to the classmap.
   */
  protected static function generatePreBootstrapContainerDefinition(Composer $composer): array {
    $return = [];
    $root_extra = $composer->getPackage()->getExtra();
    $container_definition = [
      'parameters' => $root_extra['drupal-pre-bootstrap']['parameters'] ?? [],
      'services' => $root_extra['drupal-pre-bootstrap']['services'] ?? [],
    ];
    $paths = [];
    $packages = $composer
      ->getRepositoryManager()
      ->getLocalRepository()
      ->getPackages();
    foreach ($packages as $package) {
      $name = $package->getName();
      foreach (['services', 'parameters'] as $key) {
        $container_definition[$key] += ($package->getExtra()['drupal-pre-bootstrap'][$key] ?? []);
      }
      $paths[$name] = $composer->getInstallationManager()->getInstallPath($package);
    }
    if (isset($paths['drupal/core'])) {
      $web_root = (new ManageOptions($composer))
        ->getLocationReplacements()
        ->interpolate('[web-root]');
      $vendor_dir = $composer->getConfig()->get('vendor-dir');
      $current_dir = Platform::getCwd();
      $container_definition['parameters'] += [
        PreBootstrapPath::CORE->value => Path::makeRelative($paths['drupal/core'], $current_dir),
        PreBootstrapPath::WEB->value => Path::makeRelative($web_root, $current_dir),
        PreBootstrapPath::VENDOR->value => Path::makeRelative($vendor_dir, $current_dir),
      ];
    }
    else {
      return $return;
    }
    // Add classes in Drupal contrib to the autoloader. This only works if a
    // class in a namespace starting with Drupal\foo belongs to the
    // "drupal/foo" project. Otherwise, it needs to be added to the root
    // composer.json as a file type autoload.
    foreach ($container_definition['services'] as $definition) {
      if (!isset($definition['class'])) {
        continue;
      }
      $parts = explode('\\', $definition['class']);
      if (isset($parts[1])) {
        $name = 'drupal/' . $parts[1];
        if (isset($paths[$name])) {
          $parts[0] = $paths[$name];
          $parts[1] = 'src';
          $return[] = implode('/', $parts) . '.php';
        }
      }
    }
    $container_definition = var_export($container_definition, TRUE);
    $contents = <<<__EOD__
<?php

// phpcs:disable

namespace Drupal;

/**
 * PreBootstrapDefinition.
 */
class PreBootstrapDefinition {

  public const array DEFINITION = $container_definition;

}

__EOD__;

    $return[] = static::writeDrupalClass($vendor_dir, 'PreBootstrapDefinition.php', $contents);
    return $return;
  }

  /**
   * Make the Composer autoloader init Drupal's pre-bootstrap container.
   *
   * @param \Composer\Script\Event $event
   *   The event.
   */
  public static function postAutoloadDump(Event $event): void {
    $composer = $event->getComposer();
    $io = $event->getIO();
    $fs = new Filesystem();
    $vendor_dir = $composer->getConfig()->get('vendor-dir');
    $vendor_dir = $fs->normalizePath($vendor_dir);
    $core_path = self::getCorePath($composer, $io);
    $fs->rename($vendor_dir . '/autoload.php', $vendor_dir . '/autoload_composer.php');
    $fs->safeCopy($core_path . '/assets/scaffold/files/autoload.php', $vendor_dir . '/autoload.php');
    $io->write('Drupal autoloader copied.');
  }

  /**
   * Writes a Drupal class into vendor/Drupal.
   *
   * @param string $vendor_dir
   *   Typically, the string vendor.
   * @param string $filename
   *   The name of the file to write.
   * @param string $contents
   *   The contents of the file to write.
   *
   * @return string
   *   The path to be added to the autoloader.
   */
  protected static function writeDrupalClass(string $vendor_dir, string $filename, string $contents): string {
    $filesystem = new Filesystem();
    // Do not remove double realpath() calls.
    // Fixes failing Windows realpath() implementation.
    // See https://bugs.php.net/bug.php?id=72738
    $vendor_path = realpath(realpath($vendor_dir));
    $filesystem->ensureDirectoryExists($vendor_path . '/drupal');
    // Create the Drupal\DrupalInstalled class.
    file_put_contents($vendor_path . '/drupal/' . $filename, $contents);
    return $vendor_dir . '/drupal/' . $filename;
  }

}
