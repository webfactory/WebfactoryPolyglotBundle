<?php

/*
 * (c) webfactory GmbH <info@webfactory.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webfactory\Bundle\PolyglotBundle;

/**
 * Eine TranslationProxy-Implementierung für eine Entität, die
 * noch neu und dem EntityManager nicht bekannt ist. Diese Implementierung
 * kann von Klassen mit Translatable-Feldern im Konstruktor ohne weitere
 * Abhängigkeiten erzeugt werden.
 *
 * Wir können an dieser Stelle nicht auf die Reflection-
 * Mechanismen (TranslationMetadata) zurückgreifen, weil diese z. T. auch
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
    protected $translations = array();

    /**
     * @param string|null $value
     * @param string|null $defaultLocale
     */
    public function __construct($value = null, $defaultLocale = null)
    {
        $this->defaultLocale = $defaultLocale ? : '';
        $this->setTranslation($value);
    }

    /**
     * @param string $locale
     */
    public function setDefaultLocale($locale)
    {
        if ($this->defaultLocale == '') {
            $this->translations[$locale] = $this->translations[''];
            unset($this->translations['']);
        }
        $this->defaultLocale = $locale;
    }

    /**
     * @param string|null $locale
     * @return string|null
     */
    public function translate($locale = null)
    {
        $locale = $locale ? : $this->defaultLocale;

        if (isset($this->translations[$locale])) {
            return $this->translations[$locale];
        } else {
            return null;
        }
    }

    /**
     * @param string $value
     * @param string|null $locale
     */
    public function setTranslation($value, $locale = null)
    {
        $locale = $locale ? : $this->defaultLocale;

        $this->translations[$locale] = $value;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return (string)$this->translate();
    }

    /**
     * @return integer
     */
    public function count()
    {
        return mb_strlen((string)$this, 'UTF-8');
    }

    /**
     * Copies translations from this object into the given one.
     *
     * @param TranslatableInterface $p
     */
    public function copy(TranslatableInterface $p)
    {
        foreach ($this->translations as $locale => $value) {
            $p->setTranslation($value, ($locale == '' ? null : $locale));
        }
    }
}
