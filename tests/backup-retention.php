<?php

declare(strict_types=1);

use NhanAZ\DataCleaner\Cleaner;

$source = $argv[1] ?? null;
if ($source === null || !is_file($source . '/vendor/autoload.php')) {
	fwrite(STDERR, "Usage: php tests/backup-retention.php /path/to/Axolotl-PM-source [cleaner-source]\n");
	exit(2);
}
require $source . '/vendor/autoload.php';
require $argv[2] ?? dirname(__DIR__) . '/src/NhanAZ/DataCleaner/Cleaner.php';

$root = sys_get_temp_dir() . '/datacleaner-' . bin2hex(random_bytes(8));
$data = $root . '/plugin_data';
$backup = $root . '/plugins/DataCleaner/DeleteBackup';
foreach ([$root, $data, $root . '/plugins', $root . '/plugins/DataCleaner', $data . '/Active', $data . '/EmptyActive', $data . '/OrphanOne', $data . '/Protected', $data . '/NestedProtected', $data . '/NestedLink'] as $dir) {
	if (!mkdir($dir)) {
		throw new RuntimeException("Cannot create fixture directory {$dir}");
	}
}
file_put_contents($data . '/Active/state.txt', 'active');
file_put_contents($data . '/OrphanOne/state.txt', 'first');
file_put_contents($data . '/Protected/state.txt', 'protected');
file_put_contents($data . '/NestedProtected/do-not-move.txt', 'nested');
$outside = $root . '/outside';
if (!mkdir($outside)) {
	throw new RuntimeException('Cannot create outside fixture directory');
}
file_put_contents($outside . '/safe.txt', 'outside');
$linkedOutside = $data . '/LinkedOutside';
$linkCreated = @symlink($outside, $linkedOutside);
$nestedLink = $data . '/NestedLink/safe.txt';
$nestedLinkCreated = @symlink($outside . '/safe.txt', $nestedLink);
if (!$nestedLinkCreated) {
	rmdir($data . '/NestedLink');
}

try {
	Cleaner::cleanWithBackup($data, ['Active', 'DataCleaner'], [], $data . '/UnsafeBackup');
	throw new RuntimeException('A backup path inside plugin_data was accepted');
} catch (RuntimeException $error) {
	if ($error->getMessage() === 'A backup path inside plugin_data was accepted') {
		throw $error;
	}
}

$loaded = ['Active', 'EmptyActive', 'DataCleaner'];
$first = Cleaner::cleanWithBackup($data, $loaded, ['Protected', 'do-not-move.txt'], $backup);
$runs = glob($backup . '/*', GLOB_ONLYDIR);
if ($first !== ['OrphanOne'] || $runs === false || count($runs) !== 1 || file_get_contents($runs[0] . '/OrphanOne/state.txt') !== 'first'
	|| !is_file($data . '/Active/state.txt') || !is_dir($data . '/EmptyActive') || !is_file($data . '/Protected/state.txt') || !is_file($data . '/NestedProtected/do-not-move.txt')) {
	throw new RuntimeException('First cleanup moved active or protected data, or failed to retain the orphan');
}
if ($linkCreated && (!is_link($linkedOutside) || file_get_contents($outside . '/safe.txt') !== 'outside')) {
	throw new RuntimeException('A linked directory outside plugin_data was changed');
}
if ($nestedLinkCreated && (!is_link($nestedLink) || file_get_contents($outside . '/safe.txt') !== 'outside')) {
	throw new RuntimeException('A linked file inside plugin_data was moved or changed');
}

if (!mkdir($data . '/OrphanTwo')) {
	throw new RuntimeException('Cannot create second orphan');
}
file_put_contents($data . '/OrphanTwo/state.txt', 'second');
$second = Cleaner::cleanWithBackup($data, $loaded, ['Protected', 'do-not-move.txt'], $backup);
$runs = glob($backup . '/*', GLOB_ONLYDIR);
if ($second !== ['OrphanTwo'] || $runs === false || count($runs) !== 2) {
	throw new RuntimeException('Second cleanup did not make a separate backup');
}
$firstFound = false;
$secondFound = false;
foreach ($runs as $run) {
	$firstPath = $run . '/OrphanOne/state.txt';
	$secondPath = $run . '/OrphanTwo/state.txt';
	$firstFound = $firstFound || (is_file($firstPath) && file_get_contents($firstPath) === 'first');
	$secondFound = $secondFound || (is_file($secondPath) && file_get_contents($secondPath) === 'second');
}
if (!$firstFound || !$secondFound) {
	throw new RuntimeException('A previous backup was lost');
}

$third = Cleaner::cleanWithBackup($data, $loaded, ['Protected', 'do-not-move.txt'], $backup);
$runsAfter = glob($backup . '/*', GLOB_ONLYDIR);
if ($third !== [] || $runsAfter === false || count($runsAfter) !== 2) {
	throw new RuntimeException('No-op cleanup created an empty backup');
}

echo "PASS: DataCleaner retains backups and protects active and exception data\n";
