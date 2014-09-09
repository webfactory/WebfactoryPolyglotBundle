<?php

/*
 * (c) webfactory GmbH <info@webfactory.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webfactory\Bundle\PolyglotBundle\Tests;

use Doctrine\Common\Annotations\AnnotationReader;
use Webfactory\Bundle\PolyglotBundle\Doctrine\PolyglotListener;
use Webfactory\Bundle\PolyglotBundle\Locale\DefaultLocaleProvider;
use Webfactory\Doctrine\ORMTestInfrastructure\ORMInfrastructure;

/**
 * Integration Tests.
 */
final class IntegrationTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ORMInfrastructure
     */
    private $infrastructure;

    protected function setUp()
    {
        parent::setUp();

        $this->infrastructure = new ORMInfrastructure(
            array(
                '\Webfactory\Bundle\PolyglotBundle\Tests\TestEntity',
                '\Webfactory\Bundle\PolyglotBundle\Tests\TestEntityTranslation',
            )
        );
    }

    /**
     * @test
     */
    public function withoutListenerTextInDefaultLanguageIsReturned()
    {
        $entity = $this->createEntityFixture('english');
        $this->infrastructure->import($entity);

        $loadedEntity = $this->infrastructure->getRepository($entity)
                                             ->find($entity->getId());
        $this->assertInternalType('string', $loadedEntity->name);
        $this->assertSame('english', $loadedEntity->name);
    }

    /**
     * @test
     */
    public function withListenerWithDefualtLanguageTextInDefaultLanguageIsReturned()
    {
        $this->addPolyglotListenerToDoctrineWithLocale('en_GB');

        $entity = $this->createEntityFixture('english');
        $this->infrastructure->import($entity);

        $loadedEntity = $this->infrastructure->getRepository($entity)
                                             ->find($entity->getId());
        $this->assertInstanceOf('\Webfactory\Bundle\PolyglotBundle\TranslatableInterface', $loadedEntity->name);
        $this->assertSame('english', $loadedEntity->name->__toString());
    }

    /**
     * @test
     */
    public function differentLanguageForDifferentLocalRequested()
    {
        $this->addPolyglotListenerToDoctrineWithLocale('de_DE');

        $entity = $this->createEntityFixture('english');
        $this->addTranslationToTestEntity('de_DE', 'deutsch', $entity);
        $this->infrastructure->import($entity);

        $loadedEntity = $this->infrastructure->getRepository($entity)
                                             ->find($entity->getId());
        $this->assertInstanceOf('\Webfactory\Bundle\PolyglotBundle\TranslatableInterface', $loadedEntity->name);
        $this->assertSame('deutsch', $loadedEntity->name->__toString());
    }

    /**
     * @param string $locale, e.g. 'en_GB'
     */
    private function addPolyglotListenerToDoctrineWithLocale($locale)
    {
        $annotationReader = new AnnotationReader();
        $defaultLocaleProvider = new DefaultLocaleProvider();
        $defaultLocaleProvider->setDefaultLocale($locale);
        $listener = new PolyglotListener($annotationReader, $defaultLocaleProvider);
        $this->infrastructure->getEntityManager()
                             ->getEventManager()
                             ->addEventSubscriber($listener);
    }

    /**
     * @param string $name
     * @return TestEntity
     */
    private function createEntityFixture($name)
    {
        $entity = new TestEntity();
        $entity->name = $name;
        return $entity;
    }

    /**
     * @param string $locale, e.g. 'de_DE'
     * @param string $name translation in the locale
     * @param TestEntity $entity
     */
    private function addTranslationToTestEntity($locale, $name, TestEntity $entity)
    {
        $translation = new TestEntityTranslation();
        $translation->setLocale($locale);
        $translation->name = $name;

        $entity->addTranslation($translation);
    }
}
