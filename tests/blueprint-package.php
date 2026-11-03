<?php
if(wp_get_environment_type()!=='local'){throw new RuntimeException('Local only');}
use FalconWF\Packages\Verifier as V;
use FalconWF\Updates\ProjectManager as P;
$m=json_decode(file_get_contents('/artifacts/blueprint-contract/project-manifest.json'),true);$p=$m['packages'][0];$path='/artifacts/blueprint-contract/'.$p['artifact'];$count=0;
$ok=static function($v,$label)use(&$count){if(!$v){throw new RuntimeException('FAIL: '.$label);}$count++;echo "PASS: $label\n";};
$ok(V::remote($path,$p)===true,'actual builder blueprint ZIP accepted by verifier');
// Metadata validation fixture only; does not claim preview source is a clean remote release.
$m['dirty']=false;$m['source_commit']=str_repeat('b',40);$connection=['tag'=>'v'.$m['version'],'project_id'=>$m['project_id'],'theme_id'=>$p['id']];
$ok(!is_wp_error(P::validate($m,$connection)),'blueprint descriptor and required FP/FT accepted');
$bad=$m;$bad['packages'][0]['compatibility']['falcon-wf']['optional']=true;$ok(is_wp_error(P::validate($bad,$connection)),'blueprint cannot declare FP optional');
$tmp=wp_tempnam('fwf-blueprint.zip');
try{
 copy($path,$tmp);$zip=new ZipArchive();$zip->open($tmp);$raw=$zip->getFromName($p['id'].'/falcon-blueprint.json');$doc=json_decode($raw,true);$doc['options']=['arbitrary'=>'not allowed'];$zip->addFromString($p['id'].'/falcon-blueprint.json',wp_json_encode($doc));$zip->close();
 $changed=$p;$changed['sha256']=hash_file('sha256',$tmp);$ok(is_wp_error(V::remote($tmp,$changed)),'blueprint byte changes rejected against descriptor');
 $changed['blueprint']['sha256']=hash('sha256',wp_json_encode($doc));$ok(is_wp_error(V::remote($tmp,$changed)),'schema rejects arbitrary options even with matching hashes');
 $noDescriptor=$p;unset($noDescriptor['blueprint']);$ok(is_wp_error(V::remote($path,$noDescriptor)),'undeclared blueprint file rejected');
}finally{if(is_file($tmp)){unlink($tmp);}}
echo "Blueprint package checks passed: $count. No remote/provider acceptance.\n";
