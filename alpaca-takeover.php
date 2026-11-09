<?php
/**
 * Plugin Name:       Alpaca Takeover
 * Description:       Turns a WordPress site into a login-required Alpaca Project Board.
 * Version:           0.1.0
 * Requires at least: 6.9
 * Requires PHP:      8.0
 * Requires Plugins:  alpaca
 * Text Domain:       alpaca-takeover
 * License:           GPL-2.0-or-later
 *
 * @package AlpacaTakeover
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-alpaca-takeover.php';
require_once __DIR__ . '/includes/class-alpaca-takeover-appearance.php';

Alpaca_Takeover::boot();
Alpaca_Takeover_Appearance::boot();
