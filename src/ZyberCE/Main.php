<?php

declare(strict_types=1);

namespace ZyberCE;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\item\Pickaxe;

class Main extends PluginBase implements Listener {

    private const TAG_DRILLER = "zyber_driller";

    /** @var array<string, bool> */
    private array $breaking = [];

    protected function onEnable(): void {
        $this->saveDefaultConfig();
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {

        if(!$sender->hasPermission("ce.use")){
            $sender->sendMessage($this->getConfig()->get("messages")["no-permission"]);
            return true;
        }

        if(!isset($args[0])){
            return true;
        }

        switch(strtolower($args[0])){

            case "enchant":

                if(count($args) < 3){
                    return true;
                }

                $target = $this->getServer()->getPlayerExact($args[1]);
                if(!$target instanceof Player){
                    return true;
                }

                $enchant = strtolower($args[2]);
                $item = $target->getInventory()->getItemInHand();

                if($enchant !== "driller"){
                    $sender->sendMessage($this->getConfig()->get("messages")["invalid-enchant"]);
                    return true;
                }

                if(!$item instanceof Pickaxe){
                    $sender->sendMessage($this->getConfig()->get("messages")["invalid-tool"]);
                    return true;
                }

                $nbt = $item->getNamedTag();
                $nbt->setByte(self::TAG_DRILLER, 1);
                $item->setNamedTag($nbt);

                $item->setCustomName("§r§bDriller I " . $item->getName());

                $target->getInventory()->setItemInHand($item);

                $msg = str_replace(
                    ["{enchant}", "{player}"],
                    ["Driller I", $target->getName()],
                    $this->getConfig()->get("messages")["enchant-success"]
                );

                $sender->sendMessage($msg);

            break;

            case "list":
                foreach($this->getConfig()->get("list-message") as $line){
                    $sender->sendMessage($line);
                }
            break;
        }

        return true;
    }

    public function onBreak(BlockBreakEvent $event): void {

        $player = $event->getPlayer();
        $item = $player->getInventory()->getItemInHand();

        if(!$item instanceof Pickaxe){
            return;
        }

        $nbt = $item->getNamedTag();

        if(!$nbt->getTag(self::TAG_DRILLER)){
            return;
        }

        $playerName = $player->getName();

        // Prevent infinite recursion
        if(isset($this->breaking[$playerName])){
            return;
        }

        $this->breaking[$playerName] = true;

        $block = $event->getBlock();
        $world = $block->getPosition()->getWorld();
        $center = $block->getPosition();

        for($x = -1; $x <= 1; $x++){
            for($y = -1; $y <= 1; $y++){
                for($z = -1; $z <= 1; $z++){

                    if($x === 0 && $y === 0 && $z === 0){
                        continue;
                    }

                    $targetPos = $center->add($x, $y, $z);
                    $target = $world->getBlock($targetPos);

                    if(!$target->isAir()){
                        $world->useBreakOn($targetPos, $item, $player);
                    }
                }
            }
        }

        unset($this->breaking[$playerName]);
    }
}
