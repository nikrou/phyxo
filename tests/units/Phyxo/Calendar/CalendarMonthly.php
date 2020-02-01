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

use mageekguy\atoum;
use Prophecy\Argument;
use Prophecy\Prophet;

class CalendarMonthly extends atoum\test
{
    public function testCalendar()
    {
        $calendarLevels = [
            [
                'sql' => 'EXTRACT(YEAR FROM date_creation)',
                'labels' => []
            ],
            [
                'sql' => 'EXTRACT(MONTH FROM date_creation)',
                'labels' => [1 => "January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"]
            ],
            [
                'sql' => 'EXTRACT(DAY FROM date_creation)',
                'labels' => []
            ]
        ];

        $prophet = new Prophet();
        $conn = $prophet->prophesize('\Phyxo\DBLayer\iDBLayer');
        $conn->db_get_year(Argument::type('string'))->will(function($args) {
            return 'EXTRACT(YEAR FROM ' . $args[0] . ')';
        });
        $conn->db_get_month(Argument::type('string'))->will(function($args) {
            return 'EXTRACT(MONTH FROM ' . $args[0] . ')';
        });
        $conn->db_get_dayofmonth(Argument::type('string'))->will(function($args) {
            return 'EXTRACT(DAY FROM ' . $args[0] . ')';
        });

        $calendar_repository = $prophet->prophesize('App\Repository\CalendarRepository');

        $calendar = new \Phyxo\Calendar\CalendarMonthly($conn->reveal(), $calendar_repository->reveal(), 'created');
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
