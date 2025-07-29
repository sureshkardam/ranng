<?php
namespace Elementor;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

if ( ! defined( 'ABSPATH' ) ) exit;

class Milestone_Slider_Widget extends Widget_Base {

    public function get_name() {
        return 'milestone_slider';
    }

    public function get_title() {
        return __( 'Milestone Slider', 'text-domain' );
    }

    public function get_icon() {
        return 'eicon-post-slider';
    }

    public function get_categories() {
        return [ 'general' ];
    }

    protected function _register_controls() {
        $this->start_controls_section(
            'content_section',
            [
                'label' => __( 'Milestones', 'text-domain' ),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $repeater = new \Elementor\Repeater();

        $repeater->add_control(
            'milestone_year',
            [
                'label' => __( 'Year', 'text-domain' ),
                'type' => Controls_Manager::TEXT,
                'default' => __( '2025', 'text-domain' ),
            ]
        );

        $repeater->add_control(
            'milestone_image',
            [
                'label' => __( 'Image', 'text-domain' ),
                'type' => Controls_Manager::MEDIA,
            ]
        );

        $repeater->add_control(
            'milestone_title',
            [
                'label' => __( 'Title', 'text-domain' ),
                'type' => Controls_Manager::TEXT,
                'default' => __( 'Foundation Laid', 'text-domain' ),
            ]
        );

        $repeater->add_control(
            'milestone_text',
            [
                'label' => __( 'Description', 'text-domain' ),
                'type' => Controls_Manager::TEXTAREA,
                'default' => __( 'Sustainability Audit & Policy Framework', 'text-domain' ),
            ]
        );

        $repeater->add_control(
            'milestone_more_content',
            [
                'label' => __( 'More Content', 'text-domain' ),
                'type' => Controls_Manager::WYSIWYG,
            ]
        );

        $repeater->add_control(
            'milestone_button',
            [
                'label' => __( 'Read More Link', 'text-domain' ),
                'type' => Controls_Manager::URL,
                'placeholder' => __( 'https://your-link.com', 'text-domain' ),
                'show_external' => true,
                'default' => [
                    'url' => '',
                    'is_external' => true,
                    'nofollow' => true,
                ],
            ]
        );

        $this->add_control(
            'milestones',
            [
                'label' => __( 'Milestones List', 'text-domain' ),
                'type' => Controls_Manager::REPEATER,
                'fields' => $repeater->get_controls(),
                'title_field' => '{{milestone_year}} - {{milestone_title}}',
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        ?>

<style>
.milestone-slider-wrap {
    position: relative;
    /*padding: 40px 0;*/
}
.milestone-slider {
    position: relative;
    padding-top: 94px;
}
.milestone-slider-line {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 0;
    display: flex;
    justify-content: center;
    align-items: center;
    pointer-events: none;
}
.milestone-slider-line img {
    width: 100%;
    height: auto;
    object-fit: contain;
}
.milestone-slider .owl-stage-outer {
    overflow: visible;
}
.milestone-slider.owl-carousel .milestone-item {
    position: relative;
    min-height: 200px;
    border-radius: 16px;
    padding: 24px;
    display: flex;
    flex-direction: column;
    background-color: #EEEBE2;
    transition: all 0.3s ease-in-out;
    z-index: 1;
    /*overflow: hidden;*/
}
.milestone-item:hover {
    min-height: 350px;
}
.milestone-year {
    position: absolute;
    top: -94px;
    left: 0;
    background: #EEEBE2;
    color: #2A7D7D;
    width: 70px;
    height: 70px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 2;
    font-family: DM Sans;
    font-weight: 600;
    font-size: 24px;
    line-height: 120%;
    text-align: center;
}
.milestone-image {
    width: 40px;
    height: 40px;
    margin-bottom: 32px;
}
.milestone-image img {
    max-width: 100%;
    object-fit: contain;
}
.milestone-title {
    margin-bottom: 8px;
    font-family: DM Sans;
    font-weight: 600;
    font-size: 24px;
    color: #333333;
    max-width: 312px;
    line-height: 1;
}
.milestone-text {
    font-family: DM Sans;
    font-size: 16px;
    color: #333333;
    margin-bottom: 0px;
}
.milestone-more-content {
    height: 0;
    opacity: 0;
    overflow: hidden;
    transition: all 0.4s ease-in-out;
}
.milestone-item:hover .milestone-more-content {
    height: auto;
    opacity: 1;
    /*margin-top: 16px;*/
}
.svg-thread:before{
    background-image: url(https://new.ranngglobal.com/wp-content/uploads/2025/07/Needle-thread.svg);
}
.milestone-more-content {
    margin-top: 16px;
}
.milestone-more-content .read-more{
    margin-top: 24px;
    display: inline-block;
}
.milestone-more-content p {
    font-family: DM Sans;
    font-weight: 400;
    font-size: 16px;
    line-height: 140%;
    letter-spacing: 0;
    color: #777777;
    margin: 0;
}
.our-journey .milestone-item {
    padding: 0 !important;
    flex-direction: column-reverse !important;
    min-height: auto !important;
    background: transparent !important;
    gap: 24px;
}
.our-journey .milestone-more-content {
    display: none !important;
}
.our-journey .milestone-title,
.our-journey .milestone-text{
    color: #242424 !important;
}
.our-journey .milestone-image{
    width: 100% !important;
    height: auto !important;
    /*min-height: 290px;*/
    border-radius: 16px;
    overflow: hidden;
}
.our-journey .milestone-image img{
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.our-journey .milestone-year {
    background:#2A7D7D;
    color: #EEEBE2;
}
.our-journey.svg-thread:before{
     background-image: url(https://new.ranngglobal.com/wp-content/uploads/2025/07/needle-thread.svg) !important;
}
</style>

<div class="milestone-slider-wrap">
    <div class="milestone-slider owl-carousel">
        <!--<div class="milestone-slider-line">-->
        <!--    <img src="https://new.ranngglobal.com/wp-content/uploads/2025/07/Needle-thread.svg" alt="Timeline Line">-->
        <!--</div>-->
        
            <?php foreach ( $settings['milestones'] as $item ) : ?>
            <div class="milestone-item">
                <div class="milestone-year"><?php echo esc_html($item['milestone_year']); ?></div>
                <div class="milestone-image">
                    <?php if ( $item['milestone_image']['url'] ) : ?>
                        <img src="<?php echo esc_url($item['milestone_image']['url']); ?>" alt="Milestone Image">
                    <?php endif; ?>
                </div>
                <div class="content-part">
                    <div class="milestone-title"><?php echo esc_html($item['milestone_title']); ?></div>
                    <div class="milestone-text"><?php echo esc_html($item['milestone_text']); ?></div>
    
                      
                    <div class="milestone-more-content">
                        <?php echo wp_kses_post($item['milestone_more_content']); ?>
                        <?php if ( ! empty( $item['milestone_button']['url'] ) ) : ?>
                            <a href="<?php echo esc_url( $item['milestone_button']['url'] ); ?>" class="read-more" <?php echo $item['milestone_button']['is_external'] ? 'target="_blank"' : ''; ?> <?php echo $item['milestone_button']['nofollow'] ? 'rel="nofollow"' : ''; ?>>
                                <?php _e( 'Read More', 'text-domain' ); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                
            </div>
            <?php endforeach; ?>
        
    </div>
</div>

<?php
    }
}
