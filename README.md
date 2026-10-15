# Sell

Sell lets players sell the item in their hand or configured items in their inventory through [EconomyAPI](https://github.com/ACM-PocketMine-MP/EconomyAPI). It preserves the legacy numeric item IDs and metadata in `resources/sell.yml`, then maps them to the current item types when the plugin starts.

## Requirements

- Axolotl-PM API 5.x.
- EconomyAPI installed and enabled.

The plugin declares a minimum API of 5.0.0 in `plugin.yml`. CI checks the minimum API and a pinned current stable API source revision, then starts the built PHAR on a checksum-verified stable server. The exact source revisions and runtime artifact identity are recorded in the [build workflow](.github/workflows/build.yml).

## Commands

| Command | Permission | Description |
| --- | --- | --- |
| `/sell hand` | `sell.hand` | Sell the item held in the main hand. |
| `/sell all` | `sell.all` | Sell all configured items in the inventory. |
| `/sell about` | `sell` | Show plugin information. |

Each configured entry in `sell.yml` is a legacy item ID with an optional metadata value. A key such as `35` sets the base price; `"35:14"` sets a price for that exact legacy variant and takes precedence over the base price. A zero price disables that exact variant. Existing server files are not rewritten. Legacy spawn-egg variants without an Axolotl-PM mapping are reported and ignored; some entries in the default price list are therefore unavailable.

## Sale safety

Sell removes the planned items before asking EconomyAPI to credit the account. If the economy provider rejects a sale, the items are returned; any inventory overflow is dropped at the player's position. If an exception leaves the credit outcome unclear, the plugin keeps the items removed and asks an administrator to reconcile the account rather than risk duplicating currency.

The CI runtime smoke test checks plugin loading and clean shutdown without a connected player or a live transaction. Test player inventory and economy behavior on a disposable server with backups before enabling this plugin on a production server.

## Build and license

The [build workflow](.github/workflows/build.yml) uses [DevTools](https://github.com/NhanAZ/DevTools) to run PHPStan at maximum level, build the plugin PHAR and verify the artifact. This port is based on [Muqsit/Sell](https://github.com/Muqsit/Sell); its GPL-3.0 license and original author credits are retained in [LICENSE](LICENSE).
