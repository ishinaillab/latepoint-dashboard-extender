<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('OsController')) {
    return;
}

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
