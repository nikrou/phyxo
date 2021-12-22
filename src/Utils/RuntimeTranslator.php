<?php
/*
 * This file is part of Phyxo package
 *
 * Copyright(c) Nicolas Roudaire  https://www.phyxo.net/
 * Licensed under the GPL version 2.0 license.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Utils;

use Symfony\Component\Translation\Formatter\MessageFormatterInterface;
use Symfony\Component\Translation\Loader\PhpFileLoader;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\MessageCatalogueInterface;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class RuntimeTranslator implements TranslatorInterface, TranslatorBagInterface, LocaleAwareInterface
{
    private $innerTranslator, $cache, $formatter, $loader, $runtimeResources = [];

    public function __construct($translator, CacheInterface $cache, MessageFormatterInterface $formatter)
    {
        $this->innerTranslator = $translator;
        $this->cache = $cache;
        $this->formatter = $formatter;
        $this->loader = new PhpFileLoader();
    }

    public function __call($method, $args)
    {
        return $this->innerTranslator->{$method}(...$args);
    }

    public function getCatalogue($locale = null): MessageCatalogueInterface
    {
        return $this->innerTranslator->getCatalogue($locale);
    }

    public function getCatalogues(): array
    {
        return $this->innerTranslator->getCatalogues();
    }

    public function setLocale($locale)
    {
        $this->innerTranslator->setLocale($locale);
    }

    public function getLocale(): string
    {
        return $this->innerTranslator->getLocale();
    }

    public function trans($id, array $parameters = [], $domain = null, $locale = null): string
    {
        if ($locale === null) {
            $locale = $this->getLocale();
        }

        if ($domain === null) {
            $domain = 'messages';
        }

        $runtimeCatalogue = $this->getRuntimeCatalogue($locale);
        if ($runtimeCatalogue->defines($id, $domain) === false) {
            return $this->innerTranslator->trans(...func_get_args());
        }

        return $this->formatter->format($runtimeCatalogue->get($id, $domain), $locale, $parameters);
    }

    public function addRuntimeResource($format, $resource, $locale, $domain = 'null')
    {
        if ($format !== 'php') {
            return;
        }

        $this->runtimeResources[] = [$format, $resource, $locale, $domain];
    }

    private function getRuntimeCatalogue($locale)
    {
        $catalogue = new MessageCatalogue($locale);
        $runtimeResources = array_filter($this->runtimeResources, function($item) use ($locale) {
            return $item[2] === $locale;
        });

        foreach ($runtimeResources as [$format, $resource, $locale, $domain]) {
            $cacheKey = str_replace('/', '_', implode('', [$resource, $locale, $domain]));
            $runtimeCatalogue = $this->cache->get($cacheKey, function(ItemInterface $item) use ($resource, $locale, $domain) {
                return $this->loader->load($resource, $locale, $domain);
            });
            $catalogue->addCatalogue($runtimeCatalogue);
        }

        return $catalogue;
    }
}
