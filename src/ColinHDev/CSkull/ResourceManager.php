<?php

namespace ColinHDev\CSkull;

use pocketmine\lang\Language;
use pocketmine\utils\Config;
use pocketmine\utils\SingletonTrait;

class ResourceManager {
    use SingletonTrait;

    private Config $config;
    private Language $language;

    public function __construct() {
        CSkull::getInstance()->saveResource("config.yml");
        CSkull::getInstance()->saveResource("language.ini");

        $this->config = new Config(CSkull::getInstance()->getDataFolder() . "config.yml", Config::YAML);
        $this->language = new Language("language", CSkull::getInstance()->getDataFolder(), "language");
    }

    public function getPrefix() : string {
        return $this->language->get("prefix");
    }

    /**
     * @param string[] $params
     */
    public function translateString(string $str, array $params = []) : string {
        if (empty($params)) {
            return $this->language->get($str);
        }
        return $this->language->translateString($str, $params);
    }

    public function getConfig() : Config {
        $this->config->reload();
        return $this->config;
    }
}