<?php
if (PHP_SAPI !== 'cli') { exit; }
require __DIR__ . '/run.php';
$start = $checks;
$GLOBALS['asset_registry'] = array('existing-ishi-icons' => (object) array('src' => 'https://cdn.example.test/icons/css/ishi_custom_icons.css?ver=1'));
$GLOBALS['asset_upload_root'] = sys_get_temp_dir() . '/ishi-font-test-' . uniqid();
$GLOBALS['styles'] = array();
$GLOBALS['scripts'] = array();
function wp_styles() { return (object) array('registered' => $GLOBALS['asset_registry']); }
function wp_enqueue_style($handle, $src = '', $deps = array(), $ver = false) { $GLOBALS['styles'][$handle] = compact('src', 'deps', 'ver'); }
function wp_enqueue_script($handle, $src, $deps, $ver, $footer) { $GLOBALS['scripts'][$handle] = compact('src', 'deps', 'ver', 'footer'); }
function wp_upload_dir() { return array('error' => false, 'basedir' => $GLOBALS['asset_upload_root'], 'baseurl' => 'https://example.test/uploads'); }
LatePoint_Dashboard_Extender::enqueue_styles();
check(isset($GLOBALS['styles']['existing-ishi-icons']) && $GLOBALS['styles']['existing-ishi-icons']['src'] === '', 'Reuse registered stylesheet, including CDN URLs');
check(!isset($GLOBALS['styles']['ishi-dashboard-custom-icons']), 'Registered font is not enqueued twice');
check(in_array('existing-ishi-icons', $GLOBALS['styles']['ishi-customer-dashboard']['deps'], true), 'Dashboard styles follow font definitions');
check($GLOBALS['scripts']['ishi-customer-dashboard']['deps'] === array('latepoint-main-front'), 'Layout script ordered after native frontend');
check($GLOBALS['scripts']['ishi-customer-dashboard']['footer'] === true, 'Layout enhancement loads in footer');
check(strpos(render_dashboard(dashboard()), 'ishi-dashboard-no-icons') === false, 'Available font renders icon controls');
$GLOBALS['asset_registry'] = array();
LatePoint_Dashboard_Extender::enqueue_styles();
check(strpos(render_dashboard(dashboard()), 'ishi-dashboard-no-icons') !== false, 'Missing stylesheet enables readable text fallback');
$relative = '/elementor/custom-icons/ishi_custom_icons-1/css/ishi_custom_icons.css';
$file = $GLOBALS['asset_upload_root'] . $relative;
mkdir(dirname($file), 0777, true);
file_put_contents($file, '/* Fixture only: font files are not copied. */');
try {
    $GLOBALS['styles'] = array();
    LatePoint_Dashboard_Extender::enqueue_styles();
    check($GLOBALS['styles']['ishi-dashboard-custom-icons']['src'] === 'https://example.test/uploads' . $relative, 'Verified uploads path enqueued on non-Elementor pages');
    check(in_array('ishi-dashboard-custom-icons', $GLOBALS['styles']['ishi-customer-dashboard']['deps'], true), 'Fallback file is a style dependency');
    check(strpos(render_dashboard(dashboard()), 'ishi-dashboard-no-icons') === false, 'Uploaded file enables icon controls');
} finally {
    unlink($file);
    $directory = dirname($file);
    while ($directory !== dirname($GLOBALS['asset_upload_root'])) {
        rmdir($directory);
        $directory = dirname($directory);
    }
}
echo 'PASS: ' . ($checks - $start) . " asset checks\n";
