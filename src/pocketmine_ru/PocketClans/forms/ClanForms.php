<?php
declare(strict_types=1);

namespace pocketmine_ru\PocketClans\forms;

use pocketmine\player\Player;
use pocketmine_ru\PocketClans\libs\formAPI\SimpleForm;
use pocketmine_ru\PocketClans\libs\formAPI\ModalForm;
use pocketmine_ru\PocketClans\libs\formAPI\CustomForm;
use pocketmine_ru\PocketClans\Main;

class ClanForms {

    public static function sendMainMenu(Player $player): void {
        $main = Main::getInstance();
        $clanManager = $main->getClanManager();
        $playerClan = $clanManager->getClan($player);
        $isLeader = $playerClan !== null && $clanManager->isClanLeader($player, $playerClan);
        $hasInvite = $clanManager->getPendingInvitation($player) !== null;

        $form = new SimpleForm(function (Player $player, mixed $data) use ($main): void {
            if ($data === null) return;

            $clanManager = $main->getClanManager();
            $pClan = $clanManager->getClan($player);

            match ($data) {
                "create" => self::sendCreateForm($player),
                "list" => self::sendClanListForm($player),
                "help" => self::sendHelpForm($player),
                "accept" => self::handleAcceptInvite($player),
                "info" => $pClan !== null ? self::sendMyClanForm($player) : $player->sendMessage($main->getMessage("no-clan")),
                "bank" => $pClan !== null ? self::sendBankForm($player, $pClan) : $player->sendMessage($main->getMessage("no-clan")),
                "members" => $pClan !== null ? self::sendMembersForm($player, $pClan) : $player->sendMessage($main->getMessage("no-clan")),
                "powertop" => self::sendPowerTopForm($player),
                "balancetop" => self::sendBalanceTopForm($player),
                "invite" => $pClan !== null ? self::sendInviteForm($player) : $player->sendMessage($main->getMessage("no-clan")),
                "leave" => $pClan !== null ? self::sendLeaveConfirm($player, $pClan) : null,
                "manage" => $pClan !== null ? self::sendManageMembersForm($player, $pClan) : null,
                default => null,
            };
        });

        $form->setTitle($main->get("form-title-main", "§b§lКЛАНЫ §7| §0Главное меню"));

        if ($playerClan !== null) {
            $leader = $clanManager->getClanLeader($playerClan);
            $members = $clanManager->getClanMembers($playerClan);
            $power = $clanManager->getClanPower($playerClan);
            $balance = number_format($clanManager->getClanBalance($playerClan), 2);
            $memberCount = count($members);
            $claims = $clanManager->getClanClaims($playerClan);
            $claimsCount = count($claims);
            $totalArea = 0;
            foreach ($claims as $claim) {
                if (is_array($claim) && isset($claim['area'])) {
                    $totalArea += $claim['area'];
                }
            }

            $form->setContent($main->get("form-content-main", "§7Выберите действие:"));

            $form->addButton($main->get("btn-info", "§8[§eИнформация§8]"), 0, $main->iconInfo, "info");
            $form->addButton($main->get("btn-bank", "§8[§cБанк§8]"), 0, $main->iconMoney, "bank");
            $form->addButton($main->get("btn-members", "§8[§eУчастники§8]"), 0, $main->iconMultiplayer, "members");
            $form->addButton($main->get("btn-power-top", "§8[§6Топ силы§8]"), 0, $main->iconTrending, "powertop");
            $form->addButton($main->get("btn-balance-top", "§8[§bТоп богатства§8]"), 0, $main->iconMoney, "balancetop");

            if ($isLeader) {
                $form->addButton($main->get("btn-invite", "§8[§dПриглашения§8]"), 0, $main->iconAdd, "invite");
                $form->addButton($main->get("btn-manage-members", "§8[§cУправление§8]"), 0, $main->iconSettings, "manage");
            } else {
                $form->addButton($main->get("btn-leave-clan", "§8[§6Покинуть§8]"), 0, $main->iconLeave, "leave");
            }

            $form->addButton($main->get("btn-clan-list", "§8[§fСписок кланов§8]"), 0, $main->iconList, "list");
            $form->addButton($main->get("btn-help", "§8[§7Помощь§8]"), 0, $main->iconHelp, "help");
        } else {
            $form->setContent($main->get("form-content-no-clan", "§7Вы не состоите в клане"));
            $form->addButton($main->get("btn-create-clan", "§8[§aСоздать клан§8]"), 0, $main->iconCreate, "create");
            $form->addButton($main->get("btn-clan-list", "§8[§fСписок кланов§8]"), 0, $main->iconList, "list");

            if ($hasInvite) {
                $form->addButton($main->get("btn-accept-invite", "§8[§aПринять приглашение§8]"), 0, $main->iconAdd, "accept");
            }

            $form->addButton($main->get("btn-help", "§8[§7Помощь§8]"), 0, $main->iconHelp, "help");
        }

        $player->sendForm($form);
    }

