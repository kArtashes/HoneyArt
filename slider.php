<?php
// Directory where images are stored
$imageDir = 'images/slider/';
$images = glob($imageDir . '*.{jpg,png,gif,webp}', GLOB_BRACE);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP Slider</title>
    <style>
        /* Basic slider styles */
        .slider-container {
            height: 100%;
            margin: auto;
            overflow: hidden;
            position: relative;
        }

        .slider-wrapper {
            display: flex;
            transition: transform 1s ease-in-out;
        }

        .slider-wrapper img {
            width: 100%;
            height: auto;
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

        .prev {
            left: 10px;
        }

        .next {
            right: 10px;
        }
    </style>
</head>
<body>
    <div class="slider-container">
        <div class="slider-wrapper">
            <?php foreach ($images as $image): ?>
                <img src="<?php echo $image; ?>" alt="Slider Image">
            <?php endforeach; ?>
        </div>
        <button class="prev">❮</button>
        <button class="next">❯</button>
    </div>

    <script>
        // JavaScript for slider functionality
        const sliderWrapper = document.querySelector('.slider-wrapper');
        const images = document.querySelectorAll('.slider-wrapper img');
        const prevButton = document.querySelector('.prev');
        const nextButton = document.querySelector('.next');
        let currentIndex = 0;

        // Move slider
        function moveSlider(index) {
            const slideWidth = images[0].clientWidth;
            sliderWrapper.style.transform = `translateX(-${index * slideWidth}px)`;
        }

        // Previous button functionality
        prevButton.addEventListener('click', () => {
            currentIndex = (currentIndex === 0) ? images.length - 1 : currentIndex - 1;
            moveSlider(currentIndex);
        });

        // Next button functionality
        nextButton.addEventListener('click', () => {
            currentIndex = (currentIndex === images.length - 1) ? 0 : currentIndex + 1;
            moveSlider(currentIndex);
        });

        const slides = document.querySelectorAll('.slider-wrapper img');

        // Function to move slider to the next slide
        function nextSlide() {
            currentIndex = (currentIndex + 1) % slides.length; // Loop back to the start
            const slideWidth = slides[0].clientWidth;
            sliderWrapper.style.transform = `translateX(-${currentIndex * slideWidth}px)`;
        }

        setInterval(nextSlide, 3000);
    </script>
</body>
</html>
