<?php

namespace Webfactory\Bundle\PolyglotBundle\Tests;

use Webfactory\Bundle\PolyglotBundle\Translatable;

class TranslatableTest extends \PHPUnit_Framework_TestCase
{
    /**
     * System under test.
     *
     * @var Translatable
     */
    private $translation = null;

    /**
     * Initializes the test environment.
     */
    protected function setUp()
    {
        parent::setUp();
        $this->translation = new Translatable('Hallo Welt!', 'de_DE');
        $this->translation->setTranslation('en_US', 'Hello world!');
    }

    /**
     * Cleans up the test environment.
     */
    protected function tearDown()
    {
        $this->translation = null;
        parent::tearDown();
    }

    public function testCountReturnsLengthOfStringInDefaultLocale()
    {
        $this->assertCount(strlen('Hallo Welt!'), $this->translation);
    }
}
