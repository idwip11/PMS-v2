/* =====================================================
   SIDEBAR RESPONSIVE TOGGLE
   ===================================================== */
(function () {
    'use strict';

    function initSidebarToggle() {
        const sidebar = document.getElementById('app-sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        const toggleBtn = document.getElementById('sidebar-toggle-btn');
        const closeBtn = document.getElementById('sidebar-close-btn');

        if (!sidebar) return;

        function openSidebar() {
            sidebar.classList.add('is-open');
            if (overlay) {
                overlay.style.display = 'block';
                // Trigger reflow for transition
                overlay.offsetHeight; // eslint-disable-line no-unused-expressions
                overlay.classList.add('is-visible');
            }
            document.body.style.overflow = 'hidden';
        }

        function closeSidebar() {
            sidebar.classList.remove('is-open');
            if (overlay) {
                overlay.classList.remove('is-visible');
                setTimeout(function () {
                    if (!overlay.classList.contains('is-visible')) {
                        overlay.style.display = 'none';
                    }
                }, 280);
            }
            document.body.style.overflow = '';
        }

        function isMobileTablet() {
            return window.innerWidth < 992;
        }

        if (toggleBtn) {
            toggleBtn.addEventListener('click', function () {
                if (sidebar.classList.contains('is-open')) {
                    closeSidebar();
                } else {
                    openSidebar();
                }
            });
        }

        if (closeBtn) {
            closeBtn.addEventListener('click', closeSidebar);
        }

        if (overlay) {
            overlay.addEventListener('click', closeSidebar);
        }

        // Auto-close sidebar when a nav link is clicked on mobile/tablet
        sidebar.addEventListener('click', function (event) {
            if (!isMobileTablet()) return;
            const link = event.target.closest('a.sidebar-nav-link');
            if (link) {
                closeSidebar();
            }
        });

        // On resize to desktop, reset sidebar state
        window.addEventListener('resize', function () {
            if (!isMobileTablet()) {
                sidebar.classList.remove('is-open');
                if (overlay) {
                    overlay.classList.remove('is-visible');
                    overlay.style.display = 'none';
                }
                document.body.style.overflow = '';
            }
        }, { passive: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSidebarToggle);
    } else {
        initSidebarToggle();
    }
})();

function getCookieValue(name) {
    return document.cookie
        .split('; ')
        .find((row) => row.startsWith(`${name}=`))
        ?.split('=')
        .slice(1)
        .join('=') || '';
}

function getCurrentCsrfToken() {
    const cookieName = document.querySelector('meta[name="csrf-cookie-name"]')?.content;
    const cookieToken = cookieName ? decodeURIComponent(getCookieValue(cookieName)) : '';
    return cookieToken || document.querySelector('meta[name="csrf-token"]')?.content || '';
}

function syncCsrfToken(token = getCurrentCsrfToken()) {
    if (!token) return;

    const meta = document.querySelector('meta[name="csrf-token"]');
    const tokenName = document.querySelector('meta[name="csrf-token-name"]')?.content || 'csrf_test_name';
    if (meta) meta.content = token;
    document.querySelectorAll(`input[name="${tokenName}"]`).forEach((input) => {
        input.value = token;
    });
}

document.body.addEventListener('htmx:configRequest', (event) => {
    const token = getCurrentCsrfToken();
    if (!token) return;

    const tokenName = document.querySelector('meta[name="csrf-token-name"]')?.content || 'csrf_test_name';
    syncCsrfToken(token);
    event.detail.headers['X-CSRF-TOKEN'] = token;
    if (event.detail.parameters) event.detail.parameters[tokenName] = token;
});

document.body.addEventListener('htmx:afterRequest', (event) => {
    const token = event.detail.xhr.getResponseHeader('X-CSRF-TOKEN');
    if (token) syncCsrfToken(token);
    else syncCsrfToken();
});

document.body.addEventListener('opsChanged', () => {
    const panel = document.getElementById('ops-panel');
    if (!panel || !window.htmx) return;

    const panelUrl = panel.dataset.panelUrl;
    if (!panelUrl) return;

    // Refresh the active Operations view after a save without inheriting stale
    // tab values into clicks on the Inventory sub-navigation.
    window.htmx.ajax('GET', `${panelUrl}${window.location.search}`, {
        target: '#ops-panel',
        swap: 'innerHTML',
    });
});

document.body.addEventListener('htmx:beforeRequest', (event) => {
    const modalContent = event.target.closest?.('#work-order-modal-content');
    const modalBody = modalContent?.querySelector('.modal-body');
    if (modalContent && modalBody) {
        modalContent.dataset.modalScrollTop = String(modalBody.scrollTop);
    }
});

document.body.addEventListener('htmx:responseError', (event) => {
    const feedback = document.getElementById('booking-form-feedback');
    if (!feedback) return;

    if (event.detail.xhr.status === 403) {
        syncCsrfToken();
        feedback.innerHTML = '<div class="alert alert-danger mb-0">Sesi form sudah kedaluwarsa. Muat ulang halaman lalu coba kembali.</div>';
    } else {
        feedback.innerHTML = '<div class="alert alert-danger mb-0">Booking gagal diproses oleh server. Silakan coba kembali.</div>';
    }
    feedback.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
});

function showReservationDetailError(message) {
    const container = document.getElementById('modal-container');
    if (!container) return;

    container.innerHTML = `<div class="modal fade" id="dynamicModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0">
            <div class="modal-header"><h2 class="modal-title h5 mb-0">Detail Reservasi</h2><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body"><div class="alert alert-danger mb-0">${message}</div></div>
        </div></div>
    </div>`;
    const modalElement = container.querySelector('#dynamicModal');
    if (modalElement) bootstrap.Modal.getOrCreateInstance(modalElement).show();
}

async function openReservationDetail(url, button) {
    const container = document.getElementById('modal-container');
    if (!container || !url) return;

    button.disabled = true;
    const originalLabel = button.textContent;
    button.textContent = 'Memuat...';
    try {
        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: { 'HX-Request': 'true', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const token = response.headers.get('X-CSRF-TOKEN');
        if (token) syncCsrfToken(token);

        const content = await response.text();
        if (!response.ok || response.url.includes('/login') || !content.includes('id="dynamicModal"')) {
            throw new Error('Detail reservasi tidak dapat dimuat. Pastikan sesi login masih aktif lalu coba lagi.');
        }

        const oldModal = container.querySelector('#dynamicModal');
        if (oldModal) {
            bootstrap.Modal.getInstance(oldModal)?.dispose();
            oldModal.remove();
            cleanupModalBackdrop();
        }
        container.innerHTML = content;
        // Detail reservasi dimuat melalui fetch, sehingga HTMX perlu memproses
        // elemen baru agar hx-post (Check In, Cancel, dan lainnya) tidak jatuh
        // kembali menjadi submit form GET bawaan browser.
        window.htmx?.process(container);
        const modalElement = container.querySelector('#dynamicModal');
        bootstrap.Modal.getOrCreateInstance(modalElement).show();
    } catch (error) {
        showReservationDetailError(error.message || 'Detail reservasi tidak dapat dimuat. Silakan coba lagi.');
    } finally {
        button.disabled = false;
        button.textContent = originalLabel;
    }
}

document.body.addEventListener('click', (event) => {
    const guestSuggestion = event.target.closest('[data-guest-suggestion]');
    if (guestSuggestion) {
        const form = guestSuggestion.closest('[data-booking-form]');
        const guests = form ? bookingGuestSuggestions.get(form) : [];
        const guest = guests?.[Number(guestSuggestion.dataset.guestSuggestion)];
        if (form && guest) selectBookingGuest(form, guest);
        return;
    }

    const button = event.target.closest('[data-reservation-detail]');
    if (!button) return;

    event.preventDefault();
    openReservationDetail(button.dataset.reservationDetail, button);
});

function parseCurrencyInput(value) {
    const digits = String(value ?? '').replace(/[^0-9]/g, '');
    return digits === '' ? 0 : Number(digits);
}

function formatCurrencyInput(input) {
    if (!(input instanceof HTMLInputElement)) return;
    const digits = input.value.replace(/[^0-9]/g, '');
    input.value = digits === '' ? '' : Number(digits).toLocaleString('id-ID');
}

function formatCurrencyInputs(root = document) {
    root.querySelectorAll?.('[data-currency-input]').forEach(formatCurrencyInput);
}

function normalizeCurrencyInputs(form) {
    form?.querySelectorAll('[data-currency-input]').forEach((input) => {
        input.value = String(parseCurrencyInput(input.value));
    });
}

document.addEventListener('DOMContentLoaded', () => formatCurrencyInputs(document));
document.addEventListener('submit', (event) => normalizeCurrencyInputs(event.target), true);
document.body.addEventListener('htmx:afterSettle', (event) => formatCurrencyInputs(event.detail?.target || document));

document.body.addEventListener('focusin', (event) => {
    const input = event.target;
    if (!(input instanceof HTMLInputElement) || input.type !== 'number' || input.value !== '0') return;

    input.value = '';
    input.dataset.zeroCleared = '1';
});

document.body.addEventListener('focusout', (event) => {
    const input = event.target;
    if (!(input instanceof HTMLInputElement) || input.type !== 'number' || input.dataset.zeroCleared !== '1') return;

    if (input.value === '') {
        input.value = '0';
        input.dispatchEvent(new Event('input', { bubbles: true }));
    }
    delete input.dataset.zeroCleared;
});

document.body.addEventListener('focusout', (event) => {
    if (!event.target.matches?.('[data-guest-suggestion-input]')) return;
    const form = event.target.closest('[data-booking-form]');
    window.setTimeout(() => hideBookingGuestSuggestions(form), 150);
});

document.body.addEventListener('click', (event) => {
    const resetConfirmedTotal = event.target.closest('[data-reset-confirmed-total]');
    if (resetConfirmedTotal) {
        const bookingForm = resetConfirmedTotal.closest('[data-booking-form]');
        const confirmedTotal = bookingForm?.querySelector('[data-confirmed-total]');
        if (confirmedTotal) delete confirmedTotal.dataset.manualTotal;
        syncBookingPayment(bookingForm);
        return;
    }

    const tabLink = event.target.closest('[data-tab-link]');
    if (!tabLink) return;
    const tabNav = tabLink.closest('.page-tabs');
    if (!tabNav) return;

    tabNav.querySelectorAll('[data-tab-link]').forEach((link) => {
        link.classList.remove('active');
        link.classList.add('bg-white');
    });
    tabLink.classList.add('active');
    tabLink.classList.remove('bg-white');

    const targetSelector = tabLink.getAttribute('hx-target');
    const panelUrl = tabLink.getAttribute('hx-get');
    if (targetSelector && panelUrl) {
        const target = document.querySelector(targetSelector);
        if (target) target.setAttribute('hx-get', panelUrl);
    }
});

document.body.addEventListener('change', (event) => {
    const expenseCategory = event.target.closest('[data-expense-category]');
    if (expenseCategory) {
        syncExpenseGuesthouseScope(expenseCategory.closest('[data-expense-form]'));
        return;
    }

    const expenseGuesthouses = event.target.closest('[data-expense-guesthouses]');
    if (expenseGuesthouses) {
        syncExpenseGuesthouseScope(expenseGuesthouses.closest('[data-expense-form]'));
        return;
    }

    const reconciliationToggle = event.target.closest('[data-reconciliation-toggle]');
    if (reconciliationToggle) {
        reconciliationToggle.form?.requestSubmit();
        return;
    }

    const bookingForm = event.target.closest('[data-booking-form]');
    if (bookingForm && event.target.matches('[data-payment-type]')) {
        syncBookingPayment(bookingForm);
    }
    if (bookingForm && event.target.matches('[data-payment-method]')) {
        syncBookingPayment(bookingForm);
    }
    if (bookingForm && event.target.matches('[data-booking-source]')) {
        syncBookingComUnknownTotal(bookingForm);
        syncBookingPayment(bookingForm);
    }
    if (bookingForm && event.target.matches('[data-agent-guest-toggle]')) {
        syncAgentGuest(bookingForm);
    }
    if (bookingForm && event.target.matches('[name="check_in_date"], [name="check_out_date"]')) {
        syncBookingPayment(bookingForm);
        syncBookingRoomMapLink(bookingForm);
        refreshBookingRoomAvailability(bookingForm);
    }

    const paymentLogForm = event.target.closest('[data-payment-log-form]');
    if (paymentLogForm && event.target.matches('[data-payment-log-method]')) {
        syncPaymentProofField(paymentLogForm, event.target.value === 'Transfer');
    }
    if (event.target.matches('[data-image-file-input]')) {
        const imageForm = event.target.closest('[data-image-upload-form]');
        if (imageForm) delete imageForm.dataset.compressionReady;
    }

    const roomPicker = event.target.closest('[data-booking-room-picker]');
    if (roomPicker && event.target.matches('input[name="room_ids[]"]')) {
        const form = event.target.closest('[data-booking-form]');
        updateBookingRoomCount(roomPicker);
        syncExtraBedPicker(form);
        syncBookingPayment(form);
    }

    const ticketForm = event.target.closest('[data-maintenance-ticket-form]');
    if (!ticketForm || ! event.target.matches('[data-room-select], [data-category-select]')) return;

    const roomId = ticketForm.querySelector('[data-room-select]')?.value || '';
    const categoryId = ticketForm.querySelector('[data-category-select]')?.value || '';
    const assetSelect = ticketForm.querySelector('[data-asset-select]');
    if (!assetSelect) return;

    let selectedStillValid = assetSelect.value === '';
    assetSelect.querySelectorAll('option[data-room-id]').forEach((option) => {
        const visible = option.dataset.roomId === roomId && option.dataset.categoryId === categoryId;
        option.hidden = !visible;
        option.disabled = !visible;
        if (visible && option.value === assetSelect.value) selectedStillValid = true;
    });
    if (!selectedStillValid) assetSelect.value = '';
    assetSelect.disabled = roomId === '' || categoryId === '';
});

function syncExpenseGuesthouseScope(form) {
    const category = form?.querySelector('[data-expense-category]');
    const guesthouses = form?.querySelector('[data-expense-guesthouses]');
    const help = form?.querySelector('[data-expense-guesthouse-help]');
    if (!category || !guesthouses) return;

    const isFeeKerjasama = category.value === 'Fee Kerjasama';
    const companyOption = guesthouses.querySelector('option[value="0"]');
    guesthouses.querySelectorAll('option:not([value="0"])').forEach((option) => {
        option.disabled = isFeeKerjasama;
        if (isFeeKerjasama || companyOption?.selected) option.selected = false;
    });
    if (companyOption && isFeeKerjasama) companyOption.selected = true;
    if (help) {
        help.textContent = isFeeKerjasama
            ? 'Fee Kerjasama dicatat sebagai biaya Perusahaan dan tidak masuk laporan Owner GH.'
            : 'Pilih lebih dari satu dengan Cmd/Ctrl + klik. Pilih Perusahaan untuk biaya perusahaan.';
    }
}

document.body.addEventListener('input', (event) => {
    if (event.target.matches?.('[data-currency-input]')) formatCurrencyInput(event.target);
    const bookingForm = event.target.closest('[data-booking-form]');
    if (!bookingForm) return;
    if (event.target.matches('[data-guest-suggestion-input]')) {
        bookingForm.querySelector('[data-selected-guest-id]').value = '';
        loadBookingGuestSuggestions(bookingForm, event.target.value);
        return;
    }
    if (event.target.matches('[data-confirmed-total]')) {
        event.target.dataset.manualTotal = '1';
        syncBookingPayment(bookingForm);
        return;
    }
    if (event.target.matches('[data-extra-bed-quantity]')) {
        clampExtraBedQuantity(event.target, bookingForm);
        syncExtraBedPicker(bookingForm);
        return;
    }
    if (!event.target.matches('[name="check_in_date"], [name="check_out_date"], [name$="_cost"]')) return;
    syncBookingPayment(bookingForm);
});

const bookingGuestSuggestions = new WeakMap();
const bookingGuestSuggestionRequests = new WeakMap();

function hideBookingGuestSuggestions(form) {
    const list = form?.querySelector('[data-guest-suggestion-list]');
    if (!list) return;
    list.classList.add('d-none');
    list.replaceChildren();
}

function selectBookingGuest(form, guest) {
    form.querySelector('[name="guest_name"]').value = guest.full_name || '';
    form.querySelector('[data-selected-guest-id]').value = guest.id || '';
    ['email', 'phone', 'origin', 'guest_notes'].forEach((name) => {
        const input = form.querySelector(`[name="${name}"]`);
        if (input) input.value = guest[name === 'guest_notes' ? 'notes' : name] || '';
    });
    hideBookingGuestSuggestions(form);
}

async function loadBookingGuestSuggestions(form, query) {
    const list = form.querySelector('[data-guest-suggestion-list]');
    const url = form.dataset.guestSuggestionsUrl;
    if (!list || !url || query.trim().length === 0) {
        hideBookingGuestSuggestions(form);
        return;
    }

    bookingGuestSuggestionRequests.get(form)?.abort();
    const controller = new AbortController();
    bookingGuestSuggestionRequests.set(form, controller);

    try {
        const response = await fetch(`${url}?q=${encodeURIComponent(query.trim())}`, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        });
        if (!response.ok || bookingGuestSuggestionRequests.get(form) !== controller) return;
        const payload = await response.json();
        const guests = Array.isArray(payload.guests) ? payload.guests : [];
        bookingGuestSuggestions.set(form, guests);
        if (guests.length === 0) {
            hideBookingGuestSuggestions(form);
            return;
        }

        list.innerHTML = guests.map((guest, index) => {
            const details = [guest.phone, guest.email].filter(Boolean).join(' · ');
            return `<button class="list-group-item list-group-item-action text-start" type="button" data-guest-suggestion="${index}">`
                + `<strong class="d-block">${escapeHtml(guest.full_name || '-')}</strong>`
                + (details ? `<small class="text-secondary">${escapeHtml(details)}</small>` : '')
                + '</button>';
        }).join('');
        list.classList.remove('d-none');
    } catch (error) {
        if (error.name !== 'AbortError') hideBookingGuestSuggestions(form);
    }
}

function formatRupiah(value) {
    return `Rp${Math.max(0, Math.round(value)).toLocaleString('id-ID')}`;
}

function localDateValue() {
    const now = new Date();
    const pad = (value) => String(value).padStart(2, '0');
    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
}

function getBookingStandardTotal(form) {
    if (!form) return 0;
    const checkIn = form.querySelector('[name="check_in_date"]')?.value || '';
    const checkOut = form.querySelector('[name="check_out_date"]')?.value || '';
    const start = checkIn ? new Date(`${checkIn}T00:00:00`) : null;
    const end = checkOut ? new Date(`${checkOut}T00:00:00`) : null;
    const nights = start && end && end >= start ? Math.max(1, Math.round((end - start) / 86400000)) : 0;
    const roomTotal = Array.from(form.querySelectorAll('[name="room_ids[]"]:checked')).reduce((sum, input) => {
        const price = Number(input.closest('[data-booking-room-option]')?.dataset.pricePerNight || 0);
        return sum + (price * nights);
    }, 0);
    const services = Array.from(form.querySelectorAll('[name$="_cost"]')).reduce((sum, input) => sum + Math.max(0, parseCurrencyInput(input.value)), 0);
    return roomTotal + services;
}

function getBookingConfirmedTotal(form) {
    const input = form?.querySelector('[data-confirmed-total]');
    const standardTotal = getBookingStandardTotal(form);
    const help = form?.querySelector('[data-confirmed-total-help]');
    if (!input) return standardTotal;

    if (input.dataset.manualTotal !== '1') input.value = String(Math.round(standardTotal));
    formatCurrencyInput(input);
    const confirmedTotal = input.value === '' ? standardTotal : Math.max(0, parseCurrencyInput(input.value));
    const isBookingCom = form.querySelector('[data-booking-source]')?.value === 'Booking.com';
    if (help) help.textContent = isBookingCom
        ? 'Nominal Booking.com boleh Rp0 dan diisi nanti saat pencairan diketahui.'
        : (input.dataset.manualTotal === '1'
        ? `Harga standar: ${formatRupiah(standardTotal)}. Harga khusus akan digunakan untuk booking ini.`
        : `Harga standar: ${formatRupiah(standardTotal)}. Ubah jika ada harga khusus atau potongan.`);
    return confirmedTotal;
}

function syncBookingComUnknownTotal(form) {
    const source = form?.querySelector('[data-booking-source]')?.value;
    const total = form?.querySelector('[data-confirmed-total]');
    if (!total) return;

    if (source === 'Booking.com') {
        total.dataset.manualTotal = '1';
        total.dataset.bookingComUnknown = '1';
        total.value = '0';
        formatCurrencyInput(total);
        return;
    }
    if (total.dataset.bookingComUnknown === '1') {
        delete total.dataset.bookingComUnknown;
        delete total.dataset.manualTotal;
    }
}

function syncAgentGuest(form) {
    const toggle = form?.querySelector('[data-agent-guest-toggle]');
    const field = form?.querySelector('[data-agent-name-field]');
    const input = form?.querySelector('[data-agent-name-input]');
    const isAgentGuest = Boolean(toggle?.checked);

    if (field) field.classList.toggle('d-none', !isAgentGuest);
    if (input) {
        input.disabled = !isAgentGuest;
        input.required = isAgentGuest;
        if (!isAgentGuest) input.value = '';
    }
}

function syncBookingPayment(form) {
    if (!form) return;

    const checkIn = form.querySelector('[name="check_in_date"]')?.value || '';
    const checkOut = form.querySelector('[name="check_out_date"]')?.value || '';
    const stayType = form.querySelector('[data-stay-type]');
    if (stayType) stayType.textContent = checkIn && checkOut && checkIn === checkOut
        ? 'Transit: kamar terpakai pada tanggal ini dan dihitung setara 1 malam.'
        : 'Check-in dan check-out di tanggal yang sama akan dicatat sebagai transit.';
    const confirmedTotal = getBookingConfirmedTotal(form);
    const type = form.querySelector('[data-payment-type]')?.value || 'unpaid';
    const amount = form.querySelector('[data-payment-amount]');
    const method = form.querySelector('[data-payment-method]');
    const paymentDate = form.querySelector('[data-payment-date]');
    const typeHelp = form.querySelector('[data-payment-type-help]');
    const paymentDateHelp = form.querySelector('[data-payment-date-help]');
    const hasPayment = type !== 'unpaid';
    const otaSources = ['Booking.com', 'Agoda', 'Traveloka', 'Airbnb', 'Tiket.com'];
    const isOtaSource = otaSources.includes(form.querySelector('[data-booking-source]')?.value || '');
    if (method && isOtaSource) {
        method.value = 'OTA';
        method.dataset.forcedOta = '1';
    } else if (method?.dataset.forcedOta === '1') {
        method.value = 'Cash';
        delete method.dataset.forcedOta;
    }
    const isOta = isOtaSource || method?.value === 'OTA';
    const isTransfer = hasPayment && method?.value === 'Transfer';

    if (amount) {
        amount.disabled = !hasPayment;
        amount.readOnly = type === 'full' || type === 'dp';
        if (type === 'unpaid') amount.value = '0';
        if (type === 'dp') amount.value = String(Math.round(confirmedTotal * 0.5));
        if (type === 'full') amount.value = String(Math.round(confirmedTotal));
        formatCurrencyInput(amount);
    }
    if (method) method.disabled = !hasPayment || isOtaSource;
    if (paymentDate) {
        paymentDate.disabled = !hasPayment || isOta;
        paymentDate.required = hasPayment && !isOta;
        if (!hasPayment || isOta) paymentDate.value = '';
        if (hasPayment && !isOta && paymentDate.value === '') paymentDate.value = localDateValue();
    }
    if (typeHelp) typeHelp.textContent = type === 'full'
        ? `Nominal otomatis mengikuti total booking: ${formatRupiah(confirmedTotal)}.`
        : (type === 'dp' ? `Nominal DP otomatis 50% dari total booking: ${formatRupiah(confirmedTotal * 0.5)}.` : 'Belum ada pembayaran yang dicatat.');
    if (paymentDateHelp) paymentDateHelp.textContent = hasPayment && isOta
        ? 'Tanggal pencairan OTA diisi nanti dari detail reservasi setelah dana diterima.'
        : (hasPayment ? 'Tanggal saat pembayaran diterima.' : 'Pilih DP atau Lunas untuk mencatat pembayaran.');
    syncPaymentProofField(form, isTransfer);
}

function syncPaymentProofField(form, visible) {
    const field = form?.querySelector('[data-payment-proof-field]');
    const input = field?.querySelector('[data-image-file-input]');
    if (!field || !input) return;

    field.classList.toggle('d-none', !visible);
    input.disabled = !visible;
    if (!visible) {
        input.value = '';
        const status = field.querySelector('[data-image-compression-status]');
        if (status) status.textContent = '';
    }
}

function updateBookingRoomCount(roomPicker) {
    const count = roomPicker.querySelectorAll('input[name="room_ids[]"]:checked').length;
    const countLabel = roomPicker.querySelector('[data-booking-room-count]');
    if (countLabel) countLabel.textContent = `${count} kamar dipilih`;
}

function syncBookingRoomMapLink(form) {
    const link = form?.querySelector('[data-booking-room-map]');
    const checkIn = form?.querySelector('[name="check_in_date"]')?.value || '';
    if (!link) return;

    const url = new URL(link.href, window.location.origin);
    if (/^\d{4}-\d{2}-\d{2}$/.test(checkIn)) {
        url.searchParams.set('month', checkIn.slice(0, 7));
    } else {
        url.searchParams.delete('month');
    }
    link.href = url.toString();
}

function totalExtraBedQuantity(form) {
    return Array.from(form?.querySelectorAll('[data-extra-bed-quantity]:not(:disabled)') || [])
        .reduce((sum, input) => sum + Math.max(0, Number(input.value) || 0), 0);
}

function syncExtraBedPicker(form) {
    const picker = form?.querySelector('[data-extra-bed-picker]');
    const roomPicker = form?.querySelector('[data-booking-room-picker]');
    if (!picker || !roomPicker) return;

    const selectedRooms = new Set(Array.from(roomPicker.querySelectorAll('input[name="room_ids[]"]:checked')).map((input) => input.value));
    picker.querySelectorAll('[data-extra-bed-option]').forEach((option) => {
        const visible = selectedRooms.has(option.dataset.roomId || '');
        const input = option.querySelector('[data-extra-bed-quantity]');
        option.classList.toggle('d-none', !visible);
        if (input) {
            input.disabled = !visible;
            if (!visible) input.value = '0';
        }
    });
    picker.classList.toggle('d-none', selectedRooms.size === 0);

    const summary = picker.querySelector('[data-extra-bed-summary]');
    if (summary) summary.textContent = `${totalExtraBedQuantity(form)} unit Extra Bed dipilih`;
}

function clampExtraBedQuantity(input, form) {
    const picker = form?.querySelector('[data-extra-bed-picker]');
    if (!picker) return;

    const available = Math.max(0, Number(picker.dataset.extraBedAvailable || 0));
    const usedByOtherRooms = Array.from(form.querySelectorAll('[data-extra-bed-quantity]'))
        .filter((other) => other !== input && !other.disabled)
        .reduce((sum, other) => sum + Math.max(0, Number(other.value) || 0), 0);
    const allowedForThisRoom = Math.max(0, available - usedByOtherRooms);
    const current = Math.max(0, Number(input.value) || 0);
    input.value = String(Math.min(current, allowedForThisRoom));
}

function syncExtraBedAvailability(form, availability = {}) {
    const picker = form?.querySelector('[data-extra-bed-picker]');
    if (!picker) return;

    const available = Math.max(0, Number(availability.available || 0));
    const total = Math.max(0, Number(availability.total_stock || 0));
    picker.dataset.extraBedAvailable = String(available);
    picker.querySelectorAll('[data-extra-bed-quantity]').forEach((input) => {
        input.max = String(available);
        if (Number(input.value) > available) input.value = String(available);
    });

    const label = picker.querySelector('[data-extra-bed-availability]');
    if (label) label.textContent = total > 0
        ? `Stok tersedia: ${available} unit dari ${total} total.`
        : 'Stok tersedia mengikuti tanggal menginap.';

    syncExtraBedPicker(form);
}

async function refreshBookingRoomAvailability(form) {
    const checkIn = form.querySelector('[name="check_in_date"]')?.value || '';
    const checkOut = form.querySelector('[name="check_out_date"]')?.value || '';
    const picker = form.querySelector('[data-booking-room-picker]');
    const status = picker?.querySelector('[data-booking-room-availability-status]');
    if (!picker) return;

    if (!checkIn || !checkOut || checkOut < checkIn) {
        picker.querySelectorAll('[data-booking-room-option]').forEach((option) => {
            const input = option.querySelector('input[name="room_ids[]"]');
            const reason = option.querySelector('[data-booking-room-reason]');
            if (input) {
                input.checked = false;
                input.disabled = true;
            }
            option.classList.add('is-disabled');
            if (reason) reason.textContent = ' · Pilih tanggal yang valid terlebih dahulu';
        });
        if (status) status.textContent = 'Check-out tidak boleh sebelum check-in.';
        updateBookingRoomCount(picker);
        syncExtraBedAvailability(form, { total_stock: 0, rented: 0, available: 0 });
        syncBookingPayment(form);
        return;
    }

    if (status) status.textContent = 'Memeriksa ketersediaan kamar...';
    try {
        const url = new URL(form.dataset.availabilityUrl, window.location.origin);
        url.searchParams.set('check_in_date', checkIn);
        url.searchParams.set('check_out_date', checkOut);
        const response = await fetch(url, { headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error('Availability request failed');
        const data = await response.json();
        const rooms = new Map((data.rooms || []).map((room) => [String(room.id), room]));

        picker.querySelectorAll('[data-booking-room-option]').forEach((option) => {
            const input = option.querySelector('input[name="room_ids[]"]');
            const reason = option.querySelector('[data-booking-room-reason]');
            const room = rooms.get(input?.value || '');
            const available = room?.available === true;
            if (input) {
                if (!available) input.checked = false;
                input.disabled = !available;
            }
            option.classList.toggle('is-disabled', !available);
            if (reason) reason.textContent = room?.reason ? ` · ${room.reason}` : '';
        });
        if (status) status.textContent = data.message || 'Ketersediaan kamar diperbarui.';
        updateBookingRoomCount(picker);
        syncExtraBedAvailability(form, data.extra_beds || {});
        syncBookingPayment(form);
    } catch (error) {
        if (status) status.textContent = 'Ketersediaan kamar tidak dapat diperiksa. Silakan coba lagi.';
        syncExtraBedPicker(form);
        syncBookingPayment(form);
    }
}

async function compressImageForUpload(file) {
    const targetBytes = 200 * 1024;
    if (!/^image\/(jpeg|png|webp)$/.test(file.type) || file.size <= targetBytes) {
        return { file, message: file.size <= targetBytes ? 'Ukuran foto sudah di bawah 200 KB.' : '' };
    }

    const imageUrl = URL.createObjectURL(file);
    const image = new Image();
    try {
        await new Promise((resolve, reject) => {
            image.onload = resolve;
            image.onerror = reject;
            image.src = imageUrl;
        });

        let blob = null;
        let smallestBlob = null;
        for (const maxEdge of [1600, 1280, 1024, 800, 640, 480]) {
            const scale = Math.min(1, maxEdge / Math.max(image.naturalWidth, image.naturalHeight));
            const canvas = document.createElement('canvas');
            canvas.width = Math.max(1, Math.round(image.naturalWidth * scale));
            canvas.height = Math.max(1, Math.round(image.naturalHeight * scale));
            const context = canvas.getContext('2d', { alpha: false });
            context.fillStyle = '#fff';
            context.fillRect(0, 0, canvas.width, canvas.height);
            context.drawImage(image, 0, 0, canvas.width, canvas.height);

            for (const quality of [0.82, 0.72, 0.62, 0.52, 0.42, 0.32]) {
                const candidate = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', quality));
                if (candidate && (!smallestBlob || candidate.size < smallestBlob.size)) smallestBlob = candidate;
                if (candidate && candidate.size <= targetBytes) {
                    blob = candidate;
                    break;
                }
            }
            if (blob) break;
        }
        blob ||= smallestBlob;
        if (!blob) {
            return { file, message: 'Kompresi browser dilewati; server tetap akan memproses foto.' };
        }

        const name = file.name.replace(/\.[^.]+$/, '') + '.jpg';
        const compressedFile = new File([blob], name, { type: 'image/jpeg', lastModified: Date.now() });
        return {
            file: compressedFile,
            message: compressedFile.size <= targetBytes
                ? `Foto siap diunggah (${Math.ceil(compressedFile.size / 1024)} KB).`
                : 'Foto diperkecil; server akan menyelesaikan kompresi hingga maksimal 200 KB.',
        };
    } finally {
        URL.revokeObjectURL(imageUrl);
    }
}

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function refreshDynamicModalFromHtml(html) {
    const container = document.getElementById('modal-container');
    if (!container || !html.includes('id="dynamicModal"')) return false;

    const oldModal = container.querySelector('#dynamicModal');
    if (oldModal) {
        bootstrap.Modal.getInstance(oldModal)?.dispose();
        oldModal.classList.remove('show');
        cleanupModalBackdrop();
    }

    container.innerHTML = html;
    const modalElement = container.querySelector('#dynamicModal');
    if (!modalElement) return true;

    bootstrap.Modal.getOrCreateInstance(modalElement).show();
    modalElement.querySelectorAll('[data-payment-log-form]').forEach((form) => {
        const method = form.querySelector('[data-payment-log-method]');
        syncPaymentProofField(form, method?.value === 'Transfer');
    });
    return true;
}

async function prepareImageUpload(form) {
    const inputs = Array.from(form.querySelectorAll('[data-image-file-input]'));
    for (const input of inputs) {
        const file = input.files?.[0];
        if (!file || !/^image\/(jpeg|png|webp)$/.test(file.type)) continue;

        const status = input.parentElement?.querySelector('[data-image-compression-status]');
        if (status) status.textContent = 'Mengompres foto sebelum unggah...';
        const result = await compressImageForUpload(file);
        if (result.file !== file) {
            const transfer = new DataTransfer();
            transfer.items.add(result.file);
            input.files = transfer.files;
        }
        if (status) status.textContent = result.message;
    }
}

async function submitPaymentLogForm(form) {
    const button = form.querySelector('button[type="submit"], button:not([type])');
    const originalLabel = button?.textContent || '';
    const feedback = document.getElementById('booking-detail-feedback');
    const url = form.getAttribute('action') || form.getAttribute('hx-post');

    if (!url || form.dataset.submitting === '1') return;
    form.dataset.submitting = '1';
    if (feedback) feedback.innerHTML = '';
    if (button) {
        button.disabled = true;
        button.textContent = 'Menyimpan...';
    }

    try {
        await prepareImageUpload(form);
        syncCsrfToken();
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'HX-Request': 'true',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': getCurrentCsrfToken(),
            },
            body: new FormData(form),
        });
        const token = response.headers.get('X-CSRF-TOKEN');
        if (token) syncCsrfToken(token);

        const html = await response.text();
        if (response.ok && refreshDynamicModalFromHtml(html)) {
            document.body.dispatchEvent(new Event('reservationChanged'));
            return;
        }

        if (feedback) {
            feedback.innerHTML = html || '<div class="alert alert-danger mb-0">Pembayaran gagal disimpan. Silakan coba lagi.</div>';
            feedback.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        }
    } catch (error) {
        if (feedback) {
            feedback.innerHTML = `<div class="alert alert-danger mb-0">${escapeHtml(error.message || 'Pembayaran gagal disimpan. Silakan coba lagi.')}</div>`;
            feedback.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        }
    } finally {
        delete form.dataset.submitting;
        if (button) {
            button.disabled = false;
            button.textContent = originalLabel;
        }
    }
}

document.body.addEventListener('submit', (event) => {
    const form = event.target.closest('[data-payment-log-form]');
    if (!form) return;

    event.preventDefault();
    event.stopImmediatePropagation();
    submitPaymentLogForm(form);
}, true);

document.body.addEventListener('submit', async (event) => {
    const form = event.target.closest('[data-image-upload-form]');
    if (!form || form.dataset.compressionReady === '1') return;

    const inputs = Array.from(form.querySelectorAll('[data-image-file-input]'));
    const hasImage = inputs.some((input) => {
        const file = input.files?.[0];
        return file && /^image\/(jpeg|png|webp)$/.test(file.type);
    });
    if (!hasImage) return;

    event.preventDefault();
    const button = form.querySelector('button[type="submit"], button:not([type])');
    if (button) button.disabled = true;

    try {
        await prepareImageUpload(form);
        form.dataset.compressionReady = '1';
        form.requestSubmit();
    } catch (error) {
        form.querySelectorAll('[data-image-compression-status]').forEach((status) => {
            status.textContent = 'Kompresi gagal, foto akan dikirim dalam ukuran asli.';
        });
        form.dataset.compressionReady = '1';
        form.requestSubmit();
    } finally {
        if (button) button.disabled = false;
    }
}, true);

document.body.addEventListener('htmx:afterSettle', (event) => {
    const swapTarget = event.detail?.target || event.target;
    if (swapTarget.id === 'work-order-modal-content' && swapTarget.dataset.modalScrollTop !== undefined) {
        const scrollTop = Number(swapTarget.dataset.modalScrollTop) || 0;
        const modalBody = swapTarget.querySelector('.modal-body');
        if (modalBody) {
            requestAnimationFrame(() => {
                modalBody.scrollTop = scrollTop;
            });
        }
        delete swapTarget.dataset.modalScrollTop;
    }
    event.target.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((element) => new bootstrap.Tooltip(element));
    initializeRoomMapTodayScroll(event.target);
});

function cleanupModalBackdrop() {
    if (document.querySelector('.modal.show')) return;

    document.querySelectorAll('.modal-backdrop').forEach((backdrop) => backdrop.remove());
    document.body.classList.remove('modal-open');
    document.body.style.removeProperty('overflow');
    document.body.style.removeProperty('padding-right');
}

document.body.addEventListener('htmx:beforeSwap', (event) => {
    const swapTarget = event.detail?.target;
    if (swapTarget?.id === 'work-order-modal-content') {
        const modalBody = swapTarget.querySelector('.modal-body');
        if (modalBody && swapTarget.dataset.modalScrollTop === undefined) {
            swapTarget.dataset.modalScrollTop = String(modalBody.scrollTop);
        }
    }
    if (swapTarget?.id !== 'modal-container') return;

    const oldModal = swapTarget.querySelector('#dynamicModal');
    if (!oldModal) return;

    bootstrap.Modal.getInstance(oldModal)?.dispose();
    oldModal.classList.remove('show');
    cleanupModalBackdrop();
});

document.body.addEventListener('htmx:afterSwap', (event) => {
    if (event.detail.target.id === 'modal-container') {
        const modalElement = document.getElementById('dynamicModal');
        if (modalElement) {
            bootstrap.Modal.getOrCreateInstance(modalElement).show();
            const bookingForm = modalElement.querySelector('[data-booking-form]');
            if (bookingForm) {
                syncBookingPayment(bookingForm);
                syncAgentGuest(bookingForm);
                syncBookingRoomMapLink(bookingForm);
                refreshBookingRoomAvailability(bookingForm);
            }
            modalElement.querySelectorAll('[data-payment-log-form]').forEach((form) => {
                const method = form.querySelector('[data-payment-log-method]');
                syncPaymentProofField(form, method?.value === 'Transfer');
            });
        }
    }
});

document.body.addEventListener('hidden.bs.modal', (event) => {
    if (event.target.id !== 'dynamicModal') return;

    bootstrap.Modal.getInstance(event.target)?.dispose();
    const container = document.getElementById('modal-container');
    if (container?.contains(event.target)) container.replaceChildren();
    requestAnimationFrame(cleanupModalBackdrop);
});

document.body.addEventListener('closeModal', () => {
    const modalElement = document.getElementById('dynamicModal');
    if (!modalElement) {
        cleanupModalBackdrop();
        return;
    }

    bootstrap.Modal.getOrCreateInstance(modalElement).hide();
    window.setTimeout(cleanupModalBackdrop, 400);
});

function showReservationToast(message) {
    let container = document.getElementById('reservation-toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'reservation-toast-container';
        container.className = 'toast-container position-fixed top-0 end-0 p-3';
        container.style.zIndex = '1090';
        document.body.append(container);
    }

    const toast = document.createElement('div');
    toast.className = 'toast align-items-center text-bg-success border-0';
    toast.setAttribute('role', 'status');
    toast.setAttribute('aria-live', 'polite');
    toast.innerHTML = `<div class="d-flex"><div class="toast-body">${escapeHtml(message)}</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Tutup"></button></div>`;
    container.append(toast);
    const instance = bootstrap.Toast.getOrCreateInstance(toast, { delay: 6000 });
    toast.addEventListener('hidden.bs.toast', () => toast.remove());
    instance.show();
}

document.body.addEventListener('reservationCancelled', (event) => {
    const rooms = event.detail?.value?.rooms || event.detail?.rooms || [];
    const roomLabel = Array.isArray(rooms) && rooms.length > 0 ? `: Room ${rooms.join(', Room ')}` : '';
    const scope = event.detail?.value?.scope || event.detail?.scope;
    showReservationToast(`${scope === 'room' ? 'Kamar' : 'Booking'} berhasil dibatalkan${roomLabel}.`);
});

let wibClockTimer = null;
function initializeWibClock() {
    const clockElements = document.querySelectorAll('[data-wib-clock]');
    if (clockElements.length === 0) {
        if (wibClockTimer) clearInterval(wibClockTimer);
        wibClockTimer = null;
        return;
    }

    const formatter = new Intl.DateTimeFormat('id-ID', {
        timeZone: 'Asia/Jakarta',
        weekday: 'long',
        day: '2-digit',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false,
    });

    const updateClock = () => {
        const parts = formatter.formatToParts(new Date()).reduce((carry, part) => {
            carry[part.type] = part.value;
            return carry;
        }, {});
        const text = `${parts.weekday}, ${parts.day} ${parts.month} ${parts.year} • ${parts.hour}.${parts.minute}.${parts.second} WIB`;
        clockElements.forEach((element) => {
            element.textContent = text;
        });
    };

    updateClock();
    if (!wibClockTimer) wibClockTimer = setInterval(updateClock, 1000);
}
initializeWibClock();

function initializeRoomMapTodayScroll(root = document) {
    if (root !== document && root.id !== 'room-map-grid' && !root.querySelector?.('.room-map-scroll')) {
        return;
    }

    const scroller = root.querySelector?.('.room-map-scroll') || document.querySelector('.room-map-scroll');
    if (!scroller) return;

    const todayHeader = scroller.querySelector('thead .today-column');
    if (!todayHeader) return;

    requestAnimationFrame(() => {
        const stickyWidth = scroller.querySelector('.room-column')?.offsetWidth || 0;
        const leftPadding = Math.round(scroller.clientWidth * 0.15);
        scroller.scrollLeft = Math.max(0, todayHeader.offsetLeft - stickyWidth - leftPadding);
    });
}
initializeRoomMapTodayScroll();

let dashboardChart = null;
let dashboardChartObserver = null;
let dashboardChartInitTimer = null;

function initializeDashboardChart(root = document) {
    const canvas = root.querySelector?.('#revenueExpenseChart') || document.querySelector('#revenueExpenseChart');
    if (!canvas || typeof Chart === 'undefined') return;

    const wrapper = canvas.closest('.dashboard-chart-wrap');
    if (!wrapper || wrapper.clientWidth < 100) {
        clearTimeout(dashboardChartInitTimer);
        dashboardChartInitTimer = window.setTimeout(() => initializeDashboardChart(document), 80);
        return;
    }

    dashboardChartObserver?.disconnect();
    dashboardChartObserver = null;
    const existingChart = Chart.getChart(canvas);
    existingChart?.destroy();
    if (dashboardChart && dashboardChart !== existingChart) dashboardChart.destroy();
    dashboardChart = null;
    canvas.removeAttribute('width');
    canvas.removeAttribute('height');
    canvas.removeAttribute('style');

    dashboardChart = new Chart(canvas.getContext('2d'), {
        type: 'line',
        data: {
            labels: JSON.parse(canvas.dataset.labels || '[]'),
            datasets: [
                { label: 'Revenue', data: JSON.parse(canvas.dataset.revenue || '[]'), borderColor: '#2563eb', backgroundColor: 'rgba(37,99,235,.12)', fill: true, tension: .3 },
                { label: 'Expense', data: JSON.parse(canvas.dataset.expenses || '[]'), borderColor: '#ef4444', backgroundColor: 'rgba(239,68,68,.08)', fill: true, tension: .3 }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            resizeDelay: 100,
            animation: false,
            plugins: { legend: { position: 'top', align: 'center' } },
            layout: { padding: { top: 4, right: 8, bottom: 0, left: 0 } },
            scales: { y: { beginAtZero: true } }
        }
    });

    dashboardChartObserver = new ResizeObserver(() => {
        if (dashboardChart && document.body.contains(canvas)) dashboardChart.resize();
    });
    dashboardChartObserver.observe(wrapper);

    requestAnimationFrame(() => requestAnimationFrame(() => dashboardChart?.resize()));
}

document.addEventListener('DOMContentLoaded', () => initializeDashboardChart(document));
document.body.addEventListener('htmx:afterSettle', (event) => {
    if (event.detail.target.id === 'dashboard-metrics') initializeDashboardChart(event.detail.target);
});

let marketingOriginChart = null;
function initializeMarketingOriginChart(root = document) {
    const canvas = root.querySelector?.('#marketingOriginChart') || document.querySelector('#marketingOriginChart');
    if (!canvas || typeof Chart === 'undefined') return;

    const labels = JSON.parse(canvas.dataset.labels || '[]');
    const values = JSON.parse(canvas.dataset.values || '[]');
    const percentages = JSON.parse(canvas.dataset.percentages || '[]');
    const colors = labels.map((label) => label === 'Tidak diketahui' ? '#f59e0b' : '#2563eb');

    Chart.getChart(canvas)?.destroy();
    marketingOriginChart?.destroy();
    marketingOriginChart = new Chart(canvas.getContext('2d'), {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                data: values,
                backgroundColor: colors,
                borderColor: colors.map((color) => color === '#f59e0b' ? '#b45309' : '#1d4ed8'),
                borderWidth: 1,
                borderRadius: 5,
                barPercentage: .72,
                categoryPercentage: .82,
            }],
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (context) => `${Number(context.raw).toLocaleString('id-ID')} tamu (${Number(percentages[context.dataIndex] || 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}%)`,
                    },
                },
            },
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: { precision: 0 },
                    grid: { color: '#e2e8f0' },
                    title: { display: true, text: 'Jumlah tamu utama unik', color: '#64748b' },
                },
                y: { grid: { display: false } },
            },
        },
    });
}

