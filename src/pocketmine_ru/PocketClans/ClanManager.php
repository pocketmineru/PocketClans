<?php
declare(strict_types=1);

namespace pocketmine_ru\PocketClans;

use pocketmine\player\Player;

class ClanManager {

    /** @var array<string, array{name: string, leader: string, members: array<string>, claims: array, power: int, balance: float, description: string}> */
    public array $clans = [];
    /** @var array<string, string> */
    public array $pendingInvitations = [];

    private Main $plugin;
    private string $dataFile;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
        $this->dataFile = $this->plugin->getDataFolder() . "clans.json";
        $this->loadClans();
    }

    public function createClan(Player $player, string $name): bool {
        $name = strtolower($name);

        if (isset($this->clans[$name])) {
            return false;
        }

        $this->clans[$name] = [
            'name' => $name,
            'leader' => strtolower($player->getName()),
            'members' => [strtolower($player->getName())],
            'claims' => [],
            'power' => 0,
            'balance' => 0.0,
            'description' => ""
        ];
        $this->saveClans();
        return true;
    }

    public function exitClan(Player $player): bool {
        $playerName = strtolower($player->getName());

        foreach ($this->clans as $name => $c) {
            if (in_array($playerName, $c['members'], true)) {
                $this->clans[$name]['members'] = array_values(array_filter(
                    $this->clans[$name]['members'],
                    fn(string $member): bool => $member !== $playerName
                ));

                if ($c['leader'] === $playerName) {
                    if (count($this->clans[$name]['members']) > 0) {
                        $newLeader = $this->clans[$name]['members'][0];
                        $this->clans[$name]['leader'] = $newLeader;
                        $player->sendMessage($this->plugin->getMessage("left-clan-new-leader", ["{NEW_LEADER}" => $newLeader]));
                        $this->notifyClan($name, $this->plugin->getMessage("notify-new-leader", ["{NAME}" => $playerName, "{NEW_LEADER}" => $newLeader]));
                    } else {
                        unset($this->clans[$name]);
                        $player->sendMessage($this->plugin->getMessage("left-clan-deleted"));
                    }
                } else {
                    $player->sendMessage($this->plugin->getMessage("left-clan"));
                    $this->notifyClan($name, $this->plugin->getMessage("notify-member-left", ["{NAME}" => $playerName]));
                }

                $this->saveClans();
                return true;
            }
        }

        return false;
    }

    public function joinClan(Player $player, string $name): bool {
        $name = strtolower($name);

        if (!isset($this->clans[$name])) {
            return false;
        }

        $this->clans[$name]['members'][] = strtolower($player->getName());
        $this->saveClans();
        $player->sendMessage($this->plugin->getMessage("joined-clan", ["{NAME}" => $name]));
        $this->notifyClan($name, $this->plugin->getMessage("notify-member-joined", ["{NAME}" => $player->getName()]));
        return true;
    }

    private function notifyClan(string $clanName, string $message): void {
        if (!isset($this->clans[$clanName])) {
            return;
        }

        foreach ($this->clans[$clanName]['members'] as $memberName) {
            $member = $this->plugin->getServer()->getPlayerByPrefix($memberName);
            if ($member !== null && $member->isOnline()) {
                $member->sendMessage($message);
            }
        }
    }

    public function getClan(Player $player): ?string {
        $playerName = strtolower($player->getName());

        foreach ($this->clans as $clanData) {
            if (in_array($playerName, $clanData['members'] ?? [], true)) {
                return $clanData['name'] ?? null;
            }
        }
        return null;
    }

    public function getPlugin(): Main {
        return $this->plugin;
    }

    public function getClanAtPosition($position): ?string {
        foreach ($this->clans as $clanData) {
            $claims = $clanData['claims'] ?? [];
            if (!is_array($claims)) {
                continue;
            }
            foreach ($claims as $claimData) {
                if (!is_array($claimData)) {
                    continue;
                }
                $required = ['x1', 'y1', 'z1', 'x2', 'y2', 'z2', 'world'];
                $hasAll = true;
                foreach ($required as $key) {
                    if (!isset($claimData[$key])) {
                        $hasAll = false;
                        break;
                    }
                }
                if (!$hasAll) {
                    continue;
                }
                if ($claimData['world'] !== $position->getWorld()->getFolderName()) {
                    continue;
                }
                $minX = min($claimData['x1'], $claimData['x2']);
                $maxX = max($claimData['x1'], $claimData['x2']);
                $minY = min($claimData['y1'], $claimData['y2']);
                $maxY = max($claimData['y1'], $claimData['y2']);
                $minZ = min($claimData['z1'], $claimData['z2']);
                $maxZ = max($claimData['z1'], $claimData['z2']);
                $x = $position->getFloorX();
                $y = $position->getFloorY();
                $z = $position->getFloorZ();
                if ($x >= $minX && $x <= $maxX && $y >= $minY && $y <= $maxY && $z >= $minZ && $z <= $maxZ) {
                    return $clanData['name'];
                }
            }
        }
        return null;
    }

    public function getClanMembers(string $name): array {
        $clan = $this->clans[$name] ?? null;
        return is_array($clan['members'] ?? null) ? $clan['members'] : [];
    }

    public function isClanLeader(Player $player, string $name): bool {
        $name = strtolower($name);
        return isset($this->clans[$name]) && $this->clans[$name]['leader'] === strtolower($player->getName());
    }

    public function getClanLeader(string $name): ?string {
        $clan = $this->clans[$name] ?? null;
        return is_string($clan['leader'] ?? null) ? $clan['leader'] : null;
    }

    /**
     * @return array<string, array{name: string, leader: string, members: array<string>, claims: array, power: int, balance: float, description: string}>
     */
    public function &getClans(): array {
        return $this->clans;
    }

    public function clanExists(string $name): bool {
        return isset($this->clans[$name]);
    }

    public function setClanDescription(string $name, string $description): bool {
        if (isset($this->clans[$name])) {
            $this->clans[$name]['description'] = $description;
            $this->saveClans();
            return true;
        }
        return false;
    }

    public function getClanDescription(string $name): string {
        return $this->clans[$name]['description'] ?? "";
    }

    public function addClaim(string $clanName, int $x1, int $y1, int $z1, int $x2, int $y2, int $z2, string $world, int $area): bool {
        if (!isset($this->clans[$clanName])) {
            return false;
        }

        $totalPrice = $area * $this->plugin->claimPricePerBlock;

        if (!$this->reduceClanBalance($clanName, $totalPrice)) {
            return false;
        }

        $this->clans[$clanName]['claims'][] = [
            'x1' => $x1,
            'y1' => $y1,
            'z1' => $z1,
            'x2' => $x2,
            'y2' => $y2,
            'z2' => $z2,
            'world' => $world,
            'area' => $area
        ];

        $this->clans[$clanName]['power'] += $area * $this->plugin->powerPerBlock;
        $this->saveClans();
        return true;
    }

    public function getClanClaims(string $name): array {
        return $this->clans[$name]['claims'] ?? [];
    }

    public function deleteClan(string $name): bool {
        if (isset($this->clans[$name])) {
            unset($this->clans[$name]);
            $this->saveClans();
            return true;
        }
        return false;
    }

    public function resetClan(string $name): bool {
        if (!isset($this->clans[$name])) {
            return false;
        }

        $this->clans[$name]['balance'] = 0.0;
        $this->clans[$name]['power'] = 0;
        $this->clans[$name]['claims'] = [];
        $this->saveClans();
        return true;
    }

    public function setClanLeader(string $name, string $newLeader): bool {
        if (!isset($this->clans[$name])) {
            return false;
        }

        if (!in_array($newLeader, $this->clans[$name]['members'], true)) {
            return false;
        }

        $this->clans[$name]['leader'] = $newLeader;
        $this->saveClans();
        return true;
    }

    public function claimLand(string $clanName, int $claimedArea, Player $player): bool {
        if (!isset($this->clans[$clanName])) {
            return false;
        }

        $totalPrice = $claimedArea * $this->plugin->claimPricePerBlock;

        if ($this->reduceClanBalance($clanName, $totalPrice)) {
            $this->clans[$clanName]['claims'][] = $claimedArea;
            $this->updateClanPower($clanName);
            $this->saveClans();
            return true;
        }

        $player->sendMessage($this->plugin->getMessage("claim-not-enough-money", ["{PRICE}" => number_format($totalPrice, 2)]));
        return false;
    }

    public function sendInvitation(string $clanName, Player $invitee): bool {
        $inviteeName = $invitee->getName();
        if (!isset($this->pendingInvitations[$inviteeName])) {
            $this->pendingInvitations[$inviteeName] = $clanName;
            return true;
        }
        return false;
    }

    public function getPendingInvitation(Player $player): ?string {
        $playerName = $player->getName();
        if (isset($this->pendingInvitations[$playerName])) {
            $clan = $this->pendingInvitations[$playerName];
            unset($this->pendingInvitations[$playerName]);
            return $clan;
        }
        return null;
    }

    public function getClanPower(string $name): int {
        $clan = $this->clans[$name] ?? null;
        return is_int($clan['power'] ?? null) ? $clan['power'] : 0;
    }

    public function setClanPower(string $name, int $power): bool {
        if (!isset($this->clans[$name])) {
            return false;
        }
        $this->clans[$name]['power'] = $power;
        $this->saveClans();
        return true;
    }

    public function addClanPower(string $name, int $amount): bool {
        if (!isset($this->clans[$name])) {
            return false;
        }
        $this->clans[$name]['power'] = ($this->clans[$name]['power'] ?? 0) + $amount;
        $this->saveClans();
        return true;
    }

    public function reduceClanPower(string $name, int $amount): bool {
        if (!isset($this->clans[$name])) {
            return false;
        }
        $this->clans[$name]['power'] = ($this->clans[$name]['power'] ?? 0) - $amount;
        $this->saveClans();
        return true;
    }

    public function updateClanPower(string $clanName): void {
        if (!isset($this->clans[$clanName])) {
            return;
        }

        $claimedAreas = $this->clans[$clanName]['claims'] ?? [];
        $totalPower = 0;
        foreach ($claimedAreas as $claimData) {
            $area = is_array($claimData) ? ($claimData['area'] ?? 0) : (int) $claimData;
            $totalPower += $area * $this->plugin->powerPerBlock;
        }
        $this->clans[$clanName]['power'] = $totalPower;
    }

    public function getClanBalance(string $name): float {
        $clan = $this->clans[$name] ?? null;
        $balance = $clan['balance'] ?? null;
        return is_float($balance) || is_int($balance) ? (float) $balance : 0.0;
    }

    public function setClanBalance(string $name, float $balance): bool {
        if (!isset($this->clans[$name])) {
            return false;
        }
        $this->clans[$name]['balance'] = $balance;
        $this->saveClans();
        return true;
    }

    public function addClanBalance(string $name, float $amount): bool {
        if (!isset($this->clans[$name])) {
            return false;
        }
        $this->clans[$name]['balance'] = ($this->clans[$name]['balance'] ?? 0.0) + $amount;
        $this->saveClans();
        return true;
    }

    public function reduceClanBalance(string $name, float $amount): bool {
        if (!isset($this->clans[$name])) {
            return false;
        }
        $currentBalance = $this->clans[$name]['balance'] ?? 0.0;
        if ($currentBalance < $amount) {
            return false;
        }
        $this->clans[$name]['balance'] = $currentBalance - $amount;
        $this->saveClans();
        return true;
    }

    public function depositToClan(Player $player, string $clanName, float $amount): bool {
        $economy = $this->plugin->getEconomyAPI();
        if ($economy === null) {
            return false;
        }

        $playerMoney = $economy->myMoney($player);
        if ($playerMoney < $amount) {
            return false;
        }

        $economy->reduceMoney($player, $amount, true, "ClanDeposit");
        $this->addClanBalance($clanName, $amount);
        return true;
    }

    public function kickMember(string $clanName, string $memberName): bool {
        if (!isset($this->clans[$clanName])) {
            return false;
        }

        $memberNameLower = strtolower($memberName);
        $members = &$this->clans[$clanName]['members'];

        $index = array_search($memberNameLower, $members, true);
        if ($index === false) {
            return false;
        }

        if ($this->clans[$clanName]['leader'] === $memberNameLower) {
            return false;
        }

        unset($members[$index]);
        $this->clans[$clanName]['members'] = array_values($members);
        $this->saveClans();

        $this->notifyClan($clanName, $this->plugin->getMessage("notify-member-kicked", ["{NAME}" => $memberName]));
        return true;
    }

    public function withdrawFromClan(Player $player, string $clanName, float $amount): bool {
        $economy = $this->plugin->getEconomyAPI();
        if ($economy === null) {
            return false;
        }

        if (!$this->reduceClanBalance($clanName, $amount)) {
            return false;
        }

        $economy->addMoney($player, $amount, true, "ClanWithdraw");
        return true;
    }

    private function loadClans(): void {
        if (!file_exists($this->dataFile)) {
            $this->clans = [];
            return;
        }

        $content = file_get_contents($this->dataFile);
        if ($content === false || $content === '') {
            $this->clans = [];
            return;
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            $this->clans = [];
            return;
        }

        $this->clans = [];
        foreach ($data as $clan) {
            if (!is_array($clan)) {
                continue;
            }
            $name = $clan['name'] ?? null;
            if (!is_string($name) || $name === '') {
                continue;
            }
            $clanName = strtolower($name);
            $this->clans[$clanName] = [
                'name' => $clanName,
                'leader' => $clan['leader'] ?? '',
                'members' => is_array($clan['members'] ?? null) ? $clan['members'] : [],
                'claims' => is_array($clan['claims'] ?? null) ? $clan['claims'] : [],
                'power' => is_int($clan['power'] ?? null) ? $clan['power'] : 0,
                'balance' => is_float($clan['balance'] ?? null) || is_int($clan['balance'] ?? null) ? (float) $clan['balance'] : 0.0,
                'description' => is_string($clan['description'] ?? null) ? $clan['description'] : ''
            ];
        }
    }

    public function saveClans(): void {
        file_put_contents($this->dataFile, json_encode(array_values($this->clans), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}