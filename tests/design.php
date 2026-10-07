<?php
if (wp_get_environment_type()!=='local') { throw new RuntimeException('Local only.'); }
use FalconTheme\Design as D;
use FalconWF\Settings as S;
$key='fwf_design_'.get_stylesheet();$before=get_option($key,null);$count=0;
$ok=static function($value,$label) use (&$count) { if (!$value) { throw new RuntimeException('FAIL: '.$label); } $count++;echo "PASS: $label\n"; };
try {
 delete_option($key);$revision=D::revision();
 $values=['body_font'=>'system','heading_font'=>'serif','ink'=>'#123456','accent'=>'#345678','h1'=>'64','h6'=>'16','body_size'=>'18','line_height'=>'1.6','content_width'=>'1100','spacing'=>'32'];
 $ok(S::saveDesign($values,get_stylesheet(),$revision)===true,'validated design saves under active theme scope');
 $ok(str_contains(D::css(),'--fwf-body-font:system-ui') && str_contains(D::css(),'--fwf-h1:64px') && str_contains(D::css(),'--fwf-line-height:1.6;'),'typed settings generate safe CSS units/fonts');
 $saved=D::values();
 foreach ([['ink'=>'</style><script>alert(1)</script>'],['body_font'=>'url(https://bad.invalid)'],['h1'=>'121'],['spacing'=>'15'],['h6'=>['16']],['unknown'=>'42'],['line_height'=>'NaN']] as $invalid) {
  $ok(is_wp_error(S::saveDesign($invalid,get_stylesheet(),D::revision())) && D::values()===$saved,'invalid design rejects entire write');
 }
 $ok(is_wp_error(S::saveDesign([],get_stylesheet(),$revision)) && D::values()===$saved,'stale design form cannot reset newer settings');
 $ok(is_wp_error(S::saveDesign([],'other-theme',D::revision())) && D::values()===$saved,'theme change refuses old form');
 $owner=FalconWF\Packages\Lock::acquire('design_'.get_stylesheet());
 try { $ok(is_wp_error(S::saveDesign([],get_stylesheet(),D::revision())),'concurrent design writer refused'); } finally { FalconWF\Packages\Lock::release('design_'.get_stylesheet(),$owner); }
 $ok(S::saveDesign(['ink'=>''],get_stylesheet(),D::revision())===true && D::css()==='' && get_option($key,null)===null,'empty form restores theme defaults without deleting content');
 update_option($key,['ink'=>'bad']);$ok(D::css()==='','corrupt stored design falls back without unsafe output');
 echo "Design contract checks passed: $count.\n";
} finally { $before===null?delete_option($key):update_option($key,$before,false); }
