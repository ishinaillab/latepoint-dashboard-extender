<?php
/**
 * Plugin Name: LatePoint Dashboard Extender
 * Description: Extends the native LatePoint Customer Dashboard through server-side shortcode output composition.
 * Version: 0.10.28
 * Author: Ishi
 */

if (!defined('ABSPATH')) {
    exit;
}

define('LATEPOINT_DASHBOARD_EXTENDER_VERSION', '0.10.28');
define('LATEPOINT_DASHBOARD_EXTENDER_PATH', plugin_dir_path(__FILE__));
define('LATEPOINT_DASHBOARD_EXTENDER_URL', plugin_dir_url(__FILE__));

final class LatePoint_Dashboard_Extender {

    private static $processing = false;

    public static function init() {
        add_filter('do_shortcode_tag', array(__CLASS__, 'filter_customer_dashboard_output'), 10, 4);
        add_action('latepoint_init', array(__CLASS__, 'load_latepoint_extension'), 20);
        add_action('latepoint_wp_enqueue_scripts', array(__CLASS__, 'enqueue_styles'));
    }

    public static function load_latepoint_extension() {
        $controller_file = LATEPOINT_DASHBOARD_EXTENDER_PATH . 'lib/controllers/ishi_press_ons_controller.php';

        if (file_exists($controller_file)) {
            require_once $controller_file;
        }
    }

    public static function enqueue_styles() {
        wp_enqueue_style(
            'latepoint-dashboard-extender',
            LATEPOINT_DASHBOARD_EXTENDER_URL . 'public/stylesheets/press-ons.css',
            array(),
            LATEPOINT_DASHBOARD_EXTENDER_VERSION
        );
    }

    public static function filter_customer_dashboard_output($output, $tag, $attr, $m) {
        if ($tag !== 'latepoint_customer_dashboard' || self::$processing) {
            return $output;
        }

        self::$processing = true;

        try {
            $output = self::rename_orders_tab($output);
            $output = self::add_press_ons_tab($output);
            $output = self::add_addresses_tab($output);
        } finally {
            self::$processing = false;
        }

        return $output;
    }

