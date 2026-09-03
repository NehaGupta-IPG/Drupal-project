<?php

declare(strict_types=1);

namespace Drupal\error_test\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\Database\SqlExecutionInfo;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller routines for error_test routes.
 */
class ErrorTestController extends ControllerBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a \Drupal\error_test\Controller\ErrorTestController object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database')
    );
  }

  /**
   * Generate warnings to test the error handler.
   */
  public function generateWarnings($collect_errors = FALSE) {
    // Tell Drupal error reporter to collect test errors or not.
    define('SIMPLETEST_COLLECT_ERRORS', $collect_errors);
    // This will generate a notice.
    $notice = new \stdClass();
    $notice == 1 ? 1 : 0;
    // This will generate a warning.
    $obj = new \stdClass();
    $obj->p =& $obj;
    var_export($obj, TRUE);
    // This will generate a user error. Use & to check for double escaping.
    trigger_error("Drupal & awesome", E_USER_WARNING);
    return [];
  }

  /**
   * Generate fatal errors to test the error handler.
   */
  public function generateFatalErrors() {
    $function = function (array $test) {
    };
    // Use an incorrect parameter type, string, for testing a fatal error.
    $function("test-string");
    return [];
  }

  /**
   * Trigger an exception to test the exception handler.
   *
   * @param string $argument
   *   A function argument which will be included in the exception backtrace.
   *
   * @throws \Exception
   */
  public function triggerException(string $argument = "<script>alert('xss')</script>"): void {
    define('SIMPLETEST_COLLECT_ERRORS', FALSE);
    // Add function arguments to the exception backtrace.
    ini_set('zend.exception_ignore_args', FALSE);
    ini_set('zend.exception_string_param_max_len', 1024);
    throw new \Exception("Drupal & awesome");
  }

  /**
   * Trigger an exception to test the PDO exception handler.
   */
  public function triggerPDOException(): void {
    define('SIMPLETEST_COLLECT_ERRORS', FALSE);
    $this->database->select('bananas_are_awesome', 'b')
      ->fields('b')
      ->condition('palm_height', 180, '>')
      ->execute();
  }

  /**
   * Trigger a database exception carrying markup in the message and the values.
   *
   * The exception is built here instead of being provoked with a real failing
   * query, because the drivers do not agree on what a message contains. MySQL
   * reports "Duplicate entry '<value>' for key ..." for a unique violation,
   * while SQLite reports only the table and column names. The escaping this
   * covers happens when the error page is built, so the message is crafted.
   *
   * @throws \Drupal\Core\Database\DatabaseExceptionWrapper
   */
  public function triggerEscapedDatabaseException(): void {
    define('SIMPLETEST_COLLECT_ERRORS', FALSE);
    $payload = "<script>alert('xss')</script>";
    $info = new SqlExecutionInfo(
      'INSERT INTO {error_test} (name) VALUES (:db_insert_placeholder_0)',
      [':db_insert_placeholder_0' => $payload],
    );
    throw new DatabaseExceptionWrapper(
      $info->appendToMessage("SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '$payload' for key 'PRIMARY'"),
      0,
      NULL,
      $info,
    );
  }

  /**
   * Trigger an exception during rendering.
   */
  public function triggerRendererException() {
    return [
      '#type' => 'page',
      '#post_render' => [
        function () {
          throw new \Exception('This is an exception that occurs during rendering');
        },
      ],
    ];
  }

}
