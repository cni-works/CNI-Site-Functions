<?php
/** Admin authorization, nonce, and escaped diagnostic rendering. */
define('ABSPATH', __DIR__);
define('DAY_IN_SECONDS',86400);
define('MINUTE_IN_SECONDS',60);
$options = array(); $caps = array(); $valid_nonce = false; $redirected = false;
function get_option($name,$default=false) { global $options; return $options[$name] ?? $default; }
function update_option($name,$value) { global $options; $options[$name]=$value; }
function current_time() { return '2026-09-01 00:00:00'; }
function current_user_can($cap) { global $caps; return in_array($cap,$caps,true); }
function wp_die() { throw new RuntimeException('forbidden'); }
function check_admin_referer($action) { global $valid_nonce; if (!$valid_nonce || $action !== 'cni_site_functions_clear_error') { throw new RuntimeException('nonce'); } }
function __($s) { return $s; }
function esc_html($s) { return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8'); }
function esc_html__($s) { return esc_html($s); }
function esc_html_e($s) { echo esc_html($s); }
function esc_attr($s) { return esc_html($s); }
function esc_url($s) { return esc_html($s); }
function esc_textarea($s) { return esc_html($s); }
function get_current_user_id() { return 1; }
function get_transient() { return false; }
function set_transient() {}
function delete_transient() {}
function wp_readonly() { return ''; }
function admin_url($s) { return '/wp-admin/'.$s; }
function wp_nonce_field($s) { echo '<input name="_wpnonce" value="'.esc_attr($s).'">'; }
function submit_button($s) { echo '<button>'.esc_html($s).'</button>'; }
function checked() {}
function wp_safe_redirect() { throw new RuntimeException('redirect'); }
require dirname(__DIR__).'/includes/class-code-repository.php';
require dirname(__DIR__).'/includes/class-admin-page.php';
class_alias('TestExecutor','CniWorks\CniSiteFunctions\Executor');
class TestExecutor { public static function get_safe_mode_reason() { return ''; } }
use CniWorks\CniSiteFunctions\Code_Repository as R;
use CniWorks\CniSiteFunctions\Admin_Page as A;
$failed=0;
function check($v,$s) { global $failed; echo ($v?'PASS: ':'FAIL: ').$s."\n"; if (!$v) { $failed++; } }
R::save('saved();',true);
R::record_execution_issue('unconfirmed_fatal_error','<script>alert(1)</script>','<img src=x>',15);
$before=R::get_state();
foreach (array(array(),array('manage_options'),array('edit_plugins')) as $caps) {
 try { A::handle_clear_error(); } catch (RuntimeException $e) { check($e->getMessage()==='forbidden' && R::get_state()===$before,'both capabilities required before clear'); }
}
$caps=array('manage_options','edit_plugins');
try { A::handle_clear_error(); } catch (RuntimeException $e) { check($e->getMessage()==='nonce' && R::get_state()===$before,'invalid nonce cannot clear'); }
ob_start(); A::render_page(); $html=ob_get_clean();
check(strpos($html,'<script>')===false && strpos($html,'&lt;script&gt;')!==false && strpos($html,'&lt;img src=x&gt;')!==false,'message and file are escaped');
foreach (array('初回検知','最終検知','検知回数','由来: 未確認','過去の記録','現在も発生中であることを意味しません','cni_site_functions_clear_error','この記録を削除') as $label) { check(strpos($html,$label)!==false,'render includes '.$label); }
check(substr_count($html,'<form ')===substr_count($html,'</form>'),'forms are balanced');
$valid_nonce=true;
try { A::handle_clear_error(); } catch (RuntimeException $e) { check($e->getMessage()==='redirect' && R::get_state()['execution_error']===array(),'authorized clear succeeds and redirects'); }
$after=R::get_state(); unset($before['execution_error'],$after['execution_error']);
check($before===$after,'admin clear preserves code and enabled state');
exit($failed?1:0);
