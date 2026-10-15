<?php

declare(strict_types=1);

namespace SellHand;

final class SellPriceCatalog{
	/** @var array<int, float> */
	private array $typePrices = [];
	/** @var array<int, float> */
	private array $statePrices = [];

	public function add(int $typeId, int $stateId, float $price, bool $specificState) : void{
		if($specificState){
			$this->statePrices[$stateId] = $price;
		}else{
			$this->typePrices[$typeId] = $price;
		}
	}

	public function getPrice(int $typeId, int $stateId) : ?float{
		if(array_key_exists($stateId, $this->statePrices)){
			return $this->statePrices[$stateId];
		}
		return $this->typePrices[$typeId] ?? null;
	}

	/** @return array{int, int, bool}|null */
	public static function parseLegacyKey(int|string $key) : ?array{
		if(is_int($key)){
			return $key > 0 ? [$key, 0, false] : null;
		}
		if(preg_match('/^([1-9][0-9]*)(?::([0-9]+))?$/D', $key, $matches) !== 1){
			return null;
		}
		$id = filter_var($matches[1], FILTER_VALIDATE_INT);
		$meta = isset($matches[2]) ? filter_var($matches[2], FILTER_VALIDATE_INT) : 0;
		if(!is_int($id) || !is_int($meta)){
			return null;
		}
		return [$id, $meta, isset($matches[2])];
	}
}
