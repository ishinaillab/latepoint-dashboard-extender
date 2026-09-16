<?php
/**
 * Plugin Name: LatePoint Dashboard Extender
 * Description: Extends the native LatePoint Customer Dashboard through native tab hooks and server-side navigation customization.
 * Version: 0.11.0
 * Text Domain: latepoint-dashboard-extender
 * Author: Ishi
 */

if (!defined('ABSPATH')) {
    exit;
}

define('LATEPOINT_DASHBOARD_EXTENDER_VERSION', '0.11.0');
define('LATEPOINT_DASHBOARD_EXTENDER_PATH', plugin_dir_path(__FILE__));
define('LATEPOINT_DASHBOARD_EXTENDER_URL', plugin_dir_url(__FILE__));
require_once LATEPOINT_DASHBOARD_EXTENDER_PATH . 'lib/dashboard-layout.php';

final class LatePoint_Dashboard_Extender {

    private static $rendering_custom_tabs = false;
    private static $dashboard_tab_frames = array();
    private static $icons_available = false;

    public static function init() {
        add_filter('do_shortcode_tag', array(__CLASS__, 'filter_customer_dashboard_output'), 20, 4);
        add_action('latepoint_customer_dashboard_after_tabs', array(__CLASS__, 'output_custom_tab_triggers'), 20, 1);
        add_action('latepoint_customer_dashboard_after_tab_contents', array(__CLASS__, 'output_custom_tab_contents'), 20, 1);
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
        // This is the existing uploaded font, not an Elementor runtime dependency.
        $icon_handle = 'elementor-icons-nails_skin_elementor_icons';
        if (wp_style_is($icon_handle, 'registered')) {
            wp_enqueue_style($icon_handle);
            self::$icons_available = true;
        } else {
            $uploads = wp_upload_dir();
            $relative = '/elementor/custom-icons/nails_skin_elementor_icons/css/nails_skin_elementor_icons.css';
            if (empty($uploads['error']) && is_readable($uploads['basedir'] . $relative)) {
                wp_enqueue_style($icon_handle, $uploads['baseurl'] . $relative, array(), (string) filemtime($uploads['basedir'] . $relative));
                self::$icons_available = true;
            }
        }
        wp_enqueue_style('ishi-customer-dashboard', LATEPOINT_DASHBOARD_EXTENDER_URL . 'public/stylesheets/customer-dashboard.css', array('latepoint-main-front', 'latepoint-dashboard-extender'), LATEPOINT_DASHBOARD_EXTENDER_VERSION);
        wp_enqueue_script('ishi-customer-dashboard', LATEPOINT_DASHBOARD_EXTENDER_URL . 'public/javascripts/customer-dashboard.js', array('latepoint-main-front'), LATEPOINT_DASHBOARD_EXTENDER_VERSION, true);
    }

    public static function filter_customer_dashboard_output($output, $tag, $attr, $m) {
        return $tag === 'latepoint_customer_dashboard' ? self::transform_customer_dashboard_html($output) : $output;
    }

    /** Explicit adapter for custom renderers. Pass native dashboard HTML before JSON encoding. */
    public static function transform_customer_dashboard_html($html) {
        return Ishi_Customer_Dashboard_Layout::transform($html, self::requested_press_ons_page() > 0, self::$icons_available);
    }

    /** Backward-compatible adapter name; the layout is now hierarchical. */
    public static function order_customer_dashboard_tabs($html, $tag, $attr, $m) {
        return self::filter_customer_dashboard_output($html, $tag, $attr, $m);
    }

