<?php

namespace Webfactory\Bundle\PolyglotBundle\Tests\Functional;

use Doctrine\ORM\Tools\ResolveTargetEntityListener;
use Webfactory\Bundle\PolyglotBundle\Tests\Fixtures\Entity\EntityInheritance\EntityInheritance_MappedSuperclassTranslation;
use Webfactory\Bundle\PolyglotBundle\Tests\Fixtures\Entity\EntityInheritance\EntityInheritance_MappedSuperclassWithTranslations;
use Webfactory\Bundle\PolyglotBundle\Tests\Fixtures\Entity\EntityInheritance\EntityInheritance_MappedSuperclassWithTranslations_Entity;
use Webfactory\Bundle\PolyglotBundle\Tests\Fixtures\Entity\EntityInheritance\EntityInheritance_MappedSuperclassWithTranslationsInterface;
use Webfactory\Bundle\PolyglotBundle\Translatable;

class MappedSuperclassWithTranslationsTest extends DatabaseFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $resolveTargetEntity = new ResolveTargetEntityListener();
        $resolveTargetEntity->addResolveTargetEntity(
            EntityInheritance_MappedSuperclassWithTranslationsInterface::class,
            EntityInheritance_MappedSuperclassWithTranslations_Entity::class,
            []
        );

        $this->entityManager->getEventManager()->addEventSubscriber($resolveTargetEntity);

        self::setupSchema([
            EntityInheritance_MappedSuperclassWithTranslations::class,
            EntityInheritance_MappedSuperclassWithTranslations_Entity::class,
            EntityInheritance_MappedSuperclassTranslation::class,
        ]);
    }

    public function testPersistAndReloadEntity(): void
    {
        $entity = new EntityInheritance_MappedSuperclassWithTranslations_Entity();
        $t = new Translatable('base text');
        $t->setTranslation('Basistext', 'de_DE');
        $entity->setText($t);

        self::import([$entity]);

        $loaded = $this->entityManager->find(EntityInheritance_MappedSuperclassWithTranslations_Entity::class, $entity->getId());

        self::assertSame('base text', $loaded->getText()->translate('en_GB'));
        self::assertSame('Basistext', $loaded->getText()->translate('de_DE'));
    }

    public function testAddTranslation(): void
    {
        $entityManager = $this->entityManager;

        $entity = new EntityInheritance_MappedSuperclassWithTranslations_Entity();
        $entity->setText(new Translatable('base text'));
        self::import([$entity]);

        $loaded = $entityManager->find(EntityInheritance_MappedSuperclassWithTranslations_Entity::class, $entity->getId());
        $loaded->getText()->setTranslation('Basistext', 'de_DE');
        $entityManager->flush();

        $entityManager->clear();
        $reloaded = $entityManager->find(EntityInheritance_MappedSuperclassWithTranslations_Entity::class, $entity->getId());

        self::assertSame('base text', $reloaded->getText()->translate('en_GB'));
        self::assertSame('Basistext', $reloaded->getText()->translate('de_DE'));
    }

    public function testUpdateTranslations(): void
    {
        $entityManager = $this->entityManager;

        $entity = new EntityInheritance_MappedSuperclassWithTranslations_Entity();
        $t = new Translatable('old text');
        $t->setTranslation('alter Text', 'de_DE');
        $entity->setText($t);
        self::import([$entity]);

        $loaded = $entityManager->find(EntityInheritance_MappedSuperclassWithTranslations_Entity::class, $entity->getId());
        $loaded->getText()->setTranslation('new text');
        $loaded->getText()->setTranslation('neuer Text', 'de_DE');
        $entityManager->flush();

        $entityManager->clear();
        $reloaded = $entityManager->find(EntityInheritance_MappedSuperclassWithTranslations_Entity::class, $entity->getId());

        self::assertSame('new text', $reloaded->getText()->translate('en_GB'));
        self::assertSame('neuer Text', $reloaded->getText()->translate('de_DE'));
    }
}
