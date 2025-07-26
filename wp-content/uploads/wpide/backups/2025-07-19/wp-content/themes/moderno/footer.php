<?php
 global $ideapark_footer, $ideapark_footer_is_elementor;
?>
</div><!-- /.l-inner -->
<footer
	class="l-section c-footer c-footer--mobile-buttons-<?php echo ideapark_mod( 'bottom_buttons_mobile_locations' ); ?> <?php ideapark_class( ! $ideapark_footer && ideapark_mod( 'footer_copyright' ), 'c-footer--simple' ); ?><?php if ( ideapark_mod( 'sticky_add_to_cart' ) ) { ?> c-footer--sticky-add-to-cart<?php } ?>">
	<?php if ( $ideapark_footer ) {
		echo ideapark_wrap( $ideapark_footer, '<div class="l-section">', '</div>' );
	} else { ?>
		<div class="l-section__container">
			<?php if ( ideapark_mod( 'footer_copyright' ) ) { ?>
				<?php get_template_part( 'templates/footer-copyright' ); ?>
			<?php } ?>
		</div>
	<?php } ?>
	<?php if ( ideapark_is_elementor_preview_mode() && ideapark_mod( 'footer_page' ) ) { ?>
		<a onclick="window.open('<?php echo esc_url( esc_url( admin_url( 'post.php?post=' . ideapark_mod( 'footer_page' ) . '&action=' . ( ! empty( $ideapark_footer_is_elementor ) ? 'elementor' : 'edit' ) ) ) ); ?>', '_blank').focus();"
		   href="#"
		   class="h-footer-edit">
			<i>
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 862 1000">
					<path
						d="M725 324L529 127l67-67c55-53 109-83 155-38l79 80c53 52 15 101-38 155zM469 187l196 196-459 459H13V646zM35 1000a35 35 0 0 0 0-70h792a35 35 0 0 0 0 70z"
						fill="white"/>
				</svg>
			</i>
		</a>
	<?php } ?>
</footer>

<script>
    document.addEventListener("DOMContentLoaded", function() {
      gsap.registerPlugin(ScrollTrigger);

      // Initialize fade on scroll for all elements with .fade-on-scroll class
      function initScrollFade() {
        const fadeElements = document.querySelectorAll('.fade-on-scroll');

        fadeElements.forEach((element, index) => {
          // Get custom delay from data attribute or use index-based delay
          const delay = element.getAttribute('data-fade-delay') || (index * 0.1);

          // Create the scroll trigger animation
          gsap.to(element, {
            duration: 0.8,
            ease: "power2.out",
            scrollTrigger: {
              trigger: element,
              start: "top 85%", // Animation starts when element is 85% down the viewport
              end: "top 15%",   // Animation reverses when element is 15% down the viewport
              toggleActions: "play none none reverse", // play on enter, reverse on leave
              onEnter: () => {
                // Add visible class with delay
                setTimeout(() => {
                  element.classList.add('fade-visible');
                }, delay * 1000);
              },
              onLeave: () => {
                // Remove visible class when scrolling past
                element.classList.remove('fade-visible');
              },
              onEnterBack: () => {
                // Add visible class when scrolling back up
                setTimeout(() => {
                  element.classList.add('fade-visible');
                }, delay * 1000);
              },
              onLeaveBack: () => {
                // Remove visible class when scrolling back up past
                element.classList.remove('fade-visible');
              }
            }
          });
        });
      }

      // Initialize the fade effects
      initScrollFade();

      // Optional: Refresh ScrollTrigger on window resize
      window.addEventListener('resize', () => {
        ScrollTrigger.refresh();
      });
    });

    // Utility function to add fade effect to new elements dynamically
    function addFadeEffect(selector, options = {}) {
      const elements = document.querySelectorAll(selector);
      const defaultOptions = {
        start: "top 85%",
        end: "top 15%",
        delay: 0,
        duration: 0.8,
        ease: "power2.out"
      };

      const config = { ...defaultOptions, ...options };

      elements.forEach(element => {
        element.classList.add('fade-on-scroll');

        gsap.to(element, {
          duration: config.duration,
          ease: config.ease,
          scrollTrigger: {
            trigger: element,
            start: config.start,
            end: config.end,
            toggleActions: "play none none reverse",
            onEnter: () => {
              setTimeout(() => {
                element.classList.add('fade-visible');
              }, config.delay * 1000);
            },
            onLeave: () => {
              element.classList.remove('fade-visible');
            },
            onEnterBack: () => {
              setTimeout(() => {
                element.classList.add('fade-visible');
              }, config.delay * 1000);
            },
            onLeaveBack: () => {
              element.classList.remove('fade-visible');
            }
          }
        });
      });
    }
  </script>


<?php wp_footer(); ?>
</body>
</html>
