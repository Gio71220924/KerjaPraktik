/*!
    * Start Bootstrap - SB Admin v7.0.7 (https://startbootstrap.com/template/sb-admin)
    * Copyright 2013-2023 Start Bootstrap
    * Licensed under MIT (https://github.com/StartBootstrap/startbootstrap-sb-admin/blob/master/LICENSE)
    */
    // 
// Scripts
// 

window.addEventListener('DOMContentLoaded', event => {

    // Toggle the side navigation
    const sidebarToggle = document.body.querySelector('#sidebarToggle');
    if (sidebarToggle) {
        // Uncomment Below to persist sidebar toggle between refreshes
        // if (localStorage.getItem('sb|sidebar-toggle') === 'true') {
        //     document.body.classList.toggle('sb-sidenav-toggled');
        // }
        sidebarToggle.addEventListener('click', event => {
            event.preventDefault();
            document.body.classList.toggle('sb-sidenav-toggled');
            localStorage.setItem('sb|sidebar-toggle', document.body.classList.contains('sb-sidenav-toggled'));
        });
    }

    // Adjustable sidebar width (drag to resize)
    const resizeHandle = document.getElementById('layoutSidenav_resize');
    const root = document.documentElement;
    const nav = document.getElementById('layoutSidenav_nav');

    const parsePx = (val, fallback) => {
        const n = parseFloat(val);
        return Number.isFinite(n) ? n : fallback;
    };

    const getClamp = () => {
        const styles = getComputedStyle(root);
        const min = parsePx(styles.getPropertyValue('--sidebar-min-width'), 180);
        const max = parsePx(styles.getPropertyValue('--sidebar-max-width'), 360);
        return { min, max };
    };

    const applyWidth = (px) => {
        const { min, max } = getClamp();
        const clamped = Math.min(max, Math.max(min, px));
        root.style.setProperty('--sidebar-width', `${clamped}px`);
        localStorage.setItem('sb|sidebar-width', String(clamped));
    };

    // Restore last width
    const saved = localStorage.getItem('sb|sidebar-width');
    if (saved) {
        const width = parsePx(saved, 225);
        if (width) applyWidth(width);
    }

    if (resizeHandle && nav) {
        let startX = 0;
        let startWidth = 0;
        const onMove = (e) => {
            const dx = e.clientX - startX;
            applyWidth(startWidth + dx);
        };
        const onUp = () => {
            window.removeEventListener('mousemove', onMove);
            window.removeEventListener('mouseup', onUp);
            document.body.classList.remove('resizing-sidebar');
        };
        resizeHandle.addEventListener('mousedown', (e) => {
            // Only enable when sidebar visible
            if (document.body.classList.contains('sb-sidenav-toggled') && window.innerWidth < 992) {
                return;
            }
            startX = e.clientX;
            startWidth = nav.getBoundingClientRect().width;
            document.body.classList.add('resizing-sidebar');
            window.addEventListener('mousemove', onMove);
            window.addEventListener('mouseup', onUp);
            e.preventDefault();
        });
    }

});
