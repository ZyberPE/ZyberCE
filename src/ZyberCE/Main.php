<?php

declare(strict_types=1);

namespace ZyberCE;

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\item\Item;
use pocketmine\item\ToolTier;
use pocketmine\item\Pickaxe;
use pocketmine\world\Position;

class Main extends PluginBase implements Listener {

    const TAG_DRILLER = "zyber_driller";

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

                $player = $this->getServer()->getPlayerExact($args[1]);
                if(!$player instanceof Player){
                    return true;
                }

                $enchant = strtolower($args[2]);
                $item = $player->getInventory()->getItemInHand();

                if($enchant === "driller"){

                    if(!$item instanceof Pickaxe){
                        $player->sendMessage($this->getConfig()->get("messages")["invalid-tool"]);
                        return true;
                    }

                    $nbt = $item->getNamedTag();
                    $nbt->setByte(self::TAG_DRILLER, 1);
                    $item->setNamedTag($nbt);

                    $item->setCustomName("§r§bDriller I " . $item->getName());

                    $player->getInventory()->setItemInHand($item);

                    $msg = str_replace(
                        ["{enchant}", "{player}"],
                        ["Driller I", $player->getName()],
                        $this->getConfig()->get("messages")["enchant-success"]
                    );

                    $sender->sendMessage($msg);
                } else {
                    $sender->sendMessage($this->getConfig()->get("messages")["invalid-enchant"]);
                }

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
        $nbt = $item->getNamedTag();

        if(!$nbt->getTag(self::TAG_DRILLER)){
            return;
        }

        $block = $event->getBlock();
        $world = $block->getPosition()->getWorld();
        $center = $block->getPosition();

        for($x = -1; $x <= 1; $x++){
            for($y = -1; $y <= 1; $y++){
                for($z = -1; $z <= 1; $z++){

                    if($x === 0 && $y === 0 && $z === 0){
                        continue;
                    }

                    $target = $world->getBlock($center->add($x, $y, $z));
                    if($target->getTypeId() !== 0){
                        $world->useBreakOn($target->getPosition(), $item, $player);
                    }
                }
            }
        }
    }
}
