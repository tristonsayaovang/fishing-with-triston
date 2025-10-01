(function (w) {
    'use strict';

    const
        document = w.document,
        console = w.console,
        run = () => {

            const imageCarouselContainer = document.querySelector('[data-role~=imageCarouselContainer');

            if (!imageCarouselContainer) {
                return;
            }

            const
                prev = imageCarouselContainer.querySelector('[data-role~=prevButton'),
                next = imageCarouselContainer.querySelector('[data-role~=nextButton'),
                carousel = imageCarouselContainer.querySelector('[data-role~=imageCarousel'),
                carouselSlides = imageCarouselContainer.querySelectorAll('[data-role~=carouselSlide]');

            carouselSlides.forEach((slide) => {
                slide.addEventListener('animationend', (event) => {
                    if (event.animationName === 'nextSlide') {
                        slide.classList.remove('animate-next');

                    }
                    if (event.animationName === 'prevSlide') {
                        slide.classList.remove('animate-prev');

                    }
                })
            })

            const setRotationForNextPhoto = (newIndex) => {
                if (parseInt(newIndex) >= carouselSlides.length - 1) {
                    carouselSlides.forEach((slide) => {
                        slide.classList.remove('next-active-slide');
                    });
                    carouselSlides[0].classList.add('next-active-slide');

                    return;
                }
                carouselSlides.forEach((slide) => {
                    slide.classList.remove('next-active-slide');
                });
                carouselSlides.forEach((slide) => {
                    if (slide.dataset.index === newIndex.toString()) {
                        slide.nextElementSibling.classList.add('next-active-slide');
                    }
                });
            };


            const setCarouselIndex = (newIndex, forwards = true) => {
                carouselSlides.forEach((slide) => {



                    if (slide.dataset.index === carousel.dataset.current) {
                        if (forwards) {
                            slide.classList.add('animate-next');
                        }
                    }


                });

                carousel.dataset.current = newIndex;

                carouselSlides.forEach((slide) => {
                    slide.classList.remove('active');

                    if (slide.dataset.index === newIndex.toString()) {
                        if (!forwards) {
                            slide.classList.add('animate-prev');
                        }
                        slide.classList.add('active');

                    }
                });
                setRotationForNextPhoto(newIndex);

            };



            prev.addEventListener('click', () => {
                let index = parseInt(carousel.dataset.current);

                if (index === 0) {
                    setCarouselIndex(carousel.querySelectorAll('[data-role~=carouselSlide').length - 1, false); // subtract 1 because data-index starts at 0
                    return;
                }

                setCarouselIndex(index - 1, false);


            });

            next.addEventListener('click', () => {
                let index = parseInt(carousel.dataset.current);

                if (index === carousel.querySelectorAll('[data-role~=carouselSlide').length - 1) {
                    setCarouselIndex(0)
                    return;
                }

                setCarouselIndex(index + 1);

            });



        },

        init = () => {
            if (['complete', 'interactive'].includes(document.readyState)) {
                run();
            } else {
                document.addEventListener('DOMContentLoaded', run);
            }
        };

    init();
})

    (window);