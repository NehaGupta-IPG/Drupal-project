<?php

/**
 * @file
 * Hooks and documentation for installation profiles.
 */

/**
 * @defgroup profile_api Installation profile API
 * @{
 * Installation profiles define the starting configuration of a Drupal site.
 *
 * @section info_yml Profile .info.yml files
 * Each installation profile requires a machine_name.info.yml file in its root
 * directory. Profile .info.yml files use the same base keys as module .info.yml
 * files (see \Drupal\Core\Extension\InfoParserInterface::parse()), plus the
 * profile-specific keys documented below.
 *
 * @subsection required Required keys
 * - name: The human-readable name of the profile. (Required)
 * - type: Must be set to 'profile'. (Required)
 * - core_version_requirement: Specifies the compatible Drupal core version.
 *   (Required)
 *
 * @subsection optional Optional keys
 * - description: A brief description of the profile.
 * - version: The version of the profile.
 * - hidden: Boolean. When TRUE, the profile is not listed on the profile
 *   selection screen during installation.
 * - php: The minimum PHP version required.
 * - dependencies: An array of module machine names that the profile requires.
 *   These modules are automatically installed. See
 *   \Drupal\Core\Extension\InfoParserInterface::parse() for the supported
 *   syntax.
 * - install: An array of additional module machine names to install that are
 *   not hard dependencies of the profile. These modules are installed but the
 *   profile does not declare a dependency on them.
 * - recipes: An array of recipe directory paths, relative to the Drupal root,
 *   to apply during installation.
 * - themes: An array of theme machine names to install. Defaults to ['stark']
 *   if not specified.
 * - keep_english: Boolean. When TRUE, English is kept as an available language
 *   even when another language is selected during installation. Defaults to
 *   FALSE, which removes English when a non-English language is selected.
 * - keep_profile: Boolean. When FALSE, the profile is automatically
 *   uninstalled once the installation is complete. Defaults to TRUE.
 * - opt_out_install: Boolean. When TRUE, the profile is not installed as a
 *   module during the installation process. This allows lightweight profiles
 *   that do not define hooks or custom install logic to skip the module
 *   installation step entirely.
 * - distribution: Denotes that the profile is intended to be used as a
 *   distribution. When this key is present, the profile is auto-selected
 *   during installation and the profile selection screen is skipped. If more
 *   than one distribution profile is found, the first discovered profile is
 *   selected. This key may contain the following subkeys:
 *   - name: The human-readable name of the distribution, shown throughout
 *     the installation process. Defaults to 'Drupal' if not provided.
 *   - langcode: The default language code to use during installation.
 *   - install: Installer customization options:
 *     - theme: The machine name of a theme to use during installation instead
 *       of Drupal's default installer theme.
 *     - finish_url: A path to redirect to after installation completes.
 *
 * Example of a minimal profile .info.yml file:
 * @code
 * name: My Profile
 * type: profile
 * description: 'A custom installation profile.'
 * core_version_requirement: ^11
 * install:
 *   - block
 *   - dblog
 * themes:
 *   - olivero
 * @endcode
 *
 * Example of a distribution profile .info.yml file:
 * @code
 * name: My Distribution
 * type: profile
 * description: 'A complete distribution built on Drupal.'
 * core_version_requirement: ^11
 * distribution:
 *   name: 'My Distribution'
 *   install:
 *     theme: my_install_theme
 *     finish_url: /welcome
 * install:
 *   - block
 *   - node
 * themes:
 *   - my_theme
 * @endcode
 *
 * @see install_profile_info()
 * @see \Drupal\Core\Extension\InfoParserInterface::parse()
 * @see https://www.drupal.org/developing/distributions
 * @}
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Return an array of tasks to be performed by an installation profile.
 *
 * Only procedural implementations are supported for this hook.
 *
 * Any tasks you define here will be run, in order, after the installer has
 * finished the site configuration step but before it has moved on to the
 * final import of languages and the end of the installation. This is invoked
 * by install_tasks(). You can have any number of custom tasks to perform
 * during this phase.
 *
 * Each task you define here corresponds to a callback function which you must
 * separately define and which is called when your task is run. This function
 * will receive the global installation state variable, $install_state, as
 * input, and has the opportunity to access or modify any of its settings. See
 * the install_state_defaults() function in the installer for the list of
 * $install_state settings used by Drupal core.
 *
 * At the end of your task function, you can indicate that you want the
 * installer to pause and display a page to the user by returning any themed
 * output that should be displayed on that page (but see below for tasks that
 * use the form API or batch API; the return values of these task functions are
 * handled differently). You should also use #title within the task
 * callback function to set a custom page title. For some tasks, however, you
 * may want to simply do some processing and pass control to the next task
 * without ending the page request; to indicate this, simply do not send back
 * a return value from your task function at all. This can be used, for
 * example, by installation profiles that need to configure certain site
 * settings in the database without obtaining any input from the user.
 *
 * The task function is treated specially if it defines a form or requires
 * batch processing; in that case, you should return either the form API
 * definition or batch API array, as appropriate. See below for more
 * information on the 'type' key that you must define in the task definition
 * to inform the installer that your task falls into one of those two
 * categories. It is important to use these APIs directly, since the installer
 * may be run non-interactively (for example, via a command line script), all
 * in one page request; in that case, the installer will automatically take
 * care of submitting forms and processing batches correctly for both types of
 * installations. You can inspect the $install_state['interactive'] boolean to
 * see whether or not the current installation is interactive, if you need
 * access to this information.
 *
 * Remember that a user installing Drupal interactively will be able to reload
 * an installation page multiple times, so you should use \Drupal::state() to
 * store any data that you may need later in the installation process. Any
 * temporary state must be removed using \Drupal::state()->delete() before
 * your last task has completed and control is handed back to the installer.
 *
 * @param array $install_state
 *   An array of information about the current installation state.
 *
 * @return array
 *   A keyed array of tasks the profile will perform during the final stage of
 *   the installation. Each key represents the name of a function (usually a
 *   function defined by this profile, although that is not strictly required)
 *   that is called when that task is run. The values are associative arrays
 *   containing the following key-value pairs (all of which are optional):
 *   - display_name: The human-readable name of the task. This will be
 *     displayed to the user while the installer is running, along with a list
 *     of other tasks that are being run. Leave this unset to prevent the task
 *     from appearing in the list.
 *   - display: This is a boolean which can be used to provide finer-grained
 *     control over whether or not the task will display. This is mostly useful
 *     for tasks that are intended to display only under certain conditions;
 *     for these tasks, you can set 'display_name' to the name that you want to
 *     display, but then use this boolean to hide the task only when certain
 *     conditions apply.
 *   - type: A string representing the type of task. This parameter has three
 *     possible values:
 *     - normal: (default) This indicates that the task will be treated as a
 *       regular callback function, which does its processing and optionally
 *       returns HTML output.
 *     - batch: This indicates that the task function will return a batch API
 *       definition suitable for batch_set() or an array of batch definitions
 *       suitable for consecutive batch_set() calls. The installer will then
 *       take care of automatically running the task via batch processing.
 *     - form: This indicates that the task function will return a standard
 *       form API definition (and separately define validation and submit
 *       handlers, as appropriate). The installer will then take care of
 *       automatically directing the user through the form submission process.
 *   - run: A constant representing the manner in which the task will be run.
 *     This parameter has three possible values:
 *     - INSTALL_TASK_RUN_IF_NOT_COMPLETED: (default) This indicates that the
 *       task will run once during the installation of the profile.
 *     - INSTALL_TASK_SKIP: This indicates that the task will not run during
 *       the current installation page request. It can be used to skip running
 *       an installation task when certain conditions are met, even though the
 *       task may still show on the list of installation tasks presented to the
 *       user.
 *     - INSTALL_TASK_RUN_IF_REACHED: This indicates that the task will run on
 *       each installation page request that reaches it. This is rarely
 *       necessary for an installation profile to use; it is primarily used by
 *       the Drupal installer for bootstrap-related tasks.
 *   - function: Normally this does not need to be set, but it can be used to
 *     force the installer to call a different function when the task is run
 *     (rather than the function whose name is given by the array key). This
 *     could be used, for example, to allow the same function to be called by
 *     two different tasks.
 *
 * @see install_state_defaults()
 * @see batch_set()
 * @see hook_install_tasks_alter()
 * @see install_tasks()
 */
