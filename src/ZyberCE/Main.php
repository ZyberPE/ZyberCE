<?php

namespace ZyberCE;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\player\Player;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\block\Air;

class Main extends PluginBase implements Listener {

    public const TAG_DRILLER = "driller";

    public function onEnable(): void {
        $this->saveDefaultConfig();
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function onCommand(\pocketmine\command\CommandSender $sender, \pocketmine\command\Command $command, string $label, array $args): bool {

        if (!$sender->hasPermission("ce.use")) {
            $sender->sendMessage($this->getConfig()->get("messages")["no-permission"]);
            return true;
        }

        if (count($args) === 0) return false;

        switch ($args[0]) {
            case "list":
                $list = $this->getConfig()->get("messages")["enchant-list"];
                foreach ($list as $line) $sender->sendMessage($line);
                return true;

            case "enchant":
                if (count($args) < 3) {
                    $sender->sendMessage("Usage: /ce enchant <player> <enchant>");
                    return true;
                }

                $playerName = $args[1];
                $enchant = strtolower($args[2]);

                $player = $this->getServer()->getPlayerByPrefix($playerName);
                if (!$player instanceof Player) {
                    $sender->sendMessage("Player not found!");
                    return true;
                }

                $item = $player->getInventory()->getItemInHand();
                if ($item->isNull()) {
                    $sender->sendMessage("Player is not holding an item!");
                    return true;
                }

                $nbt = $item->getNamedTag();
                switch ($enchant) {
                    case "driller":
                        $nbt->setByte(self::TAG_DRILLER, 1);
                        break;
                    default:
                        $sender->sendMessage("Unknown enchant: $enchant");
                        return true;
                }

                $item->setNamedTag($nbt);

                // Glow like normal enchantment
                $item->addEnchantment(VanillaItems::ENCHANTED_BOOK()->getEnchantment(0)); // Vanilla glow hack

                // Add lore
                $lore = $item->getLore();
                if ($lore === null) $lore = [];
                $lore[] = "§b$enchant";
                $item->setLore($lore);

                $player->getInventory()->setItemInHand($item);
                $sender->sendMessage(str_replace(["{player}", "{enchant}"], [$player->getName(), ucfirst($enchant)], $this->getConfig()->get("messages")["enchant-success"]));
                return true;
        }

        return false;
    }

    public function onBreak(BlockBreakEvent $event): void {

        $player = $event->getPlayer();
        $item = $player->getInventory()->getItemInHand();
        $tag = $item->getNamedTag();

        if (!$tag->getByte(self::TAG_DRILLER)) return;

        $event->cancel();

        $world = $player->getWorld();
        $center = $event->getBlock()->getPosition();

        for ($x = -1; $x <= 1; $x++) {
            for ($y = -1; $y <= 1; $y++) {
                for ($z = -1; $z <= 1; $z++) {

                    $pos = $center->add($x, $y, $z);
                    $block = $world->getBlock($pos);

                    if ($block instanceof Air) continue;

                    $drops = $block->getDrops($item);

                    $world->setBlock($pos, VanillaItems::AIR());

                    foreach ($drops as $drop) {
                        if (!$player->getInventory()->canAddItem($drop)) {
                            $player->sendMessage($this->getConfig()->get("messages")["inventory-full"]);
                            continue;
                        }
                        $player->getInventory()->addItem($drop);
                    }
                }
            }
        }
    }
}
