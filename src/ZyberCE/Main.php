<?php

declare(strict_types=1);

namespace ZyberCE;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\world\Position;

class Main extends PluginBase implements Listener {

    private const TAG_DRILLER = "zyber_driller";

    protected function onEnable(): void {
        $this->saveDefaultConfig();
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    /* ------------------------------------------------ */
    /* COMMAND */
    /* ------------------------------------------------ */

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {

        if (!$sender instanceof Player) return true;

        if (!$sender->hasPermission("ce.use")) {
            $sender->sendMessage($this->getConfig()->get("messages")["no-permission"]);
            return true;
        }

        if (!isset($args[0])) {
            foreach ($this->getConfig()->get("messages")["ce-usage"] as $line) {
                $sender->sendMessage($line);
            }
            return true;
        }

        switch (strtolower($args[0])) {

            case "list":
                foreach ($this->getConfig()->get("messages")["ce-list"] as $line) {
                    $sender->sendMessage($line);
                }
                return true;

            case "enchant":

                if (!isset($args[1], $args[2], $args[3])) {
                    $sender->sendMessage("§cUsage: /ce enchant <player> <enchant> <level>");
                    return true;
                }

                $target = $this->getServer()->getPlayerByPrefix($args[1]);
                if (!$target instanceof Player) {
                    $sender->sendMessage("§cPlayer not found.");
                    return true;
                }

                $enchant = strtolower($args[2]);
                $level = (int)$args[3];

                if ($enchant === "driller" && $level === 1) {
                    $item = $target->getInventory()->getItemInHand();
                    if ($item->isNull()) {
                        $sender->sendMessage("§cPlayer is not holding an item.");
                        return true;
                    }

                    $this->applyDriller($item);
                    $target->getInventory()->setItemInHand($item);

                    $msg = $this->getConfig()->get("messages")["enchant-success"];
                    $msg = str_replace(
                        ["{player}", "{enchant}", "{level}"],
                        [$target->getName(), "Driller", "1"],
                        $msg
                    );
                    $sender->sendMessage($msg);
                }

                return true;
        }

        return true;
    }

    /* ------------------------------------------------ */
    /* APPLY DRILLER */
    /* ------------------------------------------------ */

    private function applyDriller(Item $item): void {

        $tag = $item->getNamedTag();
        $tag->setByte(self::TAG_DRILLER, 1);
        $item->setNamedTag($tag);

        // Add glow safely (Efficiency 1 hidden)
        $item->addEnchantment(new EnchantmentInstance(VanillaEnchantments::EFFICIENCY(), 1));

        // Custom lore
        $lore = $item->getLore();
        $lore[] = "§r§bDriller I";
        $item->setLore($lore);
    }

    /* ------------------------------------------------ */
    /* BLOCK BREAK EVENT */
    /* ------------------------------------------------ */

    public function onBreak(BlockBreakEvent $event): void {

        $player = $event->getPlayer();
        $item = $player->getInventory()->getItemInHand();
        $tag = $item->getNamedTag();

        if (!$tag->getTag(self::TAG_DRILLER)) {
            return;
        }

        $event->cancel();

        $world = $player->getWorld();
        $center = $event->getBlock()->getPosition();

        for ($x = -1; $x <= 1; $x++) {
            for ($y = -1; $y <= 1; $y++) {
                for ($z = -1; $z <= 1; $z++) {

                    $pos = $center->add($x, $y, $z);
                    $block = $world->getBlock($pos);

                    if ($block->isAir()) continue;

                    $drops = $block->getDrops($item);

                    $world->setBlock($pos, VanillaItems::AIR()->getBlock());

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
