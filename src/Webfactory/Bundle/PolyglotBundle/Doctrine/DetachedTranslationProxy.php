<?php

namespace Webfactory\Bundle\PolyglotBundle\Doctrine;

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
 */
class DetachedTranslationProxy implements TranslationProxy {

    protected $defaultLocale;
    protected $translations = array();

    public function __construct($value = null, $defaultLocale = null) {
        $this->defaultLocale = $defaultLocale ?: '';
        $this->setTranslation($value);
    }

    public function setDefaultLocale($locale) {
        if ($this->defaultLocale == '') {
            $this->translations[$locale] = $this->translations[''];
            unset($this->translations['']);
        }
        $this->defaultLocale = $locale;
    }

    public function translate($locale = null) {
        $locale = $locale ?: $this->defaultLocale;

        if (isset($this->translations[$locale])) {
            return $this->translations[$locale];
        } else return null;
    }

    public function setTranslation($value, $locale = null) {
        $locale = $locale ?: $this->defaultLocale;

        $this->translations[$locale] = $value;
    }

    public function __toString() {
        return $this->translate();
    }

    public function copy(TranslationProxy $p) {
        foreach ($this->translations as $locale => $value) {
            $p->setTranslation($value, ($locale == '' ? null : $locale));
        }
    }
}

