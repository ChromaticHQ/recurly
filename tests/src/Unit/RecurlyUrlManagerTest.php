<?php

namespace Drupal\Tests\recurly\Unit;

use Drupal\recurly\RecurlyUrlManager;
use Drupal\Tests\UnitTestCase;

/**
 * Test the Recurly Url Manager.
 *
 * @coversDefaultClass \Drupal\recurly\RecurlyUrlManager
 * @group recurly
 */
class RecurlyUrlManagerTest extends UnitTestCase {

  /**
   * The url manager to be tested.
   *
   * @var \Drupal\recurly\RecurlyUrlManager
   */
  protected $recurlyUrlManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    $config_factory = $this->getConfigFactoryStub(['recurly.settings' => ['verbose' => TRUE]]);

    $this->recurlyUrlManager = new RecurlyUrlManager($config_factory);
  }

  /**
   * Verify url is being generated correctly.
   *
   * @covers ::hostedUrl
   */
  public function testHostedUrl() {
    $hosted_url = $this->recurlyUrlManager->hostedUrl('configuration/currencies', 'sub-domain')->getUri();
    $this->assertEquals($hosted_url, 'https://sub-domain.recurly.com/configuration/currencies');
  }

}
