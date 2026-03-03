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
    }

    public function onBlockBreak(BlockBreakEvent $event): void {
        $player = $event->getPlayer();
        $rewards = $this->config->get("rewards", []);

        foreach($rewards as $rewardName => $data){
            $chance = (int)$data["chance"];
            $roll = mt_rand(1, 50);

            if($roll <= $chance){
                // Give items
                foreach($data["items"] ?? [] as $itemStr){
                    [$id, $count] = explode(":", $itemStr);
                    $item = StringToItemParser::getInstance()->parse($id);
                    $item->setCount((int)$count);
                    $player->getInventory()->addItem($item);
                }

                // Run commands as console
                foreach($data["commands"] ?? [] as $cmd){
                    $cmd = str_replace("{PLAYER}", $player->getName(), $cmd);
                    $this->getServer()->dispatchCommand($this->getServer()->getConsoleSender(), $cmd);
                }

                $player->sendMessage(TextFormat::GREEN . "You received reward: " . $rewardName);
            }
        }
    }
}
