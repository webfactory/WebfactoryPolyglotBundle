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
use Webfactory\Bundle\PolyglotBundle\TranslatableInterface;
use Webfactory\Doctrine\ORMTestInfrastructure\ORMInfrastructure;

/**
 * Integration Tests.
 */
final class IntegrationTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Infrastructure for ORM tests.
     *
     * @var ORMInfrastructure
     */
    private $infrastructure;

    /**
     * @see \PHPUnit_Framework_TestCase::setUp()
     */
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
    public function worksWithoutTranslations()
    {
        $this->addPolyglotListenerToDoctrineWithLocale('en_GB');

        $entity = new TestEntity('english');
        $this->infrastructure->import($entity);

        $loadedText = $this->getTextOfLoadedEntity($entity);
        $this->assertInstanceOf('\Webfactory\Bundle\PolyglotBundle\TranslatableInterface', $loadedText);
        $this->assertSame('english', $loadedText->__toString());
    }

    /**
     * @test
     */
    public function defaultLocaleForPrimaryLocaleRequest()
    {
        $this->addPolyglotListenerToDoctrineWithLocale('en_GB');
        $entity = $this->createAndImportFixtureWithTranslation();

        $loadedText = $this->getTextOfLoadedEntity($entity);
        $this->assertInstanceOf('\Webfactory\Bundle\PolyglotBundle\TranslatableInterface', $loadedText);
        $this->assertSame('english', $loadedText->__toString());
    }

    /**
     * @test
     */
    public function differentLocaleForSecondaryLocaleRequest()
    {
        $this->addPolyglotListenerToDoctrineWithLocale('de_DE');
        $entity = $this->createAndImportFixtureWithTranslation();

        $loadedText = $this->getTextOfLoadedEntity($entity);
        $this->assertInstanceOf('\Webfactory\Bundle\PolyglotBundle\TranslatableInterface', $loadedText);
        $this->assertSame('deutsch', $loadedText->__toString());
    }

    /**
     * Ensures one can request a locale other than the one from the request.
     *
     * @test
     */
    public function differentLocaleThanRequestedOne()
    {
        $this->addPolyglotListenerToDoctrineWithLocale('de_DE');
        $entity = $this->createAndImportFixtureWithTranslation();

        $loadedText = $this->getTextOfLoadedEntity($entity);
        $this->assertInstanceOf('\Webfactory\Bundle\PolyglotBundle\TranslatableInterface', $loadedText);
        $this->assertSame('english', $loadedText->translate('en_GB'));
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
     * @return TestEntity
     */
    private function createAndImportFixtureWithTranslation()
    {
        $entity = new TestEntity('english');
        $translation = new TestEntityTranslation('de_DE', 'deutsch', $entity);
        $this->infrastructure->import(array($translation, $entity));

        return $entity;
    }

    /**
     * @param TestEntity $entity
     * @return TranslatableInterface|string|null
     */
    private function getTextOfLoadedEntity($entity)
    {
        /* @var $loadedEntity TestEntity */
        $loadedEntity =  $this->infrastructure->getRepository($entity)
                                              ->find($entity->getId());
        return $loadedEntity->getText();
    }
}
