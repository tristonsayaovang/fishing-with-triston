(function (w) {
    'use strict';

    const
        document = w.document,
        console = w.console,
        run = () => {

            const seasonsTabs = document.querySelector('[data-role~=seasonsTabs');

            if (!seasonsTabs) {
                return;
            }

            const tabs = seasonsTabs.querySelectorAll('[data-role~=tab');

            tabs.forEach((tab) => {
                tab.addEventListener('click', () => {
                    seasonsTabs.dataset.activeTab = tab.dataset.season;
                    tabs.forEach((singleTab) => {
                        singleTab.classList.remove('active');
                    })
                    tab.classList.add('active');
                })
            })
            
            
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