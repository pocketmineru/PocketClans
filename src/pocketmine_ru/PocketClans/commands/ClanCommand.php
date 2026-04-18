<?php
declare(strict_types=1);

namespace pocketmine_ru\PocketClans\commands;

use pocketmine_ru\PocketClans\ClanManager;
use pocketmine_ru\PocketClans\forms\ClanForms;
use pocketmine_ru\PocketClans\Main;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

class ClanCommand extends Command {

    private Main $plugin;
    private ClanManager $clanManager;
    /** @var array<string, \pocketmine\world\Position> */
    public array $pos1 = [];
    /** @var array<string, \pocketmine\world\Position> */
    public array $pos2 = [];

    public function __construct(Main $plugin, ClanManager $clanManager) {
        parent::__construct("clan", "§rКланы");
        $this->setPermission("pocketclans.cmd");
        $this->setAliases(['clans', 'c']);
        $this->plugin = $plugin;
        $this->clanManager = $clanManager;
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): bool {
        if (!$sender instanceof Player) {
            $sender->sendMessage($this->plugin->getMessage("player-only"));
            return false;
        }

        if (empty($args)) {
            ClanForms::sendMainMenu($sender);
            return true;
        }

        $subCommand = array_shift($args);

        return match ($subCommand) {
            'create' => $this->handleCreate($sender, $args),
            'leave' => $this->handleLeave($sender),
            'invite' => $this->handleInvite($sender, $args),
            'accept' => $this->handleAccept($sender),
            'disband' => $this->handleDisband($sender, $args),
            'members' => $this->handleMembers($sender),
            'claim' => $this->handleClaim($sender, $args),
            'promote' => $this->handlePromote($sender, $args),
            'powertop' => $this->handlePowerTop($sender),
            'balancetop', 'moneytop' => $this->handleMoneyTop($sender),
            'status' => $this->handleStatus($sender),
            'seestatus', 'info' => $this->handleInfo($sender, $args),
            'bank' => $this->handleBank($sender),
            'deposit' => $this->handleDeposit($sender, $args),
            'withdraw' => $this->handleWithdraw($sender, $args),
            'admin' => $this->handleAdmin($sender, $args),
            'help', '?' => $this->sendHelp($sender),
            default => $this->unknownCommand($sender, $subCommand),
        };
    }

    private function sendHelp(Player $player): bool {
        $player->sendMessage($this->plugin->getMessage("help"));
        return true;
    }

    private function handleCreate(Player $player, array $args): bool {
        if (count($args) !== 1) {
            $player->sendMessage($this->plugin->getMessage("clan-create-price", ["{PRICE}" => number_format($this->plugin->createPrice, 2)]));
            return false;
        }

        $name = $args[0];

        if ($this->clanManager->getClan($player) !== null) {
            $player->sendMessage($this->plugin->getMessage("already-in-clan"));
            return false;
        }

        if ($this->clanManager->clanExists($name)) {
            $player->sendMessage($this->plugin->getMessage("clan-exists", ["{NAME}" => $name]));
            return false;
        }

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
            $player->sendMessage($this->plugin->getMessage("clan-create-name-invalid"));
            return false;
        }

        if (strlen($name) < $this->plugin->minClanNameLength || strlen($name) > $this->plugin->maxClanNameLength) {
            $player->sendMessage($this->plugin->getMessage("clan-create-name-length"));
            return false;
        }

        $economy = $this->plugin->getEconomyAPI();

        if ($economy !== null) {
            $playerMoney = $economy->myMoney($player);
            if ($playerMoney < $this->plugin->createPrice) {
                $player->sendMessage($this->plugin->getMessage("clan-create-no-money", [
                    "{PRICE}" => number_format($this->plugin->createPrice, 2),
                    "{MONEY}" => number_format($playerMoney, 2)
                ]));
                return false;
            }
            $economy->reduceMoney($player, $this->plugin->createPrice, true, "ClanCreate");
        }

        $this->clanManager->createClan($player, $name);
        $player->sendMessage($this->plugin->getMessage("clan-created", ["{NAME}" => $name]));

