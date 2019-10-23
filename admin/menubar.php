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

use App\Repository\ConfigRepository;
use Phyxo\Block\BlockManager;

function abs_fn_cmp($a, $b)
{
    return abs($a) - abs($b);
}

function make_consecutive(array $blocks = [], array $orders = [], $step = 50): array
{
    uasort($orders, function($a, $b) {
        return abs($a) - abs($b);
    });

    $idx = 1;
    foreach ($blocks as $id => $block) {
        if (!isset($orders[$id])) {
            $orders[$id] = $idx * 50;
        }
        $idx++;
    }

    $crt = 1;
    foreach ($orders as $id => $pos) {
        $orders[$id] = $step * ($pos < 0 ? -$crt : $crt);
        $crt++;
    }

    return $orders;
}

if (isset($_POST['submit'])) {
    $menu = new BlockManager('menubar');
    $menu->loadDefaultBlocks();
    $reg_blocks = $menu->getRegisteredBlocks();

    if (!empty($conf['blk_menubar'])) {
        $menu->loadMenuConfig(json_decode($conf['blk_menubar'], true));
        $mb_conf = json_decode($conf['blk_menubar'], true);
    } else {
        $mb_conf = [];
    }

    foreach ($mb_conf as $id => $pos) {
        $hide = isset($_POST['hide_' . $id]);
        $mb_conf[$id] = ($hide ? -1 : +1) * abs($pos);

        if ($pos = $_POST['pos_' . $id]) {
            $mb_conf[$id] = $mb_conf[$id] > 0 ? $pos : -$pos;
        }
    }
    $mb_conf = make_consecutive($reg_blocks, $mb_conf);
    $em->getRepository(ConfigRepository::class)->addOrUpdateParam('blk_' . $menu->getId(), $mb_conf);

    $page['infos'][] = \Phyxo\Functions\Language::l10n('Order of menubar items has been updated successfully.');
} else {
    $menu = new BlockManager('menubar');
    $menu->loadDefaultBlocks();
    $menu->loadRegisteredBlocks();
    if (!empty($conf['blk_menubar'])) {
        $menu->loadMenuConfig(json_decode($conf['blk_menubar'], true));
        $mb_conf = json_decode($conf['blk_menubar'], true);
    } else {
        $mb_conf = [];
    }
    $menu->prepareDisplay();
    $reg_blocks = $menu->getRegisteredBlocks();
    $mb_conf = make_consecutive($reg_blocks, $mb_conf);
    if (empty($conf['blk_menubar'])) {
        $em->getRepository(ConfigRepository::class)->addOrUpdateParam('blk_' . $menu->getId(), $mb_conf);
    }
}

$blocks = [];
foreach ($mb_conf as $id => $pos) {
    $blocks[] = [
        'pos' => $pos / 5,
        'reg' => $reg_blocks[$id]
    ];
}

$action = \Phyxo\Functions\URL::get_root_url() . 'admin/index.php?page=menubar';
$template->assign([
    'F_ACTION' => $action,
    'blocks' => $blocks
]);

$template_filename = 'menubar';
