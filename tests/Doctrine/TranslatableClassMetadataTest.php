<?php

namespace Webfactory\Bundle\PolyglotBundle\Tests\Doctrine;

use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use Webfactory\Bundle\PolyglotBundle\Doctrine\TranslatableClassMetadata;
use Webfactory\Bundle\PolyglotBundle\Tests\Fixtures\Entity\EntityInheritance\EntityInheritance_MappedSuperclassChainEntityLocaleOverride;
use Webfactory\Bundle\PolyglotBundle\Tests\Fixtures\Entity\TestEntity;
use Webfactory\Bundle\PolyglotBundle\Tests\Functional\DatabaseFunctionalTestCase;

class TranslatableClassMetadataTest extends DatabaseFunctionalTestCase
{
    /**
     * @test
     */
    public function can_be_serialized_and_retrieved(): void
    {
        $metadata = $this->createMetadata();

        $serialize = serialize($metadata->sleep());
        $unserialized = TranslatableClassMetadata::wakeup(unserialize($serialize), new RuntimeReflectionService());

        self::assertEquals($metadata, $unserialized);
    }

    /**
     * @test
     */
    public function subclass_locale_takes_priority_over_parent_locale(): void
    {
        $metadata = TranslatableClassMetadata::parseFromClass(
            EntityInheritance_MappedSuperclassChainEntityLocaleOverride::class,
            $this->entityManager->getMetadataFactory()
        );

        // EntityInheritance_MappedSuperclassChain (parent) declares en_GB,
        // EntityInheritance_MappedSuperclassChainEntityLocaleOverride (child) declares de_DE.
        self::assertSame('de_DE', $metadata->sleep()->primaryLocale);
    }

    private function createMetadata(): TranslatableClassMetadata
    {
        $entityManager = $this->entityManager;

        return TranslatableClassMetadata::parseFromClass(TestEntity::class, $entityManager->getMetadataFactory());
    }
}
