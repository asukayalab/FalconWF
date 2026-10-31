<?php
$owner=getenv('FWF_TRANSIT_TEST_OWNER');if(!is_string($owner) || !preg_match('/^[a-f0-9-]{36}$/D',$owner)){throw new RuntimeException('Owner required');}
$root='/fwf-backups/transit-test-'.$owner;if(file_exists($root)){throw new RuntimeException('Fixture collision');}
foreach([$root,$root.'/blocked',$root.'/blocked/public',$root.'/home',$root.'/transit'] as $path){if(!mkdir($path,0700)){throw new RuntimeException('Fixture failed');}}
chmod($root.'/blocked',0555);chmod($root.'/home',0555);
$extra=getenv('WORDPRESS_CONFIG_EXTRA');$needle="define('FWF_BACKUP_DIR', '/fwf-backups');";if(substr_count($extra,$needle)!==1){throw new RuntimeException('Config mismatch');}putenv('WORDPRESS_CONFIG_EXTRA='.str_replace($needle,'// no configured backup directory in fixture',$extra));