function initializeMarketingGuesthouseFilter(root = document) {
    const form = root.querySelector?.('[data-marketing-filter]') || document.querySelector('[data-marketing-filter]');
    if (!form || form.dataset.guesthouseFilterReady === '1') return;

    const allGuesthouses = form.querySelector('[data-marketing-all-gh]');
    const guesthouses = Array.from(form.querySelectorAll('[data-marketing-gh]'));
    if (!allGuesthouses) return;

    allGuesthouses.addEventListener('change', () => {
        if (allGuesthouses.checked) guesthouses.forEach((checkbox) => { checkbox.checked = false; });
    });
    guesthouses.forEach((checkbox) => {
        checkbox.addEventListener('change', () => {
            allGuesthouses.checked = !guesthouses.some((item) => item.checked);
        });
    });
    form.addEventListener('submit', () => {
        if (allGuesthouses.checked) guesthouses.forEach((checkbox) => { checkbox.checked = false; });
    });
    form.dataset.guesthouseFilterReady = '1';
}

document.addEventListener('DOMContentLoaded', () => {
    initializeMarketingOriginChart(document);
    initializeMarketingGuesthouseFilter(document);
});
document.body.addEventListener('htmx:afterSettle', (event) => {
    initializeMarketingOriginChart(event.detail.target);
    initializeMarketingGuesthouseFilter(event.detail.target);
});

