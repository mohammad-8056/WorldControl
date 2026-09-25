<?php

declare(strict_types=1);

namespace ApexMine\WorldControl\listener;

use ApexMine\WorldControl\rule\Rule;
use ApexMine\WorldControl\WorldControl;
use pocketmine\block\Anvil;
use pocketmine\block\Barrel;
use pocketmine\block\BaseSign;
use pocketmine\block\Beacon;
use pocketmine\block\Bed;
use pocketmine\block\Block;
use pocketmine\block\BrewingStand;
use pocketmine\block\Button;
use pocketmine\block\Cake;
use pocketmine\block\CakeWithCandle;
use pocketmine\block\Campfire;
use pocketmine\block\CartographyTable;
use pocketmine\block\ChiseledBookshelf;
use pocketmine\block\CraftingTable;
use pocketmine\block\DaylightSensor;
use pocketmine\block\Door;
use pocketmine\block\EnchantingTable;
use pocketmine\block\EnderChest;
use pocketmine\block\FenceGate;
use pocketmine\block\Fire;
use pocketmine\block\FlowerPot;
use pocketmine\block\ItemFrame;
use pocketmine\block\Jukebox;
use pocketmine\block\Lectern;
use pocketmine\block\Lever;
use pocketmine\block\Liquid;
use pocketmine\block\Loom;
use pocketmine\block\Note;
use pocketmine\block\RedstoneComparator;
use pocketmine\block\RedstoneRepeater;
use pocketmine\block\RespawnAnchor;
use pocketmine\block\SmithingTable;
use pocketmine\block\Stonecutter;
use pocketmine\block\tile\Container;
use pocketmine\block\Trapdoor;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockBurnEvent;
use pocketmine\event\block\BlockGrowEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\block\BlockSpreadEvent;
use pocketmine\event\block\LeavesDecayEvent;
use pocketmine\event\entity\EntityExplodeEvent;
use pocketmine\event\entity\EntityItemPickupEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerBucketEmptyEvent;
use pocketmine\event\player\PlayerBucketFillEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\Axe;
use pocketmine\item\Fertilizer;
use pocketmine\item\FireCharge;
use pocketmine\item\FlintSteel;
use pocketmine\item\Hoe;
use pocketmine\item\Item;
use pocketmine\item\Shovel;
use pocketmine\item\SpawnEgg;
use pocketmine\player\Player;
use pocketmine\world\World;

/** Building and world rules: break, place, interact, buckets, explosions, fire, liquids, leaves, crops, items. */
final class ProtectionListener implements Listener{

    public function __construct(private WorldControl $plugin){}

    /** @return bool true when the action must be cancelled (and the player was told) */
    private function denied(Player $player, World $world, Rule $rule) : bool{
        if($this->plugin->rulesOf($world)->allows($rule) || $this->plugin->canBypass($player, "build")){
            return false;
        }
        $this->plugin->deny($player, "deny." . $rule->value);
        return true;
    }

    public function onBreak(BlockBreakEvent $event) : void{
        if($this->denied($event->getPlayer(), $event->getBlock()->getPosition()->getWorld(), Rule::BLOCK_BREAK)){
            $event->cancel();
        }
    }

    public function onPlace(BlockPlaceEvent $event) : void{
        if($this->denied($event->getPlayer(), $event->getBlockAgainst()->getPosition()->getWorld(), Rule::BLOCK_PLACE)){
            $event->cancel();
        }
    }

    /**
     * Right-clicking a block also places blocks, so only clicks on blocks that
     * DO something (doors, chests, buttons ...) count as "interact". Tools that
     * change the world by right-clicking (flint and steel, hoes, bone meal ...)
     * count as "place".
     */
    public function onInteract(PlayerInteractEvent $event) : void{
        if($event->getAction() !== PlayerInteractEvent::RIGHT_CLICK_BLOCK) return;
        $block = $event->getBlock();
        $world = $block->getPosition()->getWorld();

        if($this->isInteractive($block)){
            // sneaking with a block in hand places it against the chest/door instead of opening it
            if($event->getPlayer()->isSneaking() && !$event->getItem()->isNull()){
                if($this->denied($event->getPlayer(), $world, Rule::BLOCK_PLACE)) $event->cancel();
                return;
            }
            if($this->denied($event->getPlayer(), $world, Rule::INTERACT)) $event->cancel();
        }elseif($this->changesWorld($event->getItem())){
            if($this->denied($event->getPlayer(), $world, Rule::BLOCK_PLACE)) $event->cancel();
        }
    }

