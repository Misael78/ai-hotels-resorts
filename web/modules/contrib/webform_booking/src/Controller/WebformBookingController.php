<?php

namespace Drupal\webform_booking\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\webform\Entity\Webform;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Controller for Webform Booking.
 */
class WebformBookingController extends ControllerBase {

  /**
   * Get available days.
   */
  public function getAvailableDays($webform_id, $element_id, $date) {
    $webform = Webform::load($webform_id);
    $elements = $webform->getElementsDecodedAndFlattened();
    $config = $elements[$element_id];

    $startDate = isset($config['#start_date']) ? new \DateTime($config['#start_date']) : new \DateTime();
    $endDate = isset($config['#end_date']) ? new \DateTime($config['#end_date']) : (new \DateTime())->modify('+10 years');
    $exclusionPatterns = explode("\n", (string) ($config['#exclusion_dates'] ?? ''));
    $excludedWeekdays = $config['#excluded_weekdays'] ?? [];

    // Prepare the exclusion dates, including intervals.
    $exclusionDates = [];
    foreach ($exclusionPatterns as $pattern) {
      if (strpos($pattern, '|') !== FALSE) {
        [$start, $end] = explode('|', $pattern);
        $period = new \DatePeriod(
          new \DateTime($start),
          new \DateInterval('P1D'),
          (new \DateTime($end))->modify('+1 day')
        );
        foreach ($period as $d) {
          $exclusionDates[] = $d->format('Y-m-d');
        }
      }
      else {
        $exclusionDates[] = $pattern;
      }
    }

    // Parse the input date to get the start and end dates of its month.
    $inputDate = new \DateTime($date);
    $startOfMonth = $inputDate->modify('first day of this month')->setTime(0, 0, 0);
    $endOfMonth = (clone $startOfMonth)->modify('last day of this month')->setTime(23, 59, 59);

    // Ensure start and end dates are within the defined range.
    $actualStartDate = $startDate > $startOfMonth ? $startDate : $startOfMonth;
    $actualEndDate = $endDate < $endOfMonth ? $endDate : $endOfMonth;

    // Adjusting end date based on 'days_advance' if set.
    if (isset($config['#days_advance']) && is_numeric($config['#days_advance'])) {
      $advanceDate = (new \DateTime())->modify('+' . $config['#days_advance'] . ' days');
      if ($actualStartDate < $advanceDate) {
        $actualStartDate = $advanceDate;
      }
    }

    // Adjusting end date based on 'days_visible' if set.
    if (isset($config['#days_visible']) && is_numeric($config['#days_visible'])) {
      $visibleDate = (new \DateTime())->modify('+' . $config['#days_visible'] . ' days');
      if ($actualEndDate > $visibleDate) {
        $actualEndDate = $visibleDate;
      }
    }

    $interval = new \DateInterval('P1D');
    $dateRange = new \DatePeriod($actualStartDate, $interval, $actualEndDate);

    $days = [];

    foreach ($dateRange as $day) {
      $formattedDay = $day->format('Y-m-d');
      $today = new \DateTime();
      $weekday = $day->format('D');
      $formattedToday = $today->format('Y-m-d');

      if ($formattedDay >= $formattedToday && !in_array($formattedDay, $exclusionDates) && (!isset($excludedWeekdays[$weekday]) || $excludedWeekdays[$weekday] === 0)) {
        $days[] = $formattedDay;
      }
    }

    return new JsonResponse($days);
  }

  /**
   * Get available slots.
   */
  public function getAvailableSlots($webform_id, $element_id, $date) {
    $webform = Webform::load($webform_id);
    $elements = $webform->getElementsDecodedAndFlattened();
    $config = $elements[$element_id];
    $timeIntervals = explode("\n", $config['#time_interval']);
    $slotDuration = $config['#slot_duration'];
    $seats = $config['#seats_slot'] ?? 1;

    $availableSlots = [];
    $dateObj = new \DateTime($date);
    $dayOfWeek = $dateObj->format('l');

    // Separate intervals into specific days and regular.
    $specificDayIntervals = [];
    $regularIntervals = [];
    foreach ($timeIntervals as $interval) {
      if (strpos($interval, '(') !== FALSE) {
        [$timeInterval, $day] = explode('(', $interval);
        $day = rtrim($day, ')');
        $specificDayIntervals[$day][] = $timeInterval;
      }
      else {
        $regularIntervals[] = $interval;
      }
    }

    // Function to add slots.
    $addSlots = function ($intervals) use (&$availableSlots, $dateObj, $slotDuration, $webform_id, $element_id, $seats) {
      foreach ($intervals as $interval) {
        [$startTimeStr, $endTimeStr] = explode('|', $interval);
        $startTime = new \DateTime($startTimeStr);
        $endTime = new \DateTime($endTimeStr);

        $currentTime = clone $startTime;

        while ($currentTime < $endTime) {
          $endSlotTime = clone $currentTime;
          $endSlotTime->add(new \DateInterval('PT' . $slotDuration . 'M'));
          if ($endSlotTime > $endTime) {
            break;
          }
          $timeRange = $currentTime->format('H:i') . '-' . $endSlotTime->format('H:i');
          $slot = $dateObj->format('Y-m-d') . ' ' . $currentTime->format('H:i');
          $slotStatus = $this->isSlotSelected($webform_id, $element_id, $slot, $seats) ? 'unavailable' : 'available';
          $availableSlots[] = ['time' => $timeRange, 'status' => $slotStatus];
          $currentTime = clone $endSlotTime;
        }
      }
    };

    // Process specific day intervals.
    if (array_key_exists($dayOfWeek, $specificDayIntervals)) {
      $addSlots($specificDayIntervals[$dayOfWeek]);
    }
    else {
      // If no specific intervals for this day, use regular intervals.
      $addSlots($regularIntervals);
    }

    return new JsonResponse($availableSlots);
  }

  /**
   * Check if the slot is booked.
   */
  protected function isSlotSelected($webform_id, $element_id, $slot, $seats) {
    $query = \Drupal::service('database')
      ->select('webform_submission_data', 'wsd')
      ->fields('wsd', ['sid'])
      ->condition('wsd.webform_id', $webform_id, '=')
      ->condition('wsd.name', $element_id, '=')
      ->condition('wsd.value', $slot, '=')
      ->execute();
    $count = count($query->fetchAll(\PDO::FETCH_COLUMN));

    if ($count < $seats) {
      return FALSE;
    }
    return TRUE;
  }

}
