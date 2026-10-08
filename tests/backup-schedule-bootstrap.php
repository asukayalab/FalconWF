<?php
$owner=getenv('FWF_SCHEDULE_TEST_OWNER');$mode=getenv('FWF_SCHEDULE_TEST_MODE');
if(!is_string($owner)||!preg_match('/^[a-f0-9-]{36}$/D',$owner))throw new RuntimeException('Schedule fixture owner required.');
$path='/fwf-backups/schedule-test-'.$owner;
if($mode==='capture'){if(file_exists($path)||!mkdir($path,0700))throw new RuntimeException('Schedule fixture collision.');}
elseif(!is_dir($path)||is_link($path)||(fileperms($path)&0077)!==0)throw new RuntimeException('Owned private directory missing.');
$extra=getenv('WORDPRESS_CONFIG_EXTRA');$needle="define('FWF_BACKUP_DIR', '/fwf-backups');";
if(!is_string($extra)||substr_count($extra,$needle)!==1)throw new RuntimeException('Local backup config mismatch.');
putenv('WORDPRESS_CONFIG_EXTRA='.str_replace($needle,"define('FWF_BACKUP_DIR', '$path');",$extra));
