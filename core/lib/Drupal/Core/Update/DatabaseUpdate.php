<?php

namespace Drupal\Core\Update;

use Drupal\Component\Graph\Graph;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Extension\Exception\UnknownExtensionException;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Update\Attribute\MarkFutureUpdateEquivalent;
use Drupal\Core\Utility\Error;

/**
 * Drupal database update API.
 *
 * This class provides methods for executing database updates within the
 * context of a Drupal installation.
 */
class DatabaseUpdate {

  use StringTranslationTrait;
  use DependencySerializationTrait;

  public function __construct(
    protected UpdateHookRegistry $updateRegistry,
    protected ModuleHandlerInterface $moduleHandler,
    protected MessengerInterface $messenger,
    protected LoggerChannelFactoryInterface $loggerChannelFactory,
    protected UpdateRegistry $postUpdateRegistry,
    protected ModuleExtensionList $moduleExtensionList,
  ) {}

  /**
   * Returns whether the minimum schema requirement has been satisfied.
   *
   * @return array<string, array{'title': \Drupal\Core\StringTranslation\TranslatableMarkup, 'value': mixed, description: \Drupal\Core\StringTranslation\TranslatableMarkup, 'severity': \Drupal\Core\Extension\Requirement\RequirementSeverity}>
   *   An array of requirements, in the same format as is returned by
   *   hook_requirements().
   *
   * @see hook_requirements()
   * @see getRequirements()
   */
  public function systemSchemaRequirements(): array {
    $requirements = [];

    $system_schema = $this->updateRegistry->getInstalledVersion('system');

    $requirements['minimum schema']['title'] = 'Minimum schema version';
    if ($system_schema >= \Drupal::CORE_MINIMUM_SCHEMA_VERSION) {
      $requirements['minimum schema'] += [
        'value' => 'The installed schema version meets the minimum.',
        'description' => 'Schema version: ' . $system_schema,
      ];
    }
    else {
      $requirements['minimum schema'] += [
        'value' => 'The installed schema version does not meet the minimum.',
        'severity' => RequirementSeverity::Error,
        'description' => 'Your system schema version is ' . $system_schema . '. Updating directly from a schema version prior to 8000 is not supported. You must upgrade your site to Drupal 8 (or later), see https://www.drupal.org/docs/upgrading-drupal.',
      ];
    }

    return $requirements;
  }

  /**
   * Checks update requirements and reports errors and (optionally) warnings.
   *
   * @return array<string, array{'title': \Drupal\Core\StringTranslation\TranslatableMarkup, 'value': mixed, description: \Drupal\Core\StringTranslation\TranslatableMarkup, 'severity': \Drupal\Core\Extension\Requirement\RequirementSeverity}>
   *   An array of requirements, in the same format as is returned by
   *   hook_requirements().
   *
   * @see hook_requirements()
   */
  public function getRequirements(): array {
    // Because this is one of the earliest points in the update process,
    // detect and fix missing schema versions for modules here to ensure
    // it runs on all update code paths.
    $this->fixMissingSchema();

    // Check requirements of all loaded modules.
    $requirements = array_merge(
      $this->moduleHandler->invokeAll('requirements', ['update']),
      $this->moduleHandler->invokeAll('update_requirements'),
    );
    $this->moduleHandler->alter('requirements', $requirements);
    $this->moduleHandler->alter('update_requirements', $requirements);
    $requirements += $this->systemSchemaRequirements();
    return $requirements;
  }

