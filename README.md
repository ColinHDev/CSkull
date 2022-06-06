## CSkull
CSkull is a plugin for the Minecraft: Bedrock Edition server software [PocketMine-MP](https://github.com/pmmp/PocketMine-MP) which aims to implement vanilla-like player skulls, the way they exist in Minecraft: Java Edition.

### Features
- Proper block collision and breaking <br>
  Many already existing plugins which implemented player skulls, used entities only, which gave the skulls their proper skin but did not give their skulls any collision. <br>
  CSkull implements collision by spawning the entity on top of the normal skull block, which makes both the correct block collision, as well as block-breaking possible.
- Skin data always accessible <br>
  Because we store the skin data of every player, that joined your server, in a database, it is possible to get the skull of a player, which is not online.
- Possibility of hiding skulls <br>
  As every skull is a unique entity, the frames per second of a player's game are likely to drop when being forced to render a big number of skull entities. So, if that is the case or the player just does not want to see those skulls, he can disable them by typing a command. If a player decides to hide the entities, they are not just made invisible but will not even be spawned to him at all. The player will only see the steve skull block below the entity, so that he can see, that there possibly was an entity there.
- Queuing skull entity spawn <br>
  As it can lag a player's client if too many entities are spawned at once, this plugin provides the possibility to only spawn a specific amount of skull entities to a player in the same tick. Read more about it [here](#customizations).
- No deletion of skulls when plugin fails to load <br>
  As we do not store the data in the skull entities which we would save in the chunk, but instead we store them in a database, there will not be any problems when a chunk is loaded without the plugin being enabled because no data is lost. There simply will not be any skull entities spawned if that is the case. <br>
  Unless the skull block is not broken without the plugin being not loaded or the database being deleted, there should not be any problems with deleted skulls.
- Removing the plugin without problems <br>
  Normally, when you would load a chunk that has unkown entities, e.g. from a removed plugin, in it, your console would be spammed with warnings that those entities were deleted. <br>
  Since, we do not store the skull entities on the disk, you can remove the plugin from your server without noticing it. (This plugin only handles the spawn of the skull entities, not of the skull block. So when you remove the plugin, the steve skull blocks, that were under the entities, will remain in your world.)
- /skull command cooldown <br>
  It may be in the interest of some servers to not let their players get unlimited amounts of skulls. By setting specific permission for their players, they can decide the cooldown a player should have on performing the /skull command. For a list of the permissions, look [here](#permissions).
- Language <br>
  Although the plugin's displayed messages are in English, you can customize that file to your need by modifying the `language.ini` file in the `plugin_data` folder of this plugin. <br>
  There, you can also change the name and lore of the skull item.

### Commands and permissions
#### Commands
Command | Description | Permission | Aliases
---|---|---|---
/showskulls <true / false> | Decide whether skull entities should be shown to you. | cskull.command.showskulls | /showheads
/skull \<player> | Give yourself the skull of a player. | cskull.command.skull | /head, /playerhead
#### Permissions
Permission | Description | Default
---|---|---
cskull.command.showskulls | This permission is required to execute the /showskulls command. | true
cskull.command.skull | This permission is required to execute the /skull command. | true
cskull.cooldown.2592000 | Set the cooldown on the /skull command to one month for a player with this permission. (30d = 2592000s) | false
cskull.cooldown.604800 | Set the cooldown on the /skull command to one week for a player with this permission. (7d = 604800s) | false
cskull.cooldown.86400 | Set the cooldown on the /skull command to one day for a player with this permission. (24h = 86400s) | true
**cskull.cooldown.N** | You can also specify your custom cooldown with this permission. (N is a natural number (a non-fractional number with a positive sign)) | ---
cskull.cooldown.none | Disable the cooldown on the /skull command for a player with this permission. | op

### Customizations
Customizations can be done in the `config.yml` in the plugin's `plugin_data` folder:
- `skullEntity.spawn.delay`: `1` <br>
  Define the delay in ticks between the spawn or despawn interval of skull entities for players.
- `skullEntity.spawn.maxPerTick`: `2` <br>
  Define how many skull entities can be spawned to a player or despawned from it in the same tick. <br>
  If `skullEntity.spawn.delay` is set to 0, this configuration will be ignored.
- `database` <br>
  Change the settings for the database this plugin will use. This is the default format provided by [libasynql](https://github.com/poggit/libasynql#configuration).

### TODO
- **FIXED:** Experience orbs are being attracted by our skull entities which should not happen. <br>
  This is due to the way PMMP handles these attractions: experience orbs are attracted to all human entities and therefore also our skull entities. But since we can not intervene in that by listening to an event, there is no easy way to fix that currently. This problem is already referenced in an [issue](https://github.com/pmmp/PocketMine-MP/issues/4589). <br>
  We would have two solutions: overwrite the `ExperienceOrb` class to implement our own entity search in the `ExperienceOrb::entityBaseTick()` method or let the `SkullEntity` class extend the `Entity` instead of the `Human` class and implement the skin handling, etc. ourselves. Both solutions are kind of complicated and in my eyes not worth the effort at the moment, just for them to get ruled out by a far easier way when the issue is resolved. <br>
  UPDATE: The issue was resolved with the [PR](https://github.com/pmmp/PocketMine-MP/pull/4623) and merged into PocketMine-MP 4.1.0. If this minor version releases, we can resolve this issue by adding the following to our SkullEntity class: <br>
  ```php
  public function initEntity(CompoundTag $nbt) : void {
        parent::initEntity($nbt);
        // Disables that experience orbs are attracted to skull entities.
        $this->xpManager->setCanAttractXpOrbs(false);
  }
  ```