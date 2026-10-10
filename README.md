# Sell

Sell is a legacy PocketMine-MP plugin for selling items through EconomyAPI. The manifest names Muqsit as the author, and the original source also credits Ghast Noob.

## Compatibility

The manifest declares API `2.0.0`. This repository has not been migrated to Axolotl-PM 5. The old item APIs and EconomyAPI integration have no verified replacement in this source, so it should not be installed on an Axolotl-PM 5 server.

## Historical behavior

The original `/sell hand` and `/sell all` commands use prices from `sell.yml`. The source calls EconomyAPI before checking that inventory removal succeeded. It has not been tested for transaction safety on a compatible legacy server. Keep backups of player inventories and economy data if investigating this code in an isolated environment.

## Project status

This repository is retained as a historical source reference. No Axolotl-PM 5 PHAR, runtime test, or stable release was produced during this campaign. There is no license file in this repository, and redistribution rights have not been verified. The source, manifest, and resource files remain unchanged.
