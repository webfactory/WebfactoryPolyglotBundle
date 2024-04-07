<?php

namespace Webfactory\Bundle\PolyglotBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Webfactory\Bundle\PolyglotBundle\Tests\Fixtures\Entity\TestEntity;
use Webfactory\Bundle\PolyglotBundle\Tests\Fixtures\Entity\TestEntityTranslation;
use Webfactory\Bundle\PolyglotBundle\Translatable;

class SymfonyIntegrationTest extends KernelTestCase
{
    /**
     * @test
     */
    public function persist_and_reload_entity_in_Symfony(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get('doctrine.orm.entity_manager');

        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->createSchema([
            $entityManager->getClassMetadata(TestEntity::class),
            $entityManager->getClassMetadata(TestEntityTranslation::class),
        ]);

        $value = new Translatable('english', 'en_GB');
        $value->setTranslation('deutsch', 'de_DE');
        $entity = new TestEntity($value);

        $entityManager->persist($entity);
        $entityManager->flush();
        $entityManager->clear();

        $reloadedEntity = $entityManager->find(TestEntity::class, $entity->getId());

        self::assertSame('english', $reloadedEntity->getText()->translate('en_GB'));
        self::assertSame('deutsch', $reloadedEntity->getText()->translate('de_DE'));
    }
}
