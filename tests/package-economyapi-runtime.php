<?php

declare(strict_types=1);

if($argc !== 3){
	fwrite(STDERR, "Usage: php -d phar.readonly=0 package-economyapi-runtime.php <EconomyAPI source> <output PHAR>\n");
	exit(2);
}

$source = realpath($argv[1]);
$output = $argv[2];
if($source === false || !is_file($source . DIRECTORY_SEPARATOR . "plugin.yml") || !is_file($source . DIRECTORY_SEPARATOR . "LICENSE")){
	fwrite(STDERR, "EconomyAPI source must contain its plugin manifest and license.\n");
	exit(2);
}
if(file_exists($output)){
	fwrite(STDERR, "Refusing to replace an existing runtime-test artifact.\n");
	exit(2);
}

$phar = new Phar($output);
$phar->startBuffering();
$phar->addFile($source . DIRECTORY_SEPARATOR . "plugin.yml", "plugin.yml");
$phar->addFile($source . DIRECTORY_SEPARATOR . "LICENSE", "LICENSE");
$expectedFiles = ["plugin.yml", "LICENSE"];
foreach(["src", "resources"] as $directory){
	$root = $source . DIRECTORY_SEPARATOR . $directory;
	if(!is_dir($root)){
		fwrite(STDERR, "EconomyAPI source is missing its $directory directory.\n");
		exit(2);
	}
	$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
	foreach($iterator as $file){
		if($file->isLink()){
			fwrite(STDERR, "Refusing to package symlinked EconomyAPI source.\n");
			exit(2);
		}
		if(!$file->isFile()){
			continue;
		}
		$relative = str_replace(DIRECTORY_SEPARATOR, "/", substr($file->getPathname(), strlen($source) + 1));
		$phar->addFile($file->getPathname(), $relative);
		$expectedFiles[] = $relative;
	}
}
$phar->setStub("<?php __HALT_COMPILER();");
$phar->setSignatureAlgorithm(Phar::SHA256);
$phar->stopBuffering();

$built = new Phar($output);
$actualFiles = [];

foreach(new RecursiveIteratorIterator($built) as $file){
	if($file->isDir()){
		continue;
	}
	$actualFiles[] = substr($file->getPathName(), strlen("phar://" . str_replace(DIRECTORY_SEPARATOR, "/", $output) . "/"));
}
sort($expectedFiles);
sort($actualFiles);
if($expectedFiles !== $actualFiles){
	fwrite(STDERR, "Runtime-test EconomyAPI PHAR contents do not match the selected source files.\n");
	exit(1);
}
fwrite(STDOUT, "Packaged the pinned EconomyAPI source for isolated runtime testing only (" . count($actualFiles) . " files).\n");
