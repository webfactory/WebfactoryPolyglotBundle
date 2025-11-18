<?php

namespace Webfactory\Bundle\PolyglotBundle\Tests\Functional;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Webfactory\Bundle\PolyglotBundle\Tests\Fixtures\Query;
use Webfactory\Bundle\PolyglotBundle\Tests\Fixtures\QueryLogger;

abstract class DatabaseFunctionalTestCase extends KernelTestCase
{
    protected EntityManager $entityManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
    }

    protected static function setupSchema(array $classes): void
    {
        $container = static::getContainer();
        $entityManager = $container->get('entity_manager_for_loading');

        $schemaTool = new SchemaTool($entityManager);

        $metadata = array_map(fn (string $class) => $entityManager->getClassMetadata($class), $classes);
        $schemaTool->createSchema($metadata);
    }

    protected static function import(object|array $objects): void
    {
        if (\is_object($objects)) {
            $objects = [$objects];
        }
        $container = static::getContainer();
        $entityManager = $container->get('entity_manager_for_loading');

        $classes = [];
        foreach ($objects as $object) {
            $entityManager->persist($object);
            // $classes[get_class($object)] = true;
        }

        // self::setupSchema(array_keys($classes));
        $entityManager->flush();
    }

    /**
     * @return list<Query>
     */
    protected static function getQueries(): array
    {
        return self::getContainer()->get(QueryLogger::class)->getQueries();
    }
}