  /**
   * Detect and fix 'missing' schema information with the helper.
   *
   * Repairs the case where a module has no schema version recorded.
   * This has to be done prior to updates being run, otherwise the update
   * system would detect and attempt to run all historical updates for a
   * module.
   *
   * @todo remove in a major version after
   *   https://www.drupal.org/project/drupal/issues/3130037 has been fixed.
   *
   * @internal
   */
  public function fixMissingSchema(): void {
    $versions = $this->updateRegistry->getAllInstalledVersions();
    $enabled_modules = $this->moduleHandler->getModuleList();

    foreach (array_keys($enabled_modules) as $module) {
      // All modules should have a recorded schema version, but when they don't,
      // detect and fix the problem.
      if (!isset($versions[$module])) {
        // Ensure the .install file is loaded.
        $this->moduleHandler->loadInclude($module, 'install');
        $all_updates = $this->updateRegistry->getAvailableUpdates($module);
        // If the schema version of a module hasn't been recorded, we cannot
        // know the actual schema version a module is at, because no updates
        // will ever have been run on the site and it was not set correctly when
        // the module was installed, so instead set it to the same as the last
        // update. This means that updates will proceed again the next time the
        // module is updated and a new update is added. Updates added in between
        // the module being installed and the schema version being fixed here
        // (if any have been added) will never be run, but we have no way to
        // identify which updates these are.
        if ($all_updates) {
          $last_update = max($all_updates);
        }
        else {
          $last_update = \Drupal::CORE_MINIMUM_SCHEMA_VERSION;
        }
        // If the module implements hook_update_last_removed() use the value of
        // that if it's higher than the schema versions found so far. The hook
        // is procedural only and is not collected by the hook system, so call
        // the function directly.
        $function = $module . '_update_last_removed';
        if (function_exists($function) && ($last_removed = $function())) {
          $last_update = max($last_update, $last_removed);
        }
        $this->updateRegistry->setInstalledVersion($module, $last_update);
        $args = ['%module' => $module, '%last_update_hook' => $module . '_update_' . $last_update . '()'];
        $this->messenger->addWarning($this->t('Schema information for module %module was missing from the database. You should manually review the module updates and your database to check if any updates have been skipped up to, and including, %last_update_hook.', $args));
        $this->loggerChannelFactory->get('update')->warning('Schema information for module %module was missing from the database. You should manually review the module updates and your database to check if any updates have been skipped up to, and including, %last_update_hook.', $args);
      }
    }
  }

