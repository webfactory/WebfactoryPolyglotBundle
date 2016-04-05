<?php

namespace Webfactory\Bundle\PolyglotBundle\Tests\Doctrine;


use Webfactory\Bundle\PolyglotBundle\Doctrine\ManagedTranslationProxy;
use Webfactory\Bundle\PolyglotBundle\Locale\DefaultLocaleProvider;
use Webfactory\Bundle\PolyglotBundle\Tests\TestEntity;

class ManagedTranslationProxyTest extends \PHPUnit_Framework_TestCase
{

    public function testToStringReturnsTranslatedMessage()
    {
        $translation = (string)$this->createProxy();

        $this->assertEquals('bar', $translation);
    }

    public function testToStringReturnsStringIfExceptionOccurredAndNoLoggerIsAvailable()
    {

    }

    public function testToStringReturnsStringIfExceptionOccurredAndLoggerIsAvailable()
    {

    }

    public function testToStringLogsExceptionIfLoggerIsAvailable()
    {

    }

    /**
     * @return ManagedTranslationProxy
     */
    private function createProxy()
    {
        $entity = new TestEntity('foo');
        $localeProvider = new DefaultLocaleProvider();
        $localeProvider->setDefaultLocale('de');

        // We need a translation class without required constructor parameters.
        $translationClass = 'Webfactory\Bundle\PolyglotBundle\Tests\TestEntityTranslation';
        $proxy = new ManagedTranslationProxy(
            $entity,
            'de',
            $localeProvider,
            $this->makeAccessible(new \ReflectionProperty($translationClass, 'text')),
            $this->makeAccessible(new \ReflectionProperty($entity, 'translations')),
            new \ReflectionClass($translationClass),
            $this->makeAccessible(new \ReflectionProperty($translationClass, 'locale')),
            $this->makeAccessible(new \ReflectionProperty($translationClass, 'entity'))
        );
        // Simulate a german translation.
        $proxy->setTranslation('bar', 'de');
        $proxy->setTranslation('bar in en', 'en');
        return $proxy;
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