function hook_install_tasks(&$install_state) {
  // Here, we define a variable to allow tasks to indicate that a particular,
  // processor-intensive batch process needs to be triggered later on in the
  // installation.
  $my_profile_needs_batch_processing = \Drupal::state()->get('my_profile.needs_batch_processing', FALSE);
  $tasks = [
    // This is an example of a task that defines a form which the user who is
    // installing the site will be asked to fill out. To implement this task,
    // your profile would define a function named my_profile_data_import_form()
    // as a normal form API callback function, with associated validation and
    // submit handlers. In the submit handler, in addition to saving whatever
    // other data you have collected from the user, you might also call
    // \Drupal::state()->set('my_profile.needs_batch_processing', TRUE) if the
    // user has entered data which requires that batch processing will need to
    // occur later on.
    'my_profile_data_import_form' => [
      'display_name' => t('Data import options'),
      'type' => 'form',
    ],
    // Similarly, to implement this task, your profile would define a function
    // named my_profile_settings_form() with associated validation and submit
    // handlers. This form might be used to collect and save additional
    // information from the user that your profile needs. There are no extra
    // steps required for your profile to act as an "installation wizard"; you
    // can simply define as many tasks of type 'form' as you wish to execute,
    // and the forms will be presented to the user, one after another.
    'my_profile_settings_form' => [
      'display_name' => t('Additional options'),
      'type' => 'form',
    ],
    // This is an example of a task that performs batch operations. To
    // implement this task, your profile would define a function named
    // my_profile_batch_processing() which returns a batch API array definition
    // that the installer will use to execute your batch operations. Due to the
    // 'my_profile.needs_batch_processing' variable used here, this task will be
    // hidden and skipped unless your profile set it to TRUE in one of the
    // previous tasks.
    'my_profile_batch_processing' => [
      'display_name' => t('Import additional data'),
      'display' => $my_profile_needs_batch_processing,
      'type' => 'batch',
      'run' => $my_profile_needs_batch_processing ? INSTALL_TASK_RUN_IF_NOT_COMPLETED : INSTALL_TASK_SKIP,
    ],
    // This is an example of a task that will not be displayed in the list that
    // the user sees. To implement this task, your profile would define a
    // function named my_profile_final_site_setup(), in which additional,
    // automated site setup operations would be performed. Since this is the
    // last task defined by your profile, you should also use this function to
    // call \Drupal::state()->delete('my_profile.needs_batch_processing') and
    // clean up the state that was used above. If you want the user to pass
    // to the final Drupal installation tasks uninterrupted, return no output
    // from this function. Otherwise, return themed output that the user will
    // see (for example, a confirmation page explaining that your profile's
    // tasks are complete, with a link to reload the current page and therefore
    // pass on to the final Drupal installation tasks when the user is ready to
    // do so).
    'my_profile_final_site_setup' => [],
  ];
  return $tasks;
}

/**
 * Alter the full list of installation tasks.
 *
 * Only procedural implementations are supported for this hook.
 *
 * You can use this hook to change or replace any part of the Drupal
 * installation process that occurs after the installation profile is selected.
 *
 * This hook is invoked on the install profile in install_tasks().
 *
 * @param string[] $tasks
 *   An array of all available installation tasks, including those provided by
 *   Drupal core. You can modify this array to change or replace individual
 *   steps within the installation process.
 * @param array $install_state
 *   An array of information about the current installation state.
 *
 * @see hook_install_tasks()
 * @see install_tasks()
 */
function hook_install_tasks_alter(&$tasks, $install_state) {
  // Replace the entire site configuration form provided by Drupal core
  // with a custom callback function defined by this installation profile.
  $tasks['install_configure_form']['function'] = 'my_profile_install_configure_form';
}

/**
 * @} End of "addtogroup hooks".
 */
