<?php

namespace Webfactory\Bundle\PolyglotBundle\Tests;

use PHPUnit\Framework\TestCase;
use Webfactory\Bundle\PolyglotBundle\Locale\DefaultLocaleProvider;
use Webfactory\Bundle\PolyglotBundle\Translatable;
use Webfactory\Bundle\PolyglotBundle\TranslatableInterface;

class TranslatableTest extends TestCase
{
    public function testImplementsInterface()
    {
        $t = new Translatable();
        $this->assertInstanceOf(TranslatableInterface::class, $t);
    }

    public function testReturnsMainValueAndTranslations()
    {
        $t = new Translatable('text locale_A', 'locale_A');
        $t->setTranslation('text locale_B', 'locale_B');

        $this->assertEquals('text locale_A', $t->translate('locale_A'));
        $this->assertEquals('text locale_B', $t->translate('locale_B'));
    }

    public function testCanBeCreatedWithoutLocale()
    {
        $t = new Translatable('text locale_A');
        $this->assertEquals('text locale_A', $t->translate());
    }

    public function testReturnsNullForUnknownLocale()
    {
        $t = new Translatable('text locale_A', 'locale_A');
        $this->assertNull($t->translate('unknown'));
    }

    public function testDeferredSettingOfDefaultLocale()
    {
        $t = new Translatable('some text');
        $t->setDefaultLocale('foo');

        $this->assertEquals('some text', $t->translate('foo'));
    }

    public function testCopyTranslations()
    {
        $t = new Translatable('text locale_A', 'locale_A');
        $t->setTranslation('text locale_B', 'locale_B');

        $other = new Translatable('foo', 'locale_A');
        $other->setTranslation('text locale_C', 'locale_C');

        $t->copy($other);

        $this->assertEquals('text locale_A', $other->translate('locale_A'));
        $this->assertEquals('text locale_B', $other->translate('locale_B'));
        $this->assertEquals('text locale_C', $other->translate('locale_C'));
    }

    public function testReturnsDefaultValueWhenCastToString()
    {
        $t = new Translatable('text locale_A', 'locale_A');
        $this->assertEquals('text locale_A', (string) $t);
    }

    public function testDefaultLocaleProviderCanProvideDefaultLocale()
    {
        $defaultLocaleProvider = new DefaultLocaleProvider('locale_A');

        $t = new Translatable('text locale_A', $defaultLocaleProvider);
        $t->setTranslation('text locale_B', 'locale_B');

        $this->assertEquals('text locale_A', (string) $t);
        $this->assertEquals('text locale_A', $t->translate());

        $defaultLocaleProvider->setDefaultLocale('locale_B');
        $this->assertEquals('text locale_B', (string) $t);
        $this->assertEquals('text locale_B', $t->translate());
    }

    /** @test */
    public function isTranslatedInto_returns_true_for_primary_translation_if_set()
    {
        $translatable = new Translatable('text locale_A', 'locale_A');

        $this->assertTrue($translatable->isTranslatedInto('locale_A'));
    }

    /** @test */
    public function isTranslatedInto_returns_true_for_translation_if_set()
    {
        $translatable = new Translatable('text locale_A', 'locale_A');
        $translatable->setTranslation('text locale_B', 'locale_B');

        $this->assertTrue($translatable->isTranslatedInto('locale_B'));
    }

    /** @test */
    public function isTranslatedInto_returns_false_if_primary_translation_is_empty()
    {
        $translatable = new Translatable('', 'locale_A');

        $this->assertFalse($translatable->isTranslatedInto('locale_A'));
    }

    /** @test */
    public function isTranslatedInto_returns_false_if_translation_is_not_set()
    {
        $translatable = new Translatable('', 'locale_A');

        $this->assertFalse($translatable->isTranslatedInto('locale_B'));
    }
}