    private static function rename_orders_tab($html) {
        if (!is_string($html) || $html === '' || !class_exists('DOMDocument')) {
            return $html;
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $wrapped = '<!DOCTYPE html><html><body><div id="latepoint-dashboard-extender-root">' . $html . '</div></body></html>';

        if (!$dom->loadHTML('<?xml encoding="UTF-8">' . $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD)) {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            return $html;
        }

        $xpath = new DOMXPath($dom);
        $triggers = $xpath->query(
            '//*[@id="latepoint-dashboard-extender-root"]//*[contains(concat(" ", normalize-space(@class), " "), " latepoint-tab-trigger ")]'
        );

        if ($triggers !== false) {
            foreach ($triggers as $trigger) {
                if (strpos($trigger->getAttribute('data-tab-target'), 'tab-content-customer-orders') === false) {
                    continue;
                }

                $nodes = $xpath->query('.//text()', $trigger);
                if ($nodes !== false) {
                    foreach ($nodes as $node) {
                        if (preg_match('/^\s*Orders\s*$/i', $node->nodeValue)) {
                            $node->nodeValue = preg_replace('/\bOrders\b/i', 'History', $node->nodeValue, 1);
                            break 2;
                        }
                    }
                }
            }
        }

        $result = self::serialize_root($dom);

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $result;
    }

    private static function add_press_ons_tab($html) {
        if (!is_string($html) || $html === '' || !class_exists('DOMDocument')) {
            return $html;
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $wrapped = '<!DOCTYPE html><html><body><div id="latepoint-dashboard-extender-root">' . $html . '</div></body></html>';

        if (!$dom->loadHTML('<?xml encoding="UTF-8">' . $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD)) {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            return $html;
        }

        $xpath = new DOMXPath($dom);
        $root = $dom->getElementById('latepoint-dashboard-extender-root');

        if (!$root || $xpath->query('.//*[@data-ishi-dashboard-tab="press-ons"]')->length > 0) {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            return $html;
        }

        $trigger_container = $xpath->query(
            './/*[contains(concat(" ", normalize-space(@class), " "), " latepoint-tab-triggers ")]'
        )->item(0);

        if (!$trigger_container) {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            return $html;
        }

        $history_trigger = null;
        $triggers = $xpath->query(
            './/*[contains(concat(" ", normalize-space(@class), " "), " latepoint-tab-trigger ")]',
            $trigger_container
        );

        foreach ($triggers as $trigger) {
            if (strpos($trigger->getAttribute('data-tab-target'), 'tab-content-customer-orders') !== false) {
                $history_trigger = $trigger;
                break;
            }
        }

        if (!$history_trigger) {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            return $html;
        }

        $press_trigger = $dom->createElement('a');
        $press_trigger->setAttribute('href', '#');
        $press_trigger->setAttribute('class', 'latepoint-tab-trigger');
        $press_trigger->setAttribute('data-tab-target', '.tab-content-ishi-customer-press-ons');
        $press_trigger->setAttribute('data-ishi-dashboard-tab', 'press-ons');

        /*
         * This deliberately uses the same LatePoint trigger structure as the
         * native dashboard. Native LatePoint JS continues to own tab switching.
         */
        $press_trigger->appendChild($dom->createTextNode('Press-Ons'));

        if ($history_trigger->nextSibling) {
            $trigger_container->insertBefore($press_trigger, $history_trigger->nextSibling);
        } else {
            $trigger_container->appendChild($press_trigger);
        }

        $press_content = $dom->createElement('div');
        $press_content->setAttribute('class', 'latepoint-tab-content tab-content-ishi-customer-press-ons');
        $press_content->setAttribute('data-ishi-dashboard-tab', 'press-ons');

        $inner = $dom->createElement('div');
        $inner->setAttribute('class', 'ishi-press-ons-shell');

        $orders = self::get_press_ons_orders();

        if (empty($orders)) {
            $empty = $dom->createElement('div');
            $empty->setAttribute('class', 'ishi-press-ons-empty');
            $empty->appendChild($dom->createTextNode('No Press-On orders found.'));
            $inner->appendChild($empty);
        } else {
            $list = $dom->createElement('div');
            $list->setAttribute('class', 'customer-orders-tiles ishi-press-ons-order-list');

            foreach ($orders as $order) {
                $card = self::create_order_card($dom, $order);
                if ($card) {
                    $list->appendChild($card);
                }
            }

            $inner->appendChild($list);
        }

        $press_content->appendChild($inner);

        $orders_content = $xpath->query(
            './/*[contains(concat(" ", normalize-space(@class), " "), " latepoint-tab-content ")][contains(concat(" ", normalize-space(@class), " "), " tab-content-customer-orders ")]'
        )->item(0);

        if ($orders_content && $orders_content->parentNode) {
            if ($orders_content->nextSibling) {
                $orders_content->parentNode->insertBefore($press_content, $orders_content->nextSibling);
            } else {
                $orders_content->parentNode->appendChild($press_content);
            }
        } else {
            $root->appendChild($press_content);
        }

        $result = self::serialize_root($dom);

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $result;
    }


    private static function add_addresses_tab($html) {
        if (!is_string($html) || $html === '' || !class_exists('DOMDocument') || !function_exists('wc_get_account_endpoint_url')) {
            return $html;
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $wrapped = '<!DOCTYPE html><html><body><div id="latepoint-dashboard-extender-root">' . $html . '</div></body></html>';

        if (!$dom->loadHTML('<?xml encoding="UTF-8">' . $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD)) {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            return $html;
        }

        $xpath = new DOMXPath($dom);
        $root = $dom->getElementById('latepoint-dashboard-extender-root');
        $trigger_container = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " latepoint-tab-triggers ")]', $root)->item(0);
        if (!$root || !$trigger_container || $xpath->query('.//*[@data-ishi-dashboard-tab="addresses"]', $root)->length > 0) {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            return $html;
        }

        $history_trigger = null;
        foreach ($xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " latepoint-tab-trigger ")]', $trigger_container) as $trigger) {
            if (strpos($trigger->getAttribute('data-tab-target'), 'tab-content-customer-orders') !== false) {
                $history_trigger = $trigger;
                break;
            }
        }
        if (!$history_trigger) {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            return $html;
        }

        $trigger = $dom->createElement('a');
        $trigger->setAttribute('href', '#');
        $trigger->setAttribute('class', 'latepoint-tab-trigger');
        $trigger->setAttribute('data-tab-target', '.tab-content-ishi-customer-addresses');
        $trigger->setAttribute('data-ishi-dashboard-tab', 'addresses');
        $trigger->appendChild($dom->createTextNode('Addresses'));
        if ($history_trigger->nextSibling) $trigger_container->insertBefore($trigger, $history_trigger->nextSibling);
        else $trigger_container->appendChild($trigger);

