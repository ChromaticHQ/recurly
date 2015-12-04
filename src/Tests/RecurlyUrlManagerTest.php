<?php

/**
 * @file
 * Contains \Drupal\recurly\Tests\RecurlyUrlManager.
 */

namespace Drupal\recurly\Tests;

use Drupal\Tests\UnitTestCase;
use Drupal\recurly\RecurlyUrlManager;

/**
 * Test the Recurly Url Manager.
 *
 * @ingroup recurly
 * @group recurly
 */
class RecurlyUrlManagerTest extends UnitTestCase {

  /**
   * Verify url is being generated correctly.
   */
  public function testHostedUrl() {
    $recurly_url_manager = new RecurlyUrlManager();
    $hosted_url = $recurly_url_manager->hostedUrl('configuration/currencies', 'sub-domain');
    $this->assertEquals($hosted_url, 'https://sub-domain.recurly.com/configuration/currencies');
  }
}
