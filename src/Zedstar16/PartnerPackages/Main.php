<?php

declare(strict_types=1);

namespace Zedstar16\PartnerPackages;

use jojoe77777\FormAPI\SimpleForm;
use pocketmine\entity\Effect;
use pocketmine\entity\EffectInstance;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
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

    public $anti_redstone = [];

    public $last_hit = [];

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
        $p = $e->getPlayer();
        if (isset($this->cant_build[$p->getName()])) {
            $p->sendMessage("§6You Temporarily Cannot §fPlace §6Or §fBuild §6Because You Have Been Hit With a §bExotic Bone§6!");
            $e->setCancelled();
        }
        $item = $p->getInventory()->getItemInHand();
        if ($item->getNamedTag()->hasTag("ability")) {
            $e->setCancelled();
        }
    }

    public function onDeath(PlayerDeathEvent $e)
    {
        $n = $e->getPlayer()->getName();
        foreach ($this->last_hit as $player => $data) {
            if ($data["player"] === $n) {
                unset($this->last_hit[$player]);
            }
        }
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
    {
        if ($command->getName() === "pi") {
            if (isset($args[0])) {
                $list = [
                    "Guardian Angel:guardian:399:Activate This bad boy before you get in a tuff situation and you get healed to full hp when on 1 and a half hearts.",
                    "Potion Counter:potion:369:Hit a Player 3 Times With This And It Tells You How Many Pots They Have!",
                    "Vecro's Tank Ingot:tank:265:Right Click This To Obtain Resistance 3 For 5 Seconds!",
                    "Dol's Rage Ability:rage:377:Right Click This To Obtain Strength 2 For 5 Seconds!",
                    "Coffee's Debuff:debuff:397:Hit a player 3 times with this and they get bad effects for a small duration!",
                    "Night's Hotbar Scrambler:hotbar:280:Hit a Player 3 Times With This And It Scrambles Their Hotbar!",
                    "Misclick's Exotic Bone:bone:352:Hit a Player 3 Times With This Exotic Bone and They cant Place Or Break for 15 seconds!",
                    "Anti Redstone:redstone:331:Hit a Player 3 Times With This O.P. Item and they cant use redstone type blocks for 15 seconds!",
                    "Portable Bard:bard:371:Chose From 3 Options To Obtain One Of The Picked Effects!",
                    "GetAway Sugar:sugar:353:Right Click This To Obtain Speed 3 For 5 Seconds!",
                    "Ninja Star:ninja:399:Right Click This Item When You get hit by a enemy within 15 seconds and you get teleported to them!"
                ];
                $names = [];
                foreach ($list as $raw) {
                    $d = explode(":", $raw);
                    $names[$d[1]] = [$d[0], $d[2], "§r§7".$d[3], $d[1]];
                }
                if ($args[0] === "list") {
                    $msg = "§9=-= §aList of Partner Packages §9=-=\n";
                    foreach ($list as $raw) {
                        $d = explode(":", $raw);
                        $msg .= "§r§9- §e/pi (username) $d[1] §a- §eGives §b§l$d[0]\n";
                    }
                    $sender->sendMessage($msg);
                } else {
                    $p = $this->getServer()->getPlayer($args[0]);
                    if (isset($args[1])) {
                        if ($p !== null) {
                            if (array_key_exists($args[1], $names) || $args[1] === "random") {
                                $d = ($args[1] === "random") ? $names[array_keys($names)[mt_rand(0, count(array_keys($names))-1)]] : $names[$args[1]];
                                $item = ItemFactory::get((int)$d[1]);
                                $item->setCustomName("§r§l§b" . $d[0]);
                                if ($args[1] === "debuff") {
                                    $item->setDamage(1);
                                }
                                $item->addEnchantment(new EnchantmentInstance(new Enchantment(255, "", Enchantment::RARITY_COMMON, Enchantment::SLOT_ALL, Enchantment::SLOT_NONE, 1)));
                                $nbt = $item->getNamedTag();
                                $nbt->setString("ability", $d[3]);
                                $item->setCompoundTag($nbt);
                                $item->setLore([$d[2]]);
                                $p->getInventory()->addItem($item);
                                $p->sendMessage("§aYou Have Been Given a Partner Item!");
                                if ($sender->getName() !== $p->getName()) {
                                    $sender->sendMessage("§aYou have Given§f {$p->getName()}§a a Partner Item");
                                }
                            } else $sender->sendMessage("§cPartner package does not exist, use /pi list to view all current partner packages");
                        } else $sender->sendMessage("§cTarget player is not online");
                    } else $sender->sendMessage("§cSpecify a package, to view all packages run §e/pi list");
                }
            } else $sender->sendMessage("§9=-= §aPartner Package Help §9=-=\n§9- §b/pi (username) (package)§9 - Gives specified package to player\n§9- §b/pi (username) random§9 - Gives random package to player\n§9- §b/pi list §9- Lists all partner packages");
        }
        return true;
    }
    
    public function ability(Player $p, $tag)
    {
        $n = $p->getName();
        if (isset($this->hits[$n][$tag])) {
            $data = $this->hits[$n][$tag];
            if (count($data) >= 3) {
                $this->hits[$n][$tag] = [];
                return true;
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
            "guardian" => 120,
            "redstone" => 120,
            "bard" => 30,
            "sugar" => 90,
            "ninja" => 120
        ];
        $t = time() - $this->cooldowns[$p->getName()][$tag];
        if ($t < $cooldowns[$tag]) {
            return explode(":", gmdate("i:s", (int)($cooldowns[$tag] - $t)));
        }
        return null;
    }

    public function openBardForm(Player $p, callable $callable)
    {
        $form = new SimpleForm(function ($player, $data = null) use($callable) {
            if($data === null){
                return;
            }
            switch ($data) {
                case 0:
                    $player->addEffect(new EffectInstance(Effect::getEffectByName("strength"), 5 * 20, 1));
                    $player->sendMessage("§aYou Have Used §fStrength 2 §aFrom Your §bPortable Bard");
                    $this->pop($player);
                    $callable(true);
                    break;
                case 1:
                    $player->addEffect(new EffectInstance(Effect::getEffectByName("resistance"), 5 * 20, 2));
                    $player->sendMessage("§aYou Have Used §fResistance 3 §aFrom Your §bPortable Bard");
                    $this->pop($player);
                    $callable(true);
                    break;
                case 2:
                    $player->addEffect(new EffectInstance(Effect::getEffectByName("regeneration"), 5 * 20, 2));
                    $player->sendMessage("§aYou Have Used §fegeneration 3 §aFrom Your §bPortable Bard");
                    $this->pop($player);
                    $callable(true);
                    break;
            }
        });
        $form->setTitle("§ePortable Bard");
        foreach (["Strength 2", "Resistance 3", "Regeneration 3"] as $button) {
            $form->addButton("§8" . $button);
        }
        $p->sendForm($form);
    }

    /**
     * @param PlayerInteractEvent $event
     * @priority HIGHEST
     */
    public function onInteract(PlayerInteractEvent $event)
    {
        $p = $event->getPlayer();
        $item = $p->getInventory()->getItemInHand();
        $redstone_blocks = [
            ItemIds::REDSTONE_DUST,
            ItemIds::STONE_BUTTON,
            ItemIds::WOODEN_BUTTON,
            ItemIds::LEVER,
            ItemIds::SIGN,
            ItemIds::TRAPDOOR,
            ItemIds::IRON_TRAPDOOR,
            ItemIds::OAK_DOOR,
            ItemIds::IRON_DOOR,
            ItemIds::OAK_DOOR_BLOCK
        ];
        if (isset($this->anti_redstone[$p->getName()]) && in_array($event->getBlock()->getId(), $redstone_blocks, true)) {
            $p->sendMessage("§6You Are §bAnti-Redstoned §6You Cannot Interact With Redstone Type Blocks");
            $event->setCancelled();
        }
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
                    case "sugar":
                        $this->addCooldown($p, $tag);
                        $p->addEffect(new EffectInstance(Effect::getEffectByName("speed"), 5 * 20, 2));
                        $p->sendMessage("§6You Have Activated §bGetAway Sugar");
                        $this->pop($p);
                        break;
                    case "ninja":
                        if($event->getAction() !== PlayerInteractEvent::RIGHT_CLICK_BLOCK) {
                            $pn = $p->getName();
                            if (isset($this->last_hit[$pn])) {
                                if (time() - $this->last_hit[$pn]["time"] < 15) {
                                    $target = $this->getServer()->getPlayer($this->last_hit[$pn]["player"]);
                                    $this->addCooldown($p, $tag);
                                    $p->sendMessage("§6You Have Activated Your §bNinja Star §6Teleporting In 3 Seconds");
                                    $this->getScheduler()->scheduleDelayedTask(new ClosureTask(static function (int $currentTick) use ($p, $target): void {
                                        if ($p !== null) {
                                            $p->teleport($target);
                                            $target->sendMessage("§f{$p->getName()} §6Used a §bNinja Star§6 On You!");
                                        }
                                    }), 3 * 20);
                                    $this->pop($p);
                                } else $p->sendMessage("§cYou Do Not Have a Valid Attacker! Only Counts If It Was In a 15 Seconds Time Span!");
                            } else $p->sendMessage("§cYou Do Not Have a Attacker Or Your Attacker Has Died");
                        }
                        break;
                    case "bard":
                        if($event->getAction() !== PlayerInteractEvent::RIGHT_CLICK_BLOCK) {
                            $this->openBardForm($p, function ($bool) use ($p, $tag) {
                                if ($bool) {
                                    $this->addCooldown($p, $tag);
                                }
                            });
                        }
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
            $this->last_hit[$p->getName()] = [
                "time" => time(),
                "player" => $damager->getName()
            ];
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
                                $damager->sendMessage("§6You Have Successfully Used a §bPotion Counter §6On §f{$p->getName()}§6");
                            }
                            break;
                        case "redstone":
                            if ($this->ability($damager, $tag)) {
                                $pn = $p->getName();
                                $this->anti_redstone[$pn] = true;
                                $this->addCooldown($p, $tag);
                                $this->getScheduler()->scheduleDelayedTask(new ClosureTask(function (int $currentTick) use ($pn): void {
                                    if (isset($this->anti_redstone[$pn])) {
                                        unset($this->anti_redstone[$pn]);
                                    }
                                }), 15 * 20);
                                $this->pop($damager);
                                $damager->sendMessage("§6You Have Successfully §bAnti-Redstoned§f {$p->getName()}§6!");
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
        $this->guardian = [];
        $this->last_hit = [];
        $this->anti_redstone = [];
    }
}
