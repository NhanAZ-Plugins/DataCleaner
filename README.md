# DataCleaner

DataCleaner moves unused Axolotl-PM plugin data into retained backups after server startup. It leaves data for loaded plugins and configured exception names in place.

## Requirements and compatibility

- Axolotl-PM API 5.0.0 or newer within the 5.x API series. The [build workflow](.github/workflows/build.yml) records exact tested source revisions. Compatibility with every 5.x release has not been verified.
- PHP 8.1 or newer, as required by Axolotl-PM 5.

## Installation

Download the PHAR from [GitHub Releases](https://github.com/NhanAZ-Plugins/DataCleaner/releases), place it in the server's `plugins` directory and restart the server. Back up the server's `plugin_data` directory before enabling any cleanup plugin.

DataCleaner does not run when Axolotl-PM uses the legacy plugin data directory setting. It starts its cleanup task after the configured delay, using the list of plugins loaded at that time.

## Cleanup and recovery

For each orphaned plugin data entry that can be moved safely, DataCleaner creates a separate directory under its own `plugins/DataCleaner/DeleteBackup/` folder and moves the entry there. Existing backup batches are retained. It does not automatically prune them, so review and remove old backups manually when no longer needed.

To restore data, stop the server, locate the batch containing the plugin's folder, and move that folder back under `plugin_data/`. Confirm the plugin name and contents before restarting. A backup can contain data from an uninstalled plugin that you may want later.

The cleanup skips loaded plugin directories, configured exception names, entries containing an exception name and symbolic links. If it cannot move an entry, that entry stays in place. This behavior is limited to the plugin data directory. It does not clean worlds, plugins or resource packs.

## Configuration

The [default configuration](resources/config.yml) provides two settings:

| Setting | Default | Meaning |
|---|---|---|
| `exceptionData` | `[]` | Folder or file names to keep. Nested matches keep their parent folder. |
| `delayTime` | `1` | Seconds to wait after enable before cleanup. Accepted integer range is 0 through 86400. |

Invalid `exceptionData` entries cause a warning and are ignored. A malformed list uses no custom exceptions. An invalid `delayTime` uses one second after a warning. The plugin does not rewrite an invalid configuration file.

## Development and validation

The [build workflow](.github/workflows/build.yml) tests backup retention, loaded plugin data, exception names and path containment against pinned Axolotl-PM source revisions. It uses [DevTools](https://github.com/NhanAZ/DevTools) to run PHPStan at maximum level, build the PHAR and inspect the artifact. Exact revisions and results are available in [GitHub Actions](https://github.com/NhanAZ-Plugins/DataCleaner/actions).

The tests use disposable directories. They do not run cleanup on a real server's data. Review backup contents before deleting them manually.

## Support

Join [NhanAZ Discord](https://discord.gg/j2X83ujT6c) for support.

## License

DataCleaner is distributed under the [GNU General Public License version 3](LICENSE).