        $content = $dom->createElement('div');
        $content->setAttribute('class', 'latepoint-tab-content tab-content-ishi-customer-addresses');
        $content->setAttribute('data-ishi-dashboard-tab', 'addresses');
        $inner = $dom->createElement('div');
        $inner->setAttribute('class', 'woocommerce');
        $account = $dom->createElement('div');
        $account->setAttribute('class', 'woocommerce-MyAccount-content');

        $notices = $dom->createElement('div');
        $notices->setAttribute('class', 'woocommerce-notices-wrapper');
        $account->appendChild($notices);
        $p = $dom->createElement('p');
        $p->appendChild($dom->createTextNode('The following addresses will be used on the checkout page by default.'));
        $account->appendChild($p);

        $columns = $dom->createElement('div');
        $columns->setAttribute('class', 'u-columns woocommerce-Addresses col2-set addresses');
        $customer = function_exists('WC') ? WC()->customer : null;
        $addresses = array(
            'billing' => array('title' => 'Billing address', 'edit' => 'Edit Billing address', 'column' => 'u-column1 col-1'),
            'shipping' => array('title' => 'Shipping address', 'edit' => 'Edit Shipping address', 'column' => 'u-column2 col-2'),
        );
        foreach ($addresses as $type => $data) {
            $column = $dom->createElement('div');
            $column->setAttribute('class', $data['column'] . ' woocommerce-Address');
            $header = $dom->createElement('header');
            $header->setAttribute('class', 'woocommerce-Address-title title');
            $h2 = $dom->createElement('h2');
            $h2->appendChild($dom->createTextNode($data['title']));
            $header->appendChild($h2);
            $edit = $dom->createElement('a');
            $edit->setAttribute('href', esc_url(wc_get_account_endpoint_url('edit-address') . '?address=' . $type));
            $edit->setAttribute('class', 'edit');
            $edit->appendChild($dom->createTextNode($data['edit']));
            $header->appendChild($edit);
            $column->appendChild($header);
            $address = $dom->createElement('address');
            if ($customer && function_exists('wc_get_account_formatted_address')) {
                $formatted = wc_get_account_formatted_address($type);
                if ($formatted !== '') {
                    $fragment = $dom->createDocumentFragment();
                    if (@$fragment->appendXML($formatted)) $address->appendChild($fragment);
                    else $address->appendChild($dom->createTextNode(wp_strip_all_tags($formatted)));
                }
            }
            $column->appendChild($address);
            $columns->appendChild($column);
        }
        $account->appendChild($columns);
        $inner->appendChild($account);
        $content->appendChild($inner);

