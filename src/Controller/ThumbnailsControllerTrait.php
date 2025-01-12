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

namespace App\Controller;

use Symfony\Component\Routing\RouterInterface;

trait ThumbnailsControllerTrait
{
    /**
     * @param array<string, string|int> $query_params
     *
     * @return array{CURRENT_PAGE:int|float, URL_FIRST:string, URL_PREV:string, pages:array<string>}
     */
    public function defineNavigation(RouterInterface $router, string $route, array $query_params, int $nb_elements, int $start, int $nb_element_page, int $pages_around = 2): array
    {
        $navbar = [];
        $start_param = 'start';

        if ($nb_elements > $nb_element_page) {
            $cur_page = $navbar['CURRENT_PAGE'] = $start / $nb_element_page + 1;
            $maximum = (int) ceil($nb_elements / $nb_element_page);

            $start = (int) ceil($nb_element_page * ($start / $nb_element_page));
            $previous = $start - $nb_element_page;
            $next = $start + $nb_element_page;
            $last = ($maximum - 1) * $nb_element_page;

            if ($cur_page > 1) {
                $navbar['URL_FIRST'] = $router->generate($route, $query_params);
                $navbar['URL_PREV'] = $router->generate($route, array_merge($query_params, [$start_param => $previous]));
            }

            if ($cur_page < $maximum) {
                $navbar['URL_NEXT'] = $router->generate($route, array_merge($query_params, [$start_param => $next < $last ? $next : $last]));
                $navbar['URL_LAST'] = $router->generate($route, array_merge($query_params, [$start_param => $last]));
            }

            $navbar['pages'] = [];
            $navbar['pages'][1] = $router->generate($route, array_merge($query_params, [$start_param => 0]));
            for ($i = max(floor($cur_page) - $pages_around, 2), $stop = min(ceil($cur_page) + $pages_around + 1, $maximum); $i < $stop; $i++) {
                $navbar['pages'][$i] = $router->generate($route, array_merge($query_params, [$start_param => (($i - 1) * $nb_element_page)]));
            }

            $navbar['pages'][$maximum] = $router->generate($route, array_merge($query_params, [$start_param => $last]));
        }

        return $navbar;
    }
}
