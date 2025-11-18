<?php

namespace Webfactory\Bundle\PolyglotBundle\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use Webfactory\Bundle\PolyglotBundle\Tests\Fixtures\Entity\CascadePersist\CascadePersistTranslationsTest_Entity;
use Webfactory\Bundle\PolyglotBundle\Tests\Fixtures\Entity\CascadePersist\CascadePersistTranslationsTest_Translation;

/**
 * This test shows that `PersistentTranslatable` will take care of adding new
 * translation entities to the entity manager, so explicit `persist()` calls are not
 * necessary; and also, `cascade={"persist"}` isn't either.
 */
class CascadePersistTranslationsTest extends DatabaseFunctionalTestCase
{
    #[Test]
    public function adding_and_persisting_translations(): void
    {
        self::setupSchema([
            CascadePersistTranslationsTest_Entity::class,
            CascadePersistTranslationsTest_Translation::class,
        ]);

        $entity = new CascadePersistTranslationsTest_Entity();
        $entity->addTranslation('de_DE', 'text de_DE');

        self::import([$entity]);
//        $this->entityManager->persist($entity);
//        $this->entityManager->flush();

        // note the absent call to $this->entityManager->persist() for the translation entity

        $result = $this->entityManager->getConnection()->executeQuery('SELECT * FROM CascadePersistTranslationsTest_Translation')->fetchAllAssociative();

        self::assertCount(1, $result);
        self::assertSame('text de_DE', $result[0]['text']);
    }
}