  /**
   * Performs one update and stores the results to display on the results page.
   *
   * If an update function completes successfully, it should return a message
   * as a string indicating success, for example:
   * @code
   * return t('New index added successfully.');
   * @endcode
   *
   * Alternatively, it may return nothing. In that case, no message
   * will be displayed at all.
   *
   * If it fails for whatever reason, it should throw an instance of
   * Drupal\Core\Utility\UpdateException with an appropriate error message, for
   * example:
   * @code
   * use Drupal\Core\Utility\UpdateException;
   * throw new UpdateException('Description of what went wrong');
   * @endcode
   *
   * If an exception is thrown, the current update and all updates that depend
   * on it will be aborted. The schema version will not be updated in this case,
   * and all the aborted updates will continue to appear on update.php as
   * updates that have not yet been run.
   *
   * If an update function needs to be re-run as part of a batch process, it
   * should accept the $sandbox array by reference as its first parameter and
   * set the #finished property to the percentage completed that it is, as a
   * fraction of 1.
   *
   * @param string $module
   *   The module whose update will be run.
   * @param int $number
   *   The update number to run.
   * @param array $dependency_map
   *   An array whose keys are the names of all update functions that will be
   *   performed during this batch process, and whose values are arrays of other
   *   update functions that each one depends on.
   * @param array $context
   *   The batch context array.
   *
   * @see callback_batch_operation()
   */
  public function doOne(string $module, int $number, array $dependency_map, array &$context): void {
    $function = $module . '_update_' . $number;

    // If this update was aborted in a previous step, or has a dependency that
    // was aborted in a previous step, go no further.
    if (!empty($context['results']['#abort']) && array_intersect($context['results']['#abort'], array_merge($dependency_map, [$function]))) {
      return;
    }

    $ret = [];
    $equivalent_update = $this->updateRegistry->getEquivalentUpdate($module, $number);
    if ($equivalent_update instanceof EquivalentUpdate) {
      $ret['results']['query'] = $equivalent_update->toSkipMessage();
      $ret['results']['success'] = TRUE;
      $context['sandbox']['#finished'] = TRUE;
    }
    elseif (function_exists($function)) {
      try {
        // Check for the MarkFutureUpdateEquivalent attribute on the update
        // function to register a future equivalent update.
        $attributes = (new \ReflectionFunction($function))->getAttributes(MarkFutureUpdateEquivalent::class);
        foreach ($attributes as $attribute) {
          /** @var \Drupal\Core\Update\Attribute\MarkFutureUpdateEquivalent $instance */
          $instance = $attribute->newInstance();
          $this->updateRegistry->markFutureUpdateEquivalent($instance->futureUpdateNumber, $instance->futureVersionString, $module, $number);
        }

        $ret['results']['query'] = $function($context['sandbox']);
        $ret['results']['success'] = TRUE;
      }
      // @todo We may want to do different error handling for different
      // exception types, but for now we'll just log the exception and
      // return the message for printing.
      // @see https://www.drupal.org/node/2564311
      catch (\Exception $e) {
        $variables = Error::decodeException($e);
        $this->loggerChannelFactory->get('update')->error(Error::DEFAULT_ERROR_MESSAGE, $variables);

        unset($variables['backtrace'], $variables['exception'], $variables['severity_level']);
        // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
        $ret['#abort'] = ['success' => FALSE, 'query' => $this->t(Error::DEFAULT_ERROR_MESSAGE, $variables)];
      }
    }

    if (isset($context['sandbox']['#finished'])) {
      $context['finished'] = $context['sandbox']['#finished'];
      unset($context['sandbox']['#finished']);
    }

    if (!isset($context['results'][$module])) {
      $context['results'][$module] = [];
    }
    if (!isset($context['results'][$module][$number])) {
      $context['results'][$module][$number] = [];
    }
    $context['results'][$module][$number] = array_merge($context['results'][$module][$number], $ret);

    if (!empty($ret['#abort'])) {
      // Record this function in the list of updates that were aborted.
      $context['results']['#abort'][] = $function;
    }

    // Record the schema update if it was completed successfully.
    if ($context['finished'] == 1 && empty($ret['#abort'])) {
      $this->updateRegistry->setInstalledVersion($module, $number);
    }

    $context['message'] = $this->t('Updating @module', ['@module' => $module]);
  }

  /**
   * Executes a single hook_post_update_NAME().
   *
   * @param string $function
   *   The function name, that should be executed.
   * @param array $context
   *   The batch context array.
   */
  public function invokePostUpdate(string $function, array &$context): void {
    $ret = [];

    // If this update was aborted in a previous step, or has a dependency that
    // was aborted in a previous step, go no further.
    if (!empty($context['results']['#abort'])) {
      return;
    }

    // Ensure extension post update code is loaded.
    [$extension, $name] = explode('_post_update_', $function, 2);
    $this->postUpdateRegistry->getUpdateFunctions($extension);

    if (function_exists($function)) {
      try {
        $ret['results']['query'] = $function($context['sandbox']);
        $ret['results']['success'] = TRUE;

        if (!isset($context['sandbox']['#finished']) || (isset($context['sandbox']['#finished']) && $context['sandbox']['#finished'] >= 1)) {
          $this->postUpdateRegistry->registerInvokedUpdates([$function]);
        }
      }
      // @todo We may want to do different error handling for different exception
      // types, but for now we'll just log the exception and return the message
      // for printing.
      // @see https://www.drupal.org/node/2564311
      catch (\Exception $e) {
        $variables = Error::decodeException($e);
        $this
          ->loggerChannelFactory
          ->get('update')
          ->error(Error::DEFAULT_ERROR_MESSAGE, $variables);
        unset($variables['backtrace'], $variables['exception'], $variables['severity_level']);
        $ret['#abort'] = [
          'success' => FALSE,
          // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
          'query' => $this->t(Error::DEFAULT_ERROR_MESSAGE, $variables),
        ];
      }
    }

    if (isset($context['sandbox']['#finished'])) {
      $context['finished'] = $context['sandbox']['#finished'];
      unset($context['sandbox']['#finished']);
    }
    if (!isset($context['results'][$extension][$name])) {
      $context['results'][$extension][$name] = [];
    }
    $context['results'][$extension][$name] = array_merge($context['results'][$extension][$name], $ret);

    if (!empty($ret['#abort'])) {
      // Record this function in the list of updates that were aborted.
      $context['results']['#abort'][] = $function;
    }

    $context['message'] = $this->t('Post updating @extension', ['@extension' => $extension]);
  }

