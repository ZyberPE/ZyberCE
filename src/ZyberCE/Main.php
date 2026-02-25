<?php

namespace ZyberCE;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\player\Player;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\item\Item;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\StringTag;

class Main extends PluginBase implements Listener {

    private array $enchants = [
        "driller" => [
            "description" => "Breaks a 3x3x3 area",
            "max" => 1
        ]
    ];

    public function onEnable(): void {
        $this->saveDefaultConfig();
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if (!$sender instanceof Player) return false;
        if (!$sender->hasPermission("ce.use")) {
            $sender->sendMessage($this->getConfig()->get("messages")["no-permission"]);
            return true;
        }

        if (count($args) === 0) {
            $sender->sendMessage("/ce enchant <player> <enchant>");
            $sender->sendMessage("/ce list");
            return true;
        }

        switch (strtolower($args[0])) {
            case "list":
                $sender->sendMessage($this->getConfig()->get("messages")["ce-list-header"]);
                foreach ($this->enchants as $name => $data) {
                    $line = str_replace(
                        ["{enchant}", "{description}", "{max}"],
                        [$name, $data["description"], $data["max"]],
                        $this->getConfig()->get("messages")["ce-list-enchant-line"]
                    );
                    $sender->sendMessage($line);
                }
                $sender->sendMessage($this->getConfig()->get("messages")["ce-list-footer"]);
                break;

            case "enchant":
                if (count($args) < 3) {
                    $sender->sendMessage("Usage: /ce enchant <player> <enchant>");
                    return true;
                }

                $targetName = $args[1];
                $enchantName = strtolower($args[2]);

                $target = $this->getServer()->getPlayerByPrefix($targetName);
                if (!$target instanceof Player) {
                    $sender->sendMessage("Player not found!");
                    return true;
                }

                if (!isset($this->enchants[$enchantName])) {
                    $sender->sendMessage("That enchant does not exist!");
                    return true;
                }

                $item = $target->getInventory()->getItemInHand();
                if ($item->isNull()) {
                    $sender->sendMessage("Player is not holding an item!");
                    return true;
                }

                $nbt = $item->getNamedTag();
                if (!$nbt->hasTag("ZyberCEEnchants", ListTag::class)) {
                    $nbt->setTag("ZyberCEEnchants", new ListTag([], StringTag::class));
                }

                /** @var ListTag $list */
                $list = $nbt->getListTag("ZyberCEEnchants");
                $list->push(new StringTag($enchantName));
                $item->setNamedTag($nbt);

                // Make item glow (vanilla effect)
                $item->setCustomName("§r" . $item->getName() . " ✨");

                $target->getInventory()->setItemInHand($item);
                $sender->sendMessage($this->getConfig()->get("messages")["enchant-success"]);
                break;
        }

        return true;
    }

    public function onBlockBreak(BlockBreakEvent $event): void {
        $player = $event->getPlayer();
        $item = $player->getInventory()->getItemInHand();
        $nbt = $item->getNamedTag();

        if (!$nbt->hasTag("ZyberCEEnchants", ListTag::class)) return;

        $enchants = $nbt->getListTag("ZyberCEEnchants")->getValues();

        if (in_array("driller", $enchants, true)) {
            $this->break3x3x3($player, $event->getBlock()->getPosition());
            $event->cancel(); // prevent default drops, handled manually
        }
    }

    private function break3x3x3(Player $player, $center): void {
        $world = $player->getWorld();
        $x0 = $center->x;
        $y0 = $center->y;
        $z0 = $center->z;

        for ($x = $x0 - 1; $x <= $x0 + 1; $x++) {
            for ($y = $y0 - 1; $y <= $y0 + 1; $y++) {
                for ($z = $z0 - 1; $z <= $z0 + 1; $z++) {
                    $block = $world->getBlockAt($x, $y, $z);
                    if (!$block->isAir()) {
                        $drops = $block->getDrops($player->getInventory()->getItemInHand());
                        foreach ($drops as $drop) {
                            if ($player->getInventory()->canAddItem($drop)) {
                                $player->getInventory()->addItem($drop);
                            } else {
                                $world->dropItem($block->getPosition(), $drop);
                            }
                        }
                        $world->setBlockAt($x, $y, $z, $block->getPosition()->getWorld()->getBlockAt(0,0,0)); // air
                    }
                }
            }
        }
    }
}
