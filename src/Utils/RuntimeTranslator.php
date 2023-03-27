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
    private readonly PhpFileLoader $loader;

    /** @var array<mixed> $runtimeResources */
    private array $runtimeResources = [];

    /**
     * @param TranslatorBagInterface|TranslatorInterface|LocaleAwareInterface $translator
     */
    public function __construct(private $translator, private readonly CacheInterface $cache, private readonly MessageFormatterInterface $formatter)
    {
        $this->loader = new PhpFileLoader();
    }

    public function __call(string $method, mixed $args): mixed
    {
        return $this->translator->{$method}(...$args);
    }

    public function getCatalogue($locale = null): MessageCatalogueInterface
    {
        return $this->translator->getCatalogue($locale);
    }

    /**
     * @return MessageCatalogueInterface[]
     */
    public function getCatalogues(): array
    {
        return $this->translator->getCatalogues();
    }

    public function setLocale(string $locale): void
    {
        $this->translator->setLocale($locale);
    }

    public function getLocale(): string
    {
        return $this->translator->getLocale();
    }

    /**
     * @param array<string, string> $parameters
     */
    public function trans(string $id, array $parameters = [], string $domain = null, string $locale = null): string
    {
        if ($locale === null) {
            $locale = $this->getLocale();
        }

        if ($domain === null) {
            $domain = 'messages';
        }

        $runtimeCatalogue = $this->getRuntimeCatalogue($locale);
        if ($runtimeCatalogue->defines($id, $domain) === false) {
            return $this->translator->trans(...func_get_args());
        }

        return $this->formatter->format($runtimeCatalogue->get($id, $domain), $locale, $parameters);
    }

    public function addRuntimeResource(string $format, string $resource, string $locale, string $domain = null): void
    {
        if ($format !== 'php') {
            return;
        }

        $this->runtimeResources[] = [$format, $resource, $locale, $domain];
    }

    private function getRuntimeCatalogue(string $locale): MessageCatalogueInterface
    {
        $catalogue = new MessageCatalogue($locale);
        $runtimeResources = array_filter($this->runtimeResources, fn($item) => $item[2] === $locale);

        foreach ($runtimeResources as [$format, $resource, $locale, $domain]) {
            $cacheKey = str_replace('/', '_', implode('', [$resource, $locale, $domain]));
            $runtimeCatalogue = $this->cache->get($cacheKey, fn(ItemInterface $item) => $this->loader->load($resource, $locale, $domain));
            $catalogue->addCatalogue($runtimeCatalogue);
        }

        return $catalogue;
    }
}
