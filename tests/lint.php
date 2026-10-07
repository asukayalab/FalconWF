<?php
$manifest=json_decode(file_get_contents('/artifacts/release-manifest.json'),true);$archives=array_map(static fn($p)=>'/artifacts/'.$p['artifact'],$manifest['packages']);$count=0;
$project=json_decode(file_get_contents('/artifacts/projects/project-manifest.json'),true);foreach($project['packages'] as $p){$archives[]='/artifacts/projects/'.$p['artifact'];}
foreach ($archives as $file) {
 $root=sys_get_temp_dir().'/fwf-lint-'.bin2hex(random_bytes(8));mkdir($root);
 $zip=new ZipArchive();if ($zip->open($file)!==true) { throw new RuntimeException('Bad ZIP'); }$zip->extractTo($root);$zip->close();
 $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));
 foreach ($iterator as $item) {
   if ($item->getExtension()==='php') { exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($item->getPathname()).' 2>&1',$lines,$status);if($status!==0){throw new RuntimeException(implode("\n",$lines));}$lines=[];$count++; }
 }
 // Remove only fresh temporary extraction created above.
 $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
 foreach($iterator as $item){$item->isDir()?rmdir($item->getPathname()):unlink($item->getPathname());}rmdir($root);
}
echo "PHP syntax checks passed: $count files\n";
