<?php

namespace Drupal\recurly\Tests;

use Drupal\recurly\RecurlyClient;
use Drupal\simpletest\WebTestBase;

/**
 * Tests recurly subscription plans form.
 *
 * @group recurly
 */
class RecurlySubscriptionPlansFormWebTest extends WebTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['recurly'];

  /**
   * Admin user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $permissions = [
      'administer recurly',
    ];
    $this->adminUser = $this->createUser($permissions);
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Test settings form submission.
   */
  protected function testPlanFormWithoutCredentials() {
    $this->drupalGet('/admin/config/services/recurly/subscription-plans');
    $this->assertResponse(200);
    $this->assertText(RecurlyClient::ERROR_MESSAGE_MISSING_API_KEY);
  }

}
