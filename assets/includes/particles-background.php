<?php
// assets/includes/particles-background.php
?>

<!-- Conteneur pour l'animation particles.js (placé au début du body) -->
<div id="particles-js"></div>

<!-- Scripts chargés à la fin avant </body> -->
<script src="https://cdn.jsdelivr.net/npm/particles.js@2.0.0/particles.min.js"></script>
<script>
    particlesJS('particles-js', {
        particles: {
            number: { value: 80, density: { enable: true, value_area: 800 } },
            color: { value: ['#7C3AED', '#FBBF24', '#3B82F6'] }, // Couleurs du thème
            shape: { type: 'circle' },
            opacity: { value: 0.5, random: true },
            size: { value: 3, random: true },
            move: { speed: 2, direction: 'none', random: true }
        },
        interactivity: {
            events: { onhover: { enable: true, mode: 'repulse' }, onclick: { enable: true, mode: 'push' } },
            modes: { repulse: { distance: 100 }, push: { particles_nb: 4 } }
        },
        retina_detect: true
    });
</script>