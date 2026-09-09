<?php
$root = dirname(__DIR__);
$files = array(
  $root.'/includes/class-wap-jalali.php' => array('normalize_range','recent_months','month_bounds','shift_year','shift_month','previous_month_range'),
  $root.'/includes/class-wap-portal.php' => array('prepare_date_ranges','render_month_pickers','wap_compare_from','ماه قبل','ماه مشابه پارسال','مقایسه فعال است','ماه جدید'),
  $root.'/assets/jalali-calendar.js' => array('setWholeMonth','jcal-months','کل این ماه'),
  $root.'/assets/app.js' => array('wapValidateDateForm','wapSubmitFilters','wapFillSameLastYear','wapFillPreviousMonth','wap_compare_from','data-wap-compare-previous-month'),
  $root.'/assets/style.css' => array('wap-month-bar','jcal-months'),
  $root.'/hesabdar.php' => array("1.19.1"),
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
$prev = WAP_Jalali::previous_month_range('1405/06/01','1405/06/31');
if (!$prev || $prev['from']!=='1405/05/01' || $prev['to']!=='1405/05/31') {
  fwrite(STDERR,"FAIL previous_month_range full month\n"); var_export($prev); exit(1);
}
$prev2 = WAP_Jalali::previous_month_range('1405/01/01','1405/01/31');
if (!$prev2 || $prev2['from']!=='1404/12/01' || $prev2['to']!=='1404/12/29') {
  fwrite(STDERR,"FAIL previous_month_range year wrap\n"); var_export($prev2); exit(1);
}
echo "OK normalize+month_bounds+previous_month\n";

// regression: PHP xor precedence must not treat both-filled as partial
$cmp_from = '1405/05/01';
$cmp_to   = '1405/05/31';
$partial  = ( ( $cmp_from !== '' ) xor ( $cmp_to !== '' ) );
if ( $partial !== false ) {
  fwrite(STDERR, "FAIL xor partial both-filled\n");
  exit(1);
}
$cmp_to = '';
$partial = ( ( $cmp_from !== '' ) xor ( $cmp_to !== '' ) );
if ( $partial !== true ) {
  fwrite(STDERR, "FAIL xor partial one-filled\n");
  exit(1);
}
// source must use parenthesized xor (assignment vs xor precedence)
$portal = file_get_contents($root.'/includes/class-wap-portal.php');
if (strpos($portal, '( ( $cmp_from !== \'\' ) xor ( $cmp_to !== \'\' ) )') === false
    && strpos($portal, '(( $cmp_from !== \'\' ) xor ( $cmp_to !== \'\' ))') === false) {
  // accept either spacing of the fixed form
  if (!preg_match('/\$partial\s*=\s*\(\s*\(\s*\$cmp_from/', $portal)) {
    fwrite(STDERR, "FAIL portal missing parenthesized xor for \$partial\n");
    exit(1);
  }
}
echo "OK xor partial precedence\n";
echo "ALL DATE COMPARE UX CHECKS PASSED\n";