    private function isInteractive(Block $block) : bool{
        if($block->getPosition()->getWorld()->getTile($block->getPosition()) instanceof Container){
            return true; // chests, furnaces, hoppers, shulker boxes, dispensers ...
        }
        return $block instanceof Door || $block instanceof Trapdoor || $block instanceof FenceGate
            || $block instanceof Button || $block instanceof Lever || $block instanceof ItemFrame
            || $block instanceof Anvil || $block instanceof CraftingTable || $block instanceof EnchantingTable
            || $block instanceof EnderChest || $block instanceof Barrel || $block instanceof BrewingStand
            || $block instanceof Beacon || $block instanceof Bed || $block instanceof Cake
            || $block instanceof CakeWithCandle || $block instanceof Note || $block instanceof Jukebox
            || $block instanceof RedstoneRepeater || $block instanceof RedstoneComparator
            || $block instanceof DaylightSensor || $block instanceof Loom || $block instanceof CartographyTable
            || $block instanceof SmithingTable || $block instanceof Stonecutter
            || $block instanceof Lectern || $block instanceof FlowerPot || $block instanceof BaseSign
            || $block instanceof Campfire || $block instanceof RespawnAnchor
            || $block instanceof ChiseledBookshelf;
    }

    private function changesWorld(Item $item) : bool{
        return $item instanceof FlintSteel || $item instanceof FireCharge || $item instanceof Hoe
            || $item instanceof Shovel || $item instanceof Axe || $item instanceof Fertilizer
            || $item instanceof SpawnEgg;
    }

    public function onBucketEmpty(PlayerBucketEmptyEvent $event) : void{
        if($this->denied($event->getPlayer(), $event->getBlockClicked()->getPosition()->getWorld(), Rule::BUCKET)){
            $event->cancel();
        }
    }

    public function onBucketFill(PlayerBucketFillEvent $event) : void{
        if($this->denied($event->getPlayer(), $event->getBlockClicked()->getPosition()->getWorld(), Rule::BUCKET)){
            $event->cancel();
        }
    }

    public function onDrop(PlayerDropItemEvent $event) : void{
        $player = $event->getPlayer();
        if($this->denied($player, $player->getWorld(), Rule::DROP_ITEMS)){
            $event->cancel();
        }
    }

    public function onPickup(EntityItemPickupEvent $event) : void{
        $player = $event->getEntity();
        // no message here: it would fire every tick while standing on an item
        if($player instanceof Player
            && !$this->plugin->rulesOf($player->getWorld())->allows(Rule::PICKUP_ITEMS)
            && !$this->plugin->canBypass($player, "build")){
            $event->cancel();
        }
    }

    /** Explosions still hurt entities; they just don't break blocks. */
    public function onExplode(EntityExplodeEvent $event) : void{
        if(!$this->plugin->rulesOf($event->getPosition()->getWorld())->allows(Rule::EXPLOSIONS)){
            $event->setBlockList([]);
        }
    }

    public function onBurn(BlockBurnEvent $event) : void{
        if(!$this->plugin->rulesOf($event->getBlock()->getPosition()->getWorld())->allows(Rule::FIRE_SPREAD)){
            $event->cancel();
        }
    }

    public function onSpread(BlockSpreadEvent $event) : void{
        $source = $event->getSource();
        $rule = match(true){
            $source instanceof Fire => Rule::FIRE_SPREAD,
            $source instanceof Liquid => Rule::LIQUID_FLOW,
            default => null,
        };
        if($rule !== null && !$this->plugin->rulesOf($event->getBlock()->getPosition()->getWorld())->allows($rule)){
            $event->cancel();
        }
    }

    public function onLeavesDecay(LeavesDecayEvent $event) : void{
        if(!$this->plugin->rulesOf($event->getBlock()->getPosition()->getWorld())->allows(Rule::LEAVES_DECAY)){
            $event->cancel();
        }
    }

    public function onGrow(BlockGrowEvent $event) : void{
        if(!$this->plugin->rulesOf($event->getBlock()->getPosition()->getWorld())->allows(Rule::CROP_GROWTH)){
            $event->cancel();
        }
    }
}
