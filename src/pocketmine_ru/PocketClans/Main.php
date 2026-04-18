<?php
declare(strict_types=1);

namespace pocketmine_ru\PocketClans;

use pocketmine\command\CommandMap;
use pocketmine\utils\SingletonTrait;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use onebone\economyapi\EconomyAPI;
use pocketmine_ru\PocketClans\commands\AdminCommand;
use pocketmine_ru\PocketClans\commands\ClanCommand;
use pocketmine_ru\PocketClans\listener\ClansListener;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;

class Main extends PluginBase {
    use SingletonTrait;

    public ClanManager $clanManager;
    public ?EconomyAPI $economyAPI = null;

    private Config $lang;
    private Config $cfg;

    public string $prefix;
    public string $separator;

    public float $createPrice;
    public float $claimPricePerBlock;
    public int $powerPerBlock;
    public int $powerLostOnDeathPercent;

    public int $maxClanNameLength;
    public int $minClanNameLength;
    public int $maxMembers;
    public int $maxClaimsPerClan;
    public int $minClaimBlocks;
    public int $maxClaimBlocks;
    public int $autoSaveInterval;
    public bool $pvpBetweenClans;
    public bool $pvpWithinClan;
    public float $minDeposit;
    public float $minWithdraw;

    public string $iconBack;
    public string $iconInfo;
    public string $iconMoney;
    public string $iconLeader;
    public string $iconSword;
    public string $iconList;
    public string $iconHelp;
    public string $iconInvite;
    public string $iconMember;
    public string $iconSettings;
    public string $iconLeave;
    public string $iconCreate;
    public string $iconSteve;
    public string $iconMultiplayer;
    public string $iconTrending;
    public string $iconAdd;

    protected function onLoad(): void {
        self::setInstance($this);
    }

    protected function onEnable(): void {
        $this->checkFolders();
        $this->initConfig();
        $this->initLang();
        $this->initEconomy();
        $this->clanManager = new ClanManager($this);
        $this->registerCommands();
        $this->registerEvents();
        $this->scheduleAutoSave();
    }

    protected function onDisable(): void {
        if (isset($this->clanManager)) {
            $this->clanManager->saveClans();
        }
    }

    private function checkFolders(): void {
        @mkdir($this->getDataFolder());
        @mkdir($this->getDataFolder() . "player_data");
    }

    private function initConfig(): void {
        $this->saveResource("config.yml");
        $this->cfg = new Config($this->getDataFolder() . "config.yml", Config::YAML);

        $icons = $this->cfg->get("icons", []);
        $this->iconBack = $icons['back'] ?? "textures/ui/back_button_default.png";
        $this->iconInfo = $icons['info'] ?? "textures/ui/infobulb.png";
        $this->iconMoney = $icons['money'] ?? "textures/ui/icon_minecoin_9x9.png";
        $this->iconLeader = $icons['leader'] ?? "textures/ui/creative_icon.png";
        $this->iconSword = $icons['sword'] ?? "textures/ui/attack.png";
        $this->iconList = $icons['list'] ?? "textures/ui/book_edit_default.png";
        $this->iconHelp = $icons['help'] ?? "textures/ui/book_addtextpage_default.png";
        $this->iconInvite = $icons['invite'] ?? "textures/ui/dark_plus.png";
        $this->iconMember = $icons['member'] ?? "textures/ui/icon_multiplayer.png";
        $this->iconSettings = $icons['settings'] ?? "textures/ui/gear.png";
        $this->iconLeave = $icons['leave'] ?? "textures/ui/arrow_dark_right.png";
        $this->iconCreate = $icons['create'] ?? "textures/ui/creative_icon.png";
        $this->iconSteve = $icons['steve'] ?? "textures/ui/icon_steve.png";
        $this->iconMultiplayer = $icons['multiplayer'] ?? "textures/ui/icon_multiplayer.png";
        $this->iconTrending = $icons['trending'] ?? "textures/ui/icon_trending.png";
        $this->iconAdd = $icons['add'] ?? "textures/ui/dark_plus.png";

        $this->createPrice = (float) $this->cfg->get("create-price", 1000);
        $this->claimPricePerBlock = (float) $this->cfg->get("claim-price-per-block", 10);
        $this->powerPerBlock = (int) $this->cfg->get("power-per-block", 10);
        $this->powerLostOnDeathPercent = (int) $this->cfg->get("power-lost-on-death-percent", 5);

        $this->maxClanNameLength = (int) $this->cfg->get("max-clan-name-length", 16);
        $this->minClanNameLength = (int) $this->cfg->get("min-clan-name-length", 3);
        $this->maxMembers = (int) $this->cfg->get("max-members", 20);
        $this->maxClaimsPerClan = (int) $this->cfg->get("max-claims-per-clan", 1);
        $this->minClaimBlocks = (int) $this->cfg->get("min-claim-blocks", 16);
        $this->maxClaimBlocks = (int) $this->cfg->get("max-claim-blocks", 10000);
        $this->autoSaveInterval = (int) $this->cfg->get("auto-save-interval", 300);
        $this->pvpBetweenClans = (bool) $this->cfg->get("pvp-between-clans", true);
        $this->pvpWithinClan = (bool) $this->cfg->get("pvp-within-clan", false);
        $this->minDeposit = (float) $this->cfg->get("min-deposit", 1);
        $this->minWithdraw = (float) $this->cfg->get("min-withdraw", 1);
    }

