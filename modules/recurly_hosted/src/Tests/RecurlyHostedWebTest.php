<?php

namespace Drupal\recurly_hosted\Tests;

use Drupal\simpletest\WebTestBase;

/**
 * Tests recurly hosted pages.
 *
 * @group recurly
 */
class RecurlyHostedWebTest extends WebTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['recurly_hosted'];

  /**
   * Test the settings page.
   */
  protected function testAnonymousUpdateBillingAccess() {
    $this->drupalGet('/user/1/subscription/billing');
    $this->assertResponse(403);
  }

}
