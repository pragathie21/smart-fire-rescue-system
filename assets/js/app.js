/**
 * Smart Fire and Rescue Response System
 * Client-Side Controller & Interactive UI Engine
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Live System Clock
    const clockEl = document.getElementById('systemClock');
    if (clockEl) {
        setInterval(() => {
            const now = new Date();
            const timeStr = now.toLocaleTimeString('en-US', { hour12: false }) + ' IST';
            clockEl.textContent = timeStr;
        }, 1000);
    }

    // 2. Mobile Sidebar Toggle
    const sidebarToggle = document.getElementById('sidebarToggle');
    const appSidebar = document.getElementById('appSidebar');
    if (sidebarToggle && appSidebar) {
        sidebarToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            appSidebar.classList.toggle('open');
        });

        document.addEventListener('click', (e) => {
            if (appSidebar.classList.contains('open') && !appSidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
                appSidebar.classList.remove('open');
            }
        });
    }

    // 3. Interactive Range Sliders (Intensity & Population Exposure)
    const intensitySlider = document.getElementById('intensity');
    const intensityVal = document.getElementById('intensityVal');
    if (intensitySlider && intensityVal) {
        const updateIntensity = () => {
            const val = intensitySlider.value;
            const labels = {
                1: '1 - Minor / Contained',
                2: '2 - Moderate',
                3: '3 - High Escalation',
                4: '4 - Severe Hazard',
                5: '5 - Catastrophic'
            };
            intensityVal.textContent = labels[val] || val;
        };
        intensitySlider.addEventListener('input', updateIntensity);
        updateIntensity();
    }

    const exposureSlider = document.getElementById('population_exposure');
    const exposureVal = document.getElementById('exposureVal');
    if (exposureSlider && exposureVal) {
        const updateExposure = () => {
            const val = exposureSlider.value;
            const labels = {
                1: '1 - Isolated (< 10 people)',
                2: '2 - Low (10-50 people)',
                3: '3 - Medium (50-200 people)',
                4: '4 - High (200-1000 people)',
                5: '5 - Critical Mass (> 1000)'
            };
            exposureVal.textContent = labels[val] || val;
        };
        exposureSlider.addEventListener('input', updateExposure);
        updateExposure();
    }

    // 4. Equipment Checkbox Card Highlighting
    const equipCards = document.querySelectorAll('.equipment-checkbox-card');
    equipCards.forEach(card => {
        const checkbox = card.querySelector('input[type="checkbox"]');
        if (checkbox) {
            const updateCard = () => {
                if (checkbox.checked) {
                    card.classList.add('selected');
                } else {
                    card.classList.remove('selected');
                }
            };
            checkbox.addEventListener('change', updateCard);
            card.addEventListener('click', (e) => {
                if (e.target !== checkbox) {
                    checkbox.checked = !checkbox.checked;
                    updateCard();
                }
            });
            updateCard();
        }
    });

    // 5. Critical Infrastructure Toggle Highlights
    const toggleOptions = document.querySelectorAll('.toggle-option');
    toggleOptions.forEach(opt => {
        const radio = opt.querySelector('input[type="radio"]');
        if (radio) {
            opt.addEventListener('click', () => {
                radio.checked = true;
                toggleOptions.forEach(o => o.classList.remove('active'));
                opt.classList.add('active');
            });
            if (radio.checked) {
                opt.classList.add('active');
            }
        }
    });

    // 6. Global Modal Confirmation for Dangerous / Critical Actions
    const modal = document.getElementById('confirmationModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalBody = document.getElementById('modalBody');
    const modalConfirmBtn = document.getElementById('modalConfirmBtn');
    const modalDismissBtn = document.getElementById('modalDismissBtn');
    const modalCancelBtn = document.getElementById('modalCancelBtn');

    let pendingForm = null;

    if (modal && modalConfirmBtn) {
        document.querySelectorAll('form[data-confirm]').forEach(form => {
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                pendingForm = form;
                const msg = form.getAttribute('data-confirm') || 'Are you sure you want to proceed?';
                const title = form.getAttribute('data-confirm-title') || 'Confirm Emergency Action';
                const actionBtnText = form.getAttribute('data-confirm-btn') || 'Proceed';
                const actionBtnClass = form.getAttribute('data-confirm-class') || 'btn-danger';

                modalTitle.textContent = title;
                modalBody.innerHTML = msg;
                modalConfirmBtn.textContent = actionBtnText;
                modalConfirmBtn.className = 'btn ' + actionBtnClass;
                modal.classList.add('show');
            });
        });

        const closeModal = () => {
            modal.classList.remove('show');
            pendingForm = null;
        };

        if (modalDismissBtn) modalDismissBtn.addEventListener('click', closeModal);
        if (modalCancelBtn) modalCancelBtn.addEventListener('click', closeModal);
        modal.addEventListener('click', (e) => {
            if (e.target === modal) closeModal();
        });

        modalConfirmBtn.addEventListener('click', () => {
            if (pendingForm) {
                const f = pendingForm;
                pendingForm = null;
                modal.classList.remove('show');
                f.submit();
            }
        });
    }

    // 7. Auto-dismiss alerts after 7 seconds
    document.querySelectorAll('.alert-dismissible').forEach(alert => {
        setTimeout(() => {
            if (alert && alert.parentElement) {
                alert.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                setTimeout(() => alert.remove(), 400);
            }
        }, 7000);
    });
});