        $orders_content = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " latepoint-tab-content ")][contains(concat(" ", normalize-space(@class), " "), " tab-content-customer-orders ")]', $root)->item(0);
        if ($orders_content && $orders_content->parentNode) {
            if ($orders_content->nextSibling) $orders_content->parentNode->insertBefore($content, $orders_content->nextSibling);
            else $orders_content->parentNode->appendChild($content);
        } else $root->appendChild($content);

        $result = self::serialize_root($dom);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        return $result;
    }

    private static function create_order_card($dom, $order) {
        if (!$order instanceof WC_Order) {
            return null;
        }

        $card = $dom->createElement('article');
        $card->setAttribute('class', 'customer-order status-' . sanitize_html_class($order->get_status()) . ' ishi-press-ons-order-card');
        $card->setAttribute('data-order-id', (string) absint($order->get_id()));

        $header = $dom->createElement('div');
        $header->setAttribute('class', 'ishi-press-ons-order-card-header');

        $number = $dom->createElement('div');
        $number->setAttribute('class', 'customer-order-confirmation ishi-press-ons-order-number');
        $number->appendChild($dom->createTextNode((string) $order->get_order_number()));
        $header->appendChild($number);

        $status = $dom->createElement('div');
        $status->setAttribute('class', 'ishi-press-ons-order-status');
        $status->appendChild($dom->createTextNode(wc_get_order_status_name($order->get_status())));
        $header->appendChild($status);

        $card->appendChild($header);

        $date = $order->get_date_created();
        if ($date) {
            $date_element = $dom->createElement('div');
            $date_element->setAttribute('class', 'customer-order-datetime ishi-press-ons-order-date');
            $date_element->appendChild($dom->createTextNode($date->date_i18n('M j, Y')));
            $card->appendChild($date_element);
        }

        $items = $order->get_items('line_item');
        $items_element = null;

        if (!empty($items)) {
            $items_element = $dom->createElement('div');
            $items_element->setAttribute('class', 'ishi-press-ons-order-items');

            foreach ($items as $item) {
                $item_row = $dom->createElement('div');
                $item_row->setAttribute('class', 'ishi-press-ons-order-item');

                $product = $item->get_product();
                if ($product) {
                    $image = self::create_product_image($dom, $product);
                    if ($image) {
                        $item_row->appendChild($image);
                    }
                }

                $details = $dom->createElement('div');
                $details->setAttribute('class', 'ishi-press-ons-order-item-details');

                $name = $dom->createElement('div');
                $name->setAttribute('class', 'ishi-press-ons-order-item-name');
                $name->appendChild($dom->createTextNode($item->get_name()));
                $details->appendChild($name);

                $meta = $dom->createElement('div');
                $meta->setAttribute('class', 'ishi-press-ons-order-item-meta');
                $meta->appendChild($dom->createTextNode('Qty: ' . absint($item->get_quantity())));
                $details->appendChild($meta);

                $item_row->appendChild($details);
                $items_element->appendChild($item_row);
            }

            $card->appendChild($items_element);
        }

        // Match the native History card's section heading between the date and items.
        // It is intentionally card-only; the Press-Ons modal keeps its own modal headings.
        $summary_heading = $dom->createElement('div');
        $summary_heading->setAttribute('class', 'summary-box-heading');
        $summary_heading_item = $dom->createElement('div');
        $summary_heading_item->setAttribute('class', 'sbh-item');
        $summary_heading_item->appendChild($dom->createTextNode('Product'));
        $summary_heading->appendChild($summary_heading_item);
        $summary_heading_line = $dom->createElement('div');
        $summary_heading_line->setAttribute('class', 'sbh-line');
        $summary_heading->appendChild($summary_heading_line);

        // The heading must sit immediately after the date and before the product list.
        if ($items_element && $items_element->parentNode === $card) {
            $card->insertBefore($summary_heading, $items_element);
        } else {
            $card->appendChild($summary_heading);
        }

        // Match the native History price-breakdown language. LatePoint's native
        // helper uses .spi-positive for deductions (green).
        self::append_order_price_breakdown($dom, $card, $order);

        $footer = $dom->createElement('div');
        $footer->setAttribute('class', 'customer-order-bottom-actions ishi-press-ons-order-card-footer');

        $button_wrapper = $dom->createElement('div');
        $button_wrapper->setAttribute('class', 'load-booking-summary-btn-w');

        /*
         * This is a native LatePoint route trigger. LatePoint's existing
         * AJAX/lightbox code handles the request and lightbox.
         */
        $button = $dom->createElement('a');
        $button->setAttribute('href', '#');
        $button->setAttribute('class', 'latepoint-btn latepoint-btn-primary latepoint-btn-outline latepoint-btn-sm ishi-press-ons-order-view');
        $button->setAttribute(
            'data-os-params',
            esc_attr(OsUtilHelper::build_os_params(array('order_id' => $order->get_id())))
        );
        $button->setAttribute(
            'data-os-action',
            esc_attr(OsRouterHelper::build_route_name('ishi_press_ons', 'view_order_in_lightbox'))
        );
        $button->setAttribute('data-os-output-target', 'lightbox');
        $button->setAttribute('data-os-lightbox-classes', 'width-500 customer-dashboard-order-summary-lightbox ishi-press-ons-order-lightbox');
        $icon = $dom->createElement('i');
        $icon->setAttribute('class', 'latepoint-icon latepoint-icon-list');
        $button->appendChild($icon);
        $button->appendChild($dom->createTextNode(' '));
        $button_label = $dom->createElement('span');
        $button_label->appendChild($dom->createTextNode('View Order'));
        $button->appendChild($button_label);

        $button_wrapper->appendChild($button);
        $footer->appendChild($button_wrapper);
        $card->appendChild($footer);

        return $card;
    }

    private static function append_order_price_breakdown($dom, $card, $order) {
        self::append_price_row($dom, $card, 'Subtotal', (float) $order->get_subtotal(), $order, 'strong');

        $coupon_items = $order->get_items('coupon');
        $rendered_coupon_discount = false;

        foreach ($coupon_items as $coupon_item) {
            $discount = (float) $coupon_item->get_discount();
            if ($discount == 0.0) {
                continue;
            }

            $coupon_code = $coupon_item->get_code();
            $label = $coupon_code ? 'Coupon' : 'Discount';
            $note = $coupon_code ? ' (' . $coupon_code . ')' : '';
            self::append_price_row($dom, $card, $label . $note, -abs($discount), $order, 'subtracted');
            $rendered_coupon_discount = true;
        }

        if (!$rendered_coupon_discount) {
            $discount_total = (float) $order->get_discount_total();
            if ($discount_total != 0.0) {
                self::append_price_row($dom, $card, 'Discount', -abs($discount_total), $order, 'subtracted');
            }
        }

        $shipping_total = (float) $order->get_shipping_total();
        if ($shipping_total != 0.0) {
            self::append_price_row($dom, $card, 'Shipping', $shipping_total, $order, 'adjustment');
        }

        foreach ($order->get_fees() as $fee) {
            $fee_total = (float) $fee->get_total();
            if ($fee_total == 0.0) {
                continue;
            }
            self::append_price_row($dom, $card, $fee->get_name(), $fee_total, $order, 'adjustment');
        }

        $tax_total = (float) $order->get_total_tax();
        if ($tax_total != 0.0) {
            self::append_price_row($dom, $card, 'Taxes', $tax_total, $order, 'adjustment');
        }

        self::append_price_row($dom, $card, 'Total', (float) $order->get_total(), $order, 'total');
    }

    private static function append_price_row($dom, $card, $label, $amount, $order, $kind = '') {
        $class = 'summary-price-item-w';
        if ($kind === 'strong') {
            $class .= ' spi-strong';
        } elseif ($kind === 'subtracted') {
            // Exact native LatePoint deduction class/color.
            $class .= ' spi-positive';
        } elseif ($kind === 'total') {
            $class .= ' spi-total ishi-press-ons-order-total';
        } else {
        }

        $row = $dom->createElement('div');
        $row->setAttribute('class', $class);

        $name = $dom->createElement('div');
        $name->setAttribute('class', 'spi-name');
        $name->appendChild($dom->createTextNode($label));
        $row->appendChild($name);

        $price = $dom->createElement('div');
        $price->setAttribute('class', 'spi-price');

        if ($kind === 'subtracted' || ($kind === 'adjustment' && $amount < 0)) {
            // Native LatePoint prepends the minus sign to the formatted amount
            // rather than relying on the currency formatter's negative layout.
            $price->appendChild($dom->createTextNode('-'));
            self::append_woocommerce_price_html(
                $dom,
                $price,
                wc_price(abs($amount), array('currency' => $order->get_currency()))
            );
        } else {
            self::append_woocommerce_price_html(
                $dom,
                $price,
                wc_price(abs($amount), array('currency' => $order->get_currency()))
            );
        }

        $row->appendChild($price);
        $card->appendChild($row);
    }

    private static function append_woocommerce_price_html($dom, $parent, $html) {
        $fragment = $dom->createDocumentFragment();

        if (@$fragment->appendXML($html)) {
            $parent->appendChild($fragment);
            return;
        }

        $parent->appendChild($dom->createTextNode(wp_strip_all_tags($html)));
    }

    private static function create_product_image($dom, $product) {
        $image_id = $product->get_image_id();

        if (!$image_id) {
            return null;
        }

        $src = wp_get_attachment_image_url($image_id, 'woocommerce_thumbnail');

        if (!$src) {
            return null;
        }

        $image = $dom->createElement('img');
        $image->setAttribute('class', 'ishi-press-ons-order-item-image');
        $image->setAttribute('src', esc_url($src));
        $image->setAttribute('alt', esc_attr($product->get_name()));
        $image->setAttribute('loading', 'lazy');

        return $image;
    }

    private static function get_press_ons_orders() {
        if (!function_exists('wc_get_orders') || !function_exists('wc_get_order_types') || !function_exists('wc_get_order_statuses')) {
            return array();
        }

        if (!is_user_logged_in()) {
            return array();
        }

        $customer_id = get_current_user_id();

        if (!$customer_id) {
            return array();
        }

        $orders = wc_get_orders(
            array(
                'customer_id' => $customer_id,
                'type'        => wc_get_order_types('view-orders'),
                'status'      => array_keys(wc_get_order_statuses()),
                'limit'       => -1,
                'orderby'     => 'date',
                'order'       => 'DESC',
                'return'      => 'objects',
            )
        );

        if (!is_array($orders)) {
            return array();
        }

        $qualifying = array();

        foreach ($orders as $order) {
            if (!$order instanceof WC_Order) {
                continue;
            }

            if (self::is_pure_latepoint_order($order)) {
                continue;
            }

            $qualifying[$order->get_id()] = $order;
        }

        return $qualifying;
    }

    private static function is_pure_latepoint_order($order) {
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

    private static function serialize_root($dom) {
        $root = $dom->getElementById('latepoint-dashboard-extender-root');

        if (!$root) {
            return '';
        }

        $result = '';

        foreach ($root->childNodes as $child) {
            $result .= $dom->saveHTML($child);
        }

        return $result;
    }
}

LatePoint_Dashboard_Extender::init();