  /**
   * Returns a list of all the pending database updates.
   *
   * @return array
   *   An associative array keyed by module name which contains all information
   *   about database updates that need to be run, and any updates that are not
   *   going to proceed due to missing requirements. The system module will
   *   always be listed first.
   *
   *   The subarray for each module can contain the following keys:
   *   - start: The starting update that is to be processed. If this does not
   *       exist then do not process any updates for this module as there are
   *       other requirements that need to be resolved.
   *   - warning: Any warnings about why this module can not be updated.
   *   - pending: An array of all the pending updates for the module including
   *       the update number and the description from source code comment for
   *       each update function. This array is keyed by the update number.
   */
  public function getUpdateList(): array {
    // Make sure that the system module is first in the list of updates.
    $ret = ['system' => []];

    $modules = $this->updateRegistry->getAllInstalledVersions();
    /** @var array $installed_module_info */
    $installed_module_info = $this->moduleExtensionList->getAllInstalledInfo();
    foreach ($modules as $module => $schema_version) {
      // Skip uninstalled and incompatible modules.
      try {
        if ($schema_version == $this->updateRegistry::SCHEMA_UNINSTALLED || $this->moduleExtensionList->checkIncompatibility($module)) {
          continue;
        }
      }
      // It is possible that the system schema has orphaned entries, so the
      // incompatibility checking might throw an exception.
      catch (UnknownExtensionException) {
        $args = [
          '%name' => $module,
          ':url' => 'https://www.drupal.org/node/3137656',
        ];
        $this
          ->messenger
          ->addWarning($this->t('Module %name has an entry in the system.schema key/value storage, but is missing from your site. <a href=":url">More information about this error</a>.', $args));
        $this
          ->loggerChannelFactory
          ->get('system')
          ->notice('Module %name has an entry in the system.schema key/value storage, but is missing from your site. <a href=":url">More information about this error</a>.', $args);
        continue;
      }
      // There might be orphaned entries for modules that are in the filesystem
      // but not installed. Also skip those, but warn site admins about it.
      if (empty($installed_module_info[$module])) {
        $args = [
          '%name' => $module,
          ':url' => 'https://www.drupal.org/node/3137656',
        ];
        $this
          ->messenger
          ->addWarning($this->t('Module %name has an entry in the system.schema key/value storage, but is not installed. <a href=":url">More information about this error</a>.', $args));
        $this
          ->loggerChannelFactory
          ->get('system')
          ->notice('Module %name has an entry in the system.schema key/value storage, but is not installed. <a href=":url">More information about this error</a>.', $args);
        continue;
      }

      // Display a requirements error if the user somehow has a schema version
      // from the previous Drupal major version.
      if ($schema_version < \Drupal::CORE_MINIMUM_SCHEMA_VERSION) {
        $ret[$module]['warning'] = '<em>' . $module . '</em> module cannot be updated. Its schema version is ' . $schema_version . ', which is from an earlier major release of Drupal. See the <a href="https://www.drupal.org/docs/upgrading-drupal">Upgrading Drupal guide</a>.';
        continue;
      }
      // Otherwise, get the list of updates defined by this module.
      $updates = $this->updateRegistry->getAvailableUpdates($module);
      if ($updates) {
        foreach ($updates as $update) {
          if ($update == \Drupal::CORE_MINIMUM_SCHEMA_VERSION) {
            $ret[$module]['warning'] = '<em>' . $module . '</em> module cannot be updated. It contains an update numbered as ' . \Drupal::CORE_MINIMUM_SCHEMA_VERSION . ' which is reserved for the earliest installation of a module in Drupal ' . \Drupal::CORE_COMPATIBILITY . ', before any updates. In order to update <em>' . $module . '</em> module, you will need to download a version of the module with valid updates.';
            continue 2;
          }
          if ($update > $schema_version) {
            // The description for an update comes from its Doxygen.
            $func = new \ReflectionFunction($module . '_update_' . $update);
            $patterns = [
              '/^\s*[\/*]*/',
              '/(\n\s*\**)(.*)/',
              '/\/$/',
              '/^\s*/',
            ];
            $replacements = ['', '$2', '', ''];
            $description = preg_replace($patterns, $replacements, $func->getDocComment());
            $ret[$module]['pending'][$update] = "$update - $description";
            if (!isset($ret[$module]['start'])) {
              $ret[$module]['start'] = $update;
            }
          }
        }
        if (!isset($ret[$module]['start']) && isset($ret[$module]['pending'])) {
          $ret[$module]['start'] = $schema_version;
        }
      }
    }

    if (empty($ret['system'])) {
      unset($ret['system']);
    }
    return $ret;
  }

