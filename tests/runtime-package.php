<?php
if (wp_get_environment_type()!=='local') { throw new RuntimeException('Local only.'); }
$manifest=json_decode(file_get_contents('/artifacts/release-manifest.json'),true);$count=0;
$scope=$args[0]??'all';
if(!in_array($scope,['all','plugin'],true)){throw new RuntimeException('Unknown runtime check scope.');}
foreach($manifest['packages'] as $package){
    if($scope==='plugin' && $package['type']!=='plugin'){continue;}
    $zip=new ZipArchive();if($zip->open('/artifacts/'.$package['artifact'])!==true){throw new RuntimeException('Runtime archive missing.');}
    try{
        $base=$package['type']==='plugin'?WP_PLUGIN_DIR:get_theme_root();$prefix=$package['id'].'/';
        for($i=0;$i<$zip->numFiles;$i++){
            $name=$zip->getNameIndex($i);if(!str_starts_with($name,$prefix) || str_contains($name,'..') || str_contains($name,'\\')){throw new RuntimeException('Unsafe runtime inventory.');}
            $path=$base.'/'.$name;$bytes=$zip->getFromIndex($i);
            if(!is_string($bytes) || !is_file($path) || !hash_equals(hash('sha256',$bytes),hash_file('sha256',$path))){throw new RuntimeException('Installed runtime differs from build: '.$name);}$count++;
        }
    }finally{$zip->close();}
}
echo ($scope==='plugin'?'Installed FP':'Installed FP/FT')." byte hashes match build: $count files\n";
