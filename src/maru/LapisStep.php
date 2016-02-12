<?php
namespace maru;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\utils\TextFormat;
use pocketmine\event\TranslationContainer;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\utils\Config;
use pocketmine\level\Position;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\player\PlayerMoveEvent;
class LapisStep extends PluginBase implements Listener {
	private $placeQueue = [ ];
	public $steplist;
	public function onEnable() {
		$this->loadStepList();
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}
	public function onDisable() {
		$this->save();
	}
	public function loadStepList() {
		@mkdir($this->getDataFolder());
		$this->steplist = (new Config($this->getDataFolder()."steplist.json", Config::JSON))->getAll();
	}
	public function save() {
		$steplist = new Config($this->getDataFolder()."steplist.json", Config::JSON);
		$steplist->setAll($this->steplist);
		$steplist->save();
	}
	public function onCommand(CommandSender $sender, Command $command, $label, Array $args) {
		if (!isset($args[0])) {
			return false;
		}
		switch(strtolower($args[0])) {
			case "추가" :
				if (!$sender->hasPermission("lapisstep.commands.add")) {
					$sender->sendMessage(new TranslationContainer(TextFormat::RED."%commands.generic.permission"));
					break;
				}
				$sender->sendMessage("첫번째 지점에 청금석을 설치하세요.");
				$this->placeQueue[$sender->getName()] = true;
				break;
			default :
				return false;
		}
		return true;
	}
	public function onPlace(BlockPlaceEvent $event) {
		$player = $event->getPlayer();
		if (!isset($this->placeQueue[$player->getName()])) {
			return;
		}
		if ($this->placeQueue[$player->getName()] === true) {
			$block = $event->getBlock();
			if ($block->getId() !== 22) {
				return;
			}
			$this->placeQueue[$player->getName()] = "{$block->getX()}:{$block->getY()}:{$block->getZ()}:{$block->getLevel()->getFolderName()}";
			$player->sendMessage("도착 지점을 터치하세요.");
		}
	}
	public function onTouch(PlayerInteractEvent $event) {
		$player = $event->getPlayer();
		if (!isset($this->placeQueue[$player->getName()])) {
			return;
		}
		if ($this->placeQueue[$player->getName()] !== true) {
			$block = $event->getBlock();
			$this->steplist[$this->placeQueue[$player->getName()]] = "{$block->getX()}:" . (string)($block->getY() + 1) . ":{$block->getZ()}:{$block->getLevel()->getFolderName()}";
			$player->sendMessage("청금석 워프를 생성했습니다.");
			unset($this->placeQueue[$player->getName()]);
		}
	}
	public function onBreak(BlockBreakEvent $event) {
		$player = $event->getPlayer();
		$block = $event->getBlock();
		if ($block->getId() !== 22) {
			return;
		}
		if (!isset($this->steplist["{$block->getX()}:{$block->getY()}:{$block->getZ()}:{$block->getLevel()->getFolderName()}"])) {
			return;
		}
		if (!$player->hasPermission("lapisstep.delete")){
			$event->setCancelled();
			$player->sendMessage(TextFormat::RED."당신은 이 블럭을 부술 권한이 없습니다.");
			return;
		}
		unset($this->steplist["{$block->getX()}:{$block->getY()}:{$block->getZ()}:{$block->getLevel()->getFolderName()}"]);
		$player->sendMessage("청금석 워프를 제거했습니다.");
	}
	private function StringToPos($string) {
		$pos = explode(":", $string);
		return new Position($pos[0], $pos[1], $pos[2], $this->getServer()->getLevelByName($pos[3]));
	}
	private function PosToString(Position $pos) {
		return "{$pos->getX()}:{$pos->getY()}:{$pos->getZ()}:{$pos->getLevel()->getFolderName()}";
	}
	public function onMove(PlayerMoveEvent $event) {
		$player = $event->getPlayer();
		if (!$player->hasPermission("lapisstep.use")) {
			return;
		}
		$pos = $player->getPosition();
		$pos->x = (int)$pos->getX();
		$pos->y = (int)$pos->getY() - 1;
		$pos->z = (int)$pos->getZ();
		if (!isset($this->steplist[$this->PosToString($pos)])) {
			return;
		}
		$target = $this->StringToPos($this->steplist[$this->PosToString($pos)]);
		if ($target->getLevel() !== $player->getLevel()) {
			return;
		}
		$player->teleport($target);
	}
}
?>