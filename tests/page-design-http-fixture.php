<?php
if(wp_get_environment_type()!=='local'){throw new RuntimeException('Local only');}
$mode=$args[0]??'';
if($mode==='create'){
 $file='fwf-http-design-'.wp_generate_uuid4().'.php';$path=get_stylesheet_directory().'/'.$file;
 if(file_put_contents($path,"<?php\n/* Template Name: HTTP Contact Fixture */\necho 'FWF_HTTP_DESIGN_RENDER';\n")===false){throw new RuntimeException('Template fixture write failed');}
 wp_clean_themes_cache();
 $id=wp_insert_post(['post_type'=>'page','post_status'=>'publish','post_title'=>'FWF HTTP Page design','post_content'=>'Preserved HTTP body','post_name'=>'fwf-http-design-'.wp_generate_uuid4()],true);
 if(is_wp_error($id)){unlink($path);throw new RuntimeException('Page fixture failed');}
 add_post_meta($id,'_fwf_page_design_fixture',$file,true);echo wp_json_encode(['id'=>$id,'file'=>$file,'url'=>get_permalink($id)]);
}else{
 $id=(int)($args[1]??0);$file=get_post_meta($id,'_fwf_page_design_fixture',true);
 if(!is_string($file) || !preg_match('/^fwf-http-design-[a-f0-9-]+\.php$/D',$file)){throw new RuntimeException('Not owned fixture');}
 if($mode==='read'){echo wp_json_encode(['template'=>get_post_meta($id,'_wp_page_template',true),'title'=>get_the_title($id),'body'=>get_post($id)->post_content,'url'=>get_permalink($id),'status'=>get_post_status($id)]);}
 elseif($mode==='native'){wp_update_post(['ID'=>$id,'page_template'=>'default']);}
 elseif($mode==='cleanup'){wp_delete_post($id,true);$path=get_stylesheet_directory().'/'.$file;if(is_file($path)){unlink($path);}wp_clean_themes_cache();}
}
