<?php

namespace Drupal\views_daterange_filters\Plugin\views\filter;

use Drupal\Component\Datetime\DateTimePlus;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\datetime\Plugin\views\filter\Date;

/**
 * Date/time views filter.
 *
 * Extend Date filter to include date range operations.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("views_daterange_filters_daterange")
 */
class ViewsDaterangeFiltersDateRange extends Date implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   *
   * @return array
   *   Array of operators.
   */
  public function operators() {
    $operators = parent::operators();
    $operators['includes'] = [
      'title' => $this->t('Includes'),
      'method' => 'opIncludes',
      'short' => $this->t('includes'),
      'values' => 1,
    ];
    $operators['overlaps'] = [
      'title' => $this->t('Overlaps'),
      'method' => 'opOverlaps',
      'short' => $this->t('within'),
      'values' => 2,
    ];
    $operators['ends_by'] = [
      'title' => $this->t('Ends by'),
      'method' => 'opEndsBy',
      'short' => $this->t('Ends by'),
      'values' => 1,
    ];

    return $operators;
  }

  /**
   * Filters by operator Includes.
   *
   * @param mixed $field
   *   The field.
   */
  protected function opIncludes($field) {
    $end_field = substr($field, 0, -6) . '_end_value';

    $timezone = $this->getTimezone();
    $origin_offset = $this->getOffset($this->value['value'], $timezone);

    // Convert to ISO. UTC timezone is used since dates are stored in UTC.
    $value = new DateTimePlus($this->value['value'], new \DateTimeZone($timezone));
    $value = $this->query->getDateFormat($this->query->getDateField("'" . $this->dateFormatter->format($value->getTimestamp() + $origin_offset, 'custom', DateTimeItemInterface::DATETIME_STORAGE_FORMAT, DateTimeItemInterface::STORAGE_TIMEZONE) . "'", TRUE, $this->calculateOffset), $this->dateFormat, TRUE);

    $field = $this->query->getDateFormat($this->query->getDateField($field, TRUE, $this->calculateOffset), $this->dateFormat, TRUE);
    $end_field = $this->query->getDateFormat($this->query->getDateField($end_field, TRUE, $this->calculateOffset), $this->dateFormat, TRUE);

    $this->query->addWhereExpression($this->options['group'], "$value BETWEEN $field AND $end_field");
  }

  /**
   * Filters by operator Overlaps.
   *
   * @param object $field
   *   The views field.
   */
  protected function opOverlaps($field) {
    $end_field = substr($field, 0, -6) . '_end_value';

    $timezone = $this->getTimezone();
    $origin_offset = $this->getOffset($this->value['min'], $timezone);

    // Although both 'min' and 'max' values are required, default empty 'min'
    // value as UNIX timestamp 0.
    $min = (!empty($this->value['min'])) ? $this->value['min'] : '@0';

    // Convert to ISO format and format for query. UTC timezone is used since
    // dates are stored in UTC.
    $a = new DateTimePlus($min, new \DateTimeZone($timezone));
    $a = $this->query->getDateFormat($this->query->getDateField("'" . $this->dateFormatter->format($a->getTimestamp() + $origin_offset, 'custom', DateTimeItemInterface::DATETIME_STORAGE_FORMAT, DateTimeItemInterface::STORAGE_TIMEZONE) . "'", TRUE, $this->calculateOffset), $this->dateFormat, TRUE);
    $b = new DateTimePlus($this->value['max'], new \DateTimeZone($timezone));
    $b = $this->query->getDateFormat($this->query->getDateField("'" . $this->dateFormatter->format($b->getTimestamp() + $origin_offset, 'custom', DateTimeItemInterface::DATETIME_STORAGE_FORMAT, DateTimeItemInterface::STORAGE_TIMEZONE) . "'", TRUE, $this->calculateOffset), $this->dateFormat, TRUE);

    $field = $this->query->getDateFormat($this->query->getDateField($field, TRUE, $this->calculateOffset), $this->dateFormat, TRUE);
    $end_field = $this->query->getDateFormat($this->query->getDateField($end_field, TRUE, $this->calculateOffset), $this->dateFormat, TRUE);
    $this->query->addWhereExpression($this->options['group'], "$a <= $end_field AND $b >= $field");
  }

  /**
   * Filters by operator Ends By.
   *
   * @param mixed $field
   *   The field.
   */
  protected function opEndsBy($field) {
    $end_field = substr($field, 0, -6) . '_end_value';

    $timezone = $this->getTimezone();
    $origin_offset = $this->getOffset($this->value['value'], $timezone);

    // Convert to ISO. UTC timezone is used since dates are stored in UTC.
    $value = new DateTimePlus($this->value['value'], new \DateTimeZone($timezone));
    $value = $this->query->getDateFormat($this->query->getDateField("'" . $this->dateFormatter->format($value->getTimestamp() + $origin_offset, 'custom', DateTimeItemInterface::DATETIME_STORAGE_FORMAT, DateTimeItemInterface::STORAGE_TIMEZONE) . "'", TRUE, $this->calculateOffset), $this->dateFormat, TRUE);

    $end_field = $this->query->getDateFormat($this->query->getDateField($end_field, TRUE, $this->calculateOffset), $this->dateFormat, TRUE);

    $this->query->addWhereExpression($this->options['group'], "$end_field <= $value");
  }

}