  /**
   * Resolves dependencies in a set of module updates, and orders them.
   *
   * This function receives a list of requested module updates and determines an
   * appropriate order to run them in such that all update dependencies are met.
   * Any updates whose dependencies cannot be met are included in the returned
   * array but have the key 'allowed' set to FALSE; the calling function should
   * take responsibility for ensuring that these updates are ultimately not
   * performed.
   *
   * In addition, the returned array also includes detailed information about
   * the dependency chain for each update, as provided by the depth-first search
   * algorithm in Drupal\Component\Graph\Graph::searchAndSort().
   *
   * @param array $startingUpdates
   *   An array whose keys contain the names of modules with updates to be run
   *   and whose values contain the number of the first requested update for
   *   that module.
   *
   * @return array
   *   An array whose keys are the names of all update functions within the
   *   provided modules that would need to be run in order to fulfill the
   *   request, arranged in the order in which the update functions should be
   *   run. (This includes the provided starting update for each module and all
   *   subsequent updates that are available.) The values are themselves arrays
   *   containing all the keys provided by the
   *   Drupal\Component\Graph\Graph::searchAndSort() algorithm, which encode
   *   detailed information about the dependency chain for this update function
   *   (for example: 'paths', 'reverse_paths', 'weight', and 'component'), as
   *   well as the following additional keys:
   *   - 'allowed': A boolean which is TRUE when the update function's
   *     dependencies are met, and FALSE otherwise. Calling functions should
   *     inspect this value before running the update.
   *   - 'missing_dependencies': An array containing the names of any other
   *     update functions that are required by this one but that are unavailable
   *     to be run. This array will be empty when 'allowed' is TRUE.
   *   - 'module': The name of the module that this update function belongs to.
   *   - 'number': The number of this update function within that module.
   *
   * @see \Drupal\Component\Graph\Graph::searchAndSort()
   */
  public function resolveDependencies(array $startingUpdates): array {
    // Obtain a dependency graph for the requested update functions.
    $update_functions = $this->getUpdateFunctionList($startingUpdates);
    $graph = $this->buildDependencyGraph($update_functions);

    // Perform the depth-first search and sort on the results.
    $graph_object = new Graph($graph);
    $graph = $graph_object->searchAndSort();
    uasort($graph, ['Drupal\Component\Utility\SortArray', 'sortByWeightElement']);

    foreach ($graph as $function => &$data) {
      $module = $data['module'];
      $number = (int) $data['number'];
      // If the update function is missing and has not yet been performed, mark
      // it and everything that ultimately depends on it as disallowed.
      if ($this->isMissing($module, $number, $update_functions) && !$this->alreadyPerformed($module, $number)) {
        $data['allowed'] = FALSE;
        foreach (array_keys($data['paths']) as $dependent) {
          $graph[$dependent]['allowed'] = FALSE;
          $graph[$dependent]['missing_dependencies'][] = $function;
        }
      }
      elseif (!isset($data['allowed'])) {
        $data['allowed'] = TRUE;
        $data['missing_dependencies'] = [];
      }
      // Now that we have finished processing this function, remove it from the
      // graph if it was not part of the original list. This ensures that we
      // never try to run any updates that were not specifically requested.
      if (!isset($update_functions[$module][$number])) {
        unset($graph[$function]);
      }
    }

    return $graph;
  }

