<?php

namespace Webfactory\Bundle\PolyglotBundle\Tests\Fixtures;

use Doctrine\DBAL\Logging\Middleware;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Kernel;

class TestKernel extends Kernel
{
    public function registerBundles(): iterable
    {
        return [
            new \Symfony\Bundle\FrameworkBundle\FrameworkBundle(),
            new \Doctrine\Bundle\DoctrineBundle\DoctrineBundle(),
            new \Webfactory\Bundle\PolyglotBundle\WebfactoryPolyglotBundle(),
        ];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(static function (ContainerBuilder $container): void {
            // Framework configuration
            $container->loadFromExtension('framework', [
                'test' => true,
            ] + (Kernel::VERSION_ID < 70000 ? ['annotations' => ['enabled' => false]] : []));

            // Webfactory Polyglot Bundle configuration
            $container->loadFromExtension('webfactory_polyglot', [
                'defaultLocale' => 'en_GB',
            ]);

            // Doctrine configuration
            $container->loadFromExtension('doctrine', [
                'dbal' => [
                    'driver' => 'pdo_sqlite',
                    'memory' => true,
                ],
                'orm' => [
                    'mappings' => [
                        'WebfactoryPolyglotBundle' => [
                            'type' => 'attribute',
                            'dir' => '../tests/Fixtures/Entity',
                            'prefix' => 'Webfactory\Bundle\PolyglotBundle\Tests\Fixtures\Entity',
                        ],
                    ],
                ],
            ]);

            // Service definitions
            $container->setDefinition('entity_manager_for_loading', new ChildDefinition('doctrine.orm.entity_manager'));

            $container->register(QueryLogger::class);

            $container->register(Middleware::class)
                ->setArguments([
                    '$logger' => new Reference(QueryLogger::class),
                ])
                ->addTag('doctrine.middleware');
        });
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }
}
