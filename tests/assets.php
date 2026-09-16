<?php
if (PHP_SAPI !== 'cli') { exit; }
require __DIR__ . '/run.php';
$start = $checks;
$GLOBALS['asset_registered'] = true;
$GLOBALS['styles'] = array();
$GLOBALS['scripts'] = array();
function wp_style_is($handle, $state) { return $GLOBALS['asset_registered']; }
function wp_enqueue_style($handle, $src = '', $deps = array(), $ver = false) { $GLOBALS['styles'][$handle] = compact('src', 'deps', 'ver'); }
function wp_enqueue_script($handle, $src, $deps, $ver, $footer) { $GLOBALS['scripts'][$handle] = compact('src', 'deps', 'ver', 'footer'); }
function wp_upload_dir() { return array('error' => false, 'basedir' => __DIR__ . '/missing-font-fixture', 'baseurl' => 'https://example.test/uploads'); }
LatePoint_Dashboard_Extender::enqueue_styles();
check(!isset($GLOBALS['styles']['elementor-icons-nails_skin_elementor_icons']), 'No redundant icon font enqueue');
check(isset($GLOBALS['styles']['ishi-customer-dashboard']), 'SVG presentation stylesheet enqueued');
check($GLOBALS['scripts']['ishi-customer-dashboard']['deps'] === array('latepoint-main-front'), 'Layout script ordered after native frontend');
check($GLOBALS['scripts']['ishi-customer-dashboard']['footer'] === true, 'Layout enhancement loads in footer');
check(strpos(render_dashboard(dashboard()), 'ishi-dashboard-no-icons') === false, 'SVG controls do not need a missing-font fallback');
echo 'PASS: ' . ($checks - $start) . " asset checks\n";
