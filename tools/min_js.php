#!/usr/bin/env php
<?php
// +-----------------------------------------------------------------------+
// | Phyxo - Another web based photo gallery                               |
// | Copyright(C) 2014-2016 Nicolas Roudaire         http://www.phyxo.net/ |
// +-----------------------------------------------------------------------+
// | This program is free software; you can redistribute it and/or modify  |
// | it under the terms of the GNU General Public License version 2 as     |
// | published by the Free Software Foundation                             |
// |                                                                       |
// | This program is distributed in the hope that it will be useful, but   |
// | WITHOUT ANY WARRANTY; without even the implied warranty of            |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU      |
// | General Public License for more details.                              |
// |                                                                       |
// | You should have received a copy of the GNU General Public License     |
// | along with this program; if not, write to the Free Software           |
// | Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,            |
// | MA 02110-1301 USA.                                                    |
// +-----------------------------------------------------------------------+


try {
	$js = (!empty($_SERVER['argv'][1])) ? $_SERVER['argv'][1] : null;

	if (!$js || !is_file($js)) {
		throw new Exception(sprintf("File %s does not exist", $js));
	}

    include_once(__DIR__.'/jsmin-1.1.1.php');

	$content = file_get_contents($js);
	$res = JSMin::minify($content);

	if (($fp = fopen($js,'wb')) === false) {
		throw new Exception(sprintf('Unable to open file %s', $js));
	}
	fwrite($fp,$res);
	fclose($fp);
} catch (Exception $e) {
	fwrite(STDERR, $e->getMessage()."\n");
	exit(1);
}
