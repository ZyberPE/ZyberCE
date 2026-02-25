<?php

declare(strict_types=1);

namespace ZyberCE;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\Tool;
use pocketmine\Server;

class Main extends PluginBase implements Listener {

    private Config $config;

    /** @var array<string, array> */
    private array $enchants = [];

    public function onEnable(): void {
        $this->saveDefaultConfig();
        $this->config = $this->getConfig();

        $this->getServer()->getPluginManager()->registerEvents($this, $this);

        // Register enchants (Expandable)
        $this->registerEnchant("driller", [
            "description" => "Breaks a 3x3 area every time you mine.",
            "max_level" => 1
        ]);
    }

    private function registerEnchant(string $name, array $data): void {
        $this->enchants[strtolower($name)] = $data;
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {

        if(!$sender->hasPermission("ce.use")){
            $sender->sendMessage($this->config->get("no-permission"));
            return true;
        }

        if(!isset($args[0])){
            $sender->sendMessage("§eUsage:");
            $sender->sendMessage("§7/ce enchant <player> <enchant> <level>");
            $sender->sendMessage("§7/ce list");
            return true;
        }

        switch(strtolower($args[0])){

            case "list":
                foreach($this->config->get("list-format") as $line){
                    $sender->sendMessage($line);
                }

                foreach($this->enchants as $name => $data){
                    $sender->sendMessage("§6" . ucfirst($name) . " §7- " . $data["description"]);
                }
            return true;

            case "enchant":

                if(count($args) < 4){
                    $sender->sendMessage("§cUsage: /ce enchant <player> <enchant> <level>");
                    return true;
                }

                $target = $this->findPlayer($args[1]);
                if($target === null){
                    $sender->sendMessage("§cPlayer not found.");
                    return true;
                }

                $enchantName = strtolower($args[2]);
                $level = (int)$args[3];

                if(!isset($this->enchants[$enchantName])){
                    $sender->sendMessage("§cEnchant does not exist.");
                    return true;
                }

                if($level > $this->enchants[$enchantName]["max_level"]){
                    $sender->sendMessage("§cMax level for this enchant is 1.");
                    return true;
                }

                $item = $target->getInventory()->getItemInHand();

                if($item->isNull()){
                    $sender->sendMessage("§cPlayer must hold an item.");
                    return true;
                }

                $nbt = $item->getNamedTag();
                $nbt->setInt("zyberce_" . $enchantName, $level);
                $item->setNamedTag($nbt);

                $target->getInventory()->setItemInHand($item);

                $sender->sendMessage(str_replace("{player}", $target->getName(), $this->config->get("enchant-success")));
                $target->sendMessage("§aYour item has been enchanted with §6" . ucfirst($enchantName) . " I");

            return true;
        }

        return false;
    }

    private function findPlayer(string $name): ?Player {
        foreach(Server::getInstance()->getOnlinePlayers() as $player){
            if(stripos($player->getName(), $name) !== false){
                return $player;
            }
        }
        return null;
    }

    public function onBreak(BlockBreakEvent $event): void {

        $player = $event->getPlayer();
        $item = $player->getInventory()->getItemInHand();
        $nbt = $item->getNamedTag();

        if(!$nbt->getTag("zyberce_driller")){
            return;
        }

        $block = $event->getBlock();
        $world = $block->getPosition()->getWorld();
        $center = $block->getPosition();

        for($x = -1; $x <= 1; $x++){
            for($z = -1; $z <= 1; $z++){

                $targetPos = $center->add($x, 0, $z);
                $target = $world->getBlock($targetPos);

                // Skip air
                if($target->getTypeId() === VanillaBlocks::AIR()->getTypeId()){
                    continue;
                }

                // Get drops
                $drops = $target->getDrops($item);

                // Break block without double triggering
                $world->setBlock($targetPos, VanillaBlocks::AIR());

                // Add drops directly to inventory
                foreach($drops as $drop){
                    $player->getInventory()->addItem($drop);
                }
            }
        }
    }
}
