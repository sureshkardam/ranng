<?php
namespace Elementor;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Simple_Slider_Widget extends Widget_Base {

    public function get_name() {
        return 'simple_slider';
    }

    public function get_title() {
        return __( 'Simple Slider', 'text-domain' );
    }

    public function get_icon() {
        return 'eicon-slider-device';
    }

    public function get_categories() {
        return [ 'general' ];
    }

    protected function _register_controls() {
        $this->start_controls_section(
            'content_section',
            [
                'label' => __( 'Testimonials', 'text-domain' ),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $repeater = new \Elementor\Repeater();

        $repeater->add_control(
            'quote_icon',
            [
                'label' => __( 'Quote Icon', 'text-domain' ),
                'type' => Controls_Manager::MEDIA,
            ]
        );

        $repeater->add_control(
            'testimonial_content',
            [
                'label' => __( 'Content', 'text-domain' ),
                'type' => Controls_Manager::TEXTAREA,
                'default' => __( 'Your testimonial goes here.', 'text-domain' ),
            ]
        );

        $repeater->add_control(
            'person_name',
            [
                'label' => __( 'Name', 'text-domain' ),
                'type' => Controls_Manager::TEXT,
                'default' => __( 'John Doe', 'text-domain' ),
            ]
        );

        $repeater->add_control(
            'designation',
            [
                'label' => __( 'Designation', 'text-domain' ),
                'type' => Controls_Manager::TEXT,
                'default' => __( 'CEO, Company', 'text-domain' ),
            ]
        );

        $this->add_control(
            'simple_testimonials',
            [
                'label' => __( 'Testimonials List', 'text-domain' ),
                'type' => Controls_Manager::REPEATER,
                'fields' => $repeater->get_controls(),
                'title_field' => '{{person_name}}',
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        ?>

<style>
.simple-slider .simple-slide {
    background: #2A7D7D;
    color: #fff;
    border-radius: 16px;
    position: relative;
    min-height: 250px;
    display: flex;
    align-items: flex-start;
    flex-direction: column;
    justify-content: space-between;
}
.simple-slider .simple-slide{
    gap: 16px;
    padding: 32px 24px;
    box-shadow: 0 2px 16px rgba(0, 0, 0, 0.07);
}
.simple-slider .quote-icon {
    width: 40px !important;
    height: auto;
    margin-right: auto;
}

.simple-slider .testi-content {
    font-family: DM Sans;
    font-weight: 500;
    font-size: 18px;
    line-height: 140%;
    letter-spacing: 0px;
    vertical-align: middle;

}

.simple-slider .testi-author,
.simple-slider .testi-designation{
    font-family: DM Sans;
    font-weight: 500;
    font-size: 18px;
    line-height: 140%;
    letter-spacing: 0px;
    color: #ffffffb3;
}
.test-footer{
    display: flex;
    gap: 3px;
    flex-wrap: wrap;
}
.circle-chav .owl-carousel {
    display: flex;
    flex-direction: column;
}
.circle-chav .owl-carousel .owl-nav {
    order: 2;
    display: flex;
    justify-content: center;
    margin-top: 20px;
    position: relative;
    line-height: 0;
    gap: 10px;
}
.circle-chav .owl-carousel .owl-nav button{
    padding: 10px;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 31px;
    border: 1px solid #2A7D7D33;
    color: #2A7D7D;
    border-radius: 50%;
}
.circle-chav .owl-carousel .owl-nav button img{
    width: 16px;
}
.circle-chav .owl-carousel .owl-nav button.disabled {
    opacity: .6;
}
@media (min-width:992px){
    .simple-slider .simple-slide{
        gap: 24px;
        padding: 48px;
    }
    .circle-chav .owl-carousel .owl-nav {
        order: -1;
        top: -70px;
        margin-top: 0px;
        justify-content: flex-end;
    }
}
        </style>

        <div class="simple-slider owl-carousel">
            <?php foreach ( $settings['simple_testimonials'] as $item ) : 
                $quote_url = '';
                if ( ! empty( $item['quote_icon']['url'] ) ) {
                    $quote_url = $item['quote_icon']['url'];
                } elseif ( ! empty( $item['quote_icon']['id'] ) ) {
                    $quote_url = wp_get_attachment_image_url( $item['quote_icon']['id'], 'full' );
                }
            ?>
                <div class="simple-slide">
                    <?php if ( $quote_url ) : ?>
                        <img src="<?php echo esc_url( $quote_url ); ?>" alt="Quote Icon" class="quote-icon">
                    <?php endif; ?>
                    <div class="testi-content"><?php echo esc_html( $item['testimonial_content'] ); ?></div>
                    <div class="test-footer">
                        <div class="testi-author"><?php echo esc_html( $item['person_name'] ); ?></div>
                        <div class="testi-designation"><?php echo esc_html( $item['designation'] ); ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php
    }
}
