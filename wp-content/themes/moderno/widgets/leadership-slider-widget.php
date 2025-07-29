<?php
namespace Elementor;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Repeater;

if ( ! defined( 'ABSPATH' ) ) exit;

class Leadership_Slider_Widget extends Widget_Base {

    public function get_name() {
        return 'leadership_slider';
    }

    public function get_title() {
        return __( 'Leadership Slider', 'text-domain' );
    }

    public function get_icon() {
        return 'eicon-slider-push';
    }

    public function get_categories() {
        return [ 'general' ];
    }

    protected function _register_controls() {
        $this->start_controls_section(
            'leaders_section',
            [
                'label' => __( 'Leaders', 'text-domain' ),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $repeater = new Repeater();

        $repeater->add_control(
            'leader_image',
            [
                'label' => __( 'Leader Image (Left Side)', 'text-domain' ),
                'type' => Controls_Manager::MEDIA,
            ]
        );

        $repeater->add_control(
            'leader_side_image',
            [
                'label' => __( 'Right Side Image (Optional)', 'text-domain' ),
                'type' => Controls_Manager::MEDIA,
            ]
        );

        $repeater->add_control(
            'leader_description',
            [
                'label' => __( 'Content', 'text-domain' ),
                'type' => Controls_Manager::WYSIWYG,
            ]
        );

        $repeater->add_control(
            'leader_name',
            [
                'label' => __( 'Leader Name', 'text-domain' ),
                'type' => Controls_Manager::TEXT,
                'default' => __( 'John Doe', 'text-domain' ),
            ]
        );

        $repeater->add_control(
            'leader_designation',
            [
                'label' => __( 'Leader Designation', 'text-domain' ),
                'type' => Controls_Manager::TEXT,
                'default' => __( 'CEO', 'text-domain' ),
            ]
        );

        $this->add_control(
            'leaders',
            [
                'label' => __( 'Leaders List', 'text-domain' ),
                'type' => Controls_Manager::REPEATER,
                'fields' => $repeater->get_controls(),
                'title_field' => '{{leader_name}} - {{leader_designation}}',
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $leaders = $settings['leaders'];
        ?>

        <div class="leadership-slider-wrapper">
            <div class="leadership-slider owl-carousel">
                <?php foreach ($leaders as $leader): ?>
                    <div class="leader-slide">
                        <div class="leader-content-wrap">
                            <div class="leader-left-side">
                                <div class="leader-left-img">
                                    <?php if ($leader['leader_image']['url']): ?>
                                        <img src="<?php echo esc_url($leader['leader_image']['url']); ?>" alt="">
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="leader-right-side">
                                <div class="leader-right-img">
                                    <?php if ($leader['leader_side_image']['url']): ?>
                                        <img src="<?php echo esc_url($leader['leader_side_image']['url']); ?>" alt="">
                                    <?php endif; ?>
                                </div>
                                <div class="leader-description">
                                    <?php echo wp_kses_post($leader['leader_description']); ?>
                                </div>
                                <div class="leader-details">
                                    <div class="leader-name"><?php echo esc_html($leader['leader_name']); ?></div>
                                    <div class="leader-role"><?php echo esc_html($leader['leader_designation']); ?></div>
                                </div>
                                
                            </div>
                            
                            
                            
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="leader-thumbs">
                <?php foreach ($leaders as $index => $leader): ?>
                    <div class="leader-thumb" data-index="<?php echo $index; ?>">
                        <img src="<?php echo esc_url($leader['leader_image']['url']); ?>" alt="">
                        <div class="thumb-leader-details">
                            <div class="thumb-name"><?php echo esc_html($leader['leader_name']); ?></div>
                            <div class="thumb-role"><?php echo esc_html($leader['leader_designation']); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php
    }
}
