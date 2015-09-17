<?php

namespace gamecore\gcframework;

use ifteam\CustomPacket\event\CustomPacketReceiveEvent;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;

class GCClientFramework extends PluginBase implements Framework, Listener{

	public $mainServer;

	public static $translations;

	public $rankingClearTerm, $lastCleared;

	public function onEnable(){
		@mkdir($this->getDataFolder());

		$this->generateFile("server.dat");

		$main = explode(":", file_get_contents($this->getDataFolder()."server.dat"));

		$this->mainServer = [
			"ip" => $main[0],
			"port" => $main[1]
		];

		$this->getServer()->getPluginManager()->registerEvents($this, $this);

		$this->getLogger()->info(TextFormat::DARK_PURPLE."GameCore Client Loaded!");

		if($this->getServer()->getPluginManager()->getPlugin("CustomPacket") === null){
			$this->getLogger()->alert(TextFormat::RED.TextFormat::BOLD."Cannot use CustomPacket, entering local mode!");
			$this->getLogger()->alert(TextFormat::RED.TextFormat::BOLD."This can be very unstable!");
		}

		GCFramework::attatchFramework($this);
	}

	public function generateFile($fileName){
		if(!is_file($this->getDataFolder().$fileName)){
			$this->getLogger()->info(TextFormat::GREEN."Generating $fileName...");

			$stream = $this->getResource($fileName);
			file_put_contents($this->getDataFolder().$fileName, stream_get_contents($stream));
			fclose($stream);

			return true;
		}
		return false;
	}

	/**
	 * @param string $gameName Name of finished game.
	 * @param string[] $winner Name of winners of finished game.
	 * @param string $message A message to broadcast.
	 */
	public function onGameFinish($gameName, array $winner, $message = null){
		GCFramework::sendPacket($this->mainServer["ip"], $this->mainServer["port"], [
			"TYPE" => GCFramework::PACKET_TYPE_GAME_FINISH,
			"NAME" => $gameName,
			"WINNER" => $winner,
			"MESSAGE" => $message
		]);
	}

	public function onPacketRecieve(CustomPacketReceiveEvent $event){
		echo "Incoming Packet!\n";
		if(($event->getPacket()->address != $this->mainServer["ip"])){
			$this->getLogger()->info(TextFormat::LIGHT_PURPLE."Packet from ".$event->getPacket()->address.":".$event->getPacket()->port." has been blocked!");
			return;
		}

		$data = json_decode($event->getPacket()->data, true);

		if(!isset($data["TYPE"])) return;

		switch($data["TYPE"]){
			case GCFramework::PACKET_TYPE_POST_WHOLE_RANK:
			case GCFramework::PACKET_TYPE_POST_GAME_RANK:
			case GCFramework::PACKET_TYPE_POST_DESCRIPTION:

				if($data["USER"] === null){
					$this->getLogger()->info($data["MESSAGE"]);
				}else{
					$player = $this->getServer()->getPlayerExact($data["USER"]);
					if($player !== null){
						$player->sendMessage($data["MESSAGE"]);
					}
				}

				break;

			case GCFramework::PACKET_TYPE_POST_GAME_MESSAGE:
				$this->getServer()->broadcastMessage($data["MESSAGE"]);
				break;
		}
	}

	public function broadcastWholeRankTo(CommandSender $sender, $page){
		GCFramework::sendPacket($this->mainServer["ip"], $this->mainServer["port"], [
			"TYPE" => GCFramework::PACKET_TYPE_GET_WHOLE_RANK,
			"USER" => ($sender instanceof Player) ? $sender->getName() : null,
			"PAGE" => $page
		]);
	}

	public function broadcastRankTo(CommandSender $sender, $gameName, $page){
		GCFramework::sendPacket($this->mainServer["ip"], $this->mainServer["port"], [
			"TYPE" => GCFramework::PACKET_TYPE_GET_GAME_RANK,
			"USER" => ($sender instanceof Player) ? $sender->getName() : null,
			"NAME" => $gameName,
			"PAGE" => $page
		]);
	}

	public function broadcastDescriptionTo(CommandSender $sender, $gameName){
		GCFramework::sendPacket($this->mainServer["ip"], $this->mainServer["port"], [
			"TYPE" => GCFramework::PACKET_TYPE_GET_DESCRIPTION,
			"USER" => ($sender instanceof Player) ? $sender->getName() : null,
			"NAME" => $gameName,
		]);
	}
}
