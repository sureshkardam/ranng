<?php
namespace Elementor;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Testimonial_Slider_Widget extends Widget_Base {

    public function get_name() {
        return 'testimonial_slider';
    }

    public function get_title() {
        return __( 'Testimonial Slider', 'text-domain' );
    }

    public function get_icon() {
        return 'eicon-testimonial';
    }

    public function get_categories() {
        return [ 'general' ];
    }

    protected function _register_controls() {
        $this->start_controls_section(
            'content_section',
            [
                'label' => __( 'Content', 'text-domain' ),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $repeater = new \Elementor\Repeater();

        $repeater->add_control(
            'name',
            [
                'label' => __( 'Name', 'text-domain' ),
                'type' => Controls_Manager::TEXT,
                'default' => __( 'Vans', 'text-domain' ),
            ]
        );

        $repeater->add_control(
            'logo',
            [
                'label' => __( 'Logo', 'text-domain' ),
                'type' => Controls_Manager::MEDIA,
            ]
        );

        $repeater->add_control(
            'paragraph',
            [
                'label' => __( 'Paragraph', 'text-domain' ),
                'type' => Controls_Manager::TEXTAREA,
                'default' => __( 'At Ranng Global, we understand the importance of providing your school with the best possible uniforms. We believe that when students look good, they feel good and perform better in the classroom.', 'text-domain' ),
            ]
        );

        $repeater->add_control(
            'main_image',
            [
                'label' => __( 'Main Image', 'text-domain' ),
                'type' => Controls_Manager::MEDIA,
            ]
        );

        $this->add_control(
            'testimonials',
            [
                'label' => __( 'Testimonials', 'text-domain' ),
                'type' => Controls_Manager::REPEATER,
                'fields' => $repeater->get_controls(),
                'default' => [
                    [
                        'name' => 'Vans',
                        'paragraph' => 'At Ranng Global, we understand the importance of providing your school with the best possible uniforms. We believe that when students look good, they feel good and perform better in the classroom.',
                    ],
                ],
                'title_field' => '{{name}}',
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();

        // Inline CSS for this widget
        ?>
        <style>
        .custom-layout {
            display: flex;
            flex-direction: row;
            justify-content: space-between;
            align-items: stretch;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 16px rgba(0,0,0,0.07);
            padding: 32px 24px;
            min-height: 220px;
            gap: 32px;
        }
        .testimonial-left {
            flex: 1 1 180px;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            justify-content: space-between;
            min-width: 180px;
        }
        .testimonial-name {
            font-size: 2em;
            font-weight: bold;
            margin-bottom: 24px;
        }
        .testimonial-logo {
            width: 80px !important;
            height: 80px !important;
            object-fit: contain !important;
            border-radius: 50% !important;
            background: #fff;
        }
        .testimonial-right {
            flex: 2 1 320px;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            justify-content: flex-start;
        }
        .testimonial-paragraph {
            font-size: 18px;
            color: #333;
            margin-bottom: 30px;
            font-weight: 400;
            margin-top: 0;
            line-height: 1.3;
        }
        .testimonial-main-image {
    width: 55% !important;
    object-fit: cover;
    border-radius: 4px !important;
    background: #f5f5f5;
    max-height: 200px !important;
}
        @media (max-width: 768px) {
            .custom-layout {
                flex-direction: column;
                align-items: center;
                gap: 16px;
            }
            .testimonial-left, .testimonial-right {
                align-items: center;
                min-width: unset;
            }
            .testimonial-main-image {
                width: 120px;
                height: 120px;
            }
            .testimonial-left {
        width: 100%;
        flex-direction: row;
        max-height: max-content;
        margin-bottom: 30px;
    }
    .testimonial-logo{
        width: 60px;
        height: 60px;
    }
        }
        </style>
        <div class="testimonialsOwl owl-carousel">
            <?php
            if ( ! empty( $settings['testimonials'] ) ):
                foreach ( $settings['testimonials'] as $testimonial ):
                    $logo_url = '';
                    if ( ! empty( $testimonial['logo']['url'] ) ) {
                        $logo_url = $testimonial['logo']['url'];
                    } elseif ( ! empty( $testimonial['logo']['id'] ) ) {
                        $logo_url = wp_get_attachment_image_url( $testimonial['logo']['id'], 'full' );
                    }

                    $main_image_url = '';
                    if ( ! empty( $testimonial['main_image']['url'] ) ) {
                        $main_image_url = $testimonial['main_image']['url'];
                    } elseif ( ! empty( $testimonial['main_image']['id'] ) ) {
                        $main_image_url = wp_get_attachment_image_url( $testimonial['main_image']['id'], 'full' );
                    }
                    ?>
                    <div class="testimonial-slide">
                        <div class="testimonial-content custom-layout">
                            <div class="testimonial-left">
                                <h2 class="testimonial-name"><?php echo esc_html( $testimonial['name'] ); ?></h2>
                                <?php if ( $logo_url ): ?>
                                    <img class="testimonial-logo" src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $testimonial['name'] ); ?> Logo">
                                <?php endif; ?>
                            </div>
                            <div class="testimonial-right">
                                <p class="testimonial-paragraph"><?php echo esc_html( $testimonial['paragraph'] ); ?></p>
                                <?php if ( $main_image_url ): ?>
                                    <img class="testimonial-main-image" src="<?php echo esc_url( $main_image_url ); ?>" alt="<?php echo esc_attr( $testimonial['name'] ); ?>">
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php
                endforeach;
            endif;
            ?>
        </div>
        <?php
    }
}