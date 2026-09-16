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

    /** Original paired SVG artwork; no external font, spritesheet or runtime dependency. */
    private static function icon($dom, $name) {
        $paths = array(
            'appointments' => array(
                'M6 4h12a2 2 0 0 1 2 2v13a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z M8 2v4 M16 2v4 M4 9h16 M8 13h.01 M12 13h.01 M16 13h.01 M8 17h.01 M12 17h.01',
                'M8 1a1 1 0 0 1 1 1v1h6V2a1 1 0 0 1 2 0v1h1a3 3 0 0 1 3 3v13a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V6a3 3 0 0 1 3-3h1V2a1 1 0 0 1 1-1Z M5 8v2h14V8Z M7 12v2h2v-2Z M11 12v2h2v-2Z M15 12v2h2v-2Z M7 16v2h2v-2Z M11 16v2h2v-2Z'
            ),
            'press-ons' => array(
                'M6.5 17V8a5.5 5.5 0 0 1 11 0v9c0 3-2 4.5-5.5 4.5S6.5 20 6.5 17Z M6.7 17c2.8-3 7.8-3 10.6 0',
                'M12 1.5a6.5 6.5 0 0 1 6.5 6.5v9c0 3.6-2.6 5.5-6.5 5.5S5.5 20.6 5.5 17V8A6.5 6.5 0 0 1 12 1.5Z M7.5 17.5c2.5-2.5 6.5-2.5 9 0v-2c-2.5-2-6.5-2-9 0Z'
            ),
            'messages' => array(
                'M5 3h14a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H9l-5 4v-4a2 2 0 0 1-1-2V5a2 2 0 0 1 2-2Z M7 10h.01 M12 10h.01 M17 10h.01',
                'M5 2h14a3 3 0 0 1 3 3v11a3 3 0 0 1-3 3H9.4l-4.8 3.8A1 1 0 0 1 3 22v-3.8A3 3 0 0 1 2 16V5a3 3 0 0 1 3-3Z M6 9v2h2V9Z M11 9v2h2V9Z M16 9v2h2V9Z'
            ),
            'account' => array(
                'M16 6a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z M4 21v-2a8 7 0 0 1 16 0v2Z',
                'M17 6A5 5 0 1 1 7 6a5 5 0 0 1 10 0Z M12 11c5 0 9 3.6 9 8v2a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-2c0-4.4 4-8 9-8Z'
            ),
        );
        $svg = $dom->createElementNS('http://www.w3.org/2000/svg', 'svg');
        foreach (array('class' => 'ishi-dashboard-icon', 'viewBox' => '0 0 24 24', 'width' => '24', 'height' => '24', 'aria-hidden' => 'true', 'focusable' => 'false') as $key => $value) { $svg->setAttribute($key, $value); }
        foreach (array('outline', 'solid') as $index => $variant) {
            $shape = $dom->createElementNS('http://www.w3.org/2000/svg', 'path');
            $shape->setAttribute('class', 'ishi-dashboard-icon-' . $variant);
            $shape->setAttribute('d', $paths[$name][$index]);
            $shape->setAttribute('fill', $index ? 'currentColor' : 'none');
            if ($index) { $shape->setAttribute('fill-rule', 'evenodd'); }
            else {
                foreach (array('stroke' => 'currentColor', 'stroke-width' => '1.75', 'stroke-linecap' => 'round', 'stroke-linejoin' => 'round') as $key => $value) { $shape->setAttribute($key, $value); }
            }
            $svg->appendChild($shape);
        }
        return $svg;
    }

    /** Raw native HTML in, grouped HTML out; never intercept JSON or whole-page output. */
    // Third argument retained for adapter compatibility; SVGs do not require a font.
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
                $wrapper->setAttribute('class', $wrapper->getAttribute('class') . ' ishi-customer-dashboard');
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
                    $primary_trigger->insertBefore(self::icon($dom, $group), $primary_trigger->firstChild);
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
