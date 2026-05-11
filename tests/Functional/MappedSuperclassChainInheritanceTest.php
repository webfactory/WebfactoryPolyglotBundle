<?php

namespace Webfactory\Bundle\PolyglotBundle\Tests\Functional;

use Webfactory\Bundle\PolyglotBundle\Tests\Fixtures\Entity\EntityInheritance\EntityInheritance_MappedSuperclass;
use Webfactory\Bundle\PolyglotBundle\Tests\Fixtures\Entity\EntityInheritance\EntityInheritance_MappedSuperclassChain;
use Webfactory\Bundle\PolyglotBundle\Tests\Fixtures\Entity\EntityInheritance\EntityInheritance_MappedSuperclassChainEntity;
use Webfactory\Bundle\PolyglotBundle\Tests\Fixtures\Entity\EntityInheritance\EntityInheritance_MappedSuperclassChainEntityTranslation;
use Webfactory\Bundle\PolyglotBundle\Translatable;

/**
 * Tests the two-level mapped superclass chain:
 *   EntityInheritance_MappedSuperclass          — property defined here, no Polyglot config
 *     └─ EntityInheritance_MappedSuperclassChain — #[Locale], #[TranslatedProperty], translations collection here
 *          └─ EntityInheritance_MappedSuperclassChainEntity — bare entity, no Polyglot config needed
 *
 * The concrete entity must pick up the #[TranslatedProperty] and #[Locale] declarations
 * from the intermediate mapped superclass two levels up.
 */
class MappedSuperclassChainInheritanceTest extends DatabaseFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        self::setupSchema([
            EntityInheritance_MappedSuperclass::class,
            EntityInheritance_MappedSuperclassChain::class,
            EntityInheritance_MappedSuperclassChainEntity::class,
            EntityInheritance_MappedSuperclassChainEntityTranslation::class,
        ]);
    }

    public function testPersistAndReloadEntity(): void
    {
        $entity = new EntityInheritance_MappedSuperclassChainEntity();
        $t = new Translatable('base text');
        $t->setTranslation('Basistext', 'de_DE');
        $entity->setText($t);

        self::import([$entity]);

        $loaded = $this->entityManager->find(EntityInheritance_MappedSuperclassChainEntity::class, $entity->getId());

        self::assertSame('base text', $loaded->getText()->translate('en_GB'));
        self::assertSame('Basistext', $loaded->getText()->translate('de_DE'));
    }

    public function testAddTranslation(): void
    {
        $entityManager = $this->entityManager;

        $entity = new EntityInheritance_MappedSuperclassChainEntity();
        $entity->setText(new Translatable('base text'));
        self::import([$entity]);

        $loaded = $entityManager->find(EntityInheritance_MappedSuperclassChainEntity::class, $entity->getId());
        $loaded->getText()->setTranslation('Basistext', 'de_DE');
        $entityManager->flush();

        $entityManager->clear();
        $reloaded = $entityManager->find(EntityInheritance_MappedSuperclassChainEntity::class, $entity->getId());

        self::assertSame('base text', $reloaded->getText()->translate('en_GB'));
        self::assertSame('Basistext', $reloaded->getText()->translate('de_DE'));
    }

    public function testUpdateTranslations(): void
    {
        $entityManager = $this->entityManager;

        $entity = new EntityInheritance_MappedSuperclassChainEntity();
        $t = new Translatable('old text');
        $t->setTranslation('alter Text', 'de_DE');
        $entity->setText($t);
        self::import([$entity]);

        $loaded = $entityManager->find(EntityInheritance_MappedSuperclassChainEntity::class, $entity->getId());
        $loaded->getText()->setTranslation('new text');
        $loaded->getText()->setTranslation('neuer Text', 'de_DE');
        $entityManager->flush();

        $entityManager->clear();
        $reloaded = $entityManager->find(EntityInheritance_MappedSuperclassChainEntity::class, $entity->getId());

        self::assertSame('new text', $reloaded->getText()->translate('en_GB'));
        self::assertSame('neuer Text', $reloaded->getText()->translate('de_DE'));
    }
}
