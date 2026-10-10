<?php

declare(strict_types=1);

namespace NhanAZ\DataCleaner;

final class Cleaner {

	private function __construct() {
	}

	/**
	 * @param string[] $plugins
	 * @param string[] $exceptionData
	 *
	 * @return string[]
	 */
	public static function clean(string $pluginDataPath, array $plugins, array $exceptionData): array {
		if (!is_dir($pluginDataPath)) {
			return [];
		}

		$deleted = [];
		$directoryIterator = new \DirectoryIterator($pluginDataPath);
		foreach ($directoryIterator as $fileInfo) {
			$fileName = $fileInfo->getFilename();
			$success = self::delete(
				$fileInfo,
				in_array($fileName, $plugins, true),
				$exceptionData
			);
			if ($success) {
				$deleted[] = $fileName;
			}
		}

		return $deleted;
	}

	/**
	 * Moves unused plugin data into a separate backup directory for each run.
	 *
	 * @param string[] $plugins
	 * @param string[] $exceptionData
	 *
	 * @return string[]
	 */
	public static function cleanWithBackup(string $pluginDataPath, array $plugins, array $exceptionData, string $backupPath): array {
		if (!is_dir($pluginDataPath)) {
			return [];
		}
		$dataRoot = realpath($pluginDataPath);
		$backupParent = dirname(rtrim($backupPath, DIRECTORY_SEPARATOR));
		$parentRoot = realpath($backupParent);
		if ($dataRoot === false || $parentRoot === false || is_link($pluginDataPath) || is_link($backupParent) || is_link($backupPath)
			|| $parentRoot === $dataRoot || str_starts_with($parentRoot, rtrim($dataRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
			throw new \RuntimeException("Backup directory must be outside plugin_data: " . $backupPath);
		}
		if (!is_dir($backupPath) && !mkdir($backupPath, 0755)) {
			throw new \RuntimeException("Unable to prepare the backup directory: " . $backupPath);
		}
		$backupRoot = realpath($backupPath);
		if ($backupRoot === false || $backupRoot === $dataRoot || str_starts_with($backupRoot, rtrim($dataRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
			throw new \RuntimeException("Backup directory must be outside plugin_data: " . $backupPath);
		}
		$runPath = $backupRoot . DIRECTORY_SEPARATOR . gmdate("Ymd-His") . "-" . bin2hex(random_bytes(8));
		if (!mkdir($runPath, 0755)) {
			throw new \RuntimeException("Unable to create a new backup directory: " . $runPath);
		}

		$deleted = [];
		$directoryIterator = new \DirectoryIterator($pluginDataPath);
		foreach ($directoryIterator as $fileInfo) {
			$fileName = $fileInfo->getFilename();
			if (in_array($fileName, $plugins, true)) {
				continue;
			}

			$success = self::moveToBackup(
				$fileInfo,
				false,
				$exceptionData,
				$runPath
			);
			if ($success) {
				$deleted[] = $fileName;
			}
		}
		if ($deleted === []) {
			rmdir($runPath);
		}

		return $deleted;
	}

	/**
	 * Removes all entries from a directory while keeping the directory itself.
	 */
	public static function clearDirectory(string $directory): bool {
		if (!is_dir($directory) && !@mkdir($directory, 0777, true) && !is_dir($directory)) {
			return false;
		}

		foreach (new \DirectoryIterator($directory) as $fileInfo) {
			if (!$fileInfo->isDot() && !self::delete($fileInfo, false, [])) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param string[] $exceptionData
	 *
	 * @return bool true on success or false on failure.
	 */
	public static function delete(\DirectoryIterator $fileInfo, bool $justEmpty, array $exceptionData): bool {
		if ($fileInfo->isLink()) {
			return false;
		}
		if ($fileInfo->isDir()) {
			return self::deleteFolder($fileInfo, $justEmpty, $exceptionData);
		} elseif ($fileInfo->isFile()) {
			return self::deleteFile($fileInfo, $exceptionData);
		}

		throw new \InvalidArgumentException($fileInfo->getFilename() . " is a " . $fileInfo->getType() . " but he must be a file or folder");
	}

	/**
	 * @param string[] $exceptionData
	 *
	 * @return bool true on success or false on failure.
	 */
	public static function deleteFile(\DirectoryIterator $file, array $exceptionData): bool {
		if ($file->isLink()) {
			return false;
		}
		if (!$file->isFile()) {
			throw new \InvalidArgumentException($file->getFilename() . " is a " . $file->getType() . " but he must be a file");
		} elseif (in_array($file->getFilename(), $exceptionData, true)) {
			return false;
		}

		return @unlink($file->getPathname());
	}

	/**
	 * @param string[] $exceptionData
	 *
	 * @return bool true on success or false on failure.
	 */
	public static function deleteFolder(\DirectoryIterator $folder, bool $justEmpty, array $exceptionData): bool {
		if ($folder->isLink()) {
			return false;
		}
		if (!$folder->isDir()) {
			throw new \InvalidArgumentException($folder->getFilename() . " is a " . $folder->getType() . " but he must be a folder");
		} elseif (in_array($folder->getFilename(), $exceptionData, true)) {
			return false;
		}

		if ($justEmpty) {
			$directoryIterator = new \DirectoryIterator($folder->getPathname());
			foreach ($directoryIterator as $fileInfo) {
				if ($fileInfo->isFile()) {
					return false;
				}
				if (!$fileInfo->isDot() && !self::deleteFolder($fileInfo, true, $exceptionData)) {
					return false;
				}
			}

			$filePathName = $folder->getPathname();
			if (count(scandir($filePathName)) <= 2) {
				return @rmdir($filePathName);
			}
		} elseif (self::deleteFilesInFolder($folder, $exceptionData)) {
			return @rmdir($folder->getPathname());
		}

		return false;
	}

	/**
	 * @param string[] $exceptionData
	 *
	 * @return bool true on success or false on failure.
	 */
	public static function deleteFilesInFolder(\DirectoryIterator $folder, array $exceptionData): bool {
		$directoryIterator = new \DirectoryIterator($folder->getPathname());
		foreach ($directoryIterator as $fileInfo) {
			if (!$fileInfo->isDot() && !self::delete($fileInfo, false, $exceptionData)) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Checks whether the existing cleanup rules allow an entry to be deleted.
	 * This is done before moving an entry so exception data is never backed up as deleted data.
	 *
	 * @param string[] $exceptionData
	 */
	private static function canDelete(\DirectoryIterator $fileInfo, bool $justEmpty, array $exceptionData): bool {
		if ($fileInfo->isLink()) {
			return false;
		}
		if ($fileInfo->isDir()) {
			if (in_array($fileInfo->getFilename(), $exceptionData, true)) {
				return false;
			}

			foreach (new \DirectoryIterator($fileInfo->getPathname()) as $child) {
				if ($child->isDot()) {
					continue;
				}
				if ($child->isLink()) {
					return false;
				}
				if ($child->isFile()) {
					if ($justEmpty || in_array($child->getFilename(), $exceptionData, true)) {
						return false;
					}
				} elseif (!$child->isDir() || !self::canDelete($child, $justEmpty, $exceptionData)) {
					return false;
				}
			}

			return true;
		}

		return $fileInfo->isFile() && !in_array($fileInfo->getFilename(), $exceptionData, true);
	}

	/**
	 * @param string[] $exceptionData
	 */
	private static function moveToBackup(
		\DirectoryIterator $fileInfo,
		bool $justEmpty,
		array $exceptionData,
		string $backupPath
	): bool {
		if (!self::canDelete($fileInfo, $justEmpty, $exceptionData)) {
			return false;
		}

		$backupFilePath = rtrim($backupPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fileInfo->getFilename();
		return @rename($fileInfo->getPathname(), $backupFilePath);
	}
}
