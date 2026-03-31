<?php

/**
 * Plugin Name: BrandonRP CF7 Honeypot
 * Description: Invisible honeypot + timing check for Contact Form 7 to reduce spam bots.
 * Version: 1.0.0
 * Author: Brandon RP
 * Requires Plugins: contact-form-7
 */

declare(strict_types=1);

namespace BrandonRP\CF7Honeypot;

if (! defined('ABSPATH')) {
    exit;
}

const FIELD_NAME = 'brp_hp_confirm';
const FIELD_TIME = 'brp_hp_ts';
const FIELD_SIG = 'brp_hp_sig';
const FIELD_JS = 'brp_hp_js';

/** Minimum seconds before submit (blocks instant bot posts). */
const MIN_SUBMIT_SECONDS = 2;

/** Maximum seconds before submit (stale tab / replay). */
const MAX_SUBMIT_SECONDS = 86400;

/** Soft-scoring threshold for additional bot signals. */
const SOFT_SPAM_THRESHOLD = 3;

/**
 * Append honeypot + server timestamp to every CF7 form HTML.
 */
add_filter('wpcf7_form_elements', static function (string $html): string {
    $ts = (string) time();
    $sig = sign_timestamp($ts);
    $honeypot = sprintf(
        '<span class="brp-cf7-hp" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;" aria-hidden="true">'
        . '<label for="%1$s">Leave this empty</label>'
        . '<input type="text" name="%1$s" id="%1$s" value="" tabindex="-1" autocomplete="off" />'
        . '<input type="hidden" name="%2$s" value="%3$s" />'
        . '<input type="hidden" name="%4$s" value="%5$s" />'
        . '<input type="hidden" name="%6$s" value="0" />'
        . '</span>',
        esc_attr(FIELD_NAME),
        esc_attr(FIELD_TIME),
        esc_attr($ts),
        esc_attr(FIELD_SIG),
        esc_attr($sig),
        esc_attr(FIELD_JS)
    );

    $js = sprintf(
        '<script>(function(){var f=document.currentScript&&document.currentScript.closest("form");if(!f){return;}var i=f.querySelector(\'input[name="%s"]\');if(i){i.value="1";}})();</script>',
        esc_js(FIELD_JS)
    );

    return $html . $honeypot . $js;
}, 20);

/**
 * Mark submission as spam if honeypot filled or timing is impossible.
 */
add_filter('wpcf7_spam', static function (bool $spam, $submission): bool {
    if ($spam) {
        return true;
    }

    $hp = isset($_POST[FIELD_NAME]) ? (string) wp_unslash($_POST[FIELD_NAME]) : '';
    if ($hp !== '') {
        return true;
    }

    $tsRaw = isset($_POST[FIELD_TIME]) ? (string) wp_unslash($_POST[FIELD_TIME]) : '';
    $ts = ctype_digit($tsRaw) ? (int) $tsRaw : 0;
    if ($ts <= 0) {
        return true;
    }

    $sigRaw = isset($_POST[FIELD_SIG]) ? (string) wp_unslash($_POST[FIELD_SIG]) : '';
    if ($sigRaw === '' || ! hash_equals(sign_timestamp($tsRaw), $sigRaw)) {
        return true;
    }

    $now = time();
    $elapsed = $now - $ts;
    if ($elapsed < MIN_SUBMIT_SECONDS || $elapsed > MAX_SUBMIT_SECONDS) {
        return true;
    }

    $score = 0;

    $jsFlag = isset($_POST[FIELD_JS]) ? (string) wp_unslash($_POST[FIELD_JS]) : '0';
    if ($jsFlag !== '1') {
        $score += 1;
    }

    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? trim((string) $_SERVER['HTTP_USER_AGENT']) : '';
    if ($ua === '') {
        $score += 1;
    }

    if (count_post_links() >= 4) {
        $score += 2;
    }

    if ($score >= SOFT_SPAM_THRESHOLD) {
        return true;
    }

    return false;
}, 10, 2);

function sign_timestamp(string $ts): string
{
    $salt = defined('AUTH_SALT') ? (string) AUTH_SALT : (string) wp_salt('auth');
    return hash_hmac('sha256', 'brp-cf7-hp|' . $ts, $salt);
}

function count_post_links(): int
{
    $haystack = '';
    foreach ($_POST as $key => $value) {
        $k = (string) $key;
        if (str_starts_with($k, '_wpcf7') || str_starts_with($k, 'brp_hp_')) {
            continue;
        }
        if (is_scalar($value)) {
            $haystack .= ' ' . (string) wp_unslash((string) $value);
        }
    }

    if ($haystack === '') {
        return 0;
    }

    preg_match_all('~https?://|www\.~i', $haystack, $m);
    return isset($m[0]) ? count($m[0]) : 0;
}
