<?php
/**
 * Plugin Name: Our Process Elementor Widget
 * Description: Custom Elementor widget for Thread & Needle animation section.
 * Version: 1.0
 * Author: Your Name
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'elementor/widgets/widgets_registered', function($widgets_manager) {
    require_once __DIR__ . '/our-process-widget-class.php';
    $widgets_manager->register( new \Our_Process_Widget() );
});

add_action( 'wp_enqueue_scripts', function() {
    wp_enqueue_style( 'our-process-style', plugins_url( 'assets/style.css', _FILE_ ) );
    wp_enqueue_script( 'gsap', plugins_url( 'assets/gsap.min.js', _FILE_ ), [], null, true );
    wp_enqueue_script( 'gsap-scroll', plugins_url( 'assets/ScrollTrigger.min.js', _FILE_ ), ['gsap'], null, true );
    wp_enqueue_script( 'gsap-drawsvg', plugins_url( 'assets/DrawSVGPlugin3.min.js', _FILE_ ), ['gsap'], null, true );
    wp_enqueue_script( 'our-process-script', plugins_url( 'assets/thread-animation.js', _FILE_ ), ['gsap', 'gsap-scroll', 'gsap-drawsvg'], null, true );
});
