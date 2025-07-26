<?php
namespace Elementor;

class award_slider_widget extends Widget_Base {

    public function get_name() {
        return 'award_slider';
    }

    public function get_title() {
        return __('Award Slider', 'moderno');
    }

    public function get_icon() {
        return 'eicon-slider-album';
    }

    public function get_categories() {
        return ['general'];
    }

    protected function _register_controls() {
        $this->start_controls_section(
            'content_section',
            [
                'label' => __('Awards', 'moderno'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'awards',
            [
                'label' => __('Award Items', 'moderno'),
                'type' => \Elementor\Controls_Manager::REPEATER,
                'fields' => [
                    [
                        'name' => 'award_image',
                        'label' => __('Award Image', 'moderno'),
                        'type' => \Elementor\Controls_Manager::MEDIA,
                        'default' => [
                            'url' => \Elementor\Utils::get_placeholder_image_src(),
                        ],
                    ],
                    [
                        'name' => 'award_text',
                        'label' => __('Award Text', 'moderno'),
                        'type' => \Elementor\Controls_Manager::TEXT,
                        'default' => __('Award Title', 'moderno'),
                    ],
                    [
                        'name' => 'award_link',
                        'label' => __('Award Link', 'moderno'),
                        'type' => \Elementor\Controls_Manager::URL,
                        'placeholder' => __('https://your-link.com', 'moderno'),
                        'default' => [
                            'url' => '',
                            'is_external' => true,
                        ],
                    ],
                ],
                'title_field' => '{{{ award_text }}}',
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        ?>
        <style>
            .award-slider .owl-stage {
                transition-timing-function: linear !important;
            }

        </style>
        <div class="award-slider owl-carousel">
            <?php foreach ( $settings['awards'] as $award ) : ?>
                <div class="award-slide">
                    <?php if ( !empty($award['award_link']['url']) ) : ?>
                        <a href="<?php echo esc_url($award['award_link']['url']); ?>" <?php if ($award['award_link']['is_external']) echo 'target="_blank"'; ?>>
                    <?php endif; ?>
                    
                    <div class="award-image">
                        <img src="<?php echo esc_url($award['award_image']['url']); ?>" alt="Award">
                    </div>
                    <div class="award-text"><?php echo esc_html($award['award_text']); ?></div>
                    
                    <?php if ( !empty($award['award_link']['url']) ) : ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <?php
    }
}
