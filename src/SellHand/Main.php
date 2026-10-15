<?php

declare(strict_types=1);

namespace SellHand;

use onebone\economyapi\EconomyAPI;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\world\format\io\GlobalItemDataHandlers;
use Throwable;

final class Main extends PluginBase{
	private Config $pricesConfig;
	private Config $messages;
	private EconomyAPI $economy;
	private SellPriceCatalog $prices;

	protected function onEnable() : void{
		$this->saveResource("sell.yml", false);
		$this->saveResource("messages.yml", false);
		$this->pricesConfig = new Config($this->getDataFolder() . "sell.yml", Config::YAML);
		$this->messages = new Config($this->getDataFolder() . "messages.yml", Config::YAML);

		$economy = EconomyAPI::getInstance();
		if(!$economy->isEnabled()){
			throw new \RuntimeException("EconomyAPI must be enabled before Sell.");
		}
		$this->economy = $economy;
		$this->prices = $this->loadPriceCatalog();
		$this->getLogger()->info("Sell API 5 is enabled with " . count($this->pricesConfig->getAll()) . " legacy price entries.");
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
		if(strtolower($command->getName()) !== "sell"){
			return false;
		}
		if(!$sender instanceof Player){
			$sender->sendMessage("This command can only be used in-game.");
			return true;
		}
		if(!$sender->isSurvival()){
			$sender->sendMessage("§b⚒§eVui Lòng Chỉnh Về Chế Độ Sinh Tồn Để Sử Dụng Lệnh§b⚒");
			return true;
		}

		$subcommand = strtolower($args[0] ?? "");
		if($subcommand === "about"){
			$sender->sendMessage("§b⚒§aThông Tin Plugin§b⚒\n§f•§eTác Giả:§a Muqsit Rayyan\n§f•§eChỉnh Sửa:§a Ghast Noob\n§f•§ePhiên Bản:§a " . $this->getDescription()->getVersion());
			return true;
		}

		if($subcommand === "hand"){
			if(!$sender->hasPermission("sell.hand")){
				$sender->sendMessage($this->message("error-nopermission-sellHand", "You do not have permission to sell the item in your hand."));
				return true;
			}
			$item = $sender->getInventory()->getItemInHand();
			if($item->isNull()){
				$sender->sendMessage("§b⚒§eBạn Không Có Bất Kỳ Vật Phẩm Nào Để Bán§b⚒");
				return true;
			}
			$price = $this->prices->getPrice($item->getTypeId(), $item->getStateId());
			if($price === null || $price <= 0){
				$sender->sendMessage("§b⚒§eKhông Thể Bán Vật Phẩm Này§b⚒");
				return true;
			}
			$this->sellItems($sender, [$item]);
			return true;
		}

		if($subcommand === "all"){
			if(!$sender->hasPermission("sell.all")){
				$sender->sendMessage($this->message("error-nopermission-sellAll", "You do not have permission to sell all items."));
				return true;
			}
			$this->sellItems($sender, array_values($sender->getInventory()->getContents()));
			return true;
		}

		$sender->sendMessage("§b⚒§aHướng Dẫn Sử Dụng Plugin§b⚒\n§f•§e /sell all §f-§a Để Bán Tất Cả Vật Phẩm Có Trong Túi Đồ\n§f•§e /sell hand §f-§a Bán Vật Phẩm Trên Tay Bạn Đang Cầm\n§f•§e /sell about §f-§a Xem Thông Tin Plugin");
		return true;
	}

	private function loadPriceCatalog() : SellPriceCatalog{
		$catalog = new SellPriceCatalog();
		$upgrader = GlobalItemDataHandlers::getUpgrader();
		$deserializer = GlobalItemDataHandlers::getDeserializer();
		foreach($this->pricesConfig->getAll() as $legacyKey => $priceValue){
			$parsed = SellPriceCatalog::parseLegacyKey($legacyKey);
			if($parsed === null || !is_numeric($priceValue)){
				$this->getLogger()->warning("A sell.yml entry has an invalid legacy item key or price and was ignored.");
				continue;
			}
			[$legacyId, $meta, $hasMeta] = $parsed;
			$price = (float) $priceValue;
			if(!is_finite($price) || $price < 0){
				$this->getLogger()->warning("A sell.yml price must be finite and nonnegative; the entry was ignored.");
				continue;
			}
			try{
				$legacyItem = $upgrader->upgradeItemTypeDataInt($legacyId, $meta, 1, null);
				$item = $deserializer->deserializeStack($legacyItem);
			}catch(Throwable){
				$key = $hasMeta ? $legacyId . ":" . $meta : (string) $legacyId;
				$this->getLogger()->warning("Legacy sell.yml item key $key cannot be mapped by this Axolotl-PM version; that entry was ignored.");
				continue;
			}
			$catalog->add($item->getTypeId(), $item->getStateId(), $price, $hasMeta);
		}
		return $catalog;
	}

