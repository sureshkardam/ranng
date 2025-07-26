  document.addEventListener("DOMContentLoaded", function () {
    gsap.registerPlugin(ScrollTrigger, DrawSVGPlugin);

    // Start with thread hidden
    gsap.set("#thread-path", { drawSVG: "0%" });

    // Insert the needle into the SVG
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

    // Move needle along thread path
    function moveNeedleToPathEnd(percent = 1) {
      const path = document.getElementById('thread-path');
      const needleGroup = document.getElementById('needle-group');
      if (!path || !needleGroup) return;

      const len = path.getTotalLength();
      const tipLen = len * percent;
      const tip = path.getPointAtLength(tipLen);
      const tipBack = path.getPointAtLength(Math.max(0, tipLen - 1));
      const dx = tip.x - tipBack.x;
      const dy = tip.y - tipBack.y;
      const angle = Math.atan2(dy, dx) * 180 / Math.PI;

      needleGroup.setAttribute(
        'transform',
        `translate(${tip.x},${tip.y}) rotate(${angle}) scale(0.35) translate(${-103.638},${-71.9115})`
      );
    }

    // Unified ScrollTrigger for thread + needle
    ScrollTrigger.create({
      trigger: "#our-process-section",
      start: "top center",
      end: "bottom center",
      scrub: true,
      onUpdate: self => {
        const progress = self.progress;

        // Animate thread drawing
        gsap.set("#thread-path", {
          drawSVG: `${progress * 100}%`
        });

        // Move needle along path
        moveNeedleToPathEnd(progress);
      }
    });

    // Process card fade-in/out logic
    const cards = document.querySelectorAll('.process-card');

    cards.forEach(card => {
      // Fade-in as card scrolls in (from 10% to 60% in view)
      ScrollTrigger.create({
        trigger: card,
        start: "top 90%",   // 10% visible
        end: "top 40%",     // 60% visible
        scrub: true,
        onUpdate: self => {
          const opacity = gsap.utils.clamp(0, 1, self.progress * 1.2);
          const scale = 0.95 + (0.05 * self.progress);
          gsap.set(card, { opacity, scale });
        }
      });

      // Fade-out as card scrolls out
      ScrollTrigger.create({
        trigger: card,
        start: "top 100%",  // below screen
        end: "top 91%",     // just entering
        scrub: true,
        onUpdate: self => {
          const opacity = gsap.utils.clamp(0, 1, 1 - self.progress);
          const scale = 0.95 + (0.05 * (1 - self.progress));
          gsap.set(card, { opacity, scale });
        }
      });
    });

    // Responsive update on resize
    window.addEventListener('resize', () => {
      const st = ScrollTrigger.getAll().find(st => st.trigger === document.getElementById("our-process-section"));
      moveNeedleToPathEnd(st?.progress || 0);
    });

    // Initial needle position
    moveNeedleToPathEnd(0);
  });