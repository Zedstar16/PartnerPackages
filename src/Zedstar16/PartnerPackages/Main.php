<?php

declare(strict_types=1);

namespace Zedstar16\PartnerPackages;

use pocketmine\entity\Effect;
use pocketmine\entity\EffectInstance;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\item\Potion;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\event\Listener;
use pocketmine\scheduler\ClosureTask;

class Main extends PluginBase implements Listener
{

    public $hits = [];

    public $cant_build = [];

    public $cooldowns = [];

    public $guardian = [];

    public function onEnable(): void
    {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function onBlockBreak(BlockBreakEvent $e)
    {
        if (isset($this->cant_build[$e->getPlayer()->getName()])) {
            $e->getPlayer()->sendMessage("§6You Temporarily Cannot §fPlace §6Or §fBuild §6Because You Have Been Hit With a §bExotic Bone§6!");
            $e->setCancelled();
        }
    }

    public function onBlockPlace(BlockPlaceEvent $e)
    {
        if (isset($this->cant_build[$e->getPlayer()->getName()])) {
            $e->getPlayer()->sendMessage("§6You Temporarily Cannot §fPlace §6Or §fBuild §6Because You Have Been Hit With a §bExotic Bone§6!");
            $e->setCancelled();
        }
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
    {
        if ($command->getName() === "pi") {
            if (isset($args[0])) {
                $list = [
                    "Guardian Angel:guardian:399",
                    "Potion Counter:potion:369",
                    "Vecro's Tank Ingot:tank:265",
                    "Dol's Rage Ability:rage:377",
                    "Coffee's Debuff:debuff:397",
                    "Night's Hotbar Scrambler:hotbar:280",
                    "Misclick's Exotic Bone:bone:352"
                ];
                if ($args[0] === "list") {
                    $msg = "§9=-= §aList of Partner Packages §9=-=\n";
                    foreach ($list as $raw) {
                        $d = explode(":", $raw);
                        $msg .= "§r§9- §e/pi (username) $d[1] §a- §eGives §b§l$d[0]\n";
                    }
                    $sender->sendMessage($msg);
                } else {
                    $p = $this->getServer()->getPlayer($args[0]);
                    if(isset($args[1])) {
                        if ($p !== null) {
                            $names = [];
                            foreach ($list as $raw) {
                                $d = explode(":", $raw);
                                $names[$d[1]] = [$d[0], $d[2]];
                            }
                            if (array_key_exists($args[1], $names)) {
                                $d = $names[$args[1]];
                                $item = ItemFactory::get((int)$d[1]);
                                $item->setCustomName("§r§l§b" . $d[0]);
                                if ($args[1] === "debuff") {
                                    $item->setDamage(1);
                                }
                                $item->addEnchantment(new EnchantmentInstance(new Enchantment(255, "", Enchantment::RARITY_COMMON, Enchantment::SLOT_ALL, Enchantment::SLOT_NONE, 1)));
                                $nbt = $item->getNamedTag();
                                $nbt->setString("ability", $args[1]);
                                $item->setCompoundTag($nbt);
                                $p->getInventory()->addItem($item);
                                $p->sendMessage("§aYou Have Been Given a Partner Item!");
                                if ($sender->getName() !== $p->getName()) {
                                    $sender->sendMessage("§aYou have Given§f {$p->getName()}}§a a Partner Item");
                                }
                            } else $sender->sendMessage("§cPartner package does not exist, use /pi list to view all current partner packages");
                        } else $sender->sendMessage("§cTarget player is not online");
                    }else $sender->sendMessage("§cSpecify a package, to view all packages run §e/pi list");
                }
            } else $sender->sendMessage("§9=-= §aPartner Package Help §9=-=\n§9- §b/pi (username) (package)§9 - Gives specified package to player\n§9- §b/pi list §9- Lists all partner packages");
        }
        return true;
    }

    public function ability(Player $p, $tag)
    {
        $n = $p->getName();
        if (isset($this->hits[$n][$tag])) {
            $data = $this->hits[$n][$tag];
            if (isset($data[count($data) - 3])) {
                if (microtime(true) - $data[count($data) - 3] > 1) {
                    $this->hits[$n][$tag] = [];
                    return true;
                }
            }
        }
        $this->hits[$n][$tag][] = microtime(true);
        return false;
    }

    public function pop(Player $p)
    {
        $item = $p->getInventory()->getItemInHand();
        if ($item->count > 1) {
            $item->setCount($item->count - 1);
            $p->getInventory()->setItemInHand($item);
        } else $p->getInventory()->setItemInHand(Item::get(ItemIds::AIR));
    }

    public function addCooldown(Player $p, $tag)
    {
        $this->cooldowns[$p->getName()][$tag] = time();
    }

    public function getCooldown(Player $p, $tag)
    {
        if (!isset($this->cooldowns[$p->getName()][$tag])) {
            return null;
        }
        $cooldowns = [
            "bone" => 120,
            "potion" => 60 * 2.5,
            "tank" => 180,
            "rage" => 180,
            "hotbar" => 30,
            "debuff" => 120,
            "guardian" => 120
        ];
        $t = time() - $this->cooldowns[$p->getName()][$tag];
        if ($t < $cooldowns[$tag]) {
            return explode(":", gmdate("i:s", $cooldowns[$tag] - $t));
        }
        return null;
    }

    public function onInteract(PlayerInteractEvent $event)
    {
        $p = $event->getPlayer();
        $item = $p->getInventory()->getItemInHand();
        if ($item->getNamedTag()->hasTag("ability")) {
            $tag = $item->getNamedTag()->getString("ability");
            $cooldown = $this->getCooldown($p, $tag);
            if ($cooldown === null) {
                switch ($tag) {
                    case "tank":
                        $this->addCooldown($p, $tag);
                        $p->addEffect(new EffectInstance(Effect::getEffectByName("regeneration"), 5 * 20, 2));
                        $p->sendMessage("§6You Have Used §b{$item->getCustomName()}§6!");
                        $this->pop($p);
                        break;
                    case "rage":
                        $this->addCooldown($p, $tag);
                        $p->addEffect(new EffectInstance(Effect::getEffectByName("strength"), 5 * 20, 2));
                        $p->sendMessage("§6You Have Used §b{$item->getCustomName()}§6!");
                        $this->pop($p);
                        break;
                    case "guardian":
                        $this->guardian[$p->getName()] = true;
                        $p->sendMessage("§6You Have Used a §bGuardian Angel §6You Will Now Gain Full HP When You Reach 1 And a Half Hearts!");
                        $this->pop($p);
                        break;
                }
            } else $p->sendTip("§r§b" . $item->getName() . "§r§6 is on cooldown for §f" . (int)$cooldown[0] . "m $cooldown[1]s");
        }
    }

    public function onDamage(EntityDamageEvent $event)
    {
        $p = $event->getEntity();
        if ($p instanceof Player) {
            if ($p->getHealth() <= 1.5 || ($p->getHealth() - $event->getBaseDamage()) < 1.5) {
                if (isset($this->guardian[$p->getName()])) {
                    $p->setHealth(20);
                    $p->sendMessage("§bGuardian Angel §6Has Been Activated");
                    unset($this->guardian[$p->getName()]);
                }
            }
        }
    }


    public function onHit(EntityDamageByEntityEvent $event)
    {
        $p = $event->getEntity();
        $damager = $event->getDamager();
        if ($p instanceof Player && $damager instanceof Player) {
            $item = $damager->getInventory()->getItemInHand();
            if ($item->getNamedTag()->hasTag("ability")) {
                $tag = $item->getNamedTag()->getString("ability");
                $cooldown = $this->getCooldown($p, $tag);
                if ($cooldown === null) {
                    switch ($tag) {
                        case "bone":
                            if ($this->ability($damager, $tag)) {
                                $pn = $p->getName();
                                $this->cant_build[$pn] = true;
                                $this->addCooldown($p, $tag);
                                $this->getScheduler()->scheduleDelayedTask(new ClosureTask(function (int $currentTick) use ($pn): void {
                                    if (isset($this->cant_build[$pn])) {
                                        unset($this->cant_build[$pn]);
                                    }
                                }), 15 * 20);
                                $this->pop($damager);
                                $damager->sendMessage("§6You Have Successfully Boned §f{$p->getName()}§6 For §c15 seconds§6!");
                            }
                            break;
                        case "hotbar":
                            if ($this->ability($damager, $tag)) {
                                $this->addCooldown($p, $tag);
                                $items = [];
                                for ($i = 0; $i < 9; $i++) {
                                    $items[] = $p->getInventory()->getItem($i);
                                }
                                shuffle($items);
                                foreach ($items as $index => $item) {
                                    $p->getInventory()->setItem($index, $item);
                                }
                                $this->pop($damager);
                                $damager->sendMessage("§6You Have Successfully Scrambled §f{$p->getName()}'s §6Hotbar!");
                                $p->sendMessage("§6You Have Been Hit With a §bHotbar Scrambler §6Your Hotbar is Scrambled!");
                            }
                            break;
                        case "debuff":
                            if ($this->ability($damager, $tag)) {
                                $this->addCooldown($p, $tag);
                                $p->addEffect(new EffectInstance(Effect::getEffectByName("blindness"), 5 * 20, 2));
                                $p->addEffect(new EffectInstance(Effect::getEffectByName("poison"), 15 * 20, 0));
                                $this->pop($damager);
                                $damager->sendMessage("§6You Have Successfully Debuffed §f{$p->getName()}}§6!");
                                $p->sendMessage("§6You Have Been Hit With a §bDebuff§6 You Got Bad Effects!");
                            }
                            break;
                        case "potion":
                            if ($this->ability($damager, $tag)) {
                                $this->addCooldown($p, $tag);
                                $pots = (string)count(array_filter($p->getInventory()->getContents(), static function (Item $item) {
                                    return ($item->getId() === ItemIds::SPLASH_POTION or $item->getId() === ItemIds::POTION) && ($item->getDamage() === 22 || $item->getDamage() === 21);
                                }));
                                $this->pop($damager);
                                $damager->sendPopup("§b{$p->getName()} §6has §c$pots pots");
                                $p->sendMessage("§6You Have Successfully Used a §bPotion Counter §6On §f{$p->getName()}§6");
                            }
                            break;
                    }
                } else $damager->sendTip("§r§b" . $item->getName() . "§r§6 is on cooldown for §f" . (int)$cooldown[0] . "m $cooldown[1]s");
            }
        }
    }


    public function onDisable(): void
    {
        $this->hits = [];
        $this->cooldowns = [];
        $this->guardian = [];
        $this->cant_build = [];
    }
}