    private function initLang(): void {
        $this->saveResource("lang.yml", false);
        $this->lang = new Config($this->getDataFolder() . "lang.yml", Config::YAML);

        $rawPrefix = $this->lang->get("prefix", "§8(§bКланы§8) §a> §f");
        $rawSeparator = $this->lang->get("separator", "§8───────────────");

        $this->prefix = is_string($rawPrefix) ? $this->colorize($rawPrefix) : "§8(§bКланы§8) §a> §f";
        $this->separator = is_string($rawSeparator) ? $this->colorize($rawSeparator) : "§8───────────────";
    }

    private function initEconomy(): void {
        $economy = $this->getServer()->getPluginManager()->getPlugin("EconomyAPI");
        if ($economy !== null && $economy->isEnabled()) {
            $this->economyAPI = EconomyAPI::getInstance();
            $this->getLogger()->info("EconomyAPI подключен!");
        }
    }

    private function registerCommands(): void {
        $this->getServer()->getCommandMap()->register(
            "PocketClans",
            new ClanCommand($this, $this->clanManager)
        );
    }

    private function registerEvents(): void {
        $this->getServer()->getPluginManager()->registerEvents(
            new ClansListener($this->clanManager),
            $this
        );
    }

    private function scheduleAutoSave(): void {
        $interval = $this->autoSaveInterval * 20;
        $this->getScheduler()->scheduleDelayedTask(new ClosureTask(function (): void {
            if ($this->isEnabled()) {
                $this->clanManager->saveClans();
            }
        }), $interval);

        $this->getScheduler()->scheduleDelayedTask(new ClosureTask(function (): void {
            $this->scheduleAutoSave();
        }), $interval);
    }

    public function getEconomyAPI(): ?EconomyAPI {
        return $this->economyAPI;
    }

    public function getClanManager(): ClanManager {
        return $this->clanManager;
    }

    public static function getInstance(): self {
        return self::$instance;
    }

    public function get(string $key, mixed $default = null): mixed {
        $keys = explode(".", $key);
        $value = $this->lang->getAll();

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    public function getMessage(string $key, array $placeholders = []): string {
        $msg = $this->lang->get($key);
        if ($msg === false || $msg === null) {
            $msg = $key;
        }

        foreach ($placeholders as $placeholder => $value) {
            $msg = str_replace($placeholder, (string) $value, $msg);
        }

        return $this->prefix . $msg;
    }

    public function sendMessage(Player $player, string $key, array $placeholders = []): void {
        $player->sendMessage($this->getMessage($key, $placeholders));
    }

    public function colorize(string $string): string {
        return str_replace("&", "§", $string);
    }
}