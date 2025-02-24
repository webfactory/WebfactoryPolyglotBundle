<?php

namespace Webfactory\Bundle\PolyglotBundle\Tests\Doctrine;

use Doctrine\ORM\UnitOfWork;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ReflectionClass;
use ReflectionProperty;
use RuntimeException;
use Symfony\Component\ErrorHandler\BufferingLogger;
use Webfactory\Bundle\PolyglotBundle\Doctrine\PersistentTranslatable;
use Webfactory\Bundle\PolyglotBundle\Locale\DefaultLocaleProvider;
use Webfactory\Bundle\PolyglotBundle\Tests\Fixtures\Entity\TestEntity;
use Webfactory\Bundle\PolyglotBundle\Tests\Fixtures\Entity\TestEntityTranslation;
use Doctrine\Common\Collections\ArrayCollection;

class PersistentTranslatableTest extends TestCase
{
    public function testToStringReturnsTranslatedMessage(): void
    {
        $entity = new TestEntity('foo');
        $proxy = $this->createProxy($entity);

        // Simulate some translations.
        $proxy->setTranslation('bar', 'de');
        $proxy->setTranslation('bar in en', 'en');

        $translation = (string) $proxy;

        self::assertEquals('bar', $translation);
    }

    public function testToStringReturnsStringIfExceptionOccurredAndNoLoggerIsAvailable(): void
    {
        $entity = new TestEntity('foo');
        $proxy = $this->createProxy($entity);
        $this->breakEntity($entity);
        $translation = (string) $proxy;

        self::assertIsString($translation);
    }

    public function testToStringReturnsStringIfExceptionOccurredAndLoggerIsAvailable(): void
    {
        $entity = new TestEntity('foo');
        $proxy = $this->createProxy($entity, new NullLogger());
        $this->breakEntity($entity);
        $translation = (string) $proxy;

        self::assertIsString($translation);
    }

    public function testToStringLogsExceptionIfLoggerIsAvailable(): void
    {
        $entity = new TestEntity('foo');

        $logger = new BufferingLogger();
        $proxy = $this->createProxy($entity, $logger);
        $this->breakEntity($entity);

        $proxy->__toString();

        self::assertGreaterThan(0, \count($logger->cleanLogs()), 'Expected at least one log message');
    }

    public function testLoggedMessageContainsInformationAboutTranslatedProperty(): void
    {
        $entity = new TestEntity('foo');

        $logger = new BufferingLogger();
        $proxy = $this->createProxy($entity, $logger);
        $this->breakEntity($entity);

        $proxy->__toString();

        $logs = $logger->cleanLogs();
        $logEntry = current($logs);
        self::assertIsArray($logEntry);
        self::assertArrayHasKey(1, $logEntry, 'Missing log message.');
        $logMessage = $logEntry[1];
        self::assertStringContainsString('TestEntity', $logMessage, 'Missing entity class name.');
        self::assertStringContainsString('text', $logMessage, 'Missing translated property.');
        self::assertStringContainsString('de', $logMessage, 'Missing locale.');
    }

    public function testLoggedMessageContainsOriginalException(): void
    {
        $entity = new TestEntity('foo');

        $logger = new BufferingLogger();
        $proxy = $this->createProxy($entity, $logger);
        $this->breakEntity($entity);

        $proxy->__toString();

        $logs = $logger->cleanLogs();
        $logEntry = current($logs);
        self::assertIsArray($logEntry);
        self::assertArrayHasKey(1, $logEntry, 'Missing log message.');
        $logMessage = $logEntry[1];
        self::assertStringContainsString('Cannot find translations', $logMessage, 'Original exception not contained.');
    }

    /** @test */
    public function isTranslatedInto_returns_true_for_primary_translation_if_set(): void
    {
        $entity = new TestEntity('foo');
        $proxy = $this->createProxy($entity);
        $proxy->setTranslation('bar', 'de');
        $proxy->setTranslation('bar in en', 'en');

        self::assertTrue($proxy->isTranslatedInto('en'));
    }

    /** @test */
    public function isTranslatedInto_returns_true_for_translation_if_set(): void
    {
        $entity = new TestEntity('foo');
        $proxy = $this->createProxy($entity);
        $proxy->setTranslation('bar', 'de');
        $proxy->setTranslation('bar in en', 'en');

        self::assertTrue($proxy->isTranslatedInto('de'));
    }

    /** @test */
    public function isTranslatedInto_returns_false_if_primary_translation_is_empty(): void
    {
        $entity = new TestEntity('foo');
        $proxy = $this->createProxy($entity);
        $proxy->setTranslation('bar', 'de');
        $proxy->setTranslation('', 'en');

        $isTranslatedInto = $proxy->isTranslatedInto('en');
        self::assertFalse($isTranslatedInto);
    }

    /** @test */
    public function isTranslatedInto_returns_false_if_translation_is_not_set(): void
    {
        $entity = new TestEntity('foo');
        $proxy = $this->createProxy($entity);
        $proxy->setTranslation('bar', 'de');
        $proxy->setTranslation('bar in en', 'en');

        self::assertFalse($proxy->isTranslatedInto('fr'));
    }

    /**
     * @return PersistentTranslatable
     */
    private function createProxy(TestEntity $entity, ?LoggerInterface $logger = null): PersistentTranslatable
    {
        $localeProvider = new DefaultLocaleProvider();
        $localeProvider->setDefaultLocale('de');

        // We need a translation class without required constructor parameters.
        $translationClass = TestEntityTranslation::class;

        return new PersistentTranslatable(
            $this->createMock(UnitOfWork::class),
            TestEntity::class,
            $entity,
            'en',
            $localeProvider,
            new ReflectionProperty($translationClass, 'text'),
            new ReflectionProperty($entity, 'translations'),
            new ReflectionClass($translationClass),
            new ReflectionProperty($translationClass, 'locale'),
            new ReflectionProperty($translationClass, 'entity'),
            self::accessibleProperty(TestEntity::class, 'text'),
            $logger
        );
    }

    private function breakEntity(TestEntity $entity): void
    {
        $brokenCollection = $this->getMockBuilder(ArrayCollection::class)->getMock();
        $brokenCollection
            ->method('matching')
            ->will($this->throwException(new RuntimeException('Cannot find translations')));
        $property = new ReflectionProperty($entity, 'translations');
        $property->setAccessible(true);
        $property->setValue($entity, $brokenCollection);
    }

    private static function accessibleProperty(string $class, string $property): ReflectionProperty
    {
        $property = new ReflectionProperty($class, $property);
        $property->setAccessible(true);

        return $property;
    }
}