let marketingPerformanceChart = null;
function initializeMarketingPerformanceChart(root = document) {
    const canvas = root.querySelector?.('#marketingPerformanceChart') || document.querySelector('#marketingPerformanceChart');
    if (!canvas || typeof Chart === 'undefined') return;

    Chart.getChart(canvas)?.destroy();
    marketingPerformanceChart?.destroy();
    marketingPerformanceChart = new Chart(canvas.getContext('2d'), {
        type: 'bar',
        data: {
            labels: JSON.parse(canvas.dataset.labels || '[]'),
            datasets: [
                { label: 'Revenue', data: JSON.parse(canvas.dataset.revenue || '[]'), backgroundColor: '#2563eb', borderRadius: 4 },
                { label: 'Cost', data: JSON.parse(canvas.dataset.cost || '[]'), backgroundColor: '#f59e0b', borderRadius: 4 },
                { label: 'Profit', data: JSON.parse(canvas.dataset.profit || '[]'), backgroundColor: '#16a34a', borderRadius: 4 },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            plugins: {
                legend: { position: 'top', align: 'start' },
                tooltip: { callbacks: { label: (context) => `${context.dataset.label}: Rp${Number(context.raw).toLocaleString('id-ID')}` } },
            },
            scales: {
                x: { grid: { display: false } },
                y: { ticks: { callback: (value) => `Rp${Number(value).toLocaleString('id-ID')}` }, grid: { color: '#e2e8f0' } },
            },
        },
    });
}
document.addEventListener('DOMContentLoaded', () => initializeMarketingPerformanceChart(document));
document.body.addEventListener('htmx:afterSettle', (event) => initializeMarketingPerformanceChart(event.detail.target));

let marketingOccupancyChart = null;
function initializeMarketingOccupancyChart(root = document) {
    const canvas = root.querySelector?.('#marketingOccupancyChart') || document.querySelector('#marketingOccupancyChart');
    if (!canvas || typeof Chart === 'undefined') return;

    const details = JSON.parse(canvas.dataset.details || '[]');
    Chart.getChart(canvas)?.destroy();
    marketingOccupancyChart?.destroy();
    marketingOccupancyChart = new Chart(canvas.getContext('2d'), {
        type: 'line',
        data: {
            labels: JSON.parse(canvas.dataset.labels || '[]'),
            datasets: [{
                label: 'Okupansi',
                data: JSON.parse(canvas.dataset.values || '[]'),
                borderColor: '#2563eb',
                backgroundColor: 'rgba(37,99,235,.12)',
                pointBackgroundColor: '#2563eb',
                pointRadius: 4,
                pointHoverRadius: 6,
                fill: true,
                tension: .3,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            interaction: { intersect: false, mode: 'index' },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (context) => {
                            const detail = details[context.dataIndex] || {};
                            const percent = Number(detail.occupancy || 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            const sold = Number(detail.room_nights_sold || 0).toLocaleString('id-ID');
                            const available = Number(detail.room_nights_available || 0).toLocaleString('id-ID');
                            return [`Okupansi: ${percent}%`, `Kamar-malam terjual: ${sold}`, `Kapasitas tersedia: ${available}`];
                        },
                    },
                },
            },
            scales: {
                x: { grid: { display: false } },
                y: {
                    beginAtZero: true,
                    ticks: { callback: (value) => `${value}%` },
                    grid: { color: '#e2e8f0' },
                    title: { display: true, text: 'Okupansi', color: '#64748b' },
                },
            },
        },
    });
}
document.addEventListener('DOMContentLoaded', () => initializeMarketingOccupancyChart(document));
document.body.addEventListener('htmx:afterSettle', (event) => initializeMarketingOccupancyChart(event.detail.target));

let marketingAnnualOccupancyChart = null;
function initializeMarketingAnnualOccupancyChart(root = document) {
    const canvas = root.querySelector?.('#marketingAnnualOccupancyChart') || document.querySelector('#marketingAnnualOccupancyChart');
    if (!canvas || typeof Chart === 'undefined') return;
    Chart.getChart(canvas)?.destroy();
    marketingAnnualOccupancyChart?.destroy();
    marketingAnnualOccupancyChart = new Chart(canvas.getContext('2d'), {
        type: 'bar',
        data: { labels: JSON.parse(canvas.dataset.labels || '[]'), datasets: [{ label: 'Okupansi', data: JSON.parse(canvas.dataset.values || '[]'), backgroundColor: '#2563eb', borderRadius: 5, maxBarThickness: 56 }] },
        options: { responsive: true, maintainAspectRatio: false, animation: false, plugins: { legend: { display: false }, tooltip: { callbacks: { label: (context) => `Okupansi: ${Number(context.raw).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}%` } } }, scales: { x: { grid: { display: false } }, y: { beginAtZero: true, ticks: { callback: (value) => `${value}%` }, grid: { color: '#e2e8f0' } } } },
    });
}

let marketingAnnualRoomNightsChart = null;
function initializeMarketingAnnualRoomNightsChart(root = document) {
    const canvas = root.querySelector?.('#marketingAnnualRoomNightsChart') || document.querySelector('#marketingAnnualRoomNightsChart');
    if (!canvas || typeof Chart === 'undefined') return;
    Chart.getChart(canvas)?.destroy();
    marketingAnnualRoomNightsChart?.destroy();
    marketingAnnualRoomNightsChart = new Chart(canvas.getContext('2d'), {
        type: 'line',
        data: { labels: JSON.parse(canvas.dataset.labels || '[]'), datasets: [{ label: 'Kamar-malam terjual', data: JSON.parse(canvas.dataset.values || '[]'), borderColor: '#16a34a', backgroundColor: 'rgba(22,163,74,.12)', pointBackgroundColor: '#16a34a', pointRadius: 4, fill: true, tension: .3 }] },
        options: { responsive: true, maintainAspectRatio: false, animation: false, plugins: { legend: { display: false }, tooltip: { callbacks: { label: (context) => `Kamar-malam terjual: ${Number(context.raw).toLocaleString('id-ID')}` } } }, scales: { x: { grid: { display: false } }, y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#e2e8f0' } } } },
    });
}

document.addEventListener('DOMContentLoaded', () => { initializeMarketingAnnualOccupancyChart(document); initializeMarketingAnnualRoomNightsChart(document); });
document.body.addEventListener('htmx:afterSettle', (event) => { initializeMarketingAnnualOccupancyChart(event.detail.target); initializeMarketingAnnualRoomNightsChart(event.detail.target); });

let marketingForecastChart = null;
function initializeMarketingForecastChart(root = document) {
    const canvas = root.querySelector?.('#marketingForecastChart') || document.querySelector('#marketingForecastChart');
    if (!canvas || typeof Chart === 'undefined') return;

    const history = JSON.parse(canvas.dataset.history || '[]');
    const forecast = JSON.parse(canvas.dataset.forecast || '[]');
    const labels = [...history.map((point) => point.label), ...forecast.map((point) => point.label)];
    const actual = [...history.map((point) => point.value), ...forecast.map(() => null)];
    const forecastValues = [...history.map((point, index) => index === history.length - 1 ? point.value : null), ...forecast.map((point) => point.value)];
    const lower = [...history.map(() => null), ...forecast.map((point) => point.lower)];
    const upper = [...history.map(() => null), ...forecast.map((point) => point.upper)];

    Chart.getChart(canvas)?.destroy();
    marketingForecastChart?.destroy();
    marketingForecastChart = new Chart(canvas.getContext('2d'), {
        type: 'line',
        data: {
            labels,
            datasets: [
                { label: 'Aktual', data: actual, borderColor: '#2563eb', backgroundColor: 'transparent', pointRadius: 3, tension: .25 },
                { label: 'Forecast', data: forecastValues, borderColor: '#f97316', backgroundColor: 'transparent', borderDash: [7, 4], pointRadius: 4, tension: .25, spanGaps: true },
                { label: 'Batas bawah', data: lower, borderColor: 'rgba(147,197,253,.35)', backgroundColor: 'transparent', pointRadius: 0, borderWidth: 1, spanGaps: true },
                { label: 'Rentang estimasi 95%', data: upper, borderColor: 'rgba(147,197,253,.35)', backgroundColor: 'rgba(147,197,253,.25)', pointRadius: 0, borderWidth: 1, fill: '-1', spanGaps: true },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            interaction: { intersect: false, mode: 'index' },
            plugins: {
                legend: { position: 'top', align: 'start', labels: { filter: (item) => item.text !== 'Batas bawah' } },
                tooltip: { callbacks: { label: (context) => `${context.dataset.label}: ${Number(context.raw).toLocaleString('id-ID', { maximumFractionDigits: 2 })} kamar-malam` } },
            },
            scales: { x: { grid: { display: false } }, y: { beginAtZero: true, grid: { color: '#e2e8f0' }, title: { display: true, text: 'Kamar-malam terjual', color: '#64748b' } } },
        },
    });
}
document.addEventListener('DOMContentLoaded', () => initializeMarketingForecastChart(document));
document.body.addEventListener('htmx:afterSettle', (event) => initializeMarketingForecastChart(event.detail.target));

let opsBudgetChart = null;
function initializeOpsBudgetChart(root = document) {
    const canvas = root.querySelector?.('#opsBudgetChart') || document.querySelector('#opsBudgetChart');
    if (!canvas || typeof Chart === 'undefined') return;
    Chart.getChart(canvas)?.destroy();
    opsBudgetChart?.destroy();
    opsBudgetChart = new Chart(canvas.getContext('2d'), {
        type: 'line',
        data: {
            labels: JSON.parse(canvas.dataset.labels || '[]'),
            datasets: [
                { label: 'Actual Expense', data: JSON.parse(canvas.dataset.actuals || '[]'), borderColor: '#f97316', backgroundColor: 'rgba(249,115,22,.12)', fill: true, tension: .3 },
                { label: 'Projected Budget', data: JSON.parse(canvas.dataset.budgets || '[]'), borderColor: '#64748b', borderDash: [6, 4], backgroundColor: 'transparent', fill: false, tension: .3 }
            ]
        },
        options: { responsive: true, maintainAspectRatio: false, animation: false, plugins: { legend: { position: 'top', align: 'end' } }, scales: { y: { beginAtZero: true } } }
    });
}
document.addEventListener('DOMContentLoaded', () => initializeOpsBudgetChart(document));
document.body.addEventListener('htmx:afterSettle', (event) => {
    if (event.detail.target.id === 'ops-panel') initializeOpsBudgetChart(event.detail.target);
});

let opsExpenseCategoryChart = null;
const opsCategoryColors = ['#2563eb', '#14b8a6', '#f97316', '#8b5cf6', '#ef4444', '#eab308', '#06b6d4', '#ec4899', '#64748b'];
function initializeOpsExpenseCategoryChart(root = document) {
    const canvas = root.querySelector?.('#opsExpenseCategoryChart') || document.querySelector('#opsExpenseCategoryChart');
    if (!canvas || typeof Chart === 'undefined') return;
    const detailUrls = JSON.parse(canvas.dataset.detailUrls || '[]');
    const colors = JSON.parse(canvas.dataset.values || '[]').map((_, index) => opsCategoryColors[index % opsCategoryColors.length]);
    Chart.getChart(canvas)?.destroy();
    opsExpenseCategoryChart?.destroy();
    opsExpenseCategoryChart = new Chart(canvas.getContext('2d'), {
        type: 'doughnut',
        data: { labels: JSON.parse(canvas.dataset.labels || '[]'), datasets: [{ data: JSON.parse(canvas.dataset.values || '[]'), backgroundColor: colors, borderColor: '#fff', borderWidth: 3, hoverOffset: 5 }] },
        options: {
            responsive: true, maintainAspectRatio: false, cutout: '62%', animation: false,
            onClick: (_event, elements) => {
                const detailUrl = detailUrls[elements[0]?.index];
                if (detailUrl) window.location.assign(detailUrl);
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (context) => {
                            const total = context.dataset.data.reduce((sum, value) => sum + Number(value || 0), 0);
                            const percent = total ? (context.parsed / total) * 100 : 0;
                            return `${context.label}: Rp${Number(context.parsed).toLocaleString('id-ID')} (${percent.toFixed(1)}%)`;
                        },
                    },
                },
            },
        },
    });
    canvas.style.cursor = detailUrls.length ? 'pointer' : '';
    (root.querySelectorAll?.('[data-category-color]') || []).forEach((element) => { element.style.backgroundColor = colors[Number(element.dataset.categoryColor) % colors.length]; });
}
document.addEventListener('DOMContentLoaded', () => initializeOpsExpenseCategoryChart(document));
document.body.addEventListener('htmx:afterSettle', (event) => {
    if (event.detail.target.id === 'ops-panel') initializeOpsExpenseCategoryChart(event.detail.target);
});

let ownerExpenseCategoryChart = null;
function initializeOwnerExpenseCategoryChart(root = document) {
    const canvas = root.querySelector?.('#ownerExpenseCategoryChart') || document.querySelector('#ownerExpenseCategoryChart');
    if (!canvas || typeof Chart === 'undefined') return;
    const values = JSON.parse(canvas.dataset.values || '[]');
    const colors = values.map((_, index) => opsCategoryColors[index % opsCategoryColors.length]);
    Chart.getChart(canvas)?.destroy();
    ownerExpenseCategoryChart?.destroy();
    ownerExpenseCategoryChart = new Chart(canvas.getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: JSON.parse(canvas.dataset.labels || '[]'),
            datasets: [{ data: values, backgroundColor: colors, borderColor: '#fff', borderWidth: 3, hoverOffset: 5 }],
        },
        options: {
            responsive: true, maintainAspectRatio: false, cutout: '62%', animation: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (context) => {
                            const total = context.dataset.data.reduce((sum, value) => sum + Number(value || 0), 0);
                            const percent = total ? (context.parsed / total) * 100 : 0;
                            return `${context.label}: Rp${Number(context.parsed).toLocaleString('id-ID')} (${percent.toFixed(1)}%)`;
                        },
                    },
                },
            },
        },
    });
    (root.querySelectorAll?.('[data-owner-category-color]') || []).forEach((element) => {
        element.style.backgroundColor = colors[Number(element.dataset.ownerCategoryColor) % colors.length];
    });
}
document.addEventListener('DOMContentLoaded', () => initializeOwnerExpenseCategoryChart(document));

