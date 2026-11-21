(function () {
    'use strict';

    var sidebar = document.getElementById('appSidebar');
    var scrim = document.querySelector('[data-sidebar-close]');

    function showModal(modal) {
        if (!modal) return;
        modal.classList.add('show');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
    }

    function hideModal(modal) {
        if (!modal) return;
        modal.classList.remove('show');
        modal.setAttribute('aria-hidden', 'true');
        if (!document.querySelector('.modal.show')) document.body.classList.remove('modal-open');
    }

    function openSidebar() {
        if (sidebar) sidebar.classList.add('is-open');
        if (scrim) scrim.classList.add('is-open');
    }

    function closeSidebar() {
        if (sidebar) sidebar.classList.remove('is-open');
        if (scrim) scrim.classList.remove('is-open');
    }

    document.addEventListener('click', function (event) {
        var toggle = event.target.closest('[data-sidebar-toggle]');
        if (toggle) {
            openSidebar();
            return;
        }
        if (event.target.closest('[data-sidebar-close]')) {
            closeSidebar();
            return;
        }

        var dismiss = event.target.closest('[data-bs-dismiss="modal"], [data-dismiss="modal"]');
        if (dismiss) {
            hideModal(dismiss.closest('.modal'));
            return;
        }
        var alertDismiss = event.target.closest('[data-dismiss="alert"]');
        if (alertDismiss) {
            var alert = alertDismiss.closest('.alert');
            if (alert) alert.remove();
            return;
        }

        var trigger = event.target.closest('[data-bs-toggle="modal"]');
        if (trigger) {
            event.preventDefault();
            var selector = trigger.getAttribute('data-bs-target');
            var modal = selector ? document.querySelector(selector) : null;
            if (trigger.classList.contains('view_data') && modal) {
                var entityFields = {
                    'editdatasiswa.php': 'id_siswa',
                    'editdatakelas.php': 'id_kelas',
                    'editdatajurusan.php': 'id_jurusan',
                    'editdataangkatan.php': 'id_angkatan'
                };
                var field = entityFields[window.location.pathname.split('/').pop()];
                var body = modal.querySelector('.modal-body');
                if (field && body) {
                    body.innerHTML = '<div class="loading-state">Memuat data...</div>';
                    showModal(modal);
                    fetch('view.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
                        body: new URLSearchParams((function () {
                            var values = {};
                            values[field] = trigger.id;
                            return values;
                        }()))
                    }).then(function (response) {
                        if (!response.ok) throw new Error('Gagal memuat data.');
                        return response.text();
                    }).then(function (html) {
                        body.innerHTML = html;
                    }).catch(function () {
                        body.innerHTML = '<p class="text-danger">Data gagal dimuat. Muat ulang halaman dan coba lagi.</p>';
                    });
                    return;
                }
            }
            showModal(modal);
            return;
        }

        if (event.target.classList.contains('modal')) hideModal(event.target);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            document.querySelectorAll('.modal.show').forEach(hideModal);
            closeSidebar();
        }
    });

    document.querySelectorAll('.table-responsive table').forEach(function (table, index) {
        var wrap = table.closest('.table-responsive');
        if (!wrap || wrap.querySelector('.table-search')) return;
        var search = document.createElement('input');
        search.type = 'search';
        search.className = 'form-control table-search';
        search.placeholder = 'Cari di tabel...';
        search.setAttribute('aria-label', 'Cari data pada tabel ' + (index + 1));
        wrap.insertBefore(search, table);
        search.addEventListener('input', function () {
            var query = search.value.trim().toLocaleLowerCase();
            table.querySelectorAll('tbody tr').forEach(function (row) {
                row.hidden = query !== '' && !row.textContent.toLocaleLowerCase().includes(query);
            });
        });
    });
}());
