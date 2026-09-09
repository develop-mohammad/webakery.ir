<?php
$root = dirname(__DIR__);
$files = array(
  $root.'/includes/class-wap-jalali.php' => array('normalize_range','recent_months','month_bounds','shift_year'),
  $root.'/includes/class-wap-portal.php' => array('prepare_date_ranges','render_month_pickers','wap_compare_from','ماه مشابه پارسال','مقایسه فعال است'),
  $root.'/assets/jalali-calendar.js' => array('setWholeMonth','jcal-months','کل این ماه'),
  $root.'/assets/app.js' => array('wapValidateDateForm','wapSubmitFilters','wapFillSameLastYear','wap_compare_from'),
  $root.'/assets/style.css' => array('wap-month-bar','jcal-months'),
  $root.'/hesabdar.php' => array("1.18.0"),
);
foreach ($files as $file=>$needles) {
  if (!is_readable($file)) { fwrite(STDERR,"FAIL missing $file\n"); exit(1);} 
  $c=file_get_contents($file);
  foreach ($needles as $n) {
    if (strpos($c,$n)===false) { fwrite(STDERR,"FAIL $file missing: $n\n"); exit(1);} 
  }
  echo "OK ".basename($file)."\n";
}
if (!defined('ABSPATH')) define('ABSPATH', '/');
require_once $root.'/includes/class-wap-jalali.php';
$n = WAP_Jalali::normalize_range('1405/06/18','1405/06/01');
if (!$n['swapped'] || $n['from']!=='1405/06/01' || $n['to']!=='1405/06/18') {
  fwrite(STDERR,"FAIL normalize_range swap\n"); exit(1);
}
$b = WAP_Jalali::month_bounds(1404,6);
if ($b['from']!=='1404/06/01' || $b['to']!=='1404/06/31') {
  fwrite(STDERR,"FAIL month_bounds\n"); var_export($b); exit(1);
}
echo "OK normalize+month_bounds\n";
echo "ALL DATE COMPARE UX CHECKS PASSED\n";
