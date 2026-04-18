<?php

declare(strict_types=1);

namespace pocketmine_ru\PocketClans\listener;

use pocketmine_ru\PocketClans\ClanManager;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\player\Player;

class ClansListener implements Listener {

    /** @var array<string, string> */
    private array $lastPositions = [];

    public function __construct(private ClanManager $clanManager) {}

    public function onEntityDamage(EntityDamageByEntityEvent $event): void {
        $entity = $event->getEntity();
        $damager = $event->getDamager();

        if (!$entity instanceof Player || !$damager instanceof Player) {
            return;
        }

        if ($entity->getHealth() - $event->getFinalDamage() <= 0) {
            $this->handlePlayerKill($entity, $damager);
        }
    }

    public function onBlockBreak(BlockBreakEvent $event): void {
        $player = $event->getPlayer();
        $block = $event->getBlock();
        $position = $block->getPosition();

        $clanAtPos = $this->clanManager->getClanAtPosition($position);
        if ($clanAtPos === null) {
            return;
        }

        $playerClan = $this->clanManager->getClan($player);
        if ($playerClan === null || $playerClan !== $clanAtPos) {
            $event->cancel();
            $player->sendMessage("§cВы не можете ломать блоки на чужой территории!");
        }
    }

    public function onBlockPlace(BlockPlaceEvent $event): void {
        $player = $event->getPlayer();
        $transaction = $event->getTransaction();
        $blocks = iterator_to_array($transaction->getBlocks());

        if (empty($blocks)) {
            return;
        }

        $firstBlock = $blocks[array_keys($blocks)[0]][3] ?? null;
        if ($firstBlock === null) {
            return;
        }

        $position = $firstBlock->getPosition();

        $clanAtPos = $this->clanManager->getClanAtPosition($position);
        if ($clanAtPos === null) {
            return;
        }

        $playerClan = $this->clanManager->getClan($player);
        if ($playerClan === null || $playerClan !== $clanAtPos) {
            $event->cancel();
            $player->sendMessage("§cВы не можете ставить блоки на чужой территории!");
        }
    }

    public function onPlayerInteract(PlayerInteractEvent $event): void {
        $player = $event->getPlayer();
        $block = $event->getBlock();
        $position = $block->getPosition();

        $clanAtPos = $this->clanManager->getClanAtPosition($position);
        if ($clanAtPos === null) {
            return;
        }

        $playerClan = $this->clanManager->getClan($player);
        if ($playerClan === null || $playerClan !== $clanAtPos) {
            if ($event->getAction() === PlayerInteractEvent::RIGHT_CLICK_BLOCK) {
                $event->cancel();
                $player->sendMessage("§cВы не можете взаимодействовать с блоками на чужой территории!");
            }
        }
    }

    public function onPlayerMove(PlayerMoveEvent $event): void {
        $player = $event->getPlayer();
        $to = $event->getTo();
        $from = $event->getFrom();

        $currentPos = $to->getFloorX() . ":" . $to->getFloorY() . ":" . $to->getFloorZ();
        $lastPos = $this->lastPositions[$player->getName()] ?? null;
        $this->lastPositions[$player->getName()] = $currentPos;

        if ($lastPos !== null && $lastPos === $currentPos) {
            return;
        }

        $playerClan = $this->clanManager->getClan($player);
        $fromClaim = $this->clanManager->getClanAtPosition($from);
        $toClaim = $this->clanManager->getClanAtPosition($to);

        if ($fromClaim === $toClaim) {
            return;
        }

        $plugin = $this->clanManager->getPlugin();

        if ($toClaim !== null) {
            $player->sendMessage($plugin->getMessage("notify-enter-region", ["{CLAN}" => $toClaim]));

            if ($playerClan !== null && $playerClan !== $toClaim) {
                foreach ($this->clanManager->getClanMembers($toClaim) as $memberName) {
                    $member = $player->getServer()->getPlayerByPrefix($memberName);
                    if ($member !== null && $member->isOnline()) {
                        $member->sendMessage($plugin->getMessage("notify-member-enter", ["{NAME}" => $player->getName()]));
                    }
                }
            }
        } elseif ($fromClaim !== null) {
            $player->sendMessage($plugin->getMessage("notify-leave-region", ["{CLAN}" => $fromClaim]));

            if ($playerClan !== null && $playerClan !== $fromClaim) {
                foreach ($this->clanManager->getClanMembers($fromClaim) as $memberName) {
                    $member = $player->getServer()->getPlayerByPrefix($memberName);
                    if ($member !== null && $member->isOnline()) {
                        $member->sendMessage($plugin->getMessage("notify-member-leave", ["{NAME}" => $player->getName()]));
                    }
                }
            }
        }
    }

    private function handlePlayerKill(Player $victim, Player $killer): void {
        $victimClan = $this->clanManager->getClan($victim);
        $killerClan = $this->clanManager->getClan($killer);

        if ($victimClan === null || $killerClan === null) {
            return;
        }

        if ($victimClan === $killerClan) {
            return;
        }

        $powerLost = $this->clanManager->getPlugin()->powerLostOnDeathPercent;

        $this->clanManager->reduceClanPower($victimClan, $powerLost);
        $this->clanManager->addClanPower($killerClan, $powerLost);

        $victim->sendMessage("§cВаш клан потерял {$powerLost} силы!");
        $killer->sendMessage("§aВаш клан получил {$powerLost} силы!");
    }
}