        return true;
    }

    private function handleLeave(Player $player): bool {
        $cname = $this->clanManager->getClan($player);

        if ($cname === null) {
            $player->sendMessage($this->plugin->getMessage("no-clan"));
            return false;
        }

        if ($this->clanManager->isClanLeader($player, $cname)) {
            $player->sendMessage($this->plugin->getMessage("leader-cannot-leave"));
            return false;
        }

        $this->clanManager->exitClan($player);
        return true;
    }

    private function handleInvite(Player $player, array $args): bool {
        if (count($args) !== 1) {
            $player->sendMessage($this->plugin->getMessage("help-invite"));
            return false;
        }

        $cname = $this->clanManager->getClan($player);
        if ($cname === null) {
            $player->sendMessage($this->plugin->getMessage("no-clan"));
            return false;
        }

        if (!$this->clanManager->isClanLeader($player, $cname)) {
            $player->sendMessage($this->plugin->getMessage("invite-not-leader"));
            return false;
        }

        $inviteeName = $args[0];
        $invitee = $this->plugin->getServer()->getPlayerByPrefix($inviteeName);

        if ($invitee === null) {
            $player->sendMessage($this->plugin->getMessage("invite-not-online", ["{NAME}" => $inviteeName]));
            return false;
        }

        if ($this->clanManager->getClan($invitee) !== null) {
            $player->sendMessage($this->plugin->getMessage("invite-already-in-clan", ["{NAME}" => $inviteeName]));
            return false;
        }

        if ($this->clanManager->sendInvitation($cname, $invitee)) {
            $player->sendMessage($this->plugin->getMessage("invite-sent", ["{NAME}" => $inviteeName]));
            $invitee->sendMessage($this->plugin->getMessage("invite-sent", ["{NAME}" => $cname]));
        }

        return true;
    }

    private function handleAccept(Player $player): bool {
        $cname = $this->clanManager->getPendingInvitation($player);

        if ($cname === null) {
            $player->sendMessage($this->plugin->getMessage("need-invite"));
            return false;
        }

        if ($this->clanManager->getClan($player) !== null) {
            $player->sendMessage($this->plugin->getMessage("already-has-clan"));
            return false;
        }

        $this->clanManager->joinClan($player, $cname);
        $player->sendMessage($this->plugin->getMessage("joined-clan", ["{NAME}" => $cname]));
        return true;
    }

    private function handleDisband(Player $player, array $args): bool {
        $cname = $this->clanManager->getClan($player);

        if ($cname === null) {
            $player->sendMessage($this->plugin->getMessage("no-clan"));
            return false;
        }

        if (!$this->clanManager->isClanLeader($player, $cname)) {
            $player->sendMessage($this->plugin->getMessage("only-leader-disband"));
            return false;
        }

        $this->clanManager->deleteClan($cname);
        $player->sendMessage($this->plugin->getMessage("clan-disbanded", ["{NAME}" => $cname]));
        return true;
    }

    private function handleMembers(Player $player): bool {
        $cname = $this->clanManager->getClan($player);

        if ($cname === null) {
            $player->sendMessage($this->plugin->getMessage("no-clan"));
            return false;
        }

        ClanForms::sendMyClanForm($player);
        return true;
    }

    private function handleClaim(Player $player, array $args): bool {
        if (count($args) !== 1) {
            $player->sendMessage($this->plugin->getMessage("help-claim"));
            return false;
        }

        $cname = $this->clanManager->getClan($player);
        if ($cname === null) {
            $player->sendMessage($this->plugin->getMessage("no-clan"));
            return false;
        }

        if (!$this->clanManager->isClanLeader($player, $cname)) {
            $player->sendMessage($this->plugin->getMessage("only-leader-claim"));
            return false;
        }

        $clanClaims = $this->clanManager->getClanClaims($cname);
        if (count($clanClaims) >= $this->plugin->maxClaimsPerClan) {
            $player->sendMessage($this->plugin->getMessage("claim-limit-reached", ["{MAX}" => $this->plugin->maxClaimsPerClan]));
            return false;
        }

        $type = $args[0];

        if ($type === 'pos1') {
            $this->pos1[$player->getName()] = $player->getPosition();
            $player->sendMessage($this->plugin->getMessage("claim-pos-set", ["{NUM}" => "1"]));
            return true;
        }

        if ($type === 'pos2') {
            $this->pos2[$player->getName()] = $player->getPosition();
            $player->sendMessage($this->plugin->getMessage("claim-pos-set", ["{NUM}" => "2"]));
            return true;
        }

        if ($type === 'save') {
            $pos1 = $this->pos1[$player->getName()] ?? null;
            $pos2 = $this->pos2[$player->getName()] ?? null;

            if ($pos1 === null || $pos2 === null) {
                $player->sendMessage($this->plugin->getMessage("claim-not-set"));
                return false;
            }

            $x1 = min($pos1->getFloorX(), $pos2->getFloorX());
            $y1 = min($pos1->getFloorY(), $pos2->getFloorY());
            $z1 = min($pos1->getFloorZ(), $pos2->getFloorZ());
            $x2 = max($pos1->getFloorX(), $pos2->getFloorX());
            $y2 = max($pos1->getFloorY(), $pos2->getFloorY());
            $z2 = max($pos1->getFloorZ(), $pos2->getFloorZ());

            $area = ($x2 - $x1 + 1) * ($y2 - $y1 + 1) * ($z2 - $z1 + 1);

            if ($area < $this->plugin->minClaimBlocks) {
                $player->sendMessage($this->plugin->getMessage("claim-too-small", ["{MIN}" => $this->plugin->minClaimBlocks]));
                return false;
            }

            if ($area > $this->plugin->maxClaimBlocks) {
                $player->sendMessage($this->plugin->getMessage("claim-too-big", ["{MAX}" => $this->plugin->maxClaimBlocks]));
                return false;
            }

            $clanClaims = $this->clanManager->getClanClaims($cname);
            if (count($clanClaims) >= $this->plugin->maxClaimsPerClan) {
                $player->sendMessage($this->plugin->getMessage("claim-limit-reached", ["{MAX}" => $this->plugin->maxClaimsPerClan]));
                return false;
            }

            $totalPrice = $area * $this->plugin->claimPricePerBlock;

            if ($this->clanManager->getClanBalance($cname) < $totalPrice) {
                $player->sendMessage($this->plugin->getMessage("claim-not-enough-money", ["{PRICE}" => number_format($totalPrice, 2)]));
                return false;
            }

            $this->clanManager->addClaim($cname, $x1, $y1, $z1, $x2, $y2, $z2, $pos1->getWorld()->getFolderName(), $area);
            $player->sendMessage($this->plugin->getMessage("claim-saved", ["{AREA}" => $area, "{PRICE}" => number_format($totalPrice, 2)]));

            unset($this->pos1[$player->getName()]);
            unset($this->pos2[$player->getName()]);
            return true;
        }

        $player->sendMessage($this->plugin->getMessage("help-claim"));
        return false;
    }

    private function handlePromote(Player $player, array $args): bool {
        $cname = $this->clanManager->getClan($player);

        if ($cname === null) {
            $player->sendMessage($this->plugin->getMessage("no-clan"));
            return false;
        }

        if (!$this->clanManager->isClanLeader($player, $cname)) {
            $player->sendMessage($this->plugin->getMessage("promote-not-leader"));
            return false;
        }

        if (count($args) !== 1) {
            $player->sendMessage($this->plugin->getMessage("help-promote"));
            return false;
        }

        $newLeader = $args[0];
        $members = $this->clanManager->getClanMembers($cname);

        if (!in_array($newLeader, $members, true)) {
            $player->sendMessage($this->plugin->getMessage("promote-not-member", ["{NAME}" => $newLeader]));
            return false;
        }

        $clans = &$this->clanManager->getClans();
        $clans[$cname]['leader'] = $newLeader;
        $this->clanManager->saveClans();

        $player->sendMessage($this->plugin->getMessage("promote-success", ["{NAME}" => $newLeader]));
        return true;
    }

    private function handlePowerTop(Player $player): bool {
        ClanForms::sendPowerTopForm($player);
        return true;
    }

    private function handleMoneyTop(Player $player): bool {
        ClanForms::sendBalanceTopForm($player);
        return true;
    }

    private function handleStatus(Player $player): bool {
        $cname = $this->clanManager->getClan($player);

        if ($cname === null) {
            $player->sendMessage($this->plugin->getMessage("no-clan"));
            return false;
        }

        ClanForms::sendMyClanForm($player);
        return true;
    }

    private function handleInfo(Player $player, array $args): bool {
        $cname = $this->clanManager->getClan($player);

        if (count($args) >= 1) {
            $cname = $args[0];
        }

        if ($cname === null) {
            $player->sendMessage($this->plugin->getMessage("help-info"));
            return false;
        }

        if (!$this->clanManager->clanExists($cname)) {
            $player->sendMessage($this->plugin->getMessage("clan-not-found", ["{NAME}" => $cname]));
            return false;
        }

        ClanForms::sendClanInfoForm($player, $cname);
        return true;
    }

    private function handleBank(Player $player): bool {
        $cname = $this->clanManager->getClan($player);

        if ($cname === null) {
            $player->sendMessage($this->plugin->getMessage("no-clan"));
            return false;
        }

        if ($this->plugin->getEconomyAPI() === null) {
            $player->sendMessage($this->plugin->getMessage("bank-unavailable"));
            return false;
        }

        ClanForms::sendBankForm($player, $cname);
        return true;
    }

    private function handleDeposit(Player $player, array $args): bool {
        $cname = $this->clanManager->getClan($player);

        if ($cname === null) {
            $player->sendMessage($this->plugin->getMessage("no-clan"));
            return false;
        }

        if (count($args) !== 1) {
            $player->sendMessage($this->plugin->getMessage("help-deposit"));
            return false;
        }

        $amount = $this->parseAmount($args[0], $player);
        if ($amount <= 0) {
            $player->sendMessage($this->plugin->getMessage("bank-not-enough", ["{AMOUNT}" => "0"]));
            return false;
        }

        $economy = $this->plugin->getEconomyAPI();
        if ($economy === null) {
            $player->sendMessage($this->plugin->getMessage("bank-unavailable"));
            return false;
        }

        $playerMoney = $economy->myMoney($player);
        if ($playerMoney < $amount) {
            $player->sendMessage($this->plugin->getMessage("bank-not-enough", ["{AMOUNT}" => number_format($playerMoney, 2)]));
            return false;
        }

        if ($this->clanManager->depositToClan($player, $cname, $amount)) {
            $player->sendMessage($this->plugin->getMessage("bank-deposit-success", ["{AMOUNT}" => number_format($amount, 2)]));
        } else {
            $player->sendMessage($this->plugin->getMessage("bank-error"));
        }

        return true;
    }

    private function handleWithdraw(Player $player, array $args): bool {
        $cname = $this->clanManager->getClan($player);

        if ($cname === null) {
            $player->sendMessage($this->plugin->getMessage("no-clan"));
            return false;
        }

        if (!$this->clanManager->isClanLeader($player, $cname)) {
            $player->sendMessage($this->plugin->getMessage("bank-withdraw-not-leader"));
            return false;
        }

        if (count($args) !== 1) {
            $player->sendMessage($this->plugin->getMessage("help-withdraw"));
            return false;
        }

        $amount = $this->parseAmount($args[0], $player);
        if ($amount <= 0) {
            $player->sendMessage($this->plugin->getMessage("bank-clan-empty"));
            return false;
        }

        $economy = $this->plugin->getEconomyAPI();
        if ($economy === null) {
            $player->sendMessage($this->plugin->getMessage("bank-unavailable"));
            return false;
        }

        $clanBalance = $this->clanManager->getClanBalance($cname);
        if ($clanBalance < $amount) {
            $player->sendMessage($this->plugin->getMessage("bank-clan-not-enough", ["{AMOUNT}" => number_format($clanBalance, 2)]));
            return false;
        }

        if ($this->clanManager->withdrawFromClan($player, $cname, $amount)) {
            $player->sendMessage($this->plugin->getMessage("bank-withdraw-success", ["{AMOUNT}" => number_format($amount, 2)]));
        } else {
            $player->sendMessage($this->plugin->getMessage("bank-error"));
        }

        return true;
    }

    private function parseAmount(string $input, Player $player): float {
        if (strtolower($input) === 'all') {
            $economy = $this->plugin->getEconomyAPI();
            if ($economy !== null) {
                return (float) $economy->myMoney($player);
            }
        }
        return (float) str_replace(',', '.', $input);
    }

    private function unknownCommand(Player $player, string $subCommand): bool {
        $player->sendMessage($this->plugin->getMessage("unknown-command", ["{SUBCMD}" => $subCommand]));
        $player->sendMessage($this->plugin->getMessage("unknown-command-hint"));
        return false;
    }

    private function handleAdmin(Player $player, array $args): bool {
        if (!$player->hasPermission("pocketclans.admin")) {
            $player->sendMessage($this->plugin->getMessage("no-permission"));
            return false;
        }

        if (count($args) < 1) {
            $this->sendAdminHelp($player);
            return true;
        }

        $action = array_shift($args);

        return match ($action) {
            "reset" => $this->handleAdminReset($player, $args),
            "delete" => $this->handleAdminDelete($player, $args),
            "setleader" => $this->handleAdminSetLeader($player, $args),
            "info" => $this->handleAdminInfo($player, $args),
            "list" => $this->handleAdminList($player),
            "reload" => $this->handleAdminReload($player),
            default => $this->sendAdminHelp($player),
        };
    }

    private function handleAdminReset(Player $player, array $args): bool {
        if (count($args) < 1) {
            $player->sendMessage($this->plugin->getMessage("admin-reset-usage"));
            return false;
        }

        $clanName = $args[0];

        if (!$this->clanManager->clanExists($clanName)) {
            $player->sendMessage($this->plugin->getMessage("clan-not-found", ["{NAME}" => $clanName]));
            return false;
        }

        $this->clanManager->resetClan($clanName);
        $player->sendMessage($this->plugin->getMessage("admin-reset-success", ["{NAME}" => $clanName]));
        return true;
    }

    private function handleAdminDelete(Player $player, array $args): bool {
        if (count($args) < 1) {
            $player->sendMessage($this->plugin->getMessage("admin-delete-usage"));
            return false;
        }

        $clanName = $args[0];

        if (!$this->clanManager->clanExists($clanName)) {
            $player->sendMessage($this->plugin->getMessage("clan-not-found", ["{NAME}" => $clanName]));
            return false;
        }

        $this->clanManager->deleteClan($clanName);
        $player->sendMessage($this->plugin->getMessage("admin-delete-success", ["{NAME}" => $clanName]));
        return true;
    }

    private function handleAdminSetLeader(Player $player, array $args): bool {
        if (count($args) < 2) {
            $player->sendMessage($this->plugin->getMessage("admin-setleader-usage"));
            return false;
        }

        $clanName = $args[0];
        $newLeader = $args[1];

        if (!$this->clanManager->clanExists($clanName)) {
            $player->sendMessage($this->plugin->getMessage("clan-not-found", ["{NAME}" => $clanName]));
            return false;
        }

        $this->clanManager->setClanLeader($clanName, $newLeader);
        $player->sendMessage($this->plugin->getMessage("admin-setleader-success", [
            "{CLAN}" => $clanName,
            "{PLAYER}" => $newLeader
        ]));
        return true;
    }

    private function handleAdminInfo(Player $player, array $args): bool {
        if (count($args) < 1) {
            $clanName = $this->clanManager->getClan($player);
            if ($clanName === null) {
                $player->sendMessage($this->plugin->getMessage("no-clan"));
                return false;
            }
        } else {
            $clanName = $args[0];
        }

        if (!$this->clanManager->clanExists($clanName)) {
            $player->sendMessage($this->plugin->getMessage("clan-not-found", ["{NAME}" => $clanName]));
            return false;
        }

        $clan = $this->clanManager->getClans()[$clanName];
        $player->sendMessage($this->plugin->getMessage("admin-info-header", ["{CLAN}" => $clanName]));
        $player->sendMessage($this->plugin->getMessage("admin-info-leader", ["{LEADER}" => $clan['leader']]));
        $player->sendMessage($this->plugin->getMessage("admin-info-members", ["{COUNT}" => count($clan['members'])]));
        $player->sendMessage($this->plugin->getMessage("admin-info-balance", ["{BALANCE}" => number_format($clan['balance'], 2)]));
        $player->sendMessage($this->plugin->getMessage("admin-info-power", ["{POWER}" => $clan['power']]));
        $player->sendMessage($this->plugin->getMessage("admin-info-claims", ["{COUNT}" => count($clan['claims'])]));
        return true;
    }

    private function handleAdminList(Player $player): bool {
        $clans = $this->clanManager->getClans();

        if (empty($clans)) {
            $player->sendMessage($this->plugin->getMessage("admin-no-clans"));
            return true;
        }

        $player->sendMessage($this->plugin->getMessage("admin-list-header", ["{COUNT}" => count($clans)]));
        foreach ($clans as $name => $clan) {
            $player->sendMessage($this->plugin->getMessage("admin-list-item", [
                "{NAME}" => $name,
                "{LEADER}" => $clan['leader'],
                "{COUNT}" => count($clan['members'])
            ]));
        }
        return true;
    }

    private function handleAdminReload(Player $player): bool {
        $this->clanManager->saveClans();
        $player->sendMessage($this->plugin->getMessage("admin-reload-success"));
        return true;
    }

    private function sendAdminHelp(Player $player): bool {
        $player->sendMessage($this->plugin->getMessage("admin-help-header"));
        $player->sendMessage($this->plugin->getMessage("admin-help-reset"));
        $player->sendMessage($this->plugin->getMessage("admin-help-delete"));
        $player->sendMessage($this->plugin->getMessage("admin-help-setleader"));
        $player->sendMessage($this->plugin->getMessage("admin-help-info"));
        $player->sendMessage($this->plugin->getMessage("admin-help-list"));
        $player->sendMessage($this->plugin->getMessage("admin-help-reload"));
        return true;
    }
}