    /**
     * LatePoint invokes the trigger hook before the content hook. Keep a frame
     * per render (not per customer) so repeated dashboards get independent tabs.
     */
    public static function output_custom_tab_triggers($customer) {
        $frame = count(self::$dashboard_tab_frames);
        self::$dashboard_tab_frames[] = array('customer' => $customer, 'tabs' => array());
        if (self::$rendering_custom_tabs || !class_exists('DOMDocument')) {
            return;
        }

        self::$rendering_custom_tabs = true;
        try {
            $tabs = array(
                'press-ons' => array(
                    'label' => 'Press-Ons',
                    'target' => '.tab-content-ishi-customer-press-ons',
                    'content' => self::render_press_ons_content(),
                ),
            );
            $addresses = self::render_addresses_content();
            if ($addresses !== null) {
                $tabs['addresses'] = array(
                    'label' => 'Addresses',
                    'target' => '.tab-content-ishi-customer-addresses',
                    'content' => $addresses,
                );
            }
            self::$dashboard_tab_frames[$frame]['tabs'] = $tabs;

            // Only serialize our generated links; native markup stays owned by LatePoint.
            $dom = new DOMDocument('1.0', 'UTF-8');
            foreach ($tabs as $id => $tab) {
                $trigger = $dom->createElement('a');
                $trigger->setAttribute('href', '#');
                $trigger->setAttribute('class', 'latepoint-tab-trigger');
                $trigger->setAttribute('data-tab-target', $tab['target']);
                $trigger->setAttribute('data-ishi-dashboard-tab', $id);
                $trigger->appendChild($dom->createTextNode($tab['label']));
                echo $dom->saveHTML($trigger);
            }
        } catch (Throwable $error) {
            // A failed renderer must not leave a frame for a later dashboard.
            array_splice(self::$dashboard_tab_frames, $frame);
            throw $error;
        } finally {
            self::$rendering_custom_tabs = false;
        }
    }

    public static function output_custom_tab_contents($customer) {
        if (empty(self::$dashboard_tab_frames)) {
            return;
        }
        $frame = end(self::$dashboard_tab_frames);
        if ($frame['customer'] !== $customer) {
            return;
        }
        array_pop(self::$dashboard_tab_frames);
        foreach ($frame['tabs'] as $tab) {
            // Generated by our renderer or WordPress's registered Addresses shortcode.
            echo $tab['content'];
        }
    }

    private static function render_press_ons_content() {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $content = $dom->createElement('div');
        $content->setAttribute('class', 'latepoint-tab-content tab-content-ishi-customer-press-ons');
        $content->setAttribute('data-ishi-dashboard-tab', 'press-ons');
        $inner = $dom->createElement('div');
        $inner->setAttribute('class', 'ishi-press-ons-shell');
        $page = self::get_press_ons_orders();
        if (empty($page['orders'])) {
            $empty = $dom->createElement('div');
            $empty->setAttribute('class', 'ishi-press-ons-empty');
            $empty->appendChild($dom->createTextNode('No Press-On orders found.'));
            $inner->appendChild($empty);
        } else {
            $list = $dom->createElement('div');
            $list->setAttribute('class', 'customer-orders-tiles ishi-press-ons-order-list');
            foreach ($page['orders'] as $order) {
                $card = self::create_order_card($dom, $order);
                if ($card) {
                    $list->appendChild($card);
                }
            }
            $inner->appendChild($list);
        }
        self::append_press_ons_pagination($dom, $inner, $page);
        $content->appendChild($inner);
        return $dom->saveHTML($content);
    }

