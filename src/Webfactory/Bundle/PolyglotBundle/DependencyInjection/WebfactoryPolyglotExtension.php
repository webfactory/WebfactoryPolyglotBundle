<?php

namespace Webfactory\Bundle\PolyglotBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

class WebfactoryPolyglotExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.xml');

        $m = array("defaultLocale" => "de_DE");
        foreach ($configs as $c) {
            $m = array_merge($m, $c);
        }

        $container->setParameter('webfactory.polyglot.default_locale', $m['defaultLocale']);
    }
}
