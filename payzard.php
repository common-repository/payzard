<?php
/*
Plugin Name: Payzard
Description: Fastest way to accept online payments!
Version: 1.0.1
*/

if (!defined('ABSPATH')) {
    exit(); // Exit if accessed directly
}

require_once __DIR__ . '/PayzardPlugin.php';

new \Payzard\PayzardPlugin(__FILE__);
