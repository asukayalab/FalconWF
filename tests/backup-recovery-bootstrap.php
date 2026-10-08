<?php
$owner=getenv('FWF_RECOVERY_TEST_OWNER');if(!is_string($owner)||!preg_match('/^[a-f0-9-]{36}$/D',$owner))throw new RuntimeException('Owner required');
$extra=getenv('WORDPRESS_CONFIG_EXTRA');$needle="define('FWF_BACKUP_DIR', '/fwf-backups');";if(substr_count($extra,$needle)!==1)throw new RuntimeException('Local config mismatch');putenv('WORDPRESS_CONFIG_EXTRA='.str_replace($needle,"define('FWF_BACKUP_DIR', '/fwf-backups/recovery-test-$owner');",$extra));