    private static function handleAcceptInvite(Player $player): void {
        $main = Main::getInstance();
        $clanManager = $main->getClanManager();
        $cname = $clanManager->getPendingInvitation($player);

        if ($cname === null) {
            $player->sendMessage($main->getMessage("need-invite"));
            return;
        }

        if ($clanManager->getClan($player) !== null) {
            $player->sendMessage($main->getMessage("already-has-clan"));
            return;
        }

        $clanManager->joinClan($player, $cname);
        $player->sendMessage($main->getMessage("joined-clan", ["{NAME}" => $cname]));
    }

    public static function sendHelpForm(Player $player): void {
        $main = Main::getInstance();

        $form = new SimpleForm(function (Player $player, mixed $data): void {
            self::sendMainMenu($player);
        });

        $form->setTitle($main->get("form-title-help", "§b§lКЛАНЫ §7| §0Помощь"));
        $form->setContent(
            "§f§l▶ §b§lПОМОЩЬ§f§l◀\n\n" .
            "§7§l▸ §fКоманды кланов:\n\n" .
            "§a/c create <имя> §f- Создать клан\n" .
            "§a/c leave §f- Покинуть клан\n" .
            "§a/c invite <ник> §f- Пригласить\n" .
            "§a/c accept §f- Принять приглашение\n" .
            "§a/c members §f- Участники\n" .
            "§a/c info [имя] §f- Информация\n" .
            "§a/c bank §f- Банк\n" .
            "§a/c deposit <сумма> §f- Вклад\n" .
            "§a/c withdraw <сумма> §f- Снятие\n" .
            "§a/c powertop §f- Топ силы\n" .
            "§a/c balancetop §f- Топ богатства\n\n" .
            "§c§lАдмин команды:\n" .
            "§f/c admin reset|delete|setleader"
        );
        $form->addButton($main->get("btn-back", "§8[§cНазад§8]"), 0, $main->iconBack, "back");

        $player->sendForm($form);
    }

    public static function sendPowerTopForm(Player $player): void {
        $main = Main::getInstance();
        $clans = $main->getClanManager()->getClans();

        $form = new SimpleForm(function (Player $player, mixed $data): void {
            self::sendMainMenu($player);
        });

        $form->setTitle($main->get("form-title-power-top", "§6§lТОП СИЛЫ §7| §0Рейтинг"));

        if (empty($clans)) {
            $form->setContent("§f§l▶ §6§lТОП СИЛЫ§f§l◀\n\n§cНет кланов!");
        } else {
            usort($clans, fn($a, $b) => $b['power'] <=> $a['power']);
            $list = "";
            $count = 0;
            foreach ($clans as $c) {
                $count++;
                $rankColor = match ($count) {
                    1 => "§l§6",
                    2 => "§l§e",
                    3 => "§l§f",
                    default => "§8",
                };
                $powerColor = $count <= 3 ? "§a" : "§7";
                $list .= "{$rankColor}{$count}. §a{$c['name']} §8- {$powerColor}Сила: {$c['power']}\n";
                if ($count >= 10) break;
            }

            $content = $main->get("form-content-power-top", "§fТоп кланов по силе:");
            $content = str_replace("{LIST}", $list, $content);
            $form->setContent($content);
        }

        $form->addButton($main->get("btn-back", "§8[§cНазад§8]"), 0, $main->iconBack, "back");
        $player->sendForm($form);
    }

    public static function sendBalanceTopForm(Player $player): void {
        $main = Main::getInstance();
        $clans = $main->getClanManager()->getClans();

        $form = new SimpleForm(function (Player $player, mixed $data): void {
            self::sendMainMenu($player);
        });

        $form->setTitle($main->get("form-title-balance-top", "§b§lТОП БОГАТСТВА §7| §0Рейтинг"));

        if (empty($clans)) {
            $form->setContent("§f§l▶ §b§lТОП БОГАТСТВА§f§l◀\n\n§cНет кланов!");
        } else {
            usort($clans, fn($a, $b) => $b['balance'] <=> $a['balance']);
            $list = "";
            $count = 0;
            foreach ($clans as $c) {
                $count++;
                $rankColor = match ($count) {
                    1 => "§l§6",
                    2 => "§l§e",
                    3 => "§l§f",
                    default => "§8",
                };
                $balanceColor = $count <= 3 ? "§a" : "§7";
                $list .= "{$rankColor}{$count}. §a{$c['name']} §8- {$balanceColor}Банк: " . number_format($c['balance'], 2) . "\n";
                if ($count >= 10) break;
            }

            $content = $main->get("form-content-balance-top", "§fТоп кланов по богатству:");
            $content = str_replace("{LIST}", $list, $content);
            $form->setContent($content);
        }

        $form->addButton($main->get("btn-back", "§8[§cНазад§8]"), 0, $main->iconBack, "back");
        $player->sendForm($form);
    }

    public static function sendMyClanForm(Player $player): void {
        $main = Main::getInstance();
        $clanManager = $main->getClanManager();
        $playerClan = $clanManager->getClan($player);

        if ($playerClan === null) {
            $player->sendMessage($main->getMessage("no-clan"));
            return;
        }

        $leader = $clanManager->getClanLeader($playerClan);
        $members = $clanManager->getClanMembers($playerClan);
        $power = $clanManager->getClanPower($playerClan);
        $balance = number_format($clanManager->getClanBalance($playerClan), 2);
        $isLeader = $clanManager->isClanLeader($player, $playerClan);

        $form = new SimpleForm(function (Player $player, mixed $data) use ($playerClan, $isLeader): void {
            if ($data === null) return;

            match ($data) {
                "members" => self::sendMembersForm($player, $playerClan),
                "bank" => self::sendBankForm($player, $playerClan),
                "info" => self::sendClanInfoForm($player, $playerClan),
                "leave" => self::sendLeaveConfirm($player, $playerClan),
                "manage" => $isLeader ? self::sendManageMembersForm($player, $playerClan) : null,
                "back" => self::sendMainMenu($player),
                default => null,
            };
        });

        $title = str_replace('{CLAN}', $playerClan, $main->get("form-title-my-clan", "§b§l{CLAN} §7| §0Управление"));
        $form->setTitle($title);

        $claims = $clanManager->getClanClaims($playerClan);
        $claimsCount = count($claims);
        $totalArea = 0;
        foreach ($claims as $claim) {
            if (is_array($claim) && isset($claim['area'])) {
                $totalArea += $claim['area'];
            }
        }

        $membersList = "§a{$leader} §6*\n" . implode("\n", array_map(fn($m) => "§f{$m}§r", array_filter($members, fn($m) => $m !== $leader)));
        $content = $main->get("form-content-my-clan", "§fИнформация о клане");
        $content = str_replace(
            ['{CLAN}', '{LEADER}', '{COUNT}', '{POWER}', '{BALANCE}', '{CLAIMS}', '{AREA}', '{MEMBERS}'],
            [$playerClan, $leader, count($members), $power, number_format($clanManager->getClanBalance($playerClan), 2), $claimsCount, $totalArea, $membersList],
            $content
        );
        $form->setContent($content);

        $form->addButton($main->get("btn-members", "§8[§eУчастники§8]"), 0, $main->iconMultiplayer, "members");
        $form->addButton($main->get("btn-bank", "§8[§cБанк§8]"), 0, $main->iconMoney, "bank");
        $form->addButton($main->get("btn-info", "§8[§eИнформация§8]"), 0, $main->iconInfo, "info");

        if ($isLeader) {
            $form->addButton($main->get("btn-manage-members", "§8[§cУправление§8]"), 0, $main->iconSettings, "manage");
        }

        if (!$isLeader) {
            $form->addButton($main->get("btn-leave-clan", "§8[§6Покинуть§8]"), 0, $main->iconLeave, "leave");
        }

        $form->addButton($main->get("btn-back", "§8[§cНазад§8]"), 0, $main->iconBack, "back");

        $player->sendForm($form);
    }

    public static function sendLeaveConfirm(Player $player, string $clanName): void {
        $main = Main::getInstance();
        $clanManager = $main->getClanManager();

        if ($clanManager->isClanLeader($player, $clanName)) {
            $player->sendMessage($main->getMessage("leader-cannot-leave"));
            self::sendMyClanForm($player);
            return;
        }

        $form = new ModalForm(function (Player $player, mixed $data) use ($clanName): void {
            if ($data === null) {
                self::sendMyClanForm($player);
                return;
            }

            if ($data === true) {
                $clanManager = Main::getInstance()->getClanManager();
                $clanManager->exitClan($player);
            } else {
                self::sendMyClanForm($player);
            }
        });

        $form->setTitle($main->get("form-title-leave", "§c§lПОКИУТЬ §7| §0Подтверждение"));
        $form->setContent($main->get("content-leave", "§fВы уверены, что хотите покинуть клан?"));
        $form->setButton1($main->get("btn-yes-leave", "§aДа, покинуть"));
        $form->setButton2($main->get("btn-cancel", "§cОтмена"));

        $player->sendForm($form);
    }

    public static function sendInviteForm(Player $player): void {
        $main = Main::getInstance();
        $clanManager = $main->getClanManager();
        $playerClan = $clanManager->getClan($player);

        if ($playerClan === null) {
            $player->sendMessage($main->getMessage("no-clan"));
            return;
        }

        if (!$clanManager->isClanLeader($player, $playerClan)) {
            $player->sendMessage($main->getMessage("invite-not-leader"));
            return;
        }

        $form = new CustomForm(function (Player $player, ?array $data) use ($main, $playerClan): void {
            if ($data === null) {
                self::sendMyClanForm($player);
                return;
            }

            $targetName = trim($data["player"] ?? "");
            if ($targetName === "") {
                $player->sendMessage($main->getMessage("invite-not-online"));
                return;
            }

            $target = $main->getServer()->getPlayerByPrefix($targetName);
            if ($target === null) {
                $player->sendMessage($main->getMessage("invite-not-online", ["{NAME}" => $targetName]));
                return;
            }

            if ($main->getClanManager()->getClan($target) !== null) {
                $player->sendMessage($main->getMessage("invite-already-in-clan", ["{NAME}" => $target->getName()]));
                return;
            }

            if ($main->getClanManager()->sendInvitation($playerClan, $target)) {
                $target->sendMessage($main->getMessage("invite-received", ["{NAME}" => $playerClan]));
                $player->sendMessage($main->getMessage("invite-sent", ["{NAME}" => $target->getName()]));
            }
        });

        $form->setTitle($main->get("form-title-invite", "§d§lПРИГЛАШЕНИЯ §7| §0Управление"));
        $form->addLabel($main->get("form-content-invite", "§fВведите имя игрока:"));
        $form->addInput("§fИмя игрока:", "Steve", "", "player");
        $player->sendForm($form);
    }

    public static function sendManageMembersForm(Player $player, string $clanName): void {
        $main = Main::getInstance();
        $clanManager = $main->getClanManager();
        $members = $clanManager->getClanMembers($clanName);
        $leader = $clanManager->getClanLeader($clanName);

        $form = new SimpleForm(function (Player $player, mixed $data) use ($clanName, $members, $leader): void {
            if ($data === null || $data === "back") {
                self::sendMyClanForm($player);
                return;
            }

            $index = (int) $data;
            $memberNames = array_values(array_filter($members, fn($m) => $m !== $leader));
            if (isset($memberNames[$index])) {
                self::sendKickConfirmForm($player, $clanName, $memberNames[$index]);
            }
        });

        $form->setTitle($main->get("form-title-manage-members", "§c§lУПРАВЛЕНИЕ §7| §0Участники"));
        $form->setContent($main->get("form-content-manage-members", "§fВыберите участника для исключения:"));

        $memberIndex = 0;
        foreach ($members as $m) {
            if ($m === $leader) continue;
            $form->addButton("§c{$m}", 0, $main->iconMultiplayer, (string) $memberIndex);
            $memberIndex++;
        }

        $form->addButton($main->get("btn-back", "§8[§cНазад§8]"), 0, $main->iconBack, "back");
        $player->sendForm($form);
    }

    private static function sendKickConfirmForm(Player $player, string $clanName, string $targetName): void {
        $main = Main::getInstance();

        $form = new ModalForm(function (Player $player, mixed $data) use ($clanName, $targetName): void {
            if ($data === null) {
                self::sendManageMembersForm($player, $clanName);
                return;
            }

            if ($data === true) {
                $clanManager = Main::getInstance()->getClanManager();
                $target = $clanManager->getPlugin()->getServer()->getPlayerByPrefix($targetName);
                if ($target !== null) {
                    $clanManager->exitClan($target);
                }
                $clanManager->kickMember($clanName, $targetName);
                $player->sendMessage(Main::getInstance()->getMessage("member-kicked", ["{NAME}" => $targetName]));
            }
            self::sendManageMembersForm($player, $clanName);
        });

        $form->setTitle($main->get("form-title-kick", "§c§lИСКЛЮЧИТЬ §7| §0Подтверждение"));
        $form->setContent("§fВы уверены, что хотите §cисключить§f игрока §e{$targetName}§f?");
        $form->setButton1($main->get("btn-confirm-kick", "§cДа, исключить"));
        $form->setButton2($main->get("btn-cancel", "§cОтмена"));

        $player->sendForm($form);
    }

    public static function sendBankForm(Player $player, string $clanName): void {
        $main = Main::getInstance();
        $clanManager = $main->getClanManager();
        $economy = $main->getEconomyAPI();

        if ($economy === null) {
            $player->sendMessage($main->getMessage("bank-unavailable"));
            return;
        }

        $clanBalance = $clanManager->getClanBalance($clanName);
        $playerMoney = $economy->myMoney($player);
        $isLeader = $clanManager->isClanLeader($player, $clanName);

        $form = new SimpleForm(function (Player $player, mixed $data) use ($clanName, $isLeader): void {
            if ($data === null) return;

            match ($data) {
                "deposit" => self::sendDepositForm($player, $clanName),
                "withdraw" => $isLeader ? self::sendWithdrawForm($player, $clanName) : $player->sendMessage(Main::getInstance()->getMessage("bank-withdraw-not-leader")),
                "info" => self::sendBankInfo($player, $clanName),
                "back" => self::sendMyClanForm($player),
                default => null,
            };
        });

        $form->setTitle($main->get("form-title-bank", "§b§lБАНК КЛАНА §7| §0Управление"));

        $content = $main->get("form-content-bank", "§fБанк клана");
        $content = str_replace(
            ['{CLAN_BALANCE}', '{YOUR_BALANCE}'],
            [number_format($clanBalance, 2), number_format($playerMoney, 2)],
            $content
        );
        $form->setContent($content);

        $form->addButton($main->get("btn-deposit", "§8[§aПоложить§8]"), 0, $main->iconMoney, "deposit");
        $form->addButton($isLeader ? $main->get("btn-withdraw", "§8[§cСнять§8]") : $main->get("btn-disabled", "§8[§7Снять§8]"), 0, $main->iconMoney, "withdraw");
        $form->addButton($main->get("btn-info", "§8[§eИнформация§8]"), 0, $main->iconInfo, "info");
        $form->addButton($main->get("btn-back", "§8[§cНазад§8]"), 0, $main->iconBack, "back");

        $player->sendForm($form);
    }

    public static function sendDepositForm(Player $player, string $clanName): void {
        $main = Main::getInstance();
        $economy = $main->getEconomyAPI();

        if ($economy === null) {
            $player->sendMessage($main->getMessage("bank-unavailable"));
            return;
        }

        $playerMoney = $economy->myMoney($player);

        $form = new CustomForm(function (Player $player, ?array $data) use ($main, $clanName, $economy): void {
            if ($data === null) {
                self::sendBankForm($player, $clanName);
                return;
            }

            $amount = (float) ($data["amount"] ?? 0);
            if ($amount <= 0) {
                $player->sendMessage($main->getMessage("bank-invalid-amount"));
                self::sendDepositForm($player, $clanName);
                return;
            }

            $playerMoney = $economy->myMoney($player);
            if ($amount > $playerMoney) {
                $player->sendMessage($main->getMessage("bank-not-enough", ["{AMOUNT}" => number_format($amount, 2)]));
                self::sendDepositForm($player, $clanName);
                return;
            }

            $clanManager = $main->getClanManager();
            if ($clanManager->depositToClan($player, $clanName, $amount)) {
                $player->sendMessage($main->getMessage("bank-deposit-success", ["{AMOUNT}" => number_format($amount, 2)]));
            } else {
                $player->sendMessage($main->getMessage("bank-error"));
            }
        });

        $form->setTitle($main->get("form-title-deposit", "§a§lВКЛАД §7| §0Банк"));
        $content = $main->get("form-content-deposit", "§fВведите сумму:");
        $content = str_replace("{AVAILABLE}", number_format($playerMoney, 2), $content);
        $form->addLabel($content);
        $form->addInput("§fСумма:", number_format($playerMoney, 0, '.', ''), "", "amount");
        $player->sendForm($form);
    }

    public static function sendWithdrawForm(Player $player, string $clanName): void {
        $main = Main::getInstance();
        $clanManager = $main->getClanManager();
        $economy = $main->getEconomyAPI();

        if (!$clanManager->isClanLeader($player, $clanName)) {
            $player->sendMessage($main->getMessage("bank-withdraw-not-leader"));
            return;
        }

        $clanBalance = $clanManager->getClanBalance($clanName);

        $form = new CustomForm(function (Player $player, ?array $data) use ($main, $clanName, $economy): void {
            if ($data === null) {
                self::sendBankForm($player, $clanName);
                return;
            }

            $amount = (float) ($data["amount"] ?? 0);
            if ($amount <= 0) {
                $player->sendMessage($main->getMessage("bank-invalid-amount"));
                self::sendWithdrawForm($player, $clanName);
                return;
            }

            $clanManager = $main->getClanManager();
            if ($clanManager->withdrawFromClan($player, $clanName, $amount)) {
                $player->sendMessage($main->getMessage("bank-withdraw-success", ["{AMOUNT}" => number_format($amount, 2)]));
            } else {
                $player->sendMessage($main->getMessage("bank-error"));
            }
        });

        $form->setTitle($main->get("form-title-withdraw", "§c§lСНЯТИЕ §7| §0Банк"));
        $content = $main->get("form-content-withdraw", "§fВведите сумму:");
        $content = str_replace("{CLAN_BALANCE}", number_format($clanBalance, 2), $content);
        $form->addLabel($content);
        $form->addInput("§fСумма:", number_format($clanBalance, 0, '.', ''), "", "amount");
        $player->sendForm($form);
    }

    private static function sendBankInfo(Player $player, string $clanName): void {
        $main = Main::getInstance();
        $clanManager = $main->getClanManager();
        $economy = $main->getEconomyAPI();

        if (!$clanManager->clanExists($clanName)) {
            $player->sendMessage($main->getMessage("clan-not-found", ["{NAME}" => $clanName]));
            return;
        }

        $clanBalance = $clanManager->getClanBalance($clanName);
        $leader = $clanManager->getClanLeader($clanName);
        $members = $clanManager->getClanMembers($clanName);
        $power = $clanManager->getClanPower($clanName);
        $claims = $clanManager->getClanClaims($clanName);
        $description = $clanManager->getClanDescription($clanName);
        $playerMoney = $economy !== null ? $economy->myMoney($player) : 0;

        $form = new SimpleForm(function (Player $player, mixed $data) use ($clanName): void {
            if ($data !== null) {
                self::sendBankForm($player, $clanName);
            }
        });

        $form->setTitle($main->get("form-title-bank-info", "§e§lБАНК §7| §0Информация"));

        $claimsCount = count($claims);
        $totalArea = 0;
        foreach ($claims as $claim) {
            if (is_array($claim) && isset($claim['area'])) {
                $totalArea += $claim['area'];
            }
        }

        $membersList = "";
        $memberNum = 0;
        foreach ($members as $m) {
            $memberNum++;
            $statusIcon = ($m === $leader) ? " §6*" : "";
            $membersList .= "§7{$memberNum}. §f{$m}{$statusIcon}§r\n";
        }

        $descLine = !empty($description) ? "§7• §fОписание: §a{$description}\n" : "";

        $content = $main->get("form-content-bank-info", "§fИнформация о банке");
        $content = str_replace(
            ['{CLAN}', '{LEADER}', '{COUNT}', '{POWER}', '{BALANCE}', '{YOUR_BALANCE}', '{CLAIMS}', '{AREA}', '{DESCRIPTION}', '{MEMBERS}'],
            [$clanName, $leader, count($members), $power, number_format($clanBalance, 2), number_format($playerMoney, 2), $claimsCount, $totalArea, $descLine, $membersList],
            $content
        );
        $form->setContent($content);
        $form->addButton($main->get("btn-back", "§8[§cНазад§8]"), 0, $main->iconBack, "back");

        $player->sendForm($form);
    }

    public static function sendClanListForm(Player $player): void {
        $main = Main::getInstance();
        $clanManager = $main->getClanManager();
        $clans = $clanManager->getClans();
        $clanList = array_values($clans);
        $clanNames = array_keys($clans);

        $form = new SimpleForm(function (Player $player, mixed $data) use ($clanNames): void {
            if ($data === null || $data === "back") {
                self::sendMainMenu($player);
                return;
            }

            $index = (int) $data;
            if (isset($clanNames[$index])) {
                self::sendClanInfoForm($player, $clanNames[$index]);
            }
        });

        $form->setTitle($main->get("form-title-clan-list", "§b§lКЛАНОВ §7| §0Список"));

        if (empty($clanList)) {
            $form->setContent("§f§l▶ §b§lКЛАНОВ§f§l◀\n\n§cНет кланов!");
        } else {
            $form->setContent($main->get("form-content-clan-list", "§fВыберите клан:"));
            foreach ($clanList as $index => $clan) {
                $itemText = "§a{$clan['name']} §8(§f" . count($clan['members']) . "§8)";
                $form->addButton($itemText, 0, $main->iconMultiplayer, (string) $index);
            }
        }

        $form->addButton($main->get("btn-back", "§8[§cНазад§8]"), 0, $main->iconBack, "back");

        $player->sendForm($form);
    }

    public static function sendCreateForm(Player $player): void {
        $main = Main::getInstance();
        $clanManager = $main->getClanManager();

        if ($clanManager->getClan($player) !== null) {
            $player->sendMessage($main->getMessage("already-in-clan"));
            self::sendMainMenu($player);
            return;
        }

        $form = new CustomForm(function (Player $player, ?array $data) use ($main, $clanManager): void {
            if ($data === null) {
                self::sendMainMenu($player);
                return;
            }

            $name = $data["clan_name"] ?? "";

            if ($name === "" || $name === null) {
                $player->sendMessage($main->getMessage("clan-create-name-required"));
                self::sendCreateForm($player);
                return;
            }

            if ($clanManager->clanExists($name)) {
                $player->sendMessage($main->getMessage("clan-exists", ["{NAME}" => $name]));
                self::sendCreateForm($player);
                return;
            }

            if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
                $player->sendMessage($main->getMessage("clan-create-name-invalid"));
                self::sendCreateForm($player);
                return;
            }

            if (strlen($name) < $main->minClanNameLength || strlen($name) > $main->maxClanNameLength) {
                $player->sendMessage($main->getMessage("clan-create-name-length"));
                self::sendCreateForm($player);
                return;
            }

            $economy = $main->getEconomyAPI();
            if ($economy !== null) {
                $playerMoney = $economy->myMoney($player);
                if ($playerMoney < $main->createPrice) {
                    $player->sendMessage($main->getMessage("clan-create-no-money", [
                        "{PRICE}" => number_format($main->createPrice, 2),
                        "{MONEY}" => number_format($playerMoney, 2)
                    ]));
                    self::sendCreateForm($player);
                    return;
                }
                $economy->reduceMoney($player, $main->createPrice, true, "ClanCreate");
            }

            $clanManager->createClan($player, $name);
            $player->sendMessage($main->getMessage("clan-created", ["{NAME}" => $name]));
        });

