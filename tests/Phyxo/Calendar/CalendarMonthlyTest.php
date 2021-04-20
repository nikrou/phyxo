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

namespace App\Tests\Phyxo\Calendar;

use App\Repository\AlbumRepository;
use App\Repository\ImageRepository;
use PHPUnit\Framework\TestCase;

class CalendarMonthlyTest extends TestCase
{
    public function testCalendar(): void
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

        $image_repository = $this->prophesize(ImageRepository::class);
        $album_repository = $this->prophesize(AlbumRepository::class);

        $calendar = new \Phyxo\Calendar\CalendarMonthly($image_repository->reveal(), $album_repository->reveal(), 'created');
        $calendar->setViewType('list'); // or calendar
        $chronology_date[] = 2019;
        $chronology_date[] = 10;
        $chronology_date[] = 20;
        $calendar->setChronologyDate($chronology_date);

        $this->assertEquals($calendar->date_field, 'date_creation');
        $this->assertEquals($calendar->getCalendarLevels(), $calendarLevels);
    }
}
