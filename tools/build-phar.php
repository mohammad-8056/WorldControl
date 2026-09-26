<?php

/**
 * Builds WorldControl.phar from this repository.
 *
 *     php -d phar.readonly=0 tools/build-phar.php [output path]
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$output = $argv[1] ?? $root . "/WorldControl.phar";
$description = yaml_parse_file($root . "/plugin.yml");
if(!is_array($description)){
    fwrite(STDERR, "Could not read plugin.yml\n");
    exit(1);
}

if(is_file($output)){
    unlink($output);
}
$phar = new Phar($output);
$phar->setMetadata($description);
$phar->setStub('<?php echo "WorldControl v' . $description["version"] . ' - put this file in your server\'s plugins folder.\n"; __HALT_COMPILER();');
$phar->setSignatureAlgorithm(Phar::SHA256);
$phar->startBuffering();

$files = 0;
foreach(["plugin.yml", "LICENSE", "icon.png"] as $file){
    $phar->addFile($root . "/" . $file, $file);
    $files++;
}
foreach(["src", "resources"] as $folder){
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . "/" . $folder, FilesystemIterator::SKIP_DOTS));
    foreach($iterator as $file){
        $local = str_replace("\\", "/", substr($file->getPathname(), strlen($root) + 1));
        $phar->addFile($file->getPathname(), $local);
        $files++;
    }
}

$phar->compressFiles(Phar::GZ);
$phar->stopBuffering();
echo "Built $output ($files files)\n";
