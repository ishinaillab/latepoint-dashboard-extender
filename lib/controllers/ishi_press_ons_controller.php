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
                return $this->send_json(array(
                    'status'  => LATEPOINT_STATUS_ERROR,
                    'message' => __('Not Allowed', 'latepoint'),
                ));
            }

            if (!function_exists('wc_get_order') || !class_exists('WC_Order')
                || !function_exists('wc_get_order_types') || !function_exists('wc_get_order_statuses')) {
                return $this->send_json(array(
                    'status'  => LATEPOINT_STATUS_ERROR,
                    'message' => __('Orders are currently unavailable.', 'latepoint-dashboard-extender'),
                ));
            }

            $order = wc_get_order($order_id);

            if (!$order instanceof WC_Order) {
                return $this->send_json(array(
                    'status'  => LATEPOINT_STATUS_ERROR,
                    'message' => __('Order Not Found', 'latepoint'),
                ));
            }

            if (!LatePoint_Dashboard_Extender::is_press_ons_order_for_customer($order, get_current_user_id())) {
                return $this->send_json(array(
                    'status'  => LATEPOINT_STATUS_ERROR,
                    'message' => __('Not Allowed', 'latepoint'),
                ));
            }

            $this->vars['order'] = $order;

            $this->format_render('view_order_in_lightbox');
        }

    }
}