  /**
   * Returns an organized list of update functions for a set of modules.
   *
   * @param array $startingUpdates
   *   An array whose keys contain the names of modules and whose values contain
   *   the number of the first requested update for that module.
   *
   * @return array
   *   An array containing all the update functions that should be run for each
   *   module, including the provided starting update and all subsequent updates
   *   that are available. The keys of the array contain the module names, and
   *   each value is an ordered array of update functions, keyed by the update
   *   number.
   *
   * @see ::resolveDependencies()
   */
  public function getUpdateFunctionList(array $startingUpdates): array {
    // Go through each module and find all updates that we need (including the
    // first update that was requested and any updates that run after it).
    $update_functions = [];
    foreach ($startingUpdates as $module => $version) {
      $update_functions[$module] = [];
      $updates = $this->updateRegistry->getAvailableUpdates($module);
      if ($updates) {
        $max_version = max($updates);
        if ($version <= $max_version) {
          foreach ($updates as $update) {
            if ($update >= $version) {
              $update_functions[$module][$update] = $module . '_update_' . $update;
            }
          }
        }
      }
    }
    return $update_functions;
  }

  /**
   * Constructs a graph which encodes the dependencies between module updates.
   *
   * This function returns an associative array which contains a "directed
   * graph" representation of the dependencies between a provided list of update
   * functions, as well as any outside update functions that they directly
   * depend on but that were not in the provided list. The vertices of the graph
   * represent the update functions themselves, and each edge represents a
   * requirement that the first update function needs to run before the second.
   * For example, consider this graph:
   *
   * system_update_8001 ---> system_update_8002 ---> system_update_8003
   *
   * Visually, this indicates that system_update_8001() must run before
   * system_update_8002(), which in turn must run before system_update_8003().
   *
   * The function takes into account standard dependencies within each module,
   * as shown above (i.e., the fact that each module's updates must run in
   * numerical order), but also finds any cross-module dependencies that are
   * defined by modules which implement hook_update_dependencies(), and builds
   *  them into the graph as well.
   *
   * @param array $updateFunctions
   *   An organized array of update functions, in the format returned by
   *   ::updateGetFunctionList().
   *
   * @return array
   *   A multidimensional array representing the dependency graph, suitable for
   *   passing in to Drupal\Component\Graph\Graph::searchAndSort(), but with
   *   extra information about each update function also included. Each array
   *   key contains the name of an update function, including all update
   *   functions from the provided list as well as any outside update functions
   *   which they directly depend on. Each value is an associative array
   *   containing the following keys:
   *   - 'edges': A representation of any other update functions that
   *     immediately depend on this one. See
   *     Drupal\Component\Graph\Graph::searchAndSort() for more details on the
   *     format.
   *   - 'module': The name of the module that this update function belongs to.
   *   - 'number': The number of this update function within that module.
   *
   * @see \Drupal\Component\Graph\Graph::searchAndSort()
   * @see ::resolveDependencies()
   */
  public function buildDependencyGraph(array $updateFunctions): array {
    // Initialize an array that will define a directed graph representing the
    // dependencies between update functions.
    $graph = [];

    // Helper variables used to to manipulate the graph so that system updates
    // run as early as possible.
    $max_system_update = 0;
    $min_non_system_updates = [];

    // Go through each update function and build an initial list of
    // dependencies.
    foreach ($updateFunctions as $module => $functions) {
      $previous_function = NULL;
      foreach ($functions as $number => $function) {
        // Add an edge to the directed graph representing the fact that each
        // update function in a given module must run after the update that
        // numerically precedes it.
        if ($previous_function) {
          $graph[$previous_function]['edges'][$function] = TRUE;
        }
        $previous_function = $function;

        // Define the module and update number associated with this function.
        $graph[$function]['module'] = $module;
        $graph[$function]['number'] = $number;
        if ($module == 'system') {
          // Save the maximum system update number so independent update
          // functions can be run after all system updates.
          $max_system_update = max($max_system_update, $number);
        }
        else {
          $min_non_system_updates[$module] = min($min_non_system_updates[$module] ?? \PHP_INT_MAX, $number);
        }
      }
    }

    $has_non_system_updates_that_must_run_before_system_updates = FALSE;
    // Now add any explicit update dependencies declared by modules.
    $update_dependencies = $this->retrieveDependencies();
    foreach ($graph as $function => $data) {
      if (!empty($update_dependencies[$data['module']][$data['number']])) {
        if ($data['module'] === 'system') {
          $has_non_system_updates_that_must_run_before_system_updates = TRUE;
        }
        foreach ($update_dependencies[$data['module']][$data['number']] as $module => $number) {
          $dependency = $module . '_update_' . $number;
          $graph[$dependency]['edges'][$function] = TRUE;
          $graph[$dependency]['module'] = $module;
          $graph[$dependency]['number'] = $number;
        }
      }
    }

    if ($max_system_update === 0) {
      // There are no system updates, so there is nothing else to do.
      return $graph;
    }

    if (!$has_non_system_updates_that_must_run_before_system_updates) {
      // This is the simple case where all non-system updates run after all
      // system updates.
      foreach ($min_non_system_updates as $module => $min_number) {
        $function = $module . '_update_' . $min_number;
        $graph['system_update_' . $max_system_update]['edges'][$function] = TRUE;
      }
      return $graph;
    }

    // In this case, there are non-system updates that must run before a system
    // update. These updates must run after any system updates that are not
    // dependent on them. Build the current graph to work this out.
    $processed_graph = (new Graph($graph))->searchAndSort();
    foreach ($processed_graph as $function => $data) {
      if ($data['module'] !== 'system') {
        $min_system_update = NULL;
        // The paths key contains the full list of update functions that must
        // run after.
        foreach (array_keys($data['paths']) as $dependent) {
          if (str_starts_with($dependent, 'system_update_')) {
            $system_update_number = (int) substr($dependent, 14);
            $min_system_update = $min_system_update !== NULL ? min($min_system_update, $system_update_number) : $system_update_number;
          }
        }
        if ($min_system_update === NULL) {
          // If there are no system updates in the paths key, then ensure this
          // update function runs after all system updates.
          $graph['system_update_' . $max_system_update]['edges'][$function] = TRUE;
        }
        else {
          // If there are system updates in the paths key, then ensure this
          // update runs after the previous system update.
          $previous_system_update = 'system_update_' . ($min_system_update - 1);
          if (isset($graph[$previous_system_update])) {
            $graph[$previous_system_update]['edges'][$function] = TRUE;
          }
        }
      }
    }
    return $graph;
  }

