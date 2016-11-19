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

namespace Phyxo\Extension;

use GuzzleHttp\Client;
use PclZip;
use Alchemy\Zippy\Zippy;

class Extensions
{
    protected $directory_pattern = '';

    public function getJsonFromServer($url, $params) {
        if (empty($params['format']) || $params['format']!=='json') {
            $params['format'] = 'json';
        }

        try {
            $client = new Client();
            $request = $client->createRequest('GET', $url);
            $request->setHeader('User-Agent', 'Phyxo');
            $query = $request->getQuery();
            foreach ($params as $key => $value) {
                $query->set($key, $value);
            }
            $response = $client->send($request);
            if ($response->getStatusCode()==200 && $response->getBody()->isReadable()) {
                return json_decode($response->getBody(), true);
            } else {
                throw new \Exception($e->getMessage());
            }
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function download($params=array(), $filename) {
        $url = PEM_URL . '/download.php';
        try {
            $client = new Client();
            $request = $client->createRequest('GET', $url);
            $request->setHeader('User-Agent', 'Phyxo');
            if (!empty($params)) {
                $query = $request->getQuery();
                foreach ($params as $key => $value) {
                    $query->set($key, $value);
                }
            }
            $response = $client->send($request);
            if ($response->getStatusCode()==200 && $response->getBody()->isReadable()) {
                file_put_contents($filename, $response->getBody());
            } else {
                throw new \Exception($e->getMessage());
            }
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    protected function extractZipFiles($zip_file, $main_file, $extract_path='') {
        $zip = new PclZip($zip_file);
        if ($list = $zip->listContent()) {
            // find main file
            foreach ($list as $file) {
                if (basename($file['filename']) === $main_file && (!isset($main_filepath) || strlen($file['filename']) < strlen($main_filepath))) {
                    $main_filepath = $file['filename'];
                }
            }

            if (!empty($main_filepath)) {
                $root = basename(dirname($main_filepath)); // dirname($main_filepath) cannot be null throw Exception if needed
                $extract_path .= '/'.$root;

                if (!empty($this->directory_pattern)) {
                    if (!preg_match($this->directory_pattern, $root)) {
                        throw new \Exception(sprintf('Root directory (%s) of archive does not follow expected pattern %s', $root, $this->directory_pattern));
                    }
                }

                if ($results = $zip->extract(PCLZIP_OPT_PATH, $extract_path, PCLZIP_OPT_REMOVE_PATH, $root, PCLZIP_OPT_REPLACE_NEWER)) {
                    $errors = array_filter($results, function($f) { return ($f['status'] !== 'ok' && $f['status']!=='filtered') && $f['status']!=='already_a_directory'; });
                    if (count($errors)>0) {
                        throw new \Exception("Error while extracting some files from archive");
                    }
                } else {
                    throw new \Exception("Error while extracting archive");
                }
            }
        } else {
            throw new \Exception("Can't read or extract archive.");
        }
    }
}