    private static function render_addresses_content() {
        if (shortcode_exists('ishi_customer_addresses')) {
            $html = do_shortcode('[ishi_customer_addresses]');
            if (!is_string($html)) {
                return null;
            }
            // Use the shortcode's returned HTML once, including intentional empty output.
            // No parser or second renderer is needed to insert it through a native hook.
            return '<div class="latepoint-tab-content tab-content-ishi-customer-addresses" data-ishi-dashboard-tab="addresses">' . $html . '</div>';
        }
        if (!function_exists('wc_get_account_endpoint_url')) {
            return null;
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $content = $dom->createElement('div');
        $content->setAttribute('class', 'latepoint-tab-content tab-content-ishi-customer-addresses');
        $content->setAttribute('data-ishi-dashboard-tab', 'addresses');
        self::render_addresses_fallback($dom, $content);
        return $dom->saveHTML($content);
    }

    private static function render_addresses_fallback($dom, $content) {
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

    private static function requested_press_ons_page() {
        // Read-only navigation, not an action; no nonce is required.
        if (!isset($_GET['ishi_press_ons_page']) || !is_scalar($_GET['ishi_press_ons_page'])) {
            return 0;
        }
        $page = filter_var(wp_unslash($_GET['ishi_press_ons_page']), FILTER_VALIDATE_INT, array(
            'options' => array('min_range' => 1),
        ));
        return $page === false ? 0 : $page;
    }

    private static function get_press_ons_orders() {
        $requested_page = max(1, self::requested_press_ons_page());
        $result = array('orders' => array(), 'page' => 1, 'has_next' => false);
        if (!function_exists('wc_get_orders') || !function_exists('wc_get_order_types') || !function_exists('wc_get_order_statuses') || !is_user_logged_in()) {
            return $result;
        }

        $customer_id = get_current_user_id();
        if (!$customer_id) {
            return $result;
        }

        // WooCommerce account/orders uses WC_Order_Query's posts_per_page default.
        // Read only the positive limit override; never import another customer scope.
        $default_limit = max(1, (int) get_option('posts_per_page', 10));
        $account_args = apply_filters('woocommerce_my_account_my_orders_query', array(
            'customer' => $customer_id,
            'page' => $requested_page,
            'paginate' => true,
        ));
        $limit = is_array($account_args) && isset($account_args['limit'])
            ? filter_var($account_args['limit'], FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)))
            : false;
        $per_page = $limit === false ? $default_limit : $limit;

        // Filter eligibility before counting a displayed page. Retain only one
        // page of orders and stop at the first eligible order on the next page.
        // Product-category predicates cannot be expressed portably in WC_Order_Query.
        $batch_size = 50;
        $offset = 0;
        do {
            $orders = wc_get_orders(array(
                'customer_id' => $customer_id,
                'type' => wc_get_order_types('view-orders'),
                'status' => array_keys(wc_get_order_statuses()),
                'limit' => $batch_size,
                'offset' => $offset,
                'paginate' => false,
                'orderby' => array('date' => 'DESC', 'ID' => 'DESC'),
                'order' => 'DESC',
                'return' => 'objects',
            ));
            if (!is_array($orders)) {
                break;
            }
            foreach ($orders as $order) {
                if (!self::is_press_ons_order_for_customer($order, $customer_id)) {
                    continue;
                }
                if (count($result['orders']) === $per_page) {
                    if ($result['page'] === $requested_page) {
                        $result['has_next'] = true;
                        return $result;
                    }
                    $result['page']++;
                    $result['orders'] = array();
                }
                $result['orders'][$order->get_id()] = $order;
            }
            $offset += $batch_size;
        } while (count($orders) === $batch_size);

        // Requests beyond the last page show the last nonempty eligible page.
        return $result;
    }

    private static function append_press_ons_pagination($dom, $parent, $page) {
        if ($page['page'] === 1 && !$page['has_next']) {
            return;
        }
        $nav = $dom->createElement('nav');
        $nav->setAttribute('class', 'ishi-press-ons-pagination');
        $nav->setAttribute('aria-label', __('Press-Ons order pages', 'latepoint-dashboard-extender'));
        if ($page['page'] > 1) {
            self::append_press_ons_page_link($dom, $nav, $page['page'] - 1, __('Previous', 'latepoint-dashboard-extender'), 'prev');
        }
        $label = $dom->createElement('span');
        $label->setAttribute('aria-current', 'page');
        /* translators: %d: current Press-Ons page number. */
        $label->appendChild($dom->createTextNode(sprintf(__('Page %d', 'latepoint-dashboard-extender'), $page['page'])));
        $nav->appendChild($label);
        if ($page['has_next']) {
            self::append_press_ons_page_link($dom, $nav, $page['page'] + 1, __('Next', 'latepoint-dashboard-extender'), 'next');
        }
        $parent->appendChild($nav);
    }

    private static function append_press_ons_page_link($dom, $nav, $page, $label, $rel) {
        $link = $dom->createElement('a');
        // Keep the dashboard URL and unrelated query parameters, including page_id.
        // DOM serialization performs HTML attribute escaping.
        $link->setAttribute('href', esc_url_raw(add_query_arg('ishi_press_ons_page', $page)));
        $link->setAttribute('class', 'latepoint-btn latepoint-btn-primary latepoint-btn-outline latepoint-btn-sm');
        $link->setAttribute('rel', $rel);
        $link->appendChild($dom->createTextNode($label));
        $nav->appendChild($link);
    }

    /**
     * Shared by the paginated list and lightbox. Always re-check ownership and
     * visibility at request time, even when the query already restricts them.
     */
    public static function is_press_ons_order_for_customer($order, $customer_id) {
        if (!$order instanceof WC_Order || (int) $customer_id <= 0
            || (int) $order->get_customer_id() !== (int) $customer_id
            || !function_exists('wc_get_order_types') || !function_exists('wc_get_order_statuses')) {
            return false;
        }
        return in_array($order->get_type(), wc_get_order_types('view-orders'), true)
            && in_array('wc-' . $order->get_status(), array_keys(wc_get_order_statuses()), true)
            && !self::is_pure_latepoint_order($order);
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

}

LatePoint_Dashboard_Extender::init();
