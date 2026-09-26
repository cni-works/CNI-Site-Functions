<?php
/** Diagnostic lifecycle and legacy migration regression checks. */
define( 'ABSPATH', __DIR__ );
define( 'DAY_IN_SECONDS', 86400 );
$options = array();
$now = '2026-09-01 00:00:00';
function get_option( $name, $default = false ) { global $options; return $options[$name] ?? $default; }
function update_option( $name, $value ) { global $options; $options[$name] = $value; }
function current_time() { global $now; return $now; }
function __( $text ) { return $text; }
require dirname(__DIR__) . '/includes/class-code-repository.php';
require dirname(__DIR__) . '/includes/class-admin-page.php';
use CniWorks\CniSiteFunctions\Code_Repository as R;
use CniWorks\CniSiteFunctions\Admin_Page as A;
$failed = 0;
function check( $value, $label ) { global $failed; echo ($value ? 'PASS: ' : 'FAIL: ') . $label . "\n"; if (!$value) { $failed++; } }
R::save('first();', true);
R::save('second();', true);
$base = R::get_state();
$options[R::OPTION_NAME]['execution_error'] = array('type'=>'unconfirmed_fatal_error','message'=>'Old failure','file'=>'old-plugin.php','line'=>15,'occurred_at'=>$now,'auto_disabled'=>false);
$legacy = R::get_state()['execution_error'];
check($legacy['first_seen_at'] === $now && $legacy['last_seen_at'] === $now && $legacy['count'] === 1 && !$legacy['attributable'], 'legacy timestamps/count/attribution normalize');
$now = '2026-09-02 00:00:00';
R::record_execution_issue('unconfirmed_fatal_error','Old failure','old-plugin.php',15);
$state = R::get_state(); $error = $state['execution_error'];
check($error['first_seen_at'] === $legacy['first_seen_at'] && $error['last_seen_at'] === $now && $error['occurred_at'] === $now && $error['count'] === 2, 'repeat updates last time/count and keeps first time');
check($error['fingerprint'] === $legacy['fingerprint'], 'legacy and new fingerprints match');
foreach (array('enabled','active_code','previous_code','has_previous','code_hash','updated_at') as $key) { check($base[$key] === $state[$key], 'external diagnostic preserves ' . $key); }
R::save('fixed();', true);
check(R::get_state()['execution_error'] === $error, 'code save retains external record');
R::restore_previous();
check(R::get_state()['execution_error'] === $error, 'restore retains external record');
foreach (array(array('fatal_error','Old failure','old-plugin.php',15),array('fatal_error','New failure','old-plugin.php',15),array('fatal_error','New failure','new-plugin.php',15),array('fatal_error','New failure','new-plugin.php',16)) as $args) {
    $before = R::get_state()['execution_error']['fingerprint'];
    R::record_execution_issue(...$args);
    $next = R::get_state()['execution_error'];
    check($next['fingerprint'] !== $before && $next['count'] === 1 && $next['first_seen_at'] === $now, 'different type/message/file/line replaces latest record');
}
R::disable_with_error('runtime_error','Runtime failure','saved.php',3);
$error = R::get_state()['execution_error'];
check(!R::get_state()['enabled'] && $error['attributable'] && $error['auto_disabled'], 'attributable error still auto-disables');
R::save('repaired();', true);
check(R::get_state()['execution_error'] === $error && R::get_state()['enabled'], 'repair save retains historical auto-stop but can enable');
R::disable_with_error('runtime_error','Runtime failure','saved.php',3);
$before = R::get_state();
R::clear_execution_error();
$after = R::get_state();
check($after['execution_error'] === array(), 'explicit clear removes diagnostic');
unset($before['execution_error'], $after['execution_error']);
check($before === $after && !$after['enabled'], 'clear preserves all code state and does not re-enable');
R::record_execution_issue('runtime_error','Runtime failure','saved.php',3,true);
check(R::get_state()['execution_error']['count'] === 1, 'recurrence after clear starts a new visible record');
check(strpos(A::error_recency(gmdate('Y-m-d H:i:s', time()-60)), '最近検知') !== false, 'recent detection label');
check(strpos(A::error_recency(gmdate('Y-m-d H:i:s', time()-86401)), '過去の記録') !== false, 'past detection label');
check(A::error_recency('') === '検知日時不明' && A::error_recency('invalid') === '検知日時不明', 'unknown timestamps do not imply recovery');
exit($failed ? 1 : 0);
