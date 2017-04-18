<?php

namespace Drupal\recurly\Tests;

use Drupal\simpletest\WebTestBase;

/**
 * Tests recurly subscription select plan form.
 *
 * @group recurly
 */
class RecurlySubscriptionSelectPlanControllerWebTest extends WebTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['recurly'];

  /**
   * Test the settings page.
   */
  protected function testAnonymousPlanAccess() {
    // With no plans configured, access should be denied.
    $this->drupalGet('/subscription/signup');
    $this->assertResponse(403);
    $this->drupalGet('/subscription/register');
    $this->assertResponse(403);
  }

}
