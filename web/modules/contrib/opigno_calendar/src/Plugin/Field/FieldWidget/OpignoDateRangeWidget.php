<?php

namespace Drupal\opigno_calendar\Plugin\Field\FieldWidget;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;
use Drupal\datetime_range\Plugin\Field\FieldType\DateRangeItem;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Drupal\datetime_range\Plugin\Field\FieldWidget\DateRangeDefaultWidget;

/**
 * Plugin implementation of the 'opigno_daterange' widget.
 *
 * @FieldWidget(
 *   id = "opigno_daterange",
 *   label = @Translation("Opigno date and time range"),
 *   field_types = {
 *     "daterange"
 *   }
 * )
 */
class OpignoDateRangeWidget extends DateRangeDefaultWidget {

  /**
   * {@inheritdoc}
   */
  public function getDatePattern() {
    return \Drupal::config('core.date_format.datepicker')->get('pattern');
  }

  /**
   * Matches each symbol of PHP date format standard
   * with jQuery equivalent codeword
   */
  private function dateformatPhpToJqueryUi($php_format) {
    $symbols_matching = [
      // Day
      'd' => 'dd',
      'D' => 'D',
      'j' => 'd',
      'l' => 'DD',
      'N' => '',
      'S' => '',
      'w' => '',
      'z' => 'o',
      // Week
      'W' => '',
      // Month
      'F' => 'MM',
      'm' => 'mm',
      'M' => 'M',
      'n' => 'm',
      't' => '',
      // Year
      'L' => '',
      'o' => '',
      'Y' => 'yy',
      'y' => 'y',
      // Time
      'a' => '',
      'A' => '',
      'B' => '',
      'g' => '',
      'G' => '',
      'h' => '',
      'H' => '',
      'i' => '',
      's' => '',
      'u' => '',
    ];
    $jqueryui_format = "";
    $escaping = FALSE;
    for ($i = 0; $i < strlen($php_format); $i++) {
      $char = $php_format[$i];
      if ($char === '\\') // PHP date format escaping character
      {
        $i++;
        if ($escaping) {
          $jqueryui_format .= $php_format[$i];
        }
        else {
          $jqueryui_format .= '\'' . $php_format[$i];
        }
        $escaping = TRUE;
      }
      else {
        if ($escaping) {
          $jqueryui_format .= "'";
          $escaping = FALSE;
        }
        if (isset($symbols_matching[$char])) {
          $jqueryui_format .= $symbols_matching[$char];
        }
        else {
          $jqueryui_format .= $char;
        }
      }
    }
    return $jqueryui_format;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    $element['end_value'] = [
        '#title' => $this->t('End date'),
      ] + $element['value'];

    if ($items[$delta]->value) {
      /** @var \Drupal\Core\Datetime\DrupalDateTime $start_date */
      $start_date = $items[$delta]->value;
      $start_date = new DrupalDateTime($start_date);
      $element['value']['#default_value'] = $this->createDefaultValue($start_date, $element['value']['#date_timezone']);
    }

    if ($items[$delta]->end_value) {
      /** @var \Drupal\Core\Datetime\DrupalDateTime $end_date */
      $end_date = $items[$delta]->end_value;
      $end_date = new DrupalDateTime($end_date);
      $element['end_value']['#default_value'] = $this->createDefaultValue($end_date, $element['end_value']['#date_timezone']);
    }

    return $element;
  }

  /**
   * Creates datetime from components.
   *
   * @param array $wrapper
   *   Datetime field wrapper.
   * @param string $pattern
   *   Date pattern.
   *
   * @return \Drupal\Core\Datetime\DrupalDateTime
   *   Datetime object.
   *
   * @throws \Exception
   */
  public static function createDateTimeFromWrapper(array $wrapper, $pattern = NULL) {
    $display_format = !empty($pattern) ? "${pattern} H:i:s" : 'm/d/Y H:i:s';

    $raw_date = $wrapper['date'];
    $raw_hours = $wrapper['hours'];
    $raw_minutes = $wrapper['minutes'];

    $date_str = "$raw_date 00:00:00";
    $time_str = "PT${raw_hours}H${raw_minutes}M";

    $date = DrupalDateTime::createFromFormat($display_format, $date_str);
    $date->add(new \DateInterval($time_str));

    return $date;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    if (!empty($form_state->getErrors())) {
      return $values;
    }

    $storage_timezone = new \DateTimeZone('UTC');
    $storage_format = 'Y-m-d\TH:i:s';
    $pattern = $this->getDatePattern();

    foreach ($values as &$item) {
      if (!empty($item['value_wrapper']['date'])) {
        $date = static::createDateTimeFromWrapper($item['value_wrapper'], $pattern);
        $item['value'] = $date
          ->setTimezone($storage_timezone)
          ->format($storage_format);
        unset($item['value_wrapper']);
      }

      if (!empty($item['end_value_wrapper']['date'])) {
        $end_date = static::createDateTimeFromWrapper($item['end_value_wrapper'], $pattern);
        $item['end_value'] = $end_date
          ->setTimezone($storage_timezone)
          ->format($storage_format);
        unset($item['end_value_wrapper']);
      }
    }
    return $values;
  }

  /**
   * Creates a date object for use as a default value.
   *
   * This will take a default value, apply the proper timezone for display in
   * a widget, and set the default time for date-only fields.
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime $date
   *   The UTC default date.
   * @param string $timezone
   *   The timezone to apply.
   *
   * @return \Drupal\Core\Datetime\DrupalDateTime
   *   A date object for use as a default value in a field widget.
   */
//  protected function createDefaultValue(DrupalDateTime $date, $timezone) {
//    // The date was created and verified during field_load(), so it is safe to
//    // use without further inspection.
//    if ($this->getFieldSetting('datetime_type') === DateTimeItem::DATETIME_TYPE_DATE) {
//      $date->setDefaultDateTime();
//    }
//    $date->setTimezone(new \DateTimeZone($timezone));
//    return $date;
//  }

  /**
   * Validate the color text field.
   */
  public static function validateDate($element, FormStateInterface $form_state) {
    $value = $element['#value'];
    if (!preg_match('/^\d{2}\/\d{2}\/\d{4}$/i', $value)) {
      $form_state->setError($element, t('The date should be in the mm/dd/yyyy format.'));
    }
  }

}
