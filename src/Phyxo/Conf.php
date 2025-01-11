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

namespace Phyxo;

use App\Enum\ConfEnum;
use ArrayAccess;
use App\Entity\Config;
use App\Repository\ConfigRepository;
use Exception;

/**
 *  Manage configuration of Phyxo in two ways :
 *  - in php files (throught include/config_default.inc.php override by local/config/config.inc.php)
 *  - in database : can be update throught admin area.
 *
 *  Add prefix file_ or db_ to configuration keys to know if database update is needed
 *
 *  Do not allow direct save vi ArrayAccess::offsetSet because type cannot be retrieved
 *
 *  @implements ArrayAccess<string, mixed>
 */
class Conf implements ArrayAccess
{
    /** @var array<string, mixed> */
    private array $keys = [];
    final public const FILE_PREFIX = 'file_';
    final public const DB_PREFIX = 'db_';

    public function __construct(private readonly ConfigRepository $configRepository)
    {
    }

    public function init(string $default_config_file, string $user_config_file = ''): void
    {
        $this->loadFromFile($default_config_file);
        if ($user_config_file !== '') {
            $this->loadFromFile($user_config_file);
        }
        $this->loadFromDB();
    }

    public function loadFromFile(string $conf_file): void
    {
        if (!is_readable($conf_file)) {
            return;
        }

        $conf = [];
        // in case of unwanted output
        ob_start();
        require $conf_file;
        ob_end_clean();

        /** @phpstan-ignore-next-line */
        if ($conf !== []) {
            foreach ($conf as $key => $value) {
                $this->keys[self::FILE_PREFIX . $key] = ['value' => $value, 'type' => ConfEnum::STRING];
            }
            unset($conf);
        }
    }

    /**
     * Add configuration parameters from database to global $conf array
     */
    public function loadFromDB(): void
    {
        foreach ($this->configRepository->findAll() as $config) {
            $this->keys[self::DB_PREFIX . $config->getParam()] = [
                'value' => $this->dbToConf($config->getValue(), $config->getType()),
                'type' => $config->getType()
            ];
        }
    }

    /**
     * Add or update a config parameter
     */
    public function addOrUpdateParam(string $param, mixed $value, ConfEnum $type = ConfEnum::STRING): void
    {
        $config = $this->configRepository->findOneBy(['param' => $param]);
        if (is_null($config)) {
            $config = new Config();
            $config->setParam($param);
        }
        $config->setValue($this->confToDb($value, $type));
        $config->setType($type);

        $this->configRepository->addOrUpdateConfig($config);

        $this->keys[self::DB_PREFIX . $param]['value'] = $value;
    }

    public function dbToConf(?string $value, ConfEnum $type = ConfEnum::STRING): mixed
    {
        if (is_null($value)) {
            return null;
        } elseif ($type === ConfEnum::BOOLEAN) {
            return ($value === 'true');
        } elseif ($type === ConfEnum::INTEGER) {
            return (int) $value;
        } elseif ($type === ConfEnum::JSON) {
            return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } elseif ($type === ConfEnum::BASE64) {
            return unserialize(base64_decode($value));
        } else {
            return $value;
        }
    }

    protected function confToDb(mixed $value, ConfEnum $type = ConfEnum::STRING): ?string
    {
        if (is_null($value)) {
            return null;
        } elseif ($type === ConfEnum::BOOLEAN) {
            return ($value === true) ? 'true' : 'false';
        } elseif ($type === ConfEnum::BASE64) {
            return base64_encode(serialize($value));
        } elseif ($type === ConfEnum::JSON || is_array($value) || is_object($value)) {
            return json_encode($value, JSON_THROW_ON_ERROR);
        } else {
            return $value;
        }
    }

    public function offsetExists(mixed $param): bool
    {
        return isset($this->keys[self::FILE_PREFIX . $param]) || isset($this->keys[self::DB_PREFIX . $param]);
    }

    public function offsetGet(mixed $param): mixed
    {
        $value = null;

        if (isset($this->keys[self::FILE_PREFIX . $param]['value'])) {
            $value = $this->keys[self::FILE_PREFIX . $param]['value'];
        }

        if (isset($this->keys[self::DB_PREFIX . $param]['value'])) {
            $value = $this->keys[self::DB_PREFIX . $param]['value'];
        }

        return $value;
    }

    public function offsetSet(mixed $param, mixed $value): void
    {
        throw new Exception('Use Conf::addOrUpdateParam method');
    }

    public function offsetUnset(mixed $param): void
    {
        if (isset($this->keys[self::DB_PREFIX . $param])) {
            unset($this->keys[self::DB_PREFIX . $param]);
            $this->deleteParam($param);
        } elseif (isset($this->keys[self::FILE_PREFIX . $param])) {
            unset($this->keys[self::FILE_PREFIX . $param]);
        }
    }

    /**
     * Delete one or more config parameters
     *
     * @param string|string[] $params
     */
    protected function deleteParam(string|array $params): void
    {
        if (!is_array($params)) {
            $params = [$params];
        }

        if ($params === []) {
            return;
        }

        $this->configRepository->delete($params);

        foreach ($params as $param) {
            unset($this->keys[self::DB_PREFIX . $param]);
        }
    }
}
