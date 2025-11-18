<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return static function (ContainerConfigurator $container) {
    $services = $container->services();
    $parameters = $container->parameters();

    $services->defaults()
        ->autowire()
        ->autoconfigure();

    $services->set(\Webfactory\Bundle\PolyglotBundle\Doctrine\PolyglotListener::class)
        ->tag('doctrine.event_listener', ['priority' => -100, 'event' => 'postFlush'])
        ->tag('doctrine.event_listener', ['priority' => -100, 'event' => 'prePersist'])
        ->tag('doctrine.event_listener', ['priority' => -100, 'event' => 'preFlush'])
        ->tag('doctrine.event_listener', ['priority' => -100, 'event' => 'postLoad'])
        ->tag('monolog.logger', ['channel' => 'webfactory_polyglot_bundle']);

    $services->set(\Webfactory\Bundle\PolyglotBundle\EventListener\LocaleListener::class);

    $services->set(\Webfactory\Bundle\PolyglotBundle\Locale\DefaultLocaleProvider::class)
        ->call('setDefaultLocale', ['%webfactory.polyglot.default_locale%']);
};
