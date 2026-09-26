<?php

declare(strict_types=1);

namespace ApexGaming\WorldControl\server;

use ApexGaming\WorldControl\WorldControl;
use pocketmine\entity\Location;
use pocketmine\Server;

/**
 * Server-wide options that are changed in game (server.yml): the server spawn,
 * always-spawn and friends. Kept apart from config.yml so saving them never
 * wipes the comments an admin reads in config.yml.
 */
final class ServerSettings{

    private const HEADER = <<<YAML
# WorldControl - server settings
# Managed from the in-game panel (/wc -> Server settings). If you edit this file
# by hand, run /wc reload afterwards.

YAML;

    /** Boolean options, in the order they appear in the form. key => default */
    public const TOGGLES = [
        "always-spawn" => false,
        "respawn-at-spawn" => false,
        "spawn-command" => true,
        "join-message" => true,
        "quit-message" => true,
    ];

    /** @var array<string, bool> */
    private array $toggles = [];

    /** @var array{world: string, x: float, y: float, z: float, yaw: float, pitch: float}|null */
    private ?array $spawn = null;

    public function __construct(private string $path){
        $this->load();
    }

    public function load() : void{
        $data = is_file($this->path) ? yaml_parse_file($this->path) : [];
        $data = is_array($data) ? $data : [];

        foreach(self::TOGGLES as $key => $default){
            $this->toggles[$key] = is_bool($data[$key] ?? null) ? $data[$key] : $default;
        }

        $spawn = $data["spawn"] ?? null;
        $this->spawn = is_array($spawn) && isset($spawn["world"], $spawn["x"], $spawn["y"], $spawn["z"]) ? [
            "world" => (string) $spawn["world"],
            "x" => (float) $spawn["x"],
            "y" => (float) $spawn["y"],
            "z" => (float) $spawn["z"],
            "yaw" => (float) ($spawn["yaw"] ?? 0),
            "pitch" => (float) ($spawn["pitch"] ?? 0),
        ] : null;

        if(!is_file($this->path)){
            $this->save();
        }
    }

    public function save() : void{
        file_put_contents($this->path, self::HEADER . yaml_emit($this->toggles + ["spawn" => $this->spawn], YAML_UTF8_ENCODING));
    }

    public function isEnabled(string $toggle) : bool{
        return $this->toggles[$toggle] ?? false;
    }

    public function setEnabled(string $toggle, bool $enabled) : void{
        if(!array_key_exists($toggle, self::TOGGLES)) return;
        $this->toggles[$toggle] = $enabled;
        $this->save();
    }

    public function hasCustomSpawn() : bool{
        return $this->spawn !== null;
    }

    public function setSpawn(Location $location) : void{
        $this->spawn = [
            "world" => $location->getWorld()->getFolderName(),
            "x" => round($location->x, 2),
            "y" => round($location->y, 2),
            "z" => round($location->z, 2),
            "yaw" => round($location->yaw, 1),
            "pitch" => round($location->pitch, 1),
        ];
        $this->save();
    }

    public function clearSpawn() : void{
        $this->spawn = null;
        $this->save();
    }

    /**
     * The server spawn: the saved one if its world exists (it is loaded if
     * needed), otherwise the safe spawn of the default world.
     */
    public function getSpawn() : ?Location{
        $worldManager = Server::getInstance()->getWorldManager();
        if($this->spawn !== null){
            $name = $this->spawn["world"];
            if(!$worldManager->isWorldLoaded($name) && $worldManager->isWorldGenerated($name)){
                $worldManager->loadWorld($name);
            }
            $world = $worldManager->getWorldByName($name);
            if($world !== null){
                return new Location($this->spawn["x"], $this->spawn["y"], $this->spawn["z"], $world, $this->spawn["yaw"], $this->spawn["pitch"]);
            }
        }
        $world = $worldManager->getDefaultWorld();
        return $world === null ? null : Location::fromObject(WorldControl::getInstance()->safeSpawn($world), $world);
    }

    /** @return string e.g. "lobby 0.5, 64, 0.5", or null when no custom spawn is set */
    public function describeSpawn() : ?string{
        if($this->spawn === null) return null;
        return sprintf("%s %s, %s, %s", $this->spawn["world"], $this->spawn["x"], $this->spawn["y"], $this->spawn["z"]);
    }
}