let financeCashFlowChart = null;
function initializeFinanceCashFlowChart(root = document) {
    const canvas = root.querySelector?.('#financeCashFlowChart') || document.querySelector('#financeCashFlowChart');
    if (!canvas || typeof Chart === 'undefined') return;

    Chart.getChart(canvas)?.destroy();
    financeCashFlowChart?.destroy();
    financeCashFlowChart = new Chart(canvas.getContext('2d'), {
        type: 'bar',
        data: {
            labels: JSON.parse(canvas.dataset.labels || '[]'),
            datasets: [
                { label: 'Pemasukan', data: JSON.parse(canvas.dataset.income || '[]'), backgroundColor: '#2563eb', borderRadius: 5, barPercentage: .65, categoryPercentage: .75 },
                { label: 'Pengeluaran', data: JSON.parse(canvas.dataset.expenses || '[]'), backgroundColor: '#94a3b8', borderRadius: 5, barPercentage: .65, categoryPercentage: .75 },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            plugins: {
                legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, padding: 16 } },
                tooltip: { callbacks: { label: (context) => `${context.dataset.label}: Rp${Number(context.parsed.y || 0).toLocaleString('id-ID')}` } },
            },
            scales: {
                x: { grid: { display: false } },
                y: { beginAtZero: true, ticks: { callback: (value) => value >= 1000000 ? `Rp${value / 1000000}jt` : `Rp${value / 1000}rb` } },
            },
        },
    });
}
document.addEventListener('DOMContentLoaded', () => initializeFinanceCashFlowChart(document));
document.body.addEventListener('htmx:afterSettle', (event) => {
    if (event.detail.target.id === 'finance-panel') initializeFinanceCashFlowChart(event.detail.target);
});
