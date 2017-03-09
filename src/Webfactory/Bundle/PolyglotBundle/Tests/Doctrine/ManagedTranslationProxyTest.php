<?php

namespace Webfactory\Bundle\PolyglotBundle\Tests\Doctrine;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Debug\BufferingLogger;
use Webfactory\Bundle\PolyglotBundle\Doctrine\ManagedTranslationProxy;
use Webfactory\Bundle\PolyglotBundle\Locale\DefaultLocaleProvider;
use Webfactory\Bundle\PolyglotBundle\Tests\TestEntity;

class ManagedTranslationProxyTest extends \PHPUnit_Framework_TestCase
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

    public function testCountReturnsLengthOfStringInDefaultLocale()
    {
        $entity = new TestEntity('foo');
        $proxy = $this->createProxy($entity);
        // Simulate default translation.
        $proxy->setTranslation('bar in de', 'de');

        $this->assertCount(strlen('bar in de'), $proxy);
    }

    /**
     * @param TestEntity $entity
     * @param LoggerInterface|null $logger
     * @return ManagedTranslationProxy
     */
    private function createProxy(TestEntity $entity, LoggerInterface $logger = null)
    {
        $localeProvider = new DefaultLocaleProvider();
        $localeProvider->setDefaultLocale('de');

        // We need a translation class without required constructor parameters.
        $translationClass = 'Webfactory\Bundle\PolyglotBundle\Tests\TestEntityTranslation';
        $proxy = new ManagedTranslationProxy(
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
        $brokenCollection = $this->getMock('Doctrine\Common\Collections\ArrayCollection');
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
