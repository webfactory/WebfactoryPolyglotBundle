<?php

namespace Webfactory\Bundle\PolyglotBundle\Tests;

use PHPUnit\Framework\TestCase;
use Webfactory\Bundle\PolyglotBundle\Translatable;
use Webfactory\Bundle\PolyglotBundle\TranslatableChain;
use Webfactory\Bundle\PolyglotBundle\TranslatableInterface;

class TranslatableChainTest extends TestCase
{
    /**
     * @test
     */
    public function instanceof_Translatable(): void
    {
        self::assertInstanceOf(TranslatableInterface::class, TranslatableChain::firstNonEmpty());
    }

    /**
     * @test
     */
    public function uses_primary_translation_if_available(): void
    {
        $chain = TranslatableChain::firstNonEmpty(new Translatable('primary', 'en'), new Translatable('secondary', 'en'));

        self::assertSame('primary', $chain->translate());
    }

    /**
     * @test
     */
    public function fallback_through_empty_translations(): void
    {
        $chain = TranslatableChain::firstNonEmpty(new Translatable('', 'en'), new Translatable('', 'en'), new Translatable('tertiary', 'en'));

        self::assertSame('tertiary', $chain->translate());
    }

    /**
     * @test
     */
    public function fallback_to_first_available_translation(): void
    {
        $chain = TranslatableChain::firstTranslation(new Translatable('', 'en'), new Translatable('secondary', 'en'));

        self::assertSame('', $chain->translate());
    }

    /**
     * @test
     */
    public function fallback_to_first_available_translation_when_in_secondary(): void
    {
        $chain = TranslatableChain::firstTranslation(new Translatable('', 'en'), new Translatable('secondary', 'de'));

        self::assertSame('secondary', $chain->translate('de'));
    }

    /**
     * @test
     */
    public function toString_uses_primary_translation_if_available(): void
    {
        $chain = TranslatableChain::firstNonEmpty(new Translatable('primary', 'en'), new Translatable('secondary', 'en'));

        self::assertSame('primary', (string) $chain);
    }

    /**
     * @test
     */
    public function toString_falls_back(): void
    {
        $chain = TranslatableChain::firstNonEmpty(new Translatable('', 'en'), new Translatable('', 'en'), new Translatable('tertiary', 'en'));

        self::assertSame('tertiary', (string) $chain);
    }

    /**
     * @test
     */
    public function setTranslation_updates_primary(): void
    {
        $primary = new Translatable('primary', 'en');
        $secondary = new Translatable('secondary', 'en');
        $chain = TranslatableChain::firstNonEmpty($primary, $secondary);

        $chain->setTranslation('new translation', 'en');

        self::assertSame('new translation', $primary->translate('en'));
        self::assertNotSame('new translation', $secondary->translate('en'));
    }

    /** @test */
    public function isTranslated_when_no_translation_available(): void
    {
        $chain = TranslatableChain::firstNonEmpty(
            new Translatable('...', 'en'),
            new Translatable('...', 'en')
        );

        self::assertFalse($chain->isTranslatedInto('de'));
    }

    /** @test */
    public function isTranslatedInto_returns_true_if_first_translation_is_set(): void
    {
        $chain = TranslatableChain::firstNonEmpty(
            new Translatable('...', 'de'),
            new Translatable('...', 'en')
        );

        self::assertTrue($chain->isTranslatedInto('de'));
    }

    /** @test */
    public function isTranslatedInto_returns_true_if_second_translation_is_set(): void
    {
        $chain = TranslatableChain::firstNonEmpty(
            new Translatable('...', 'en'),
            new Translatable('...', 'de')
        );

        self::assertTrue($chain->isTranslatedInto('de'));
    }
}