	/** @param list<Item> $candidates */
	private function sellItems(Player $player, array $candidates) : void{
		$plan = [];
		$totalCents = 0;
		foreach($candidates as $item){
			$unitPrice = $this->prices->getPrice($item->getTypeId(), $item->getStateId());
			if($unitPrice === null){
				continue;
			}
			$itemCents = SellPolicy::itemValueInCents($unitPrice, $item->getCount());
			if($itemCents === null){
				if($unitPrice > 0){
					$player->sendMessage("The sale total is outside the supported money range. No items were sold.");
					return;
				}
				continue;
			}
			if($itemCents > PHP_INT_MAX - $totalCents){
				$player->sendMessage("The sale total is outside the supported money range. No items were sold.");
				return;
			}
			$totalCents += $itemCents;
			$plan[] = ["item" => clone $item, "unit_price" => $unitPrice, "total_cents" => $itemCents];
		}

		if($plan === [] || $totalCents < 1){
			$player->sendMessage("§b⚒§eKhông Có Vật Phẩm Nào Có Thể Bán§b⚒");
			return;
		}

		try{
			$balanceBefore = $this->economy->myMoney($player);
		}catch(Throwable){
			$player->sendMessage("EconomyAPI could not read your balance. No items were sold.");
			return;
		}
		if(!is_numeric($balanceBefore) || !is_finite((float) $balanceBefore)){
			$player->sendMessage("EconomyAPI could not read your balance. No items were sold.");
			return;
		}

		$inventory = $player->getInventory();
		$removed = [];
		$inventoryChanged = false;
		$inventoryFailed = false;
		try{
			foreach($plan as $entry){
				$item = $entry["item"];
				$leftovers = $inventory->removeItem(clone $item);
				$leftoverCount = array_sum(array_map(static fn(Item $leftover) : int => $leftover->getCount(), $leftovers));
				$removedCount = $item->getCount() - $leftoverCount;
				if($removedCount > 0){
					$removedItem = clone $item;
					$removedItem->setCount($removedCount);
					$removed[] = $removedItem;
				}
				if($leftovers !== []){
					$inventoryChanged = true;
					break;
				}
			}
		}catch(Throwable){
			$inventoryFailed = true;
		}

		if($inventoryChanged || $inventoryFailed){
			try{
				$this->restoreItems($player, $removed);
			}catch(Throwable){
				$this->getLogger()->critical("An inventory error interrupted a sale for player " . $player->getName() . ". Administrators must reconcile the player's items before any credit is issued.");
				$player->sendMessage("An inventory error interrupted the sale. Contact an administrator; no money was credited.");
				return;
			}
			$player->sendMessage($inventoryChanged ? "Your inventory changed before the sale completed. Your items were returned; no money was credited." : "An inventory error interrupted the sale. Your items were returned; no money was credited.");
			return;
		}

		$amount = $totalCents / 100;
		try{
			$result = $this->economy->addMoney($player, $amount, false, "Sell");
		}catch(Throwable){
			try{
				$balanceAfter = $this->economy->myMoney($player);
			}catch(Throwable){
				$balanceAfter = false;
			}
			if(is_numeric($balanceAfter) && abs((float) $balanceAfter - (float) $balanceBefore - $amount) < 0.005){
				$this->getLogger()->warning("EconomyAPI raised after the sale amount was credited; the sold items were kept removed to prevent duplicate credit.");
				$this->sendSaleReceipt($player, $plan, $totalCents);
				return;
			}
			if(is_numeric($balanceAfter) && abs((float) $balanceAfter - (float) $balanceBefore) < 0.005){
				$this->restoreItems($player, $removed);
				$player->sendMessage("EconomyAPI did not complete the sale. Your items were returned.");
				return;
			}
			$this->getLogger()->warning("An EconomyAPI sale outcome is uncertain for player " . $player->getName() . " and amount " . SellPolicy::formatCents($totalCents) . ". Items were not automatically restored; reconcile the account before changing inventory.");
			$player->sendMessage("The sale outcome needs administrator review. Your items were not returned automatically to prevent duplicate credit.");
			return;
		}

		if($result !== EconomyAPI::RET_SUCCESS){
			$this->restoreItems($player, $removed);
			$player->sendMessage("EconomyAPI rejected the sale. Your items were returned.");
			return;
		}

		$this->sendSaleReceipt($player, $plan, $totalCents);
	}

	/** @param list<Item> $items */
	private function restoreItems(Player $player, array $items) : void{
		foreach($player->getInventory()->addItem(...$items) as $leftover){
			$player->getWorld()->dropItem($player->getPosition(), $leftover);
		}
	}

	/** @param list<array{item: Item, unit_price: float, total_cents: int}> $plan */
	private function sendSaleReceipt(Player $player, array $plan, int $totalCents) : void{
		foreach($plan as $entry){
			$item = $entry["item"];
			$player->sendMessage("§b⚒§aBạn Đã Nhận Được §e" . SellPolicy::formatCents($entry["total_cents"]) . "§e Xu§a Từ Việc Bán §e" . $item->getCount() . " " . $item->getName() . "§a Với Giá Cấu Hình §e" . $this->formatConfiguredPrice($entry["unit_price"]) . "§e Xu§a Mỗi Vật Phẩm§b⚒");
		}
		$player->sendMessage("§aTổng cộng: §e" . SellPolicy::formatCents($totalCents) . " Xu§a.");
	}

	private function message(string $key, string $fallback) : string{
		$value = $this->messages->get($key, $fallback);
		return is_string($value) ? $value : $fallback;
	}

	private function formatConfiguredPrice(float $price) : string{
		return rtrim(rtrim(number_format($price, 8, ".", ""), "0"), ".");
	}
}
