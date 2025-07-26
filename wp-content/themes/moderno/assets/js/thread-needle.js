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

  // Thread Script

  document.addEventListener("DOMContentLoaded", function() {
    gsap.registerPlugin(ScrollTrigger, DrawSVGPlugin);

    // Thread drawing animation
    gsap.fromTo("#thread-path", { drawSVG: "0%" }, {
      drawSVG: "100%",
      ease: "none",
      scrollTrigger: {
        trigger: "#our-process-section",
        start: "top 70%",
        end: "bottom 10%",
        scrub: true,
      }
    });

    // Card reveal animations with accumulative visibility
    const cards = document.querySelectorAll('.process-card');
    const cardTriggerPoints = [0.2, 0.4, 0.6, 0.8]; // When each card appears
    const cardHidePoints = [0.15, 0.35, 0.55, 0.75]; // When each card starts to hide on scroll up

    // Function to update card visibility based on scroll progress
    function updateCardVisibility(progress) {
      cards.forEach((card, index) => {
        const showPoint = cardTriggerPoints[index];
        const hidePoint = cardHidePoints[index];
        let opacity = 0;
        let scale = 0.95;

        if (progress >= showPoint) {
          // Card should be visible (scrolling down past show point)
          opacity = 1;
          scale = 1;
        } else if (progress >= hidePoint) {
          // Card is in fade-in zone
          const fadeProgress = (progress - hidePoint) / (showPoint - hidePoint);
          opacity = fadeProgress;
          scale = 0.95 + (0.05 * fadeProgress);
        } else {
          // Card is hidden (scrolling up past hide point)
          opacity = 0;
          scale = 0.95;
        }

        // Apply the calculated values
        gsap.set(card, {
          opacity: opacity,
          scale: scale,
          duration: 0.1
        });
      });
    }

    // Create ScrollTrigger for card visibility
    ScrollTrigger.create({
      trigger: "#our-process-section",
      start: "top 80%",
      end: "bottom 20%",
      scrub: 0.1,
      onUpdate: self => {
        updateCardVisibility(self.progress);
      }
    });

    // --- NEEDLE SVG ---
    const needleSVG = `
      <svg id="needle-svg" width="162" height="142" viewBox="0 0 162 142" fill="none" xmlns="http://www.w3.org/2000/svg">
        <g id="Thread">
          <path id="left" d="M101.514 74.541C105.762 78.7702 108.056 86.918 87.2314 88.1871C61.2004 89.7734 40.9186 102.053 40.0217 114.416" stroke="#499193" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          <path id="needle" d="M103.638 71.9115L11.5817 80.8172L98.989 84.4259C99.3554 83.3474 99.6065 82.2393 99.7655 81.1201L97.36 80.9675C96.7721 80.9329 96.2112 80.6885 95.7811 80.2815C94.7689 79.3237 94.7247 77.7204 95.6834 76.7073C96.0886 76.2791 96.6343 76.0051 97.2211 75.9344L100.369 75.5604C100.369 75.5604 100.371 75.5604 100.372 75.5594L105.167 74.9902C106.206 74.867 107.239 75.2092 108 75.9296C109.26 77.1222 109.315 79.1195 108.123 80.3799C107.403 81.1402 106.391 81.5388 105.346 81.4731L99.7655 81.1201C99.6135 82.23 99.2622 83.3498 98.9202 84.4231L103.989 84.6323C106.219 84.7253 108.378 83.8471 109.912 82.2267C112.121 79.8925 112.019 76.1958 109.684 73.9861C108.063 72.4526 105.859 71.6967 103.638 71.9115Z" fill="#e2554d"/>
          <path id="right" d="M98.0388 73.3706C87.4648 79.4268 109.151 108.505 131.419 99.386C137.454 96.9146 147.473 92.6433 160.915 86.8262" stroke="#499193" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </g>
      </svg>
    `;

    document.getElementById('needle-group').innerHTML = needleSVG;

    function moveNeedleToPathEnd(percent=1) {
      const path = document.getElementById('thread-path');
      const needleGroup = document.getElementById('needle-group');
      if (!path || !needleGroup) return;
      const len = path.getTotalLength();
      const tipLen = len * percent;
      const tip = path.getPointAtLength(tipLen);
      const tipBack = path.getPointAtLength(Math.max(0, tipLen - 1));
      const dx = tip.x - tipBack.x, dy = tip.y - tipBack.y;
      const angle = Math.atan2(dy, dx) * 180 / Math.PI;
      needleGroup.setAttribute(
        'transform',
        `translate(${tip.x},${tip.y}) rotate(${angle}) scale(0.35) translate(${-103.638},${-71.9115})`
      );
    }

    ScrollTrigger.create({
      trigger: "#our-process-section",
      start: "top 70%",
      end: "bottom 10%",
      scrub: true,
      onUpdate: self => {
        moveNeedleToPathEnd(self.progress);
      }
    });

    window.addEventListener('resize', () => {
      const st = ScrollTrigger.getAll().find(st => st.trigger === document.getElementById("our-process-section"));
      moveNeedleToPathEnd(st?.progress || 1);
    });

    moveNeedleToPathEnd(1);
  });
