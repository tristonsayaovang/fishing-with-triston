(function (w) {
    'use strict';

    const
        document = w.document,
        console = w.console,
        run = () => {

            const header = document.querySelector('[data-role~=siteHeader]');

            if (!header) {
                return;
            }

            const mobileNav = header.querySelector('[data-role~=mobileNav]');
            const mobileNavList = header.querySelector('[data-role~=mobileNavList]');
            const primaryNav = header.querySelector('#block-fishing-with-triston-main-menu');
            const mobileMenuButton = header.querySelector('[data-role~=mobileMenuButton]');
            const dialog = header.querySelector('[data-role~=mobileNavDialog]');
            const closeModal = header.querySelector('[data-role~=closeModal]');


            primaryNav.querySelectorAll('ul:not(.contextual-links) li').forEach((link) => {
                const clonedLink = link.cloneNode(true);
                clonedLink.classList.add('mobile-navigation-link');
                mobileNavList.appendChild(clonedLink);
            });


            mobileMenuButton.addEventListener('click', () => {
                dialog.showModal();
            });


            closeModal.addEventListener('click', () => {
                dialog.close();
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