<?php

declare(strict_types=1);

namespace SellHand;

final class SellPolicy{
	public static function itemValueInCents(float $unitPrice, int $count) : ?int{
		if(!is_finite($unitPrice) || $unitPrice <= 0 || $count < 1){
			return null;
		}
		$total = $unitPrice * $count;
		if(!is_finite($total) || $total > PHP_INT_MAX / 100){
			return null;
		}
		$cents = (int) round(round($total, 2) * 100);
		return $cents > 0 ? $cents : null;
	}

	public static function formatCents(int $cents) : string{
		return number_format($cents / 100, 2, ".", ",");
	}
}
