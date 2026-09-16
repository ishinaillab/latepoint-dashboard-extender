<?php
defined('ABSPATH') || exit;

/** Presentation adapter for the verified LatePoint 5.6.10 dashboard view. */
final class Ishi_Customer_Dashboard_Layout {
    private static $rendering = false;

    private static function has_class($node, $class) {
        return strpos(' ' . preg_replace('/\s+/', ' ', $node->getAttribute('class')) . ' ', ' ' . $class . ' ') !== false;
    }

    private static function element($dom, $tag, $attributes = array(), $text = null) {
        $node = $dom->createElement($tag);
        foreach ($attributes as $name => $value) { $node->setAttribute($name, (string) $value); }
        if ($text !== null) { $node->appendChild($dom->createTextNode($text)); }
        return $node;
    }

    /** Raw native HTML in, grouped HTML out; never intercept JSON or whole-page output. */
    public static function transform($html, $press_ons_page = false, $icons_available = true) {
        if (self::$rendering || !is_string($html) || $html === '' || !class_exists('DOMDocument')) { return $html; }
        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        self::$rendering = true;
        try {
            if (!$dom->loadHTML('<?xml encoding="UTF-8"><!DOCTYPE html><html><body><div id="ishi-layout-fragment">' . $html . '</div></body></html>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD)) { return $html; }
            $root = $dom->getElementById('ishi-layout-fragment');
            if (!$root) { return $html; }
            $xpath = new DOMXPath($dom);
            $targets = array(
                'appointments' => '.tab-content-customer-bookings',
                'history' => '.tab-content-customer-orders',
                'press-ons' => '.tab-content-ishi-customer-press-ons',
                'custom-press-ons' => '.tab-content-ishi-customer-custom-press-ons',
                'profile' => '.tab-content-customer-info-form',
                'addresses' => '.tab-content-ishi-customer-addresses',
                'book' => '.tab-content-customer-new-appointment-form',
                'messages' => '.tab-content-customer-booking-messages',
            );
            $plans = array();
            // Preflight all instances before invoking any component renderer.
            foreach ($xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " customer-dashboard-tabs ")]', $root) as $nav) {
                $wrapper = $nav->parentNode;
                if (!$wrapper instanceof DOMElement || !self::has_class($wrapper, 'latepoint-tabs-w')) { continue; }
                if ($wrapper->hasAttribute('data-ishi-layout')) { continue; }
                $views = array();
                foreach ($xpath->query('./*[contains(concat(" ", normalize-space(@class), " "), " latepoint-tab-trigger ")]', $nav) as $trigger) {
                    $key = array_search($trigger->getAttribute('data-tab-target'), $targets, true);
                    // Unknown add-ons or ambiguous markup keep their functional native layout.
                    if ($key === false || isset($views[$key])) { return $html; }
                    $panels = $xpath->query('./*[contains(concat(" ", normalize-space(@class), " "), " ' . substr($targets[$key], 1) . ' ")]', $wrapper);
                    if ($panels->length !== 1) { return $html; }
                    $views[$key] = array('trigger' => $trigger, 'panel' => $panels->item(0));
                }
                if (!isset($views['appointments'], $views['history'], $views['profile'])) { continue; }
                $plans[] = array($wrapper, $nav, $views);
            }
            if (!$plans) { return $html; }
            $raw = array();
            foreach ($plans as $plan) {
                list($wrapper, $old_nav, $views) = $plan;
                // Remove only the inspected native header pair, identified by structure
                // and route rather than translated Welcome/Logout text.
                $outer = $wrapper->parentNode;
                $heading = $xpath->query('preceding-sibling::*[1]', $wrapper)->item(0);
                $logout = $heading ? $xpath->query('preceding-sibling::*[1]', $heading)->item(0) : null;
                if ($outer instanceof DOMElement && self::has_class($outer, 'latepoint-w')
                    && $heading instanceof DOMElement && $heading->tagName === 'h4'
                    && $logout instanceof DOMElement && $logout->tagName === 'a') {
                    $query = parse_url($logout->getAttribute('href'), PHP_URL_QUERY);
                    $params = array();
                    if (is_string($query)) { parse_str($query, $params); }
                    if (($params['action'] ?? '') === 'latepoint_route_call'
                        && ($params['route_name'] ?? '') === 'customer_cabinet__logout') {
                        $outer->removeChild($logout);
                        $outer->removeChild($heading);
                    }
                }
                $prefix = wp_unique_id('ishi-dashboard-');
                $wrapper->setAttribute('class', $wrapper->getAttribute('class') . ' ishi-customer-dashboard' . ($icons_available ? '' : ' ishi-dashboard-no-icons'));
                $wrapper->setAttribute('data-ishi-layout', '1');
                $labels = array(
                    'appointments' => __('Appointments', 'latepoint-dashboard-extender'),
                    'history' => __('History', 'latepoint-dashboard-extender'),
                    'press-ons' => __('Press-Ons', 'latepoint-dashboard-extender'),
                    'custom-press-ons' => __('Custom Press-Ons', 'latepoint-dashboard-extender'),
                    'messages' => __('Messages', 'latepoint-dashboard-extender'),
                    'account' => __('Account', 'latepoint-dashboard-extender'),
                    'profile' => __('Profile', 'latepoint-dashboard-extender'),
                    'addresses' => __('Addresses', 'latepoint-dashboard-extender'),
                    'book' => __('New Appointment', 'latepoint-dashboard-extender'),
                );
                // The component owns its fields, REST routes, nonces, validation and assets.
                if (shortcode_exists('ishi_latepoint_profile')) {
                    $profile = do_shortcode('[ishi_latepoint_profile]');
                    if (is_string($profile)) {
                        $panel = $views['profile']['panel'];
                        while ($panel->firstChild) { $panel->removeChild($panel->firstChild); }
                        $marker = 'ishi-profile-' . $prefix;
                        $panel->appendChild($dom->createComment($marker));
                        $raw['<!--' . $marker . '-->'] = $profile;
                    }
                }
                $active = 'appointments';
                foreach ($views as $key => $view) {
                    if (self::has_class($view['panel'], 'active')) { $active = $key; break; }
                }
                if ($press_ons_page === true) { $press_ons_page = 'press-ons'; }
                if (in_array($press_ons_page, array('press-ons', 'custom-press-ons'), true) && isset($views[$press_ons_page])) { $active = $press_ons_page; }
                foreach ($views as $key => &$view) {
                    // Tabs are controls, not destinations. Keep feature classes/data attributes
                    // while removing anchor hashes that theme smooth-scroll handlers can follow.
                    $button = self::element($dom, 'button', array('type' => 'button'));
                    foreach ($view['trigger']->attributes as $attribute) {
                        if (!in_array($attribute->name, array('href', 'target', 'type'), true)) {
                            $button->setAttribute($attribute->name, $attribute->value);
                        }
                    }
                    while ($view['trigger']->firstChild) { $button->appendChild($view['trigger']->firstChild); }
                    $view['trigger']->parentNode->replaceChild($button, $view['trigger']);
                    $view['trigger'] = $button;
                    foreach ($view as $node) {
                        $classes = trim(preg_replace('/(^|\s)active(?=\s|$)/', '', $node->getAttribute('class')));
                        $node->setAttribute('class', $classes . ($active === $key ? ' active' : ''));
                        $node->setAttribute('data-ishi-view', $key);
                    }
                    $view['panel']->setAttribute('id', $prefix . '-view-' . $key);
                    $view['trigger']->setAttribute('id', $prefix . '-trigger-' . $key);
                    $view['trigger']->setAttribute('aria-label', $labels[$key]);
                    // Only the label text changes. Retain the Pro Messages badge and all hook attributes.
                    $label = $xpath->query('.//text()[normalize-space(.) != ""]', $view['trigger'])->item(0);
                    if ($label) { $label->nodeValue = $labels[$key]; }
                    else { $view['trigger']->appendChild($dom->createTextNode($labels[$key])); }
                }
                unset($view);
                // Deliberately omit latepoint-tab-triggers on redesigned navigation:
                // its flat descendant handler cannot own this two-level interface.
                $primary = self::element($dom, 'div', array('class' => 'customer-dashboard-tabs ishi-dashboard-primary', 'aria-label' => __('Customer dashboard', 'latepoint-dashboard-extender')));
                $wrapper->insertBefore($primary, $old_nav);
                $groups = array('appointments' => array('appointments', 'history', 'book'), 'press-ons' => array('press-ons', 'custom-press-ons'), 'messages' => array('messages'), 'account' => array('profile', 'addresses'));
                $icons = array('appointments' => 'calendar', 'press-ons' => 'nail', 'messages' => 'comment-light', 'account' => 'avatar');
                foreach ($groups as $group => $keys) {
                    $keys = array_values(array_filter($keys, static function ($key) use ($views) { return isset($views[$key]); }));
                    if (!$keys) { continue; }
                    $section = self::element($dom, 'section', array('id' => $prefix . '-section-' . $group, 'class' => 'ishi-dashboard-section', 'data-ishi-section' => $group));
                    $wrapper->insertBefore($section, $old_nav);
                    $leaf_keys = $keys;
                    if (count($leaf_keys) === 1) {
                        $primary_trigger = $views[$leaf_keys[0]]['trigger'];
                    } else {
                        $primary_trigger = self::element($dom, 'button', array('type' => 'button', 'data-ishi-open-view' => $leaf_keys[0]));
                    }
                    $primary_trigger->setAttribute('class', $primary_trigger->getAttribute('class') . ' ishi-dashboard-primary-control');
                    $primary_trigger->setAttribute('id', $prefix . '-primary-' . $group);
                    $primary_trigger->setAttribute('data-ishi-primary', $group);
                    $primary_trigger->setAttribute('aria-label', $labels[$group]);
                    $label = $xpath->query('.//text()[normalize-space(.) != ""]', $primary_trigger)->item(0);
                    if ($label) { $label->parentNode->removeChild($label); }
                    $primary_trigger->insertBefore(self::element($dom, 'i', array('aria-hidden' => 'true', 'class' => 'nails_skin_elementor_icons nails_skin_elementor_icons-' . $icons[$group])), $primary_trigger->firstChild);
                    $primary_trigger->appendChild(self::element($dom, 'span', array('class' => 'ishi-dashboard-icon-label'), $labels[$group]));
                    $badge = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " lp-new-messages-count ")]', $primary_trigger)->item(0);
                    if ($badge) {
                        $badge->setAttribute('id', $prefix . '-unread-count');
                        $primary_trigger->setAttribute('aria-describedby', $badge->getAttribute('id'));
                    }
                    $primary->appendChild($primary_trigger);
                    if (count($leaf_keys) > 1) {
                        $secondary = self::element($dom, 'div', array('class' => 'ishi-dashboard-secondary', 'data-ishi-secondary' => $group, 'aria-label' => $labels[$group]));
                        foreach ($leaf_keys as $key) { $secondary->appendChild($views[$key]['trigger']); }
                        $section->appendChild($secondary);
                    }
                    foreach ($keys as $key) {
                        $views[$key]['trigger']->setAttribute('data-ishi-group', $group);
                        $views[$key]['panel']->setAttribute('data-ishi-group', $group);
                        $section->appendChild($views[$key]['panel']);
                    }
                }
                $wrapper->removeChild($old_nav);
            }
            $result = '';
            foreach ($root->childNodes as $child) { $result .= $dom->saveHTML($child); }
            return strtr($result, $raw);
        } finally {
            self::$rendering = false;
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