  /**
   * Determines if a module update is missing or unavailable.
   *
   * @param string $module
   *   The name of the module.
   * @param int $number
   *   The number of the update within that module.
   * @param array $updateFunctions
   *   An organized array of update functions, in the format returned by
   *   ::updateGetFunctionList(). This should represent all module updates that
   *   are requested to run at the time this function is called.
   *
   * @return bool
   *   TRUE if the provided module update is not installed or is not in the
   *   provided list of updates to run; FALSE otherwise.
   */
  public function isMissing(string $module, int $number, array $updateFunctions): bool {
    return !isset($updateFunctions[$module][$number]) || !function_exists($updateFunctions[$module][$number]);
  }

  /**
   * Determines if a module update has already been performed.
   *
   * @param string $module
   *   The name of the module.
   * @param int $number
   *   The number of the update within that module.
   *
   * @return bool
   *   TRUE if the database schema indicates that the update has already been
   *   performed; FALSE otherwise.
   */
  public function alreadyPerformed(string $module, int $number): bool {
    return $number <= $this->updateRegistry->getInstalledVersion($module);
  }

  /**
   * Invokes hook_update_dependencies() in all installed modules.
   *
   * This function is similar to \Drupal::moduleHandler()->invokeAll(), with the
   * main difference that it does not require that a module be enabled to invoke
   * its hook, only that it be installed. This allows the update system to
   * properly perform updates even on modules that are currently disabled.
   *
   * @return array
   *   An array of return values obtained by merging the results of the
   *   hook_update_dependencies() implementations in all installed modules.
   *
   * @see \Drupal\Core\Extension\ModuleHandlerInterface::invokeAll()
   * @see hook_update_dependencies()
   */
  public function retrieveDependencies(): array {
    $return = [];
    // Get a list of installed modules, arranged so that we invoke their hooks
    // in the same order that \Drupal::moduleHandler()->invokeAll() does.
    foreach ($this->updateRegistry->getAllInstalledVersions() as $module => $schema) {
      // Skip modules that are entirely missing from the filesystem here, since
      // loading .install file will call trigger_error() if invoked on a module
      // that doesn't exist. There's no way to catch() that, so avoid it
      // entirely This can happen when there are orphaned entries in the
      // system.schema k/v store for modules that have been removed from a site
      // without first being cleanly uninstalled. We don't care here if the
      // module has been installed or not, since we'll filter those out in
      // ::getUpdateList().
      if ($schema == $this->updateRegistry::SCHEMA_UNINSTALLED || !$this->moduleExtensionList->exists($module)) {
        // Nothing to upgrade.
        continue;
      }
      $function = $module . '_update_dependencies';
      // Ensure install file is loaded.
      $this->moduleHandler->loadInclude($module, 'install');
      if (function_exists($function)) {
        $updated_dependencies = $function();
        // Each implementation of hook_update_dependencies() returns a
        // multidimensional, associative array containing some keys that
        // represent module names (which are strings) and other keys that
        // represent update function numbers (which are integers). We cannot use
        // array_merge_recursive() to properly merge these results, since it
        // treats strings and integers differently. Therefore, we have to
        // explicitly loop through the expected array structure here and perform
        // the merge manually.
        if (isset($updated_dependencies) && is_array($updated_dependencies)) {
          foreach ($updated_dependencies as $module_name => $module_data) {
            foreach ($module_data as $update_version => $update_data) {
              foreach ($update_data as $module_dependency => $update_dependency) {
                // If there are redundant dependencies declared for the same
                // update function (so that it is declared to depend on more
                // than one update from a particular module), record the
                // dependency on the highest numbered update here, since that
                // automatically implies the previous ones. For example, if one
                // module's implementation of hook_update_dependencies()
                // required this ordering:
                //
                // system_update_8002 ---> user_update_8001
                //
                // but another module's implementation of the hook required this
                // one:
                //
                // system_update_8003 ---> user_update_8001
                //
                // we record the second one, since system_update_8002() is
                // always guaranteed to run before system_update_8003() anyway
                // (with an individual module, updates are always run in
                // numerical order).
                if (!isset($return[$module_name][$update_version][$module_dependency]) || $update_dependency > $return[$module_name][$update_version][$module_dependency]) {
                  $return[$module_name][$update_version][$module_dependency] = $update_dependency;
                }
              }
            }
          }
        }
      }
    }

    return $return;
  }

}
