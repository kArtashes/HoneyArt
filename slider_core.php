<?php
// Image slider shared by slider.php and slider_home.php.
// Set $imageDir before including this file.
$images = glob($imageDir . '*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE) ?: [];
?>
<?php if (!defined('SLIDER_STYLES_PRINTED')): define('SLIDER_STYLES_PRINTED', true); ?>
<style>
    .slider-container {
        height: 100%;
        margin: auto;
        overflow: hidden;
        position: relative;
        touch-action: pan-y; /* vertical page scroll still works, horizontal swipe is ours */
    }

    .slider-wrapper {
        display: flex;
        transition: transform 1s ease-in-out;
    }

    .slider-wrapper picture {
        display: contents;   /* the image itself stays the slide */
    }

    .slider-wrapper img {
        flex: 0 0 100%;   /* each slide is exactly one slider wide */
        width: 100%;
        height: auto;
        user-select: none;
        -webkit-user-drag: none;
    }

    .slider-container button {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        background: rgba(0, 0, 0, 0.5);
        color: white;
        border: none;
        padding: 10px;
        cursor: pointer;
    }

    .slider-container .prev {
        left: 10px;
    }

    .slider-container .next {
        right: 10px;
    }

    /* Phones: the slide changes with a swipe, and the arrows would cover the banner text */
    @media (max-width: 575px) {
        .slider-container button.prev,
        .slider-container button.next {
            display: none;
        }
    }
</style>
<?php endif; ?>

<div class="slider-container">
    <div class="slider-wrapper">
        <?php foreach ($images as $index => $image): ?>
            <?php
            // images/<folder>/small/ holds a lighter copy for phones
            $phoneImage = dirname($image) . '/small/' . basename($image);
            // The first slide loads right away; the script below loads the others when their turn comes
            $loading = $index === 0 ? 'fetchpriority="high"' : 'loading="lazy"';
            ?>
            <picture>
                <?php if (is_file(__DIR__ . '/' . $phoneImage)): ?>
                    <source media="(max-width: 575px)" srcset="<?php echo htmlspecialchars($phoneImage); ?>">
                <?php endif; ?>
                <img src="<?php echo htmlspecialchars($image); ?>" alt="" draggable="false" <?php echo $loading; ?>>
            </picture>
        <?php endforeach; ?>
    </div>
    <button class="prev" type="button" aria-label="Previous slide">❮</button>
    <button class="next" type="button" aria-label="Next slide">❯</button>
</div>

<script>
    (function (container) {
        const wrapper = container.querySelector('.slider-wrapper');
        const slides = wrapper.querySelectorAll('img');
        const count = slides.length;
        const prevButton = container.querySelector('.prev');
        const nextButton = container.querySelector('.next');

        if (count < 2) {
            prevButton.hidden = true;
            nextButton.hidden = true;
            return;
        }

        let currentIndex = 0;
        let timer = null;

        // Start loading the slide that comes next, so it is ready when its turn comes
        function prepareNextSlide() {
            slides[(currentIndex + 1) % count].loading = 'eager';
        }

        // Percent-based, so it stays correct when the window is resized or the phone is rotated
        function showSlide(index) {
            currentIndex = (index + count) % count;
            slides[currentIndex].loading = 'eager';
            wrapper.style.transform = `translateX(-${currentIndex * 100}%)`;
            prepareNextSlide();
        }

        function restartAutoplay() {
            clearInterval(timer);
            timer = setInterval(() => showSlide(currentIndex + 1), 3000);
        }

        prevButton.addEventListener('click', () => { showSlide(currentIndex - 1); restartAutoplay(); });
        nextButton.addEventListener('click', () => { showSlide(currentIndex + 1); restartAutoplay(); });

        // Swipe on touch screens
        let touchStartX = null;
        container.addEventListener('touchstart', (e) => { touchStartX = e.touches[0].clientX; }, { passive: true });
        container.addEventListener('touchend', (e) => {
            if (touchStartX === null) return;
            const deltaX = e.changedTouches[0].clientX - touchStartX;
            touchStartX = null;
            if (Math.abs(deltaX) > 40) {
                showSlide(currentIndex + (deltaX < 0 ? 1 : -1));
                restartAutoplay();
            }
        });

        restartAutoplay();
        window.addEventListener('load', prepareNextSlide);
    })(document.currentScript.previousElementSibling);
</script>
