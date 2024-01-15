<?php

namespace Webfactory\Bundle\PolyglotBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Webfactory\Bundle\PolyglotBundle\Doctrine\TranslatableType;

final class RegisterDoctrineTypePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('doctrine.dbal.connection_factory.types')) {
            throw new \RuntimeException('This bundle expects DoctrineBundle to be registered, it should provide the doctrine.dbal.connection_factory.types container parameter');
        }

        $types = $container->getParameter('doctrine.dbal.connection_factory.types');
        $types[TranslatableType::TYPE] = ['class' => TranslatableType::class];

        $container->setParameter('doctrine.dbal.connection_factory.types', $types);
    }
}