        $price = number_format($main->createPrice, 2);
        $minLen = $main->minClanNameLength;
        $maxLen = $main->maxClanNameLength;

        $content = $main->get("form-content-create", "§fСоздание клана");
        $content = str_replace(['{PRICE}', '{MIN}', '{MAX}'], [$price, $minLen, $maxLen], $content);

        $form->setTitle("§a§lСОЗДАНИЕ §7| §0Клан");
        $form->addLabel($content);
        $form->addInput("§fНазвание клана:", "MyClan", "", "clan_name");

        $player->sendForm($form);
    }

    public static function sendClanInfoForm(Player $player, string $clanName): void {
        $main = Main::getInstance();
        $clanManager = $main->getClanManager();

        if (!$clanManager->clanExists($clanName)) {
            $player->sendMessage($main->getMessage("clan-not-found", ["{NAME}" => $clanName]));
            return;
        }

        $leader = $clanManager->getClanLeader($clanName);
        $members = $clanManager->getClanMembers($clanName);
        $power = $clanManager->getClanPower($clanName);
        $balance = number_format($clanManager->getClanBalance($clanName), 2);
        $claims = $clanManager->getClanClaims($clanName);
        $claimsCount = count($claims);
        $totalArea = 0;
        foreach ($claims as $claim) {
            if (is_array($claim) && isset($claim['area'])) {
                $totalArea += $claim['area'];
            }
        }

        $form = new SimpleForm(function (Player $player, mixed $data): void {
            self::sendClanListForm($player);
        });

        $title = str_replace('{CLAN}', $clanName, $main->get("form-title-clan-info", "§b§l{CLAN} §7| §0Информация"));
        $form->setTitle($title);

        $content = $main->get("form-content-clan-info", "§fИнформация о клане");
        $membersList = "§a{$leader} §6*\n" . implode("\n", array_map(fn($m) => "§f{$m}§r", array_filter($members, fn($m) => $m !== $leader)));
        $content = str_replace(
            ['{CLAN}', '{LEADER}', '{COUNT}', '{POWER}', '{BALANCE}', '{CLAIMS}', '{AREA}', '{MEMBERS}'],
            [$clanName, $leader, count($members), $power, $balance, $claimsCount, $totalArea, $membersList],
            $content
        );
        $form->setContent($content);

        $form->addButton($main->get("btn-back", "§8[§cНазад§8]"), 0, $main->iconBack, "back");

        $player->sendForm($form);
    }

    public static function sendMembersForm(Player $player, string $clanName): void {
        $main = Main::getInstance();
        $clanManager = $main->getClanManager();

        $leader = $clanManager->getClanLeader($clanName);
        $members = $clanManager->getClanMembers($clanName);

        $form = new SimpleForm(function (Player $player, mixed $data) use ($clanName): void {
            self::sendMyClanForm($player);
        });

        $title = str_replace('{CLAN}', $clanName, $main->get("form-title-members", "§e§lУЧАСТНИКИ §7| §0{CLAN}"));
        $form->setTitle($title);

        $list = "§a{$leader} §6*\n";
        foreach ($members as $m) {
            if ($m !== $leader) {
                $list .= "§f{$m}\n";
            }
        }

        $content = $main->get("form-content-members", "§fУчастники:");
        $content = str_replace("{LIST}", $list, $content);
        $form->setContent($content);

        $form->addButton($main->get("btn-back", "§8[§cНазад§8]"), 0, $main->iconBack, "back");

        $player->sendForm($form);
    }
}