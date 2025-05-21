<?php

declare(strict_types=1);

namespace Drupal\Composer\Plugin\Logger;

use Composer\Composer;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\InstallerEvent;
use Composer\Installer\InstallerEvents;
use Composer\IO\IOInterface;
use Composer\Package\Dumper\ArrayDumper;
use Composer\Plugin\PluginInterface;

/**
 * A Composer plugin to log various installer operations before they happen.
 *
 * @internal
 */
final class Plugin implements PluginInterface, EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      InstallerEvents::PRE_OPERATIONS_EXEC => 'preExec',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function activate(Composer $composer, IOInterface $io): void {
  }

  /**
   * {@inheritdoc}
   */
  public function deactivate(Composer $composer, IOInterface $io): void {
  }

  /**
   * {@inheritdoc}
   */
  public function uninstall(Composer $composer, IOInterface $io): void {
  }

  /**
   * Logs operations before Composer performs them.
   *
   * @param \Composer\Installer\InstallerEvent $event
   *   The event being handled.
   */
  public function preExec(InstallerEvent $event): void {
    $extra = $event->getComposer()->getPackage()->getExtra();
    $file = $extra['drupal-installer-log']['file'] ?? 'installer-log.php';

    $data = [];
    $dumper = new ArrayDumper();
    $operations = $event->getTransaction()?->getOperations() ?? [];

    foreach ($operations as $operation) {
      $item = [
        'operation' => $operation->getOperationType(),
      ];
      if ($operation instanceof InstallOperation || $operation instanceof UninstallOperation) {
        $item['package'] = $dumper->dump($operation->getPackage());
      }
      elseif ($operation instanceof UpdateOperation) {
        $item['from_version'] = $dumper->dump($operation->getInitialPackage());
        $item['to_version'] = $dumper->dump($operation->getTargetPackage());
      }
      $data[] = $item;
    }
    file_put_contents($file, "<?php\nreturn " . var_export($data, TRUE));
  }

}
