<?php
/** Exercise actual PHP shutdown fatals in isolated subprocesses. */
if (isset($argv[1])) {
    define('ABSPATH', __DIR__);
    define('WP_CONTENT_DIR', __DIR__ . '/nonexistent-content');
    $options=array();
    function get_option($n,$d=false) { global $options; return $options[$n]??$d; }
    function update_option($n,$v) { global $options; $options[$n]=$v; }
    function current_time() { return '2026-09-26 00:00:00'; }
    function __($s) { return $s; }
    function trailingslashit($s) { return rtrim($s,'/').'/'; }
    function is_wp_error($s) { return false; }
    require dirname(__DIR__).'/includes/class-code-repository.php';
    require dirname(__DIR__).'/includes/class-code-validator.php';
    require dirname(__DIR__).'/includes/class-executor.php';
    $code = $argv[1]==='internal' ? 'function cni_test_fatal_callback() { trigger_error("test fatal", E_USER_ERROR); }' : '// monitoring enabled';
    CniWorks\CniSiteFunctions\Code_Repository::save($code,true);
    CniWorks\CniSiteFunctions\Executor::maybe_execute();
    register_shutdown_function(function() { echo json_encode(CniWorks\CniSiteFunctions\Code_Repository::get_state()); });
    if ($argv[1]==='internal') { cni_test_fatal_callback(); }
    trigger_error('external test fatal',E_USER_ERROR);
}
$failed=0;
foreach (array('internal','external') as $mode) {
    $process=proc_open(array(PHP_BINARY,'-d','display_errors=0',__FILE__,$mode),array(0=>array('pipe','r'),1=>array('pipe','w'),2=>array('pipe','w')),$pipes);
    fclose($pipes[0]); $output=stream_get_contents($pipes[1]); fclose($pipes[1]);
    $stderr=stream_get_contents($pipes[2]); fclose($pipes[2]); $status=proc_close($process);
    $state=json_decode($output,true); $error=$state['execution_error']??array();
    $internal=$mode==='internal';
    $ok=$status!==0 && isset($error['count']) && $error['count']===1 && $error['attributable']===$internal && $error['auto_disabled']===$internal && $state['enabled']===!$internal && $error['type']===($internal?'fatal_error':'unconfirmed_fatal_error');
    echo ($ok?'PASS: ':'FAIL: ').$mode." real fatal records and preserves auto-stop policy\n";
    if (!$ok) { $failed++; echo $output.$stderr; }
}
exit($failed?1:0);
