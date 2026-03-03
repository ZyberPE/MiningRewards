<?php
declare(strict_types=1);

namespace MiningRewards;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\utils\Config;
use pocketmine\item\StringToItemParser;
use pocketmine\utils\TextFormat;

class Main extends PluginBase implements Listener {

    private Config $config;

    public function onEnable(): void {
        @mkdir($this->getDataFolder());
        $this->saveResource("config.yml");
        $this->config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
        $this->getServer()->getPluginManager()->registerEvents($this, $this);

        $this->getLogger()->info(TextFormat::GREEN . "MiningRewards enabled with 10,000,000 chance system!");
    }

    public function onBlockBreak(BlockBreakEvent $event): void {
        $player = $event->getPlayer();
        $rewards = $this->config->get("rewards", []);
        $messages = $this->config->get("messages", []);

        foreach($rewards as $rewardName => $data){

            $chance = (int)($data["chance"] ?? 1);

            // Clamp between 1 and 10,000,000
            if($chance < 1) $chance = 1;
            if($chance > 10000000) $chance = 10000000;

            // If chance is 1 → NEVER reward
            if($chance === 1){
                continue;
            }

            // If chance is 10,000,000 → ALWAYS reward
            if($chance === 10000000){
                $rollWin = true;
            } else {
                $roll = mt_rand(1, 10000000);
                $rollWin = $roll <= $chance;
            }

            if(!$rollWin){
                continue;
            }

            // --- GIVE ITEMS ---
            foreach($data["items"] ?? [] as $itemStr){
                if(trim($itemStr) === "") continue;

                $parts = explode(":", $itemStr) + [null, 1];
                [$id, $count] = $parts;
                $count = (int)$count;

                $item = StringToItemParser::getInstance()->parse((string)$id);

                if($item === null){
                    $msg = $messages["invalid_item"] ?? "&cInvalid item: {ITEM}";
                    $msg = str_replace("{ITEM}", (string)$id, $msg);
                    $player->sendMessage(TextFormat::colorize($msg));
                    $this->getLogger()->warning("Invalid item ID in MiningRewards config: $id");
                    continue;
                }

                $item->setCount($count);
                $player->getInventory()->addItem($item);
            }

            // --- RUN COMMANDS ---
            foreach($data["commands"] ?? [] as $cmd){
                if(trim($cmd) === "") continue;

                $cmd = str_replace("{PLAYER}", $player->getName(), $cmd);

                try {
                    $this->getServer()->dispatchCommand(
                        $this->getServer()->getConsoleSender(),
                        $cmd
                    );
                } catch (\Throwable $e){
                    $this->getLogger()->warning("Failed to run command: $cmd | ".$e->getMessage());
                }
            }

            // --- SEND MESSAGE ---
            $rewardMessage = $data["message"] ?? $messages["reward_received"] ?? "&aYou received reward: {REWARD}";
            $rewardMessage = str_replace("{REWARD}", $rewardName, $rewardMessage);

            $player->sendMessage(TextFormat::colorize($rewardMessage));
        }
    }
}
