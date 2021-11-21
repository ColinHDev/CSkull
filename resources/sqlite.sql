-- # !sqlite

-- #{ cskull

-- #  { init
-- #    { playersTable
CREATE TABLE IF NOT EXISTS players(
    playerUUID      VARCHAR(256)    NOT NULL,
    playerName      VARCHAR(256)    NOT NULL,
    skinData        TEXT            NOT NULL,
    showSkulls      BOOLEAN         NOT NULL    DEFAULT true,
    lastCommandUse  TEXT                        DEFAULT NULL,
    PRIMARY KEY (playerUUID)
);
-- #    }
-- #    { skullsTable
CREATE TABLE IF NOT EXISTS skulls(
    worldName       VARCHAR(256)    NOT NULL,
    x               INTEGER         NOT NULL,
    y               INTEGER         NOT NULL,
    z               INTEGER         NOT NULL,
    playerUUID      VARCHAR(256)    NOT NULL,
    skinData        TEXT            NOT NULL,
    PRIMARY KEY (worldName, x, y, z),
    FOREIGN KEY (playerUUID) REFERENCES players(playerUUID) ON DELETE CASCADE
);
-- #    }
-- #  }

-- #  { get
-- #    { playerByPrefix
-- #      :playerPrefix string
SELECT playerUUID, skinData
FROM players
WHERE playerName LIKE :playerPrefix;
-- #    }
-- #    { showSkullsByUUID
-- #      :playerUUID string
SELECT showSkulls
FROM players
WHERE playerUUID = :playerUUID;
-- #    }
-- #    { lastCommandUseByUUID
-- #      :playerUUID string
SELECT lastCommandUse
FROM players
WHERE playerUUID = :playerUUID;
-- #    }
-- #    { skullsByChunk
-- #      :worldName string
-- #      :chunkX int
-- #      :chunkZ int
SELECT *, (
    SELECT players.playerName
    FROM players
    WHERE players.playerUUID = skulls.playerUUID
) as playerName
FROM skulls
WHERE worldName = :worldName AND (x >> 4) = :chunkX AND (z >> 4) = :chunkZ;
-- #    }
-- #  }

-- #  { set
-- #    { skinData
-- #      :playerUUID string
-- #      :playerName string
-- #      :skinData string
INSERT INTO players(playerUUID, playerName, skinData)
VALUES (:playerUUID, :playerName, :skinData)
ON CONFLICT (playerUUID) DO UPDATE SET playerName = excluded.playerName, skinData = excluded.skinData;
-- #    }
-- #    { showSkulls
-- #      :playerUUID string
-- #      :showSkulls bool
UPDATE players
SET showSkulls = :showSkulls
WHERE playerUUID = :playerUUID;
-- #    }
-- #    { lastCommandUse
-- #      :playerUUID string
-- #      :lastCommandUse string
UPDATE players
SET lastCommandUse = :lastCommandUse
WHERE playerUUID = :playerUUID;
-- #    }
-- #    { skull
-- #      :worldName string
-- #      :x int
-- #      :y int
-- #      :z int
-- #      :playerUUID string
-- #      :skinData string
INSERT OR REPLACE INTO skulls(worldName, x, y, z, playerUUID, skinData)
VALUES (:worldName, :x, :y, :z, :playerUUID, :skinData);
-- #    }
-- #  }

-- #  { delete
-- #    { skullByPosition
-- #      :worldName string
-- #      :x int
-- #      :y int
-- #      :z int
DELETE
FROM skulls
WHERE worldName = :worldName AND x = :x AND y = :y AND z = :z;
-- #    }
-- #  }
-- #}