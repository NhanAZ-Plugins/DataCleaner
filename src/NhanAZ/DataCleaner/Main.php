<?php

declare(strict_types=1);

namespace NhanAZ\DataCleaner;

use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;

class Main extends PluginBase {

	/** @return list<string> */
	private function getExceptionData(): array {
		$configured = $this->getConfig()->get("exceptionData", []);
		if (!is_array($configured)) {
			$this->getLogger()->warning("exceptionData must be a list of names. Using an empty list.");
			return [".", ".."];
		}
		$names = [".", ".."];
		foreach ($configured as $name) {
			if (is_string($name) && $name !== "") {
				$names[] = $name;
			} else {
				$this->getLogger()->warning("exceptionData contains an invalid name. Ignoring that entry.");
			}
		}
		return $names;
	}

	/** @param list<string> $deleted */
	public function deleteMessage(array $deleted): void {
		$this->getLogger()->info("§fArchived data (" . count($deleted) . "): §a" . implode("§f,§a ", $deleted));
	}

	/**
	 * @param bool $justEmpty (only for folders) true delete the folder only if it is empty or false delete the folder and all its contents
	 *
	 * @return bool true on success or false on failure.
	 */
	public function delete(\DirectoryIterator $fileInfo, bool $justEmpty = false): bool {
		return Cleaner::delete($fileInfo, $justEmpty, $this->getExceptionData());
	}

	/**
	 * @return bool true on success or false on failure.
	 */
	public function deleteFile(\DirectoryIterator $file): bool {
		return Cleaner::deleteFile($file, $this->getExceptionData());
	}

	/**
	 * @param bool $justEmpty true delete the folder only if it is empty or false delete the folder and all its contents
	 *
	 * @return bool true on success or false on failure.
	 */
	public function deleteFolder(\DirectoryIterator $folder, bool $justEmpty = false): bool {
		return Cleaner::deleteFolder($folder, $justEmpty, $this->getExceptionData());
	}

	/**
	 * @return bool true on success or false on failure.
	 */
	public function deleteFilesInFolder(\DirectoryIterator $folder): bool {
		return Cleaner::deleteFilesInFolder($folder, $this->getExceptionData());
	}

	protected function onEnable(): void {
		$this->saveDefaultConfig();
		if ($this->getServer()->getConfigGroup()->getProperty("plugins.legacy-data-dir")) {
			$this->getLogger()->warning("legacy-data-dir is true, please set it to false in the pocketmine.yml");
			return;
		}
		$delaySeconds = $this->getConfig()->get("delayTime", 1);
		if (!is_int($delaySeconds) || $delaySeconds < 0 || $delaySeconds > 86400) {
			$this->getLogger()->warning("delayTime must be an integer between 0 and 86400. Using one second.");
			$delaySeconds = 1;
		}
		$this->getScheduler()->scheduleDelayedTask(new ClosureTask(function (): void {
			$plugins = array_map(
				function (Plugin $plugin): string {
					return $plugin->getDescription()->getName();
				},
				$this->getServer()->getPluginManager()->getPlugins()
			);

			$this->getServer()->getAsyncPool()->submitTask(new CleanupTask(
				$this->getServer()->getDataPath() . "plugin_data" . DIRECTORY_SEPARATOR,
				rtrim($this->getDataFolder(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . "DeleteBackup" . DIRECTORY_SEPARATOR,
				$plugins,
				$this->getExceptionData(),
				$this
			));
		}), $delaySeconds * 20);
	}
}
