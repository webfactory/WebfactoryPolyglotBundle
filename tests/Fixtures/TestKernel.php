<?php

namespace Webfactory\Bundle\PolyglotBundle\Tests\Fixtures;

use Symfony\Component\Config\Loader\LoaderInterface;
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
        $loader->load(__DIR__.'/config/config.yml');
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

//    public function getCacheDir(): string
//    {
//        return __DIR__.'/cache/'.$this->environment;
//    }
}

#class_alias(TestKernel::class, 'TestKernel');
