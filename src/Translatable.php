<?php

/*
 * (c) webfactory GmbH <info@webfactory.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webfactory\Bundle\PolyglotBundle;

use InvalidArgumentException;
use Webfactory\Bundle\PolyglotBundle\Locale\DefaultLocaleProvider;

/**
 * Eine TranslationInterface-Implementierung für eine Entität, die
 * noch neu und dem EntityManager nicht bekannt ist. Diese Implementierung
 * kann von Klassen mit Translatable-Feldern im Konstruktor ohne weitere
 * Abhängigkeiten erzeugt werden.
 *
 * Wir können an dieser Stelle nicht auf die Reflection-
 * Mechanismen (TranslatableClassMetadata) zurückgreifen, weil diese z. T. auch
 * Details des EntityManagers (z. B. den Metadata-Cache) benötigen, was
 * wir alles an der Stelle in Klienten nicht zur Verfügung haben.
 *
 * Solange eine Entität aber so frisch ist, reicht es auch aus, "ihre" Übersetzungen
 * nur im Proxy mitzuführen.
 *
 * Die Klasse heisst "Translatable", damit sie in Klienten "nett" initialisiert werden
 * kann:
 *
 *  use Webfactory\Bundle\PolyglotBundle\Annotation as Polyglot;
 *  use Webfactory\Bundle\PolyglotBundle\Translatable;
 *
 *  class MyClass { ...
 *     // @ Polyglot\Translatable
 *     protected $aField;
 *     public function __construct() {...
 *       $aField = new Translatable();
 */
class Translatable implements TranslatableInterface
{
    /**
     * @var string
     */
    protected $defaultLocale;

    /**
     * Maps locales to translations.
     *
     * @var array<string, string>
     */
    protected $translations = [];

    /**
     * @param string|null                       $value
     * @param string|DefaultLocaleProvider|null $defaultLocale
     */
    public function __construct($value = null, $defaultLocale = null)
    {
        if (null !== $defaultLocale && !\is_string($defaultLocale) && !$defaultLocale instanceof DefaultLocaleProvider) {
            throw new InvalidArgumentException('When provided, the $defaultLocale argument must either be a string or an instance of DefaultLocaleProvider');
        }

        $this->defaultLocale = $defaultLocale;
        $this->setTranslation($value);
    }

    /**
     * @param string $locale
     */
    public function setDefaultLocale($locale)
    {
        $oldLocale = $this->getDefaultLocale();
        $this->defaultLocale = $locale;
        $newLocale = $this->getDefaultLocale();

        if ('' == $oldLocale && '' != $newLocale) {
            $this->translations[$newLocale] = $this->translations[$oldLocale];
            unset($this->translations[$oldLocale]);
        }
        $this->defaultLocale = $locale;
    }

    public function translate(string $locale = null): mixed
    {
        $locale = $locale ?: $this->getDefaultLocale();

        if (isset($this->translations[$locale])) {
            return $this->translations[$locale];
        } else {
            return null;
        }
    }

    public function setTranslation(mixed $value, string $locale = null): void
    {
        $locale = $locale ?: $this->getDefaultLocale();

        $this->translations[$locale] = $value;
    }

    public function isTranslatedInto(string $locale): bool
    {
        return isset($this->translations[$locale]) && !empty($this->translations[$locale]);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return (string) $this->translate();
    }

    /**
     * Copies translations from this object into the given one.
     */
    public function copy(TranslatableInterface $p)
    {
        foreach ($this->translations as $locale => $value) {
            $p->setTranslation($value, '' == $locale ? null : $locale);
        }
    }

    private function getDefaultLocale()
    {
        return (string) $this->defaultLocale;
    }
}
