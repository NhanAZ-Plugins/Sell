<?php

namespace SellHand;

use pocketmine\Player;
use pocketmine\event\Listener;
use pocketmine\plugin\PluginBase;
use pocketmine\command\{Command, CommandSender};
use pocketmine\utils\{Config, TextFormat as TF};
use onebone\economyapi\EconomyAPI;

class Main extends PluginBase implements Listener{

	public function onEnable(){
    $this->getLogger()->info("§aĐã Hoạt Động");
		$files = array("sell.yml", "messages.yml");
		foreach($files as $file){
			if(!file_exists($this->getDataFolder() . $file)) {
				@mkdir($this->getDataFolder());
				file_put_contents($this->getDataFolder() . $file, $this->getResource($file));
			}
		}
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->sell = new Config($this->getDataFolder() . "sell.yml", Config::YAML);
		$this->messages = new Config($this->getDataFolder() . "messages.yml", Config::YAML);
	}

	public function onCommand(CommandSender $sender, Command $cmd, $label, array $args) {
		switch(strtolower($cmd->getName())){
			case "sell":
			    
				if($sender->hasPermission("sell") || $sender->hasPermission("sell.hand") || $sender->hasPermission("sell.all")){

					if(!$sender->isSurvival()){
						$sender->sendMessage("§b⚒§eVui Lòng Chỉnh Về Chế Độ Sinh Tồn Để Sử Dụng Lệnh§b⚒");
						return false;
					}
					
					if(isset($args[0]) && strtolower($args[0]) == "hand"){
						if(!$sender->hasPermission("sell.hand")){
							$error_handPermission = $this->messages->get("error-nopermission-sellHand");
							$sender->sendMessage($error_handPermission);
							return false;
						}
						$item = $sender->getInventory()->getItemInHand();
						$itemId = $item->getId();

						if($item->getId() === 0){
							$sender->sendMessage("§b⚒§eBạn Không Có Bất Kỳ Vật Phẩm Nào Để Bán§b⚒");
							return false;
						}

						if($this->sell->get($itemId) == null){
							$sender->sendMessage("§b⚒§eKhông Thể Bán Vật Phẩm Này§b⚒");
							return false;
						}

						EconomyAPI::getInstance()->addMoney($sender, $this->sell->get($itemId) * $item->getCount());
						$sender->getInventory()->removeItem($item);
						$price = $this->sell->get($item->getId()) * $item->getCount();
						$sender->sendMessage("§b⚒§aBạn Đã Nhận Được §e" . $price . "§e Xu§a Từ Việc Bán §e" . $item->getCount() . " " . $item->getName() . "§a Với Giá §e" . $this->sell->get($item->getId()) . "§e Xu§a Mỗi Vật Phẩm§b⚒");

					}elseif(isset($args[0]) && strtolower($args[0]) == "all"){
						if(!$sender->hasPermission("sell.all")){
							$error_allPermission = $this->messages->get("error-nopermission-sellAll");
							$sender->sendMessage($error_allPermission);
							return false;
						}
						$items = $sender->getInventory()->getContents();
						foreach($items as $item){
							if($this->sell->get($item->getId()) !== null && $this->sell->get($item->getId()) > 0){
								$price = $this->sell->get($item->getId()) * $item->getCount();
								EconomyAPI::getInstance()->addMoney($sender, $price);
								$sender->sendMessage("§b⚒§aBạn Đã Nhận Được §e" . $price . "§e Xu§a Từ Việc Bán §e" . $item->getCount() . " " . $item->getName() . "§a Với Giá §e" . $this->sell->get($item->getId()) . "§e Xu§a Mỗi Vật Phẩm§b⚒");							$sender->getInventory()->remove($item);
							}
						}
					}elseif(isset($args[0]) && strtolower($args[0]) == "about"){
						$sender->sendMessage("§b⚒§aThông Tin Plugin§b⚒\n§f•§eTác Giả:§a Muqsit Rayyan\n§f•§eChỉnh Sửa:§a Ghast Noob\n§f•§ePhiên Bản:§a 1.1.x");
					}else{
						$sender->sendMessage("§b⚒§aHướng Dẫn Sử Dụng Plugin§b⚒\n§f•§e /sell all §f-§a Để Bán Tất Cả Vật Phẩm Có Trong Túi Đồ\n§f•§e /sell hand §f-§a Bán Vật Phẩm Trên Tay Bạn Đang Cầm\n§f•§e /sell about §f-§a Xem Thông Tin Plugin");
						return true;
					}
				}else{
					$error_permission = $this->messages->get("error-permission");
					$sender->sendMessage($error_permission);
				}
				break;
		}
	}
}
