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
    wp_enqueue_style( 'our-process-style', plugins_url( 'assets/style.css', __FILE__ ) );
    wp_enqueue_script( 'gsap', 'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js', [], null, true );
    wp_enqueue_script( 'gsap-scroll', 'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js', ['gsap'], null, true );
    wp_enqueue_script( 'gsap-drawsvg', 'https://assets.codepen.io/16327/DrawSVGPlugin3.min.js', ['gsap'], null, true );
    wp_enqueue_script( 'our-process-script', plugins_url( 'assets/thread-animation.js', __FILE__ ), ['gsap', 'gsap-scroll', 'gsap-drawsvg'], null, true );
});
