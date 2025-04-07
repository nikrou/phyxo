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

namespace Phyxo\Extension;

use Exception;
use PclZip;
use Symfony\Component\HttpClient\HttpClient;

class Extensions
{
    final public const array TYPES = ['plugins', 'themes', 'languages'];
    protected string $directory_pattern = '';
    protected string $pem_url;

    /**
     * @param array<string, string> $params
     *
     * @return array<string, mixed>
     */
    public function getJsonFromServer(string $url, $params = []): array
    {
        try {
            $client = HttpClient::create(['headers' => ['User-Agent' => 'Phyxo']]);
            $response = $client->request('GET', $url, ['query' => $params]);
            if ($response->getStatusCode() === 200 && $response->getContent()) {
                return json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
            } else {
                throw new Exception('Response is not readable');
            }
        } catch (Exception $exception) {
            throw new Exception($exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    public function setExtensionsURL(string $url): void
    {
        $this->pem_url = $url;
    }

    public function getExtensionsURL(): string
    {
        return $this->pem_url;
    }

    /**
     * @return array<string, mixed>
     */
    public function getFsExtensions(): array
    {
        return [];
    }

    /**
     * @param array<string, string> $params
     */
    public function download(string $filename, $params = []): void
    {
        $url = $this->pem_url . '/download.php';

        try {
            $client = HttpClient::create(['headers' => ['User-Agent' => 'Phyxo']]);
            $response = $client->request('GET', $url, ['query' => $params]);
            if ($response->getStatusCode() === 200 && $response->getContent()) {
                file_put_contents($filename, $response->getContent());
            } else {
                throw new Exception('Response is not readable');
            }
        } catch (Exception $exception) {
            throw new Exception($exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    protected function extractZipFiles(string $zip_file, string $main_file, string $extract_path = ''): string
    {
        $zip = new PclZip($zip_file);
        if ($list = $zip->listContent()) {
            $root = '.';

            // find main file
            foreach ($list as $file) {
                if (isset($file['filename']) && basename((string) $file['filename']) === $main_file && (!isset($main_filepath) || strlen((string) $file['filename']) < strlen((string) $main_filepath))) {
                    $main_filepath = $file['filename'];
                }
            }

            if (!empty($main_filepath)) {
                $root = basename(dirname((string) $main_filepath)); // dirname($main_filepath) cannot be null throw Exception if needed
                $extract_path .= '/' . $root;

                if ($this->directory_pattern !== '' && $this->directory_pattern !== '0' && !preg_match($this->directory_pattern, $root)) {
                    throw new Exception(sprintf('Root directory (%s) of archive does not follow expected pattern %s', $root, $this->directory_pattern));
                }

                // @TODO: use native zip library ; use arobase before
                if ($results = @$zip->extract(PCLZIP_OPT_PATH, $extract_path, PCLZIP_OPT_REMOVE_PATH, $root, PCLZIP_OPT_REPLACE_NEWER)) {
                    $errors = array_filter($results, fn ($f): bool => ($f['status'] !== 'ok' && $f['status'] !== 'filtered') && $f['status'] !== 'already_a_directory');
                    if ($errors !== []) {
                        throw new Exception('Error while extracting some files from archive');
                    }
                } else {
                    throw new Exception('Error while extracting archive');
                }
            }

            return $root;
        } else {
            throw new Exception("Can't read or extract archive.");
        }
    }
}
