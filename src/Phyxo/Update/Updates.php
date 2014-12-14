<?php
// +-----------------------------------------------------------------------+
// | Phyxo - Another web based photo gallery                               |
// | Copyright(C) 2014 Nicolas Roudaire              http://www.phyxo.net/ |
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

namespace Phyxo\Update;

use Phyxo\Plugin\Plugins;
use Phyxo\Theme\Themes;
use Phyxo\Language\Languages;
use PclZip;
use GuzzleHttp\Client;

class Updates
{
    private $versions = array(), $version = array();

    public function __construct($url) {
        $this->update_url = $url;
    }

    public function getAllVersions() {
        try {
            $client = new Client();
            $request = $client->createRequest('GET', $this->update_url);
            $response = $client->send($request);
            if ($response->getStatusCode()==200 && $response->getBody()->isReadable()) {
                $this->versions = json_decode($response->getBody(), true);
                return $this->versions;
            } else {
                return false;
            }
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function upgradeTo($version, $release='stable') {
        foreach ($this->versions as $v) {
            if ($v['version']==$version && $v['release']==$release) {
                $this->version = $v;
            }
        }
    }

    public function download($zip_file) {
        @mkgetdir(dirname($zip_file)); // @TODO: remove arobase and use a fs library

        try {
            $client = new Client();
            $request = $client->createRequest('GET', $this->getFileURL());
            $response = $client->send($request);
            if ($response->getStatusCode()==200 && $response->getBody()->isReadable()) {
                file_put_contents($zip_file, $response->getBody());
            }
        } catch (\Exception $e) {

        }
    }

    public function getFileURL() {
        return $this->version['href'];
    }

    public function upgrade($zip_file) {
        $zip = new PclZip($zip_file);
        $zip_files = array();
		$not_writable = array();
        $root = PHPWG_ROOT_PATH;

        foreach ($zip->listContent() as $file) {
            $filename = str_replace('phyxo/', '', $file['filename']);
            $dest = $dest_dir = $root.'/'.$filename;
			while (!is_dir($dest_dir = dirname($dest_dir)));

			if ((file_exists($dest) && !is_writable($dest)) ||
                (!file_exists($dest) && !is_writable($dest_dir))) {
				$not_writable[] = $filename;
				continue;
			}
        }
        if (!empty($not_writable)) {
            $e = new \Exception('Some files or directories are not writable');
            $e->not_writable = $not_writable;
            throw $e;
        }

        // @TODO: remove arobase ; extract try to make a touch on every file but sometimes failed.
        $result = @$zip->extract(PCLZIP_OPT_PATH, PHPWG_ROOT_PATH,
                                PCLZIP_OPT_REMOVE_PATH, 'phyxo',
                                PCLZIP_OPT_SET_CHMOD, 0755,
                                PCLZIP_OPT_REPLACE_NEWER
        );
    }
}
