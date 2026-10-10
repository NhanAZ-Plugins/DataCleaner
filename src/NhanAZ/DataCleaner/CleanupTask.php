<?php

declare(strict_types=1);

namespace NhanAZ\DataCleaner;

use pocketmine\scheduler\AsyncTask;

final class CleanupTask extends AsyncTask {

	private string $pluginDataPath;

	private string $backupPath;

	private string $pluginsJson;

	private string $exceptionDataJson;

	/**
	 * @param string[] $plugins
	 * @param string[] $exceptionData
	 */
	public function __construct(string $pluginDataPath, string $backupPath, array $plugins, array $exceptionData, Main $plugin) {
		$this->pluginDataPath = $pluginDataPath;
		$this->backupPath = $backupPath;
		$this->pluginsJson = json_encode(array_values($plugins), JSON_THROW_ON_ERROR);
		$this->exceptionDataJson = json_encode(array_values($exceptionData), JSON_THROW_ON_ERROR);

		// Plugin instances cannot be serialized into a worker thread.
		$this->storeLocal("plugin", $plugin);
	}

	public function onRun(): void {
		$this->setResult(Cleaner::cleanWithBackup(
			$this->pluginDataPath,
			self::decodeNames($this->pluginsJson),
			self::decodeNames($this->exceptionDataJson),
			$this->backupPath
		));
	}

	/** @return list<string> */
	private static function decodeNames(string $json): array {
		$decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
		if (!is_array($decoded)) {
			throw new \UnexpectedValueException("Cleanup task names must be an array");
		}
		$names = [];
		foreach ($decoded as $name) {
			if (!is_string($name)) {
				throw new \UnexpectedValueException("Cleanup task names must be strings");
			}
			$names[] = $name;
		}
		return $names;
	}

	public function onCompletion(): void {
		/** @var Main $plugin */
		$plugin = $this->fetchLocal("plugin");
		$deleted = $this->getResult();
		if (is_array($deleted)) {
			$names = [];
			foreach ($deleted as $name) {
				if (is_string($name)) {
					$names[] = $name;
				}
			}
			$plugin->deleteMessage($names);
		}
	}

	public function onError(): void {
		/** @var Main $plugin */
		$plugin = $this->fetchLocal("plugin");
		$plugin->getLogger()->error("Unable to clean the plugin_data directory asynchronously");
	}
}
