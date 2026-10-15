<?php

declare(strict_types=1);

require dirname(__DIR__) . "/src/SellHand/SellPolicy.php";
require dirname(__DIR__) . "/src/SellHand/SellPriceCatalog.php";

use SellHand\SellPolicy;
use SellHand\SellPriceCatalog;

function expectSame(mixed $expected, mixed $actual, string $case) : void{
	if($expected !== $actual){
		throw new RuntimeException($case . ": expected " . var_export($expected, true) . ", got " . var_export($actual, true));
	}
}

expectSame([35, 14, true], SellPriceCatalog::parseLegacyKey("35:14"), "legacy metadata key");
expectSame([35, 0, false], SellPriceCatalog::parseLegacyKey(35), "legacy base item key");
expectSame(null, SellPriceCatalog::parseLegacyKey("35:metadata"), "reject malformed metadata");
expectSame(null, SellPriceCatalog::parseLegacyKey("0"), "reject zero item ID");
expectSame(null, SellPriceCatalog::parseLegacyKey("1e3"), "reject exponent item ID");

$catalog = new SellPriceCatalog();
$catalog->add(35, 500, 4.0, false);
$catalog->add(35, 501, 0.0, true);
$catalog->add(35, 502, 1.25, true);
expectSame(4.0, $catalog->getPrice(35, 500), "base price");
expectSame(0.0, $catalog->getPrice(35, 501), "specific zero price overrides base price");
expectSame(1.25, $catalog->getPrice(35, 502), "specific metadata price");
expectSame(null, $catalog->getPrice(36, 503), "unknown item has no price");

expectSame(94, SellPolicy::itemValueInCents(0.3125, 3), "legacy fractional unit price rounds like EconomyAPI");
expectSame(1300, SellPolicy::itemValueInCents(6.5, 2), "whole-cent item price");
expectSame(null, SellPolicy::itemValueInCents(0.001, 1), "sub-cent total does not create a zero-value sale");
expectSame(null, SellPolicy::itemValueInCents(0.0, 4), "zero price");
expectSame(null, SellPolicy::itemValueInCents(-1.0, 4), "negative price");
expectSame(null, SellPolicy::itemValueInCents(NAN, 4), "non-finite price");
expectSame(null, SellPolicy::itemValueInCents(1.0, 0), "empty stack");
expectSame(null, SellPolicy::itemValueInCents((float) PHP_INT_MAX, 1), "integer overflow");
expectSame("1,234.50", SellPolicy::formatCents(123450), "currency formatting");

fwrite(STDOUT, "Sell contract fixtures passed.\n");
