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

/** Minimum seconds before submit (blocks instant bot posts). */
const MIN_SUBMIT_SECONDS = 2;

/** Maximum seconds before submit (stale tab / replay). */
const MAX_SUBMIT_SECONDS = 86400;

/**
 * Append honeypot + server timestamp to every CF7 form HTML.
 */
add_filter('wpcf7_form_elements', static function (string $html): string {
    $ts = (string) time();
    $honeypot = sprintf(
        '<span class="brp-cf7-hp" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;" aria-hidden="true">'
        . '<label for="%1$s">Leave this empty</label>'
        . '<input type="text" name="%1$s" id="%1$s" value="" tabindex="-1" autocomplete="off" />'
        . '<input type="hidden" name="%2$s" value="%3$s" />'
        . '</span>',
        esc_attr(FIELD_NAME),
        esc_attr(FIELD_TIME),
        esc_attr($ts)
    );

    return $html . $honeypot;
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

    $now = time();
    $elapsed = $now - $ts;
    if ($elapsed < MIN_SUBMIT_SECONDS || $elapsed > MAX_SUBMIT_SECONDS) {
        return true;
    }

    return false;
}, 10, 2);
