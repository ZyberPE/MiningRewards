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

        $this->getLogger()->info(TextFormat::GREEN . "MiningRewards enabled!");
    }

    public function onBlockBreak(BlockBreakEvent $event): void {
        $player = $event->getPlayer();
        $rewards = $this->config->get("rewards", []);

        foreach($rewards as $rewardName => $data){

            $chance = (int)($data["chance"] ?? 1);
            if($chance < 1) $chance = 1;
            if($chance > 50) $chance = 50;

            $roll = mt_rand(1, 50);
            if($roll > $chance) continue; // not won

            // --- GIVE ITEMS ---
            foreach($data["items"] ?? [] as $itemStr){
                if(trim($itemStr) === "") continue;

                $parts = explode(":", $itemStr) + [null, 1]; // [id, count]
                [$id, $count] = $parts;
                $count = (int)$count;

                $item = StringToItemParser::getInstance()->parse((string)$id);
                if($item === null){
                    $this->getLogger()->warning("Invalid item ID in MiningRewards config: $id");
                    continue;
                }

                $item->setCount($count);
                $player->getInventory()->addItem($item);
            }

            // --- RUN COMMANDS AS CONSOLE ---
            foreach($data["commands"] ?? [] as $cmd){
                if(trim($cmd) === "") continue;

                $cmd = str_replace("{PLAYER}", $player->getName(), $cmd);
                try {
                    $this->getServer()->dispatchCommand($this->getServer()->getConsoleSender(), $cmd);
                } catch (\Throwable $e){
                    $this->getLogger()->warning("Failed to run command: $cmd | ".$e->getMessage());
                }
            }

            $player->sendMessage(TextFormat::GREEN . "You received reward: " . $rewardName);
        }
    }
}
