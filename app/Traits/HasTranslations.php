<?php

namespace App\Traits;

trait HasTranslations
{
    public function getTranslatedAttribute($attribute, $locale = null): ?string
    {
        if (! $locale) {
            $locale = app()->getLocale();
        }

        $translations = $this->getAttribute($attribute);

        if (! is_array($translations)) {
            return $translations;
        }

        return $translations[$locale] ?? $translations['en'] ?? null;
    }

    protected function setTranslatedAttribute($attribute, $value, $locale = null)
    {
        if (! $locale) {
            $locale = app()->getLocale();
        }

        $translations = $this->getAttribute($attribute) ?? [];

        if (! is_array($translations)) {
            $translations = [];
        }

        $translations[$locale] = $value;
        $this->setAttribute($attribute, $translations);
    }

    public function getAttribute($key)
    {
        $value = parent::getAttribute($key);

        if ($this->isTranslatableAttribute($key)) {
            if (is_string($value)) {
                return json_decode($value, true) ?: [];
            }

            return is_array($value) ? $value : [];
        }

        return $value;
    }

    public function setAttribute($key, $value)
    {
        if ($this->isTranslatableAttribute($key) && is_array($value)) {
            $value = json_encode($value);
        }

        return parent::setAttribute($key, $value);
    }

    protected function isTranslatableAttribute(string $key): bool
    {
        return in_array($key, $this->translatable ?? []);
    }
}
