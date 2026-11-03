<?php

namespace Drupal\views\Plugin\views\argument;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\views\Attribute\ViewsArgument;

/**
 * Basic argument handler for arguments that are numeric.
 *
 * Incorporates break_phrase.
 *
 * @ingroup views_argument_handlers
 */
#[ViewsArgument(
  id: 'numeric',
)]
class NumericArgument extends ArgumentPluginBase {

  /**
   * The actual value which is used for querying.
   *
   * @var array
   */
  public $value;

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['break_phrase'] = ['default' => FALSE];
    $options['not'] = ['default' => FALSE];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    // Allow '+' for "or". Allow ',' for "and".
    $form['break_phrase'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow multiple values'),
      '#description' => $this->t('If selected, users can enter multiple values in the form of 1+2+3 (for OR) or 1,2,3 (for AND).'),
      '#default_value' => !empty($this->options['break_phrase']),
      '#group' => 'options][more',
    ];

    $form['not'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Exclude'),
      '#description' => $this->t('If selected, the numbers entered for the filter will be excluded rather than limiting the view.'),
      '#default_value' => !empty($this->options['not']),
      '#group' => 'options][more',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function title() {
    if (!$this->argument) {
      return !empty($this->definition['empty field name']) ? $this->definition['empty field name'] : $this->t('Uncategorized');
    }

    if (!empty($this->options['break_phrase'])) {
      $break = static::breakString($this->argument, FALSE);
      $this->value = $break->value;
      $this->operator = $break->operator;
    }
    else {
      $this->value = [$this->argument];
      $this->operator = 'or';
    }

    if (empty($this->value)) {
      return !empty($this->definition['empty field name']) ? $this->definition['empty field name'] : $this->t('Uncategorized');
    }

    if ($this->value === [-1]) {
      return !empty($this->definition['invalid input']) ? $this->definition['invalid input'] : $this->t('Invalid input');
    }

    return implode($this->operator == 'or' ? ' + ' : ', ', $this->titleQuery());
  }

  /**
   * Override for specific title lookups.
   *
   * @return array
   *   Returns all titles, if it's just one title it's an array with one entry.
   */
  public function titleQuery() {
    return $this->value;
  }

  /**
   * {@inheritdoc}
   */
  public function query($group_by = FALSE) {
    $this->ensureMyTable();

    if (!empty($this->options['break_phrase'])) {
      $this->unpackArgumentValue();
    }
    else {
      $this->value = [$this->argument];
    }

    $placeholder = $this->placeholder();
    $null_check = empty($this->options['not']) ? '' : " OR $this->tableAlias.$this->realField IS NULL";

    if (count($this->value) > 1) {
      // Multiple values with 'OR' operator.
      if ($this->operator == 'or') {
        $operator = empty($this->options['not']) ? 'IN' : 'NOT IN';
        $placeholder .= '[]';
        $this->query->addWhereExpression(0, "$this->tableAlias.$this->realField $operator($placeholder)" . $null_check, [$placeholder => $this->value]);
      }
      // Multiple values with 'AND' operator.
      elseif ($this->operator == 'and') {
        $connection = $this->query->getConnection();

        if ($this->options['not']) {
          $subquery_clause = $connection->condition('AND');
          $join = $this->getJoin();

          if (isset($join->configuration['table'], $join->configuration['field'])) {
            $main_table = $join->configuration['table'];
            $main_field = $join->configuration['field'];
            /** @var \Drupal\Core\Database\Query\Select $subquery */
            $subquery = $connection->select($main_table);
            $subquery->addField($main_table, $main_field);
            $count = 0;

            foreach ($this->value as $item_value) {
              $alias = !$count++ ? $main_table : $subquery->leftJoin($main_table, NULL, "%alias.$main_field = $main_table.$main_field");
              $subquery_clause->condition("$alias.$this->realField", $item_value);
            }

            $subquery->condition($subquery_clause);

            // Add to selection the rows that don't have any value.
            $clause = $connection->condition('OR');
            $clause->condition("$this->tableAlias.$main_field", $subquery, 'NOT IN');
            $clause->condition("$this->tableAlias.$this->realField", NULL, 'IS NULL');

            $this->query->addWhere(0, $clause);
          }
        }
        else {
          $clause = $connection->condition('AND');
          $count = 0;

          foreach ($this->value as $item_value) {
            $alias = !$count++ ? $this->tableAlias : $this->query->addTable($this->table);
            $clause->condition("$alias.$this->realField", $item_value);
          }

          $this->query->addWhere(0, $clause);
        }
      }
    }
    // Single value.
    else {
      $operator = empty($this->options['not']) ? '=' : '!=';
      $this->query->addWhereExpression(0, "$this->tableAlias.$this->realField $operator $placeholder" . $null_check, [$placeholder => $this->argument]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getSortName() {
    return $this->t('Numerical', [], ['context' => 'Sort order']);
  }

  /**
   * {@inheritdoc}
   */
  public function getContextDefinition() {
    if ($context_definition = parent::getContextDefinition()) {
      return $context_definition;
    }

    // If the parent does not provide a context definition through the
    // validation plugin, fall back to the integer type.
    return new ContextDefinition('integer', $this->adminLabel(), FALSE);
  }

}
