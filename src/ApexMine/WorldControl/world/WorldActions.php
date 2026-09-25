<?php

declare(strict_types=1);

namespace ApexMine\WorldControl\world;

use ApexMine\WorldControl\WorldControl;
use pocketmine\world\generator\GeneratorManager;
use pocketmine\world\WorldCreationOptions;
use pocketmine\world\WorldException;

/**
 * Creating, loading and unloading worlds, shared by the commands and the
 * forms. Each method returns the language key (and params) of its result
 * message, so the caller only has to show it.
 */
final class WorldActions{

    public function __construct(private WorldControl $plugin){}

    /** @return list<string> generator names this server knows (normal, flat, nether, void, plus any from plugins) */
    public static function getGenerators() : array{
        return array_values(GeneratorManager::getInstance()->getGeneratorList());
    }

    /**
     * @return array{0: string, 1: array<string, string|int>} language key and params
     */
    public function create(string $name, string $generatorName, string $seed) : array{
        $name = trim($name);
        if(preg_match('/^[A-Za-z0-9_\-]{1,32}$/', $name) !== 1){
            return ["world.invalid-name", []];
        }
        $worldManager = $this->plugin->getServer()->getWorldManager();
        if($worldManager->isWorldGenerated($name) || $this->plugin->resolveWorldName($name) !== null){
            return ["world.already-exists", ["world" => $name]];
        }
        $generator = GeneratorManager::getInstance()->getGenerator(strtolower($generatorName));
        if($generator === null){
            return ["world.unknown-generator", ["generator" => $generatorName, "list" => implode(", ", self::getGenerators())]];
        }

        $seed = trim($seed);
        $options = WorldCreationOptions::create()
            ->setGeneratorClass($generator->getGeneratorClass())
            ->setSeed($seed === "" ? random_int(PHP_INT_MIN, PHP_INT_MAX) : (is_numeric($seed) ? (int) $seed : crc32($seed)));
        // void and similar generators know where a safe spawn is (PocketMine 5.12+)
        if(method_exists($generator, "getSpawnPosition")){
            $spawn = $generator->getSpawnPosition($options->getSeed());
            if($spawn !== null){
                $options->setSpawnPosition($spawn);
            }
        }

        if(!$worldManager->generateWorld($name, $options)){
            return ["world.create-failed", ["world" => $name]];
        }
        return ["world.created", ["world" => $name, "generator" => strtolower($generatorName)]];
    }

    /** @return array{0: string, 1: array<string, string|int>} */
    public function load(string $name) : array{
        $worldManager = $this->plugin->getServer()->getWorldManager();
        if($worldManager->isWorldLoaded($name)){
            return ["world.already-loaded", ["world" => $name]];
        }
        if(!$worldManager->isWorldGenerated($name)){
            return ["world.not-found", ["world" => $name]];
        }
        try{
            $loaded = $worldManager->loadWorld($name, true);
        }catch(WorldException $e){
            $this->plugin->getLogger()->logException($e);
            $loaded = false;
        }
        return $loaded ? ["world.loaded", ["world" => $name]] : ["world.load-failed", ["world" => $name]];
    }

    /** @return array{0: string, 1: array<string, string|int>} */
    public function unload(string $name) : array{
        $worldManager = $this->plugin->getServer()->getWorldManager();
        $world = $worldManager->getWorldByName($name);
        if($world === null){
            return ["world.not-loaded", ["world" => $name]];
        }
        if($world === $worldManager->getDefaultWorld()){
            return ["world.cannot-unload-default", ["world" => $name]];
        }
        // players inside are moved to the default world by PocketMine
        try{
            $unloaded = $worldManager->unloadWorld($world);
        }catch(\InvalidArgumentException){
            $unloaded = false;
        }
        return $unloaded ? ["world.unloaded", ["world" => $name]] : ["world.unload-failed", ["world" => $name]];
    }
}
