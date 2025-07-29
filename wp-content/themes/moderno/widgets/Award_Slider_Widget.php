<?php
namespace Elementor;

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class Award_Slider_Widget extends Widget_Base {

    public function get_name() {
        return 'award_slider';
    }

    public function get_title() {
        return __('Award Slider', 'text-domain');
    }

    public function get_icon() {
        return 'eicon-slider-push';
    }

    public function get_categories() {
        return ['general'];
    }

    protected function register_controls() {

        $this->start_controls_section(
            'content_section',
            [
                'label' => __('Slider Items', 'text-domain'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'items',
            [
                'label' => __('Award Items', 'text-domain'),
                'type' => \Elementor\Controls_Manager::REPEATER,
                'fields' => [
                    [
                        'name' => 'image',
                        'label' => __('Image', 'text-domain'),
                        'type' => \Elementor\Controls_Manager::MEDIA,
                        'default' => [
                            'url' => \Elementor\Utils::get_placeholder_image_src(),
                        ],
                    ],
                    [
                        'name' => 'title',
                        'label' => __('Title', 'text-domain'),
                        'type' => \Elementor\Controls_Manager::TEXT,
                        'default' => __('Award Title', 'text-domain'),
                    ],
                    [
                        'name' => 'link',
                        'label' => __('Link', 'text-domain'),
                        'type' => \Elementor\Controls_Manager::URL,
                        'placeholder' => 'https://your-link.com',
                        'default' => ['url' => '#'],
                    ],
                ],
                'default' => [],
                'title_field' => '{{{ title }}}',
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        
        
        if (!empty($settings['items'])) :
            echo '<div class="award-slider-wrapper">';
            echo '<div class="award-slider-track">';
            
            foreach ($settings['items'] as $item) :
                $img = $item['image']['url'] ?? '';
                $title = $item['title'] ?? '';
                $link = $item['link']['url'] ?? '';
                $target = $item['link']['is_external'] ? ' target="_blank"' : '';
                $nofollow = $item['link']['nofollow'] ? ' rel="nofollow"' : '';
                ?>
                
               
                <div class="award-slide-item">
                    <a href="<?= esc_url($link); ?>"<?= $target . $nofollow ?>>
                        <div class="award-image">
                            <?php if ($img): ?>
                                <img src="<?= esc_url($img); ?>" alt="<?= esc_attr($title); ?>" />
                             <?php endif; ?>
                        </div>
                        <p><?= esc_html($title); ?></p>
                    </a>
                </div>
                <?php
            endforeach;
            echo '</div></div>';
        endif;
    }

    public function get_script_depends() {
        return ['gsap'];
    }
}

// Add this JavaScript in your footer or enqueue properly
add_action('wp_footer', function () {
    ?>
    <script>
    document.addEventListener("DOMContentLoaded", function () {
    const wrapper = document.querySelector('.award-slider-wrapper');
    const track = document.querySelector('.award-slider-track');
    if (!wrapper || !track) return;

    // Clone the track for infinite loop
    const cloneTrack = track.cloneNode(true);
    wrapper.appendChild(cloneTrack);

    // Screen width ke hisaab se visible items set karo
    let visibleItems;
    const screenWidth = window.innerWidth;

    if (screenWidth <= 767) {
        visibleItems = 2; // Mobile
    } else if (screenWidth <= 1024) {
        visibleItems = 4; // Tablet
    } else {
        visibleItems = 5; // Desktop
    }

    // Pehle ek slide item select karo aur uski width aur margin nikalo
    const slide = track.querySelector(".award-slide-item");
    if (!slide) return;

    const slideStyle = window.getComputedStyle(slide);
    const slideWidth = slide.offsetWidth;
    const marginRight = parseInt(slideStyle.marginRight) || 0;

    // Total slides count karo
    const totalSlides = track.querySelectorAll(".award-slide-item").length;

    // Total width calculate karo (slide width + margin) * total slides
    const totalWidth = (slideWidth + marginRight) * totalSlides;

    // Tracks ki width set karo
    track.style.width = totalWidth + "px";
    cloneTrack.style.width = totalWidth + "px";

    // Clone track ko original track ke baad absolute position par set karo
    cloneTrack.style.position = 'absolute';
    cloneTrack.style.top = '0';
    cloneTrack.style.left = totalWidth + 'px';

    // Wrapper ko relative position do
    wrapper.style.position = 'relative';

    // Animation duration adjust karo visible items ke hisaab se (optional)
    const baseDuration = 30;
    const duration = baseDuration * (6 / visibleItems);

    // GSAP animation start karo
    gsap.to([track, cloneTrack], {
        x: `-=${totalWidth}`,
        duration: duration,
        ease: "linear",
        repeat: -1,
        modifiers: {
            x: gsap.utils.unitize(x => {
                let val = parseFloat(x);
                if (val <= -totalWidth) {
                    return val + totalWidth;
                }
                return val;
            })
        }
    });
});

    </script>
     <style>
        .award-slider-wrapper {
            overflow: hidden;
            width: 100%;
        }
        
        .award-slider-track {
            display: flex;
            white-space: nowrap;
        }
        
        .award-slide-item {
            display: inline-block;
            margin-right: 40px;
        }
        .award-slide-item p{
font-size: 16px;
    text-transform: capitalize;
    line-height: 24px;
    font-weight: 500;
    padding: 0 !important;
    margin-bottom: 10px;
    margin-top: 10px;
color: #242424;
width: 98%;
margin: auto;
display: block;
text-align: center;
white-space: wrap;
}

.award-slide-item img{
    border-radius: 16px;
    aspect-ratio:1/1;
    
}
.award-slider-track{
    margin-left: 40px;
}
.award-slide-item {
    width: calc((100% / 5) - 40px); /* Desktop default */
    margin-right: 40px;
}
.award-slider-wrapper {
  position: relative;
  overflow: hidden;
}

/* Left fade overlay */
.award-slider-wrapper::before {
  content: "";
  position: absolute;
  left: 0;
  top: 0;
  width: 80px; /* adjust width as per need */
  height: 100%;
  pointer-events: none; /* so that clicks pass through */
  background: linear-gradient(to right, white 0%, transparent 100%);
  z-index: 10;
}

/* Right fade overlay */
.award-slider-wrapper::after {
  content: "";
  position: absolute;
  right: 0;
  top: 0;
  width: 80px; /* adjust width as per need */
  height: 100%;
  pointer-events: none;
  background: linear-gradient(to left, white 0%, transparent 100%);
  z-index: 10;
}
.award-image{
    aspect-ratio: 1/1;
    
}
.award-image img{
    width: 100%;
}
.award-slide-item a{
    display: block !important;
}
@media (max-width: 1024px) {
    .award-slide-item {
        width: calc((100% / 4) - 40px);
    }
}

@media (max-width: 767px) {
    .award-slide-item {
        width: 200px !important;
        margin-right: 20px !important;
    }
}

        </style>
    <?php
});

