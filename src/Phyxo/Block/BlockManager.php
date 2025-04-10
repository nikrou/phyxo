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

namespace Phyxo\Block;

use App\Events\BlockEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class BlockManager
{
    /** @var array<string, mixed> */
    private array $menuBlockConfig = [];

    /** @var RegisteredBlock[] */
    private array $registered_blocks = [];

    /** @var array<int, DisplayBlock> */
    private array $display_blocks = [];

    public function __construct(private readonly string $id)
    {
    }

    /**
     * @param array<string, mixed> $menuBlockConfig
     */
    public function loadMenuConfig(array $menuBlockConfig = []): void
    {
        $this->menuBlockConfig = $menuBlockConfig;
    }

    public function loadDefaultBlocks(): void
    {
        $this->registerBlock(new RegisteredBlock('mbLinks', 'Links', 'core'));
        $this->registerBlock(new RegisteredBlock('mbCategories', 'Albums', 'core'));
        $this->registerBlock(new RegisteredBlock('mbTags', 'Related tags', 'core'));
        $this->registerBlock(new RegisteredBlock('mbSpecials', 'Specials', 'core'));
        $this->registerBlock(new RegisteredBlock('mbMenu', 'Menu', 'core'));
        $this->registerBlock(new RegisteredBlock('mbIdentification', 'Identification', 'core'));
    }

    /**
     * Triggers a notice that allows plugins of menu blocks to register the blocks.
     */
    public function loadRegisteredBlocks(?EventDispatcherInterface $eventDispatcher = null): void
    {
        if (!is_null($eventDispatcher)) {
            $eventDispatcher->dispatch(new BlockEvent($this));
        }
    }

    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return RegisteredBlock[]
     */
    public function getRegisteredBlocks(): array
    {
        return $this->registered_blocks;
    }

    public function registerBlock(RegisteredBlock $block): void
    {
        if (!isset($this->registered_blocks[$block->getId()])) {
            $this->registered_blocks[$block->getId()] = $block;
        }
    }

    /**
     * Performs one time preparation of registered blocks for display.
     * Triggers 'blockmanager_prepare_display' event where plugins can
     * reposition or hide blocks.
     */
    public function prepareDisplay(): void
    {
        $idx = 1;
        foreach ($this->registered_blocks as $id => $block) {
            $pos = $this->menuBlockConfig[$id] ?? $idx * 50;
            if ($pos > 0) {
                $this->display_blocks[$id] = new DisplayBlock($block);
                $this->display_blocks[$id]->setPosition($pos);
                $block->applyData($this->display_blocks[$id]);
            }

            $idx++;
        }

        $this->sortBlocks();
    }

    public function isHidden(int $block_id): bool
    {
        return !isset($this->display_blocks[$block_id]);
    }

    public function hideBlock(int $block_id): void
    {
        unset($this->display_blocks[$block_id]);
    }

    public function getBlock(string $block_id): ?DisplayBlock
    {
        return $this->display_blocks[$block_id] ?? null;
    }

    public function setBlockPosition(string $block_id, int $position): void
    {
        if (isset($this->display_blocks[$block_id])) {
            $this->display_blocks[$block_id]->setPosition($position);
        }
    }

    protected function sortBlocks(): void
    {
        uasort($this->display_blocks, $this->cmp_by_position(...));
    }

    /**
     * Callback for blocks sorting.
     */
    protected static function cmp_by_position(DisplayBlock $a, DisplayBlock $b): int
    {
        return $a->getPosition() - $b->getPosition();
    }

    /**
     * @return DisplayBlock[]
     */
    public function getDisplayBlocks(): array
    {
        foreach ($this->display_blocks as $id => $block) {
            if (empty($block->getTemplate())) {
                $this->hideBlock((int) $id);
            }
        }

        $this->sortBlocks();

        return $this->display_blocks;
    }
}
