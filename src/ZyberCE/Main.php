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

    /** @var array<string,bool> */
    private array $breaking = [];

    protected function onEnable(): void {
        $this->saveDefaultConfig();
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    /* ------------------------------------------------ */
    /* ---------------- COMMAND SYSTEM ---------------- */
    /* ------------------------------------------------ */

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {

        if(!$sender->hasPermission("ce.use")){
            $sender->sendMessage($this->getConfig()->get("messages")["no-permission"]);
            return true;
        }

        if(!isset($args[0])){
            foreach($this->getConfig()->get("help-message") as $line){
                $sender->sendMessage($line);
            }
            return true;
        }

        switch(strtolower($args[0])){

            case "enchant":

                if(count($args) < 4){
                    $sender->sendMessage("§cUsage: /ce enchant <player> <enchant> <level>");
                    return true;
                }

                $target = $this->getServer()->getPlayerByPrefix($args[1]);
                if(!$target instanceof Player){
                    $sender->sendMessage($this->getConfig()->get("messages")["player-not-found"]);
                    return true;
                }

                $enchant = strtolower($args[2]);
                $level = (int)$args[3];

                if($enchant !== "driller" || $level !== 1){
                    $sender->sendMessage($this->getConfig()->get("messages")["invalid-enchant"]);
                    return true;
                }

                $item = $target->getInventory()->getItemInHand();

                if(!$item instanceof Pickaxe){
                    $sender->sendMessage($this->getConfig()->get("messages")["invalid-tool"]);
                    return true;
                }

                $nbt = $item->getNamedTag();
                $nbt->setByte(self::TAG_DRILLER, 1);
                $item->setNamedTag($nbt);

                $item->setCustomName("§r§bDriller I §r" . $item->getName());
                $target->getInventory()->setItemInHand($item);

                $sender->sendMessage(str_replace(
                    ["{enchant}", "{player}", "{level}"],
                    ["Driller", $target->getName(), "I"],
                    $this->getConfig()->get("messages")["enchant-success"]
                ));

            break;

            case "list":

                foreach($this->getConfig()->get("list-message") as $line){
                    $sender->sendMessage($line);
                }

            break;
        }

        return true;
    }

    /* ------------------------------------------------ */
    /* ---------------- DRILLER LOGIC ----------------- */
    /* ------------------------------------------------ */

    public function onBreak(BlockBreakEvent $event): void {

        $player = $event->getPlayer();
        $item = $player->getInventory()->getItemInHand();

        if(!$item instanceof Pickaxe){
            return;
        }

        if(!$item->getNamedTag()->getTag(self::TAG_DRILLER)){
            return;
        }

        $name = $player->getName();

        // Prevent recursion crash
        if(isset($this->breaking[$name])){
            return;
        }

        $this->breaking[$name] = true;

        $block = $event->getBlock();
        $world = $block->getPosition()->getWorld();
        $center = $block->getPosition();

        // 3x3 FLAT AREA (horizontal)
        for($x = -1; $x <= 1; $x++){
            for($z = -1; $z <= 1; $z++){

                if($x === 0 && $z === 0){
                    continue;
                }

                $pos = $center->add($x, 0, $z);
                $target = $world->getBlock($pos);

                if(!$target->isAir()){
                    $world->useBreakOn($pos, $item, $player);
                }
            }
        }

        unset($this->breaking[$name]);
    }
}
