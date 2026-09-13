<?php
if (!defined('ABSPATH')) {
    exit;
}

/** @var WC_Order $order */
?>
<div class="latepoint-lightbox-heading">
    <h2><?php esc_html_e('Order Summary', 'latepoint'); ?></h2>
</div>
<div class="latepoint-lightbox-content">
    <div class="full-summary-wrapper ishi-press-ons-full-summary">
        <div class="full-summary-head-info">
            <div class="full-summary-order-info-wrapper">
                <div class="fsoi-main-wrapper">
                    <div class="fsoi-main">
                        <span><?php esc_html_e('Order #', 'latepoint'); ?></span>
                        <strong><?php echo esc_html($order->get_order_number()); ?></strong>
                    </div>
                </div>
                <div class="full-summary-order-info-elements">
                    <?php if ($order->get_date_created()) : ?>
                        <div class="fsoi-element">
                            <span><?php esc_html_e('Created:', 'latepoint'); ?></span>
                            <strong><?php echo esc_html($order->get_date_created()->date_i18n('M j, Y')); ?></strong>
                        </div>
                    <?php endif; ?>
                    <div class="fsoi-element">
                        <span><?php esc_html_e('Status:', 'latepoint'); ?></span>
                        <strong><?php echo esc_html(wc_get_order_status_name($order->get_status())); ?></strong>
                    </div>
                    <?php if ($order->get_payment_method_title()) : ?>
                        <div class="fsoi-element">
                            <span><?php esc_html_e('Payment:', 'latepoint'); ?></span>
                            <strong><?php echo esc_html($order->get_payment_method_title()); ?></strong>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="full-summary-info-w">
            <div class="order-summary-main-section">
                <div class="order-summary-items-heading">
                    <?php esc_html_e('Order Items', 'latepoint'); ?>
                    <div class="osih-line"></div>
                </div>

                <div class="summary-box-wrapper ishi-press-ons-summary-items">
                    <?php foreach ($order->get_items('line_item') as $item) : ?>
                        <?php
                        $product = $item->get_product();
                        $quantity = absint($item->get_quantity());
                        $line_total = (float) $item->get_total();
                        ?>
                        <div class="summary-box ishi-press-ons-summary-item">
                            <div class="ishi-press-ons-summary-item-row">
                                <?php if ($product && $product->get_image_id()) : ?>
                                    <?php $src = wp_get_attachment_image_url($product->get_image_id(), 'woocommerce_thumbnail'); ?>
                                    <?php if ($src) : ?>
                                        <img class="ishi-press-ons-summary-item-image" src="<?php echo esc_url($src); ?>" alt="<?php echo esc_attr($product->get_name()); ?>" loading="lazy">
                                    <?php endif; ?>
                                <?php endif; ?>

                                <div class="ishi-press-ons-summary-item-main">
                                    <div class="sbc-big-item"><?php echo esc_html($item->get_name()); ?></div>
                                    <?php if ($product && $product->is_type('variation')) : ?>
                                        <div class="summary-attributes">
                                            <?php echo wp_kses_post(wc_get_formatted_variation($product, true)); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="sbc-subtle-item">Qty: <?php echo esc_html($quantity); ?></div>
                                </div>

                                <div class="ishi-press-ons-summary-item-price">
                                    <?php echo wp_kses_post(wc_price($line_total, array('currency' => $order->get_currency()))); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="summary-price-breakdown-wrapper">
                <div class="pb-heading">
                    <div class="pbh-label"><?php esc_html_e('Cost Breakdown', 'latepoint'); ?></div>
                    <div class="pbh-line"></div>
                </div>

                <?php ishi_latepoint_render_price_row('Subtotal', $order->get_subtotal(), $order); ?>

                <?php if ((float) $order->get_discount_total() !== 0.0) : ?>
                    <?php ishi_latepoint_render_price_row('Discount', -1 * (float) $order->get_discount_total(), $order, 'spi-positive'); ?>
                <?php endif; ?>

                <?php foreach ($order->get_coupon_codes() as $coupon_code) : ?>
                    <div class="summary-price-item-w spi-sub">
                        <span class="spi-name">Coupon</span>
                        <span class="spi-price"><?php echo esc_html($coupon_code); ?></span>
                    </div>
                <?php endforeach; ?>

                <?php if ((float) $order->get_shipping_total() !== 0.0) : ?>
                    <?php ishi_latepoint_render_price_row('Shipping', $order->get_shipping_total(), $order); ?>
                <?php endif; ?>

                <?php foreach ($order->get_fees() as $fee) : ?>
                    <?php if ((float) $fee->get_total() === 0.0) continue; ?>
                    <?php ishi_latepoint_render_price_row($fee->get_name(), $fee->get_total(), $order); ?>
                <?php endforeach; ?>

                <?php if ((float) $order->get_total_tax() !== 0.0) : ?>
                    <?php ishi_latepoint_render_price_row('Taxes', $order->get_total_tax(), $order); ?>
                <?php endif; ?>

                <div class="summary-price-item-w spi-total">
                    <span class="spi-name">Total</span>
                    <span class="spi-price"><?php echo wp_kses_post($order->get_formatted_order_total()); ?></span>
                </div>
            </div>

            <div class="summary-boxes-columns ishi-press-ons-addresses">
                <div class="summary-box">
                    <div class="summary-box-heading">
                        <div class="sbh-item">Billing Address</div>
                        <div class="sbh-line"></div>
                    </div>
                    <div class="summary-box-content">
                        <div class="sbc-subtle-item ishi-press-ons-address-content">
                            <?php echo wp_kses_post($order->get_formatted_billing_address() ?: '&mdash;'); ?>
                        </div>
                    </div>
                </div>

                <div class="summary-box">
                    <div class="summary-box-heading">
                        <div class="sbh-item">Shipping Address</div>
                        <div class="sbh-line"></div>
                    </div>
                    <div class="summary-box-content">
                        <div class="sbc-subtle-item ishi-press-ons-address-content">
                            <?php echo wp_kses_post($order->get_formatted_shipping_address() ?: '&mdash;'); ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php
            $customer_notes = wc_get_order_notes(array(
                'order_id' => $order->get_id(),
                'type'     => 'customer',
                'orderby'  => 'date_created',
                'order'    => 'ASC',
                'return'   => 'objects',
            ));
            ?>

            <?php if (!empty($customer_notes)) : ?>
                <div class="order-summary-main-section ishi-press-ons-extra-section">
                    <div class="order-summary-items-heading">
                        <?php esc_html_e('Order Notes', 'latepoint'); ?>
                        <div class="osih-line"></div>
                    </div>
                    <div class="ishi-press-ons-notes">
                        <?php foreach ($customer_notes as $note) : ?>
                            <div class="ishi-press-ons-note">
                                <div class="summary-attributes">
                                    <?php echo esc_html($note->date_created->date_i18n('M j, Y')); ?>
                                </div>
                                <div><?php echo wp_kses_post(wpautop($note->content)); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php $downloads = $order->get_downloadable_items(); ?>
            <?php if (!empty($downloads)) : ?>
                <div class="order-summary-main-section ishi-press-ons-extra-section">
                    <div class="order-summary-items-heading">
                        <?php esc_html_e('Downloads', 'latepoint'); ?>
                        <div class="osih-line"></div>
                    </div>
                    <div class="ishi-press-ons-downloads">
                        <?php foreach ($downloads as $download) : ?>
                            <a class="latepoint-btn latepoint-btn-primary latepoint-btn-outline latepoint-btn-sm" href="<?php echo esc_url($download['download_url']); ?>">
                                <?php echo esc_html($download['download_name']); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
function ishi_latepoint_render_price_row($label, $amount, $order, $extra_class = '') {
    $class = trim('summary-price-item-w ' . $extra_class);
    $is_subtracted = ((float) $amount < 0);
    ?>
    <div class="<?php echo esc_attr($class); ?>">
        <span class="spi-name"><?php echo esc_html($label); ?></span>
        <span class="spi-price"><?php
            if ($is_subtracted) {
                echo '-';
                echo wp_kses_post(wc_price(abs((float) $amount), array('currency' => $order->get_currency())));
            } else {
                echo wp_kses_post(wc_price((float) $amount, array('currency' => $order->get_currency())));
            }
        ?></span>
    </div>
    <?php
}
?>
