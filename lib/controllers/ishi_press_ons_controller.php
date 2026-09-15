<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('OsController')) {
    return;
}

/*
 * The dashboard extender creates the Addresses tab in the main plugin file.
 * At priority 11, after that composition has finished, replace the complete
 * contents of that tab with the canonical Ishi customer-addresses shortcode.
 * This keeps the shortcode owner as the single source of truth for address UI
 * and behavior while LatePoint continues to own the tab trigger/switching.
 */
add_filter('do_shortcode_tag', function($output, $tag, $attr, $m) {
    if ($tag !== 'latepoint_customer_dashboard' || !is_string($output) || $output === '' || !class_exists('DOMDocument')) {
        return $output;
    }

    if (!shortcode_exists('ishi_customer_addresses')) {
        return $output;
    }

    $addresses_output = do_shortcode('[ishi_customer_addresses]');

    if (!is_string($addresses_output)) {
        return $output;
    }

    $dom = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    $wrapped = '<!DOCTYPE html><html><body><div id="ishi-addresses-dashboard-root">' . $output . '</div></body></html>';

    if (!$dom->loadHTML('<?xml encoding="UTF-8">' . $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD)) {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        return $output;
    }

    $xpath = new DOMXPath($dom);
    $root = $dom->getElementById('ishi-addresses-dashboard-root');
    $addresses_tab = $root ? $xpath->query(
        './/*[contains(concat(" ", normalize-space(@class), " "), " tab-content-ishi-customer-addresses ")]',
        $root
    )->item(0) : null;

    if (!$addresses_tab) {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        return $output;
    }

    while ($addresses_tab->firstChild) {
        $addresses_tab->removeChild($addresses_tab->firstChild);
    }

    if ($addresses_output !== '') {
        $fragment_dom = new DOMDocument('1.0', 'UTF-8');
        $fragment_wrapped = '<!DOCTYPE html><html><body><div id="ishi-addresses-shortcode-root">' . $addresses_output . '</div></body></html>';

        if ($fragment_dom->loadHTML('<?xml encoding="UTF-8">' . $fragment_wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD)) {
            $fragment_root = $fragment_dom->getElementById('ishi-addresses-shortcode-root');

            if ($fragment_root) {
                foreach (iterator_to_array($fragment_root->childNodes) as $child) {
                    $addresses_tab->appendChild($dom->importNode($child, true));
                }
            }
        }
    }

    $result = '';
    if ($root) {
        foreach ($root->childNodes as $child) {
            $result .= $dom->saveHTML($child);
        }
    }

    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    return $result !== '' ? $result : $output;
}, 11, 4);

if (!class_exists('OsIshiPressOnsController')) {

    class OsIshiPressOnsController extends OsController {

        public function __construct() {
            parent::__construct();

            $this->action_access['customer'] = array_merge(
                $this->action_access['customer'],
                array('view_order_in_lightbox')
            );

            $this->views_folder = LATEPOINT_DASHBOARD_EXTENDER_PATH . 'lib/views/';
        }

        public function view_order_in_lightbox() {
            $order_id = isset($this->params['order_id']) ? absint($this->params['order_id']) : 0;

            if (!$order_id || !is_user_logged_in()) {
                $this->send_json(array(
                    'status'  => LATEPOINT_STATUS_ERROR,
                    'message' => __('Not Allowed', 'latepoint'),
                ));
            }

            $order = wc_get_order($order_id);

            if (!$order instanceof WC_Order) {
                $this->send_json(array(
                    'status'  => LATEPOINT_STATUS_ERROR,
                    'message' => __('Order Not Found', 'latepoint'),
                ));
            }

            if ((int) $order->get_customer_id() !== (int) get_current_user_id()) {
                $this->send_json(array(
                    'status'  => LATEPOINT_STATUS_ERROR,
                    'message' => __('Not Allowed', 'latepoint'),
                ));
            }

            if (!$this->is_customer_viewable_order($order)) {
                $this->send_json(array(
                    'status'  => LATEPOINT_STATUS_ERROR,
                    'message' => __('Not Allowed', 'latepoint'),
                ));
            }

            /*
             * A pure LatePoint-category order belongs in History, not Press-Ons.
             * Re-check this at request time so the endpoint cannot be used to
             * bypass the Press-Ons filtering performed on the dashboard page.
             */
            if ($this->is_pure_latepoint_order($order)) {
                $this->send_json(array(
                    'status'  => LATEPOINT_STATUS_ERROR,
                    'message' => __('Not Allowed', 'latepoint'),
                ));
            }

            $this->vars['order'] = $order;

            $this->format_render('view_order_in_lightbox');
        }

        private function is_customer_viewable_order($order) {
            if (!function_exists('wc_get_order_types') || !function_exists('wc_get_order_statuses')) {
                return false;
            }

            return in_array($order->get_type(), wc_get_order_types('view-orders'), true)
                && in_array('wc-' . $order->get_status(), array_keys(wc_get_order_statuses()), true);
        }

        private function is_pure_latepoint_order($order) {
            $line_items = $order->get_items('line_item');

            if (empty($line_items)) {
                return false;
            }

            $has_product_line_item = false;

            foreach ($line_items as $item) {
                $product = $item->get_product();

                if (!$product) {
                    return false;
                }

                $category_product_id = $product->get_id();

                if ($product->is_type('variation') && $product->get_parent_id()) {
                    $category_product_id = $product->get_parent_id();
                }

                $has_product_line_item = true;

                if (!has_term('latepoint', 'product_cat', $category_product_id)) {
                    return false;
                }
            }

            return $has_product_line_item;
        }
    }
}
