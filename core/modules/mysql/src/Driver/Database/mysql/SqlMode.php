<?php

namespace Drupal\mysql\Driver\Database\mysql;

/**
 * @addtogroup database
 * @{
 */

/**
 * SQL Mode Settings that are relevant to Drupal's operation.
 *
 * @link https://dev.mysql.com/doc/refman/8.0/en/sql-mode.html
 * @link https://mariadb.com/kb/en/sql-mode/
 */
class SqlMode {

  /**
   * ANSI changes the SQL syntax to be closer to ANSI SQL.
   *
   * This is a meta-mode that sets a certain set of other SQL modes
   * When selected. Drupal enables ANSI mode by default. The
   * following modes are set when ANSI is selected:
   *
   *   ANSI_QUOTES
   *   IGNORE_SPACE
   *   ONLY_FULL_GROUP_BY (Included by Mysql, but not Mariadb.)
   *   PIPES_AS_CONCAT
   *   REAL_AS_FLOAT
   *
   * In order to disable one of these modes, it is necessary to
   * set ANSI mode to FALSE, and then re-enable the desired modes.
   * For example, to disable REAL_AS_FLOAT:
   *
   *   $databases['default']['default']['sql_mode_options'] = [
   *     SqlMode::ANSI => FALSE,
   *     'ANSI_QUOTES' => TRUE,
   *     'IGNORE_SPACE' => TRUE,
   *     'ONLY_FULL_GROUP_BY' => TRUE,
   *     'PIPES_AS_CONCAT' => TRUE,
   *   ];
   */
  const ANSI = "ANSI";

  /**
   * TRADITIONAL makes MySQL behave like a “traditional” SQL database system.
   *
   * This is a meta-mode that sets a certain set of other SQL modes
   * when selected. Drupal enables TRADITIONAL mode by default. The
   * following modes are set when TRADITIONAL is selected:
   *
   *   ERROR_FOR_DIVISION_BY_ZERO
   *   NO_ENGINE_SUBSTITUTION
   *   NO_ZERO_DATE
   *   NO_ZERO_IN_DATE
   *   STRICT_ALL_TABLES
   *   STRICT_TRANS_TABLES
   *
   * As is the case with the ANSI mode, in order to disable just one of
   * these modes, it is necessary to set TRADITIONAL to FALSE, and then
   * re-enable the desired modes.
   */
  const TRADITIONAL = "TRADITIONAL";

}

/**
 * @} End of "addtogroup database".
 */
