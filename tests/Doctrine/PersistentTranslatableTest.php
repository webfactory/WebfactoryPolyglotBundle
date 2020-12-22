<?php

namespace Webfactory\Bundle\PolyglotBundle\Tests\Doctrine;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\ErrorHandler\BufferingLogger;
use Webfactory\Bundle\PolyglotBundle\Doctrine\PersistentTranslatable;
use Webfactory\Bundle\PolyglotBundle\Locale\DefaultLocaleProvider;
use Webfactory\Bundle\PolyglotBundle\Tests\TestEntity;

class PersistentTranslatableTest extends \PHPUnit_Framework_TestCase
{

    public function testToStringReturnsTranslatedMessage()
    {
        $entity = new TestEntity('foo');
        $proxy = $this->createProxy($entity);

        // Simulate some translations.
        $proxy->setTranslation('bar', 'de');
        $proxy->setTranslation('bar in en', 'en');

        $translation = (string)$proxy;

        $this->assertEquals('bar', $translation);
    }

    public function testToStringReturnsStringIfExceptionOccurredAndNoLoggerIsAvailable()
    {
        $entity = new TestEntity('foo');
        $proxy = $this->createProxy($entity);
        $this->breakEntity($entity);
        $translation = (string)$proxy;

        $this->assertInternalType('string', $translation);
    }

    public function testToStringReturnsStringIfExceptionOccurredAndLoggerIsAvailable()
    {
        $entity = new TestEntity('foo');
        $proxy = $this->createProxy($entity, new NullLogger());
        $this->breakEntity($entity);
        $translation = (string)$proxy;

        $this->assertInternalType('string', $translation);
    }

    public function testToStringLogsExceptionIfLoggerIsAvailable()
    {
        $entity = new TestEntity('foo');

        $logger = new BufferingLogger();
        $proxy = $this->createProxy($entity, $logger);
        $this->breakEntity($entity);

        $proxy->__toString();

        $this->assertGreaterThan(0, count($logger->cleanLogs()), 'Expected at least one log message');
    }

    public function testLoggedMessageContainsInformationAboutTranslatedProperty()
    {
        $entity = new TestEntity('foo');

        $logger = new BufferingLogger();
        $proxy = $this->createProxy($entity, $logger);
        $this->breakEntity($entity);

        $proxy->__toString();

        $logs = $logger->cleanLogs();
        $logEntry = current($logs);
        $this->assertInternalType('array', $logEntry);
        $this->assertArrayHasKey(1, $logEntry, 'Missing log message.');
        $logMessage = $logEntry[1];
        $this->assertContains('TestEntity', $logMessage, 'Missing entity class name.');
        $this->assertContains('text', $logMessage, 'Missing translated property.');
        $this->assertContains('de', $logMessage, 'Missing locale.');
    }

    public function testLoggedMessageContainsOriginalException()
    {
        $entity = new TestEntity('foo');

        $logger = new BufferingLogger();
        $proxy = $this->createProxy($entity, $logger);
        $this->breakEntity($entity);

        $proxy->__toString();

        $logs = $logger->cleanLogs();
        $logEntry = current($logs);
        $this->assertInternalType('array', $logEntry);
        $this->assertArrayHasKey(1, $logEntry, 'Missing log message.');
        $logMessage = $logEntry[1];
        $this->assertContains('Cannot find translations', $logMessage, 'Original exception not contained.');
    }

    /** @test */
    public function isTranslatedInto_returns_true_for_primary_translation_if_set()
    {
        $entity = new TestEntity('foo');
        $proxy = $this->createProxy($entity);
        $proxy->setTranslation('bar', 'de');
        $proxy->setTranslation('bar in en', 'en');

        $this->assertTrue($proxy->isTranslatedInto('en'));
    }

    /** @test */
    public function isTranslatedInto_returns_true_for_translation_if_set()
    {
        $entity = new TestEntity('foo');
        $proxy = $this->createProxy($entity);
        $proxy->setTranslation('bar', 'de');
        $proxy->setTranslation('bar in en', 'en');

        $this->assertTrue($proxy->isTranslatedInto('de'));
    }

    /** @test */
    public function isTranslatedInto_returns_false_if_primary_translation_is_empty()
    {
        $entity = new TestEntity('foo');
        $proxy = $this->createProxy($entity);
        $proxy->setTranslation('bar', 'de');
        $proxy->setTranslation('', 'en');

        $isTranslatedInto = $proxy->isTranslatedInto('en');
        $this->assertFalse($isTranslatedInto);
    }

    /** @test */
    public function isTranslatedInto_returns_false_if_translation_is_not_set()
    {
        $entity = new TestEntity('foo');
        $proxy = $this->createProxy($entity);
        $proxy->setTranslation('bar', 'de');
        $proxy->setTranslation('bar in en', 'en');

        $this->assertFalse($proxy->isTranslatedInto('fr'));
    }

    /**
     * @param TestEntity $entity
     * @param LoggerInterface|null $logger
     *
     * @return PersistentTranslatable
     */
    private function createProxy(TestEntity $entity, LoggerInterface $logger = null)
    {
        $localeProvider = new DefaultLocaleProvider();
        $localeProvider->setDefaultLocale('de');

        // We need a translation class without required constructor parameters.
        $translationClass = 'Webfactory\Bundle\PolyglotBundle\Tests\TestEntityTranslation';
        $proxy = new PersistentTranslatable(
            $entity,
            'en',
            $localeProvider,
            $this->makeAccessible(new \ReflectionProperty($translationClass, 'text')),
            $this->makeAccessible(new \ReflectionProperty($entity, 'translations')),
            new \ReflectionClass($translationClass),
            $this->makeAccessible(new \ReflectionProperty($translationClass, 'locale')),
            $this->makeAccessible(new \ReflectionProperty($translationClass, 'entity')),
            $logger
        );
        return $proxy;
    }

    /**
     * @param TestEntity $entity
     */
    private function breakEntity(TestEntity $entity)
    {
        $brokenCollection = $this->getMockBuilder('Doctrine\Common\Collections\ArrayCollection')->getMock();
        $brokenCollection->expects($this->any())
            ->method('matching')
            ->will($this->throwException(new \RuntimeException('Cannot find translations')));
        $property = new \ReflectionProperty($entity, 'translations');
        $property->setAccessible(true);
        $property->setValue($entity, $brokenCollection);
    }

    /**
     * @param \ReflectionProperty $property
     * @return \ReflectionProperty
     */
    private function makeAccessible(\ReflectionProperty $property)
    {
        $property->setAccessible(true);
        return $property;
    }
}
