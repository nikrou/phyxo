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

namespace tests\units\Phyxo\Calendar;

use atoum;
use Prophecy\Prophet;

class CalendarMonthly extends atoum
{
    public function testCalendar()
    {
        $calendarLevels = [
            [
                'sql' => 'date_creation',
                'labels' => []
            ],
            [
                'sql' => 'date_creation',
                'labels' => [1 => "January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"]
            ],
            [
                'sql' => 'date_creation',
                'labels' => []
            ]
        ];

        $prophet = new Prophet();
        $image_repository = $prophet->prophesize('App\Repository\ImageRepository');
        $album_repository = $prophet->prophesize('App\Repository\AlbumRepository');

        $calendar = new \Phyxo\Calendar\CalendarMonthly($image_repository->reveal(), $album_repository->reveal(), 'created');
        $calendar->setViewType('list'); // or calendar
        $chronology_date[] = 2019;
        $chronology_date[] = 10;
        $chronology_date[] = 20;
        $calendar->setChronologyDate($chronology_date);

        $this
            ->string($calendar->date_field)
            ->isIdenticalTo('date_creation')

            ->array($calendar->getCalendarLevels())
            ->isIdenticalTo($calendarLevels);
    }
}
