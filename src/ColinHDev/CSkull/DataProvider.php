<?php

namespace ColinHDev\CSkull;

use pocketmine\utils\SingletonTrait;
use poggit\libasynql\DataConnector;
use poggit\libasynql\libasynql;

class DataProvider {
    use SingletonTrait;

    private DataConnector $database;

    private const INIT_PLAYERS_TABLE = "cskull.init.playersTable";
    private const INIT_SKULLS_TABLE = "cskull.init.skullsTable";
    private const GET_PLAYER_BY_PREFIX = "cskull.get.playerByPrefix";
    private const GET_SHOW_SKULLS_BY_UUID = "cskull.get.showSkullsByUUID";
    private const GET_LAST_COMMAND_USE_BY_UUID = "cskull.get.lastCommandUseByUUID";
    private const GET_SKULLS_BY_CHUNK = "cskull.get.skullsByChunk";
    private const SET_SKIN_DATA = "cskull.set.skinData";
    private const SET_SHOW_SKULLS = "cskull.set.showSkulls";
    private const SET_LAST_COMMAND_USE = "cskull.set.lastCommandUse";
    private const SET_SKULL = "cskull.set.skull";
    private const DELETE_SKULL_BY_POSITION = "cskull.delete.skullByPosition";

    public function __construct() {
        $this->database = libasynql::create(CSkull::getInstance(), ResourceManager::getInstance()->getConfig()->get("database"), [
            "sqlite" => "sqlite.sql",
            "mysql" => "mysql.sql"
        ]);
        $this->database->executeGeneric(self::INIT_PLAYERS_TABLE);
        $this->database->executeGeneric(self::INIT_SKULLS_TABLE);
    }

    public function getPlayerByPrefix(string $playerPrefix, ?\Closure $onSuccess = null, ?\Closure $onFailure = null) : void {
        $this->database->executeSelect(
            self::GET_PLAYER_BY_PREFIX,
            [
                "playerPrefix" => $playerPrefix . "%"
            ],
            $onSuccess,
            $onFailure
        );
    }

    public function getShowSkullsByUUID(string $playerUUID, ?\Closure $onSuccess = null, ?\Closure $onFailure = null) : void {
        $this->database->executeSelect(
            self::GET_SHOW_SKULLS_BY_UUID,
            [
                "playerUUID" => $playerUUID
            ],
            $onSuccess,
            $onFailure
        );
    }

    public function getLastCommandUseByUUID(string $playerUUID, ?\Closure $onSuccess = null, ?\Closure $onFailure = null) : void {
        $this->database->executeSelect(
            self::GET_LAST_COMMAND_USE_BY_UUID,
            [
                "playerUUID" => $playerUUID
            ],
            $onSuccess,
            $onFailure
        );
    }

    public function getSkullsByChunk(string $worldName, int $chunkX, int $chunkZ, ?\Closure $onSuccess = null, ?\Closure $onFailure = null) : void {
        $this->database->executeSelect(
            self::GET_SKULLS_BY_CHUNK,
            [
                "worldName" => $worldName,
                "chunkX" => $chunkX,
                "chunkZ" => $chunkZ
            ],
            $onSuccess,
            $onFailure
        );
    }

    public function setSkinData(string $playerUUID, string $playerName, string $skinData, ?\Closure $onSuccess = null, ?\Closure $onFailure = null) : void {
        $this->database->executeInsert(
            self::SET_SKIN_DATA,
            [
                "playerUUID" => $playerUUID,
                "playerName" => $playerName,
                "skinData" => $skinData
            ],
            $onSuccess,
            $onFailure
        );
    }

    public function setShowSkulls(string $playerUUID, bool $showSkulls, ?\Closure $onSuccess = null, ?\Closure $onFailure = null) : void {
        $this->database->executeChange(
            self::SET_SHOW_SKULLS,
            [
                "playerUUID" => $playerUUID,
                "showSkulls" => $showSkulls,
            ],
            $onSuccess,
            $onFailure
        );
    }

    public function setLastCommandUse(string $playerUUID, string $lastCommandUse, ?\Closure $onSuccess = null, ?\Closure $onFailure = null) : void {
        $this->database->executeChange(
            self::SET_LAST_COMMAND_USE,
            [
                "playerUUID" => $playerUUID,
                "lastCommandUse" => $lastCommandUse,
            ],
            $onSuccess,
            $onFailure
        );
    }

    public function setSkull(string $worldName, int $x, int $y, int $z, string $playerUUID, string $skinData, ?\Closure $onSuccess = null, ?\Closure $onFailure = null) : void {
        $this->database->executeInsert(
            self::SET_SKULL,
            [
                "worldName" => $worldName,
                "x" => $x,
                "y" => $y,
                "z" => $z,
                "playerUUID" => $playerUUID,
                "skinData" => $skinData
            ],
            $onSuccess,
            $onFailure
        );
    }

    public function deleteSkullByPosition(string $worldName, int $x, int $y, int $z, ?\Closure $onSuccess = null, ?\Closure $onFailure = null) : void {
        $this->database->executeChange(
            self::DELETE_SKULL_BY_POSITION,
            [
                "worldName" => $worldName,
                "x" => $x,
                "y" => $y,
                "z" => $z
            ],
            $onSuccess,
            $onFailure
        );
    }

    public function close() : void {
        $this->database->close();
    }
}