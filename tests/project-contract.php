<?php
if(wp_get_environment_type()!=='local'){throw new RuntimeException('Local only');}
use FalconWF\Packages\Verifier as V;
$root='/artifacts/project-contract';$manifest=json_decode(file_get_contents($root.'/project-manifest.json'),true);$package=$manifest['packages'][0];$path=$root.'/'.$package['artifact'];$count=0;
$ok=static function($x,$label)use(&$count){if(!$x){throw new RuntimeException('FAIL: '.$label);}$count++;echo "PASS: $label\n";};
$ok(V::remote($path,$package)===true,'actual Node builder ZIP accepted by PHP project verifier');
$z=new ZipArchive();$z->open($path);$ok($z->getFromName($package['id'].'/assets/font.woff2')==="\0\xff\x80FONT",'builder font bytes preserved across Node ZIP and PHP read');$z->close();
$tmp=wp_tempnam('fwf-contract.zip');
try{
 foreach(['missing-index','unsupported-asset','alias','symlink','bad-parent','bad-version'] as $case){
  copy($path,$tmp);$z=new ZipArchive();$z->open($tmp);$prefix=$package['id'].'/';
  if($case==='missing-index'){$z->deleteName($prefix.'index.php');}
  if($case==='unsupported-asset'){$z->addFromString($prefix.'config.env','not allowed');}
  if($case==='alias'){$z->addFromString($prefix.'assets//motion.js','not allowed');}
  if($case==='symlink'){$z->addFromString($prefix.'assets/link.js','motion.js');$z->setExternalAttributesName($prefix.'assets/link.js',ZipArchive::OPSYS_UNIX,0120777<<16);}
  if($case==='bad-parent'){$z->addFromString($prefix.'style.css',"/*\nTheme Name: Test\nTemplate: wrong-parent\nVersion: ".$package['version']."\n*/");}
  if($case==='bad-version'){$z->addFromString($prefix.'style.css',"/*\nTheme Name: Test\nTemplate: falcon-theme\nVersion: 99.0.0\n*/");}
  $z->close();$p=$package;$p['sha256']=hash_file('sha256',$tmp);$error=V::remote($tmp,$p);$ok(is_wp_error($error),'verifier refuses '.$case.' with checksum otherwise valid');
 }
 $p=$package;$p['sha256']=str_repeat('0',64);$ok(is_wp_error(V::remote($path,$p)),'checksum mismatch rejected before install');
}finally{if(is_file($tmp)){unlink($tmp);}}
echo "Project shared contract checks passed: $count. No host/client acceptance.\n";
