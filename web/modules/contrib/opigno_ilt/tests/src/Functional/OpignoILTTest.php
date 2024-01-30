<?php

namespace Drupal\Tests\opigno_ilt\Functional;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Url;
use Drupal\opigno_ilt\Entity\ILT;

/**
 * Common tests for Opigno ILT.
 *
 * @group opigno_ilt
 */
class OpignoILTTest extends OpignoILTBrowserTestBase {

  /**
   * Tests ILT access.
   */
  public function testOpignoILTAccess() {
    // Create test training.
    $training = $this->createGroup();
    // Create students.
    $student_1 = $this->drupalCreateUser();
    $student_2 = $this->drupalCreateUser();
    // Add students to training.
    $training->addMember($student_1);
    $training->addMember($student_2);
    $training->save();
    // Training members count.
    $this->assertEquals(3, count($training->getMembers()), 'Training members - a Group admin and two students.');

    // Create ILT.
    $meeting = ILT::create([
      'title' => $this->randomString(),
      'training' => $training->id(),
      'place' => $this->randomString(),
      'date' => $this->createDummyDaterange(),
    ]);
    $meeting->save();

    $ilt_path = Url::fromRoute('entity.opigno_ilt.canonical', [
      'opigno_ilt' => $meeting->id()
    ]);
    // Log out admin.
    $this->drupalLogout();

    // Check access for a student without restriction.
    $this->drupalLogin($student_1);
    $this->drupalGet($ilt_path);
    $this->assertSession()->pageTextContains($meeting->getTitle());
    $this->assertSession()->statusCodeEquals(200, 'Student without restriction has access to ILT');

    // Add only one student to ILT.
    $meeting = ILT::load($meeting->id());
    $meeting->setMembersIds([$student_2->id()]);
    $meeting->save();

    // Check access for a student with restriction.
    $this->drupalGet($ilt_path);
    $this->assertSession()->pageTextNotContains($meeting->getTitle());
    $this->assertSession()->statusCodeEquals(403, 'Student with restriction does not have access to ILT');
  }

  /**
   * Create dummy date ranfge for ILT (interval +1 hour)
   *
   * @return array
   *   Array with Start date and End date.
   */
  protected function createDummyDateRange() {
    $display_format = 'm-d-Y H:i:s';
    $start_date = date($display_format, strtotime("1 hour"));
    $end_date = date($display_format, strtotime("2 hour"));
    $start_date_value = DrupalDateTime::createFromFormat($display_format, $start_date);
    $end_date_value = DrupalDateTime::createFromFormat($display_format, $end_date);
    $date_range = [
      'value' => $start_date_value->setTimezone(new \DateTimeZone(date_default_timezone_get()))
        ->format(DrupalDateTime::FORMAT),
      'end_value' => $end_date_value->setTimezone(new \DateTimeZone(date_default_timezone_get()))
        ->format(DrupalDateTime::FORMAT),
    ];
    return $date_range;
  }

}
