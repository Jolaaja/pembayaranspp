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
        var passwordToggle = event.target.closest('[data-password-toggle]');
        if (passwordToggle) {
            var passwordInput = document.getElementById(passwordToggle.getAttribute('data-password-toggle'));
            if (passwordInput) {
                var showPassword = passwordInput.type === 'password';
                passwordInput.type = showPassword ? 'text' : 'password';
                passwordToggle.textContent = showPassword ? 'Sembunyikan' : 'Lihat';
                passwordToggle.setAttribute('aria-label', showPassword ? 'Sembunyikan password' : 'Tampilkan password');
                passwordToggle.setAttribute('aria-pressed', showPassword ? 'true' : 'false');
            }
            return;
        }

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
        var body = table.tBodies[0];
        if (!wrap || !body || wrap.querySelector('.table-search')) return;

        var rows = Array.prototype.filter.call(body.rows, function (row) {
            return !row.querySelector('td[colspan]');
        });
        if (rows.length === 0) return;

        var search = document.createElement('input');
        search.type = 'search';
        search.className = 'form-control table-search';
        search.placeholder = 'Cari di tabel...';
        search.setAttribute('aria-label', 'Cari data pada tabel ' + (index + 1));
        wrap.insertBefore(search, table);

        var sortColumn = -1;
        var sortDirection = 1;
        var currentPage = 1;
        var pageSize = 10;
        var paginate = table.getAttribute('data-server-pagination') !== 'true';
        var noResultsRow = document.createElement('tr');
        noResultsRow.className = 'table-no-results';
        noResultsRow.hidden = true;
        var noResultsCell = document.createElement('td');
        noResultsCell.colSpan = table.tHead ? table.tHead.rows[0].cells.length : 1;
        noResultsCell.textContent = 'Tidak ada data yang cocok.';
        noResultsRow.appendChild(noResultsCell);
        body.appendChild(noResultsRow);

        var pagination = document.createElement('nav');
        pagination.className = 'table-pagination';
        pagination.setAttribute('aria-label', 'Navigasi halaman tabel ' + (index + 1));
        var summary = document.createElement('span');
        summary.className = 'table-pagination-summary';
        summary.setAttribute('aria-live', 'polite');
        var previous = document.createElement('button');
        previous.type = 'button';
        previous.className = 'btn btn-secondary btn-sm';
        previous.textContent = 'Sebelumnya';
        previous.setAttribute('aria-label', 'Halaman sebelumnya');
        var next = document.createElement('button');
        next.type = 'button';
        next.className = 'btn btn-secondary btn-sm';
        next.textContent = 'Berikutnya';
        next.setAttribute('aria-label', 'Halaman berikutnya');
        pagination.append(previous, summary, next);
        table.insertAdjacentElement('afterend', pagination);
        pagination.hidden = !paginate || rows.length <= pageSize;

        var sortableHeaders = [];
        if (table.tHead && table.tHead.rows.length) {
            Array.prototype.forEach.call(table.tHead.rows[0].cells, function (header, column) {
                var label = header.textContent.trim();
                if (!label || /^(aksi|tindakan)$/i.test(label)) return;
                var button = document.createElement('button');
                var indicator = document.createElement('span');
                button.type = 'button';
                button.className = 'table-sort';
                button.textContent = label;
                button.setAttribute('aria-label', 'Urutkan berdasarkan ' + label);
                indicator.className = 'table-sort-indicator';
                indicator.setAttribute('aria-hidden', 'true');
                indicator.textContent = '↕';
                button.appendChild(indicator);
                header.textContent = '';
                header.appendChild(button);
                sortableHeaders.push({ header: header, button: button, indicator: indicator });
                button.addEventListener('click', function () {
                    if (sortColumn === column) {
                        sortDirection *= -1;
                    } else {
                        sortColumn = column;
                        sortDirection = 1;
                    }
                    currentPage = 1;
                    sortableHeaders.forEach(function (item, headerIndex) {
                        var headerLabel = item.button.textContent.replace('↕', '').replace('↑', '').replace('↓', '').trim();
                        item.header.removeAttribute('aria-sort');
                        item.indicator.textContent = '↕';
                        item.button.setAttribute('aria-label', 'Urutkan berdasarkan ' + headerLabel);
                        if (headerIndex === sortColumn) {
                            item.header.setAttribute('aria-sort', sortDirection === 1 ? 'ascending' : 'descending');
                            item.indicator.textContent = sortDirection === 1 ? '↑' : '↓';
                            item.button.setAttribute('aria-label', 'Urutkan ' + headerLabel + (sortDirection === 1 ? ' menurun' : ' menaik'));
                        }
                    });
                    applyTableState();
                });
            });
        }

        function applyTableState() {
            var query = search.value.trim().toLocaleLowerCase();
            var filtered = rows.filter(function (row) {
                return query === '' || row.textContent.toLocaleLowerCase().includes(query);
            });
            if (sortColumn >= 0) {
                var collator = new Intl.Collator('id', { numeric: true, sensitivity: 'base' });
                filtered.sort(function (leftRow, rightRow) {
                    var leftCell = leftRow.cells[sortColumn];
                    var rightCell = rightRow.cells[sortColumn];
                    var leftValue = leftCell ? leftCell.getAttribute('data-sort-value') || leftCell.textContent.trim() : '';
                    var rightValue = rightCell ? rightCell.getAttribute('data-sort-value') || rightCell.textContent.trim() : '';
                    return collator.compare(leftValue, rightValue) * sortDirection;
                });
                filtered.forEach(function (row) {
                    body.appendChild(row);
                });
            }

            var totalPages = Math.max(1, Math.ceil(filtered.length / pageSize));
            currentPage = Math.min(currentPage, totalPages);
            var start = filtered.length ? (currentPage - 1) * pageSize : 0;
            var end = Math.min(start + pageSize, filtered.length);
            var visibleRows = new Set(filtered.slice(start, end));
            rows.forEach(function (row) {
                row.hidden = !visibleRows.has(row);
            });
            noResultsRow.hidden = filtered.length !== 0;
            summary.textContent = filtered.length
                ? 'Menampilkan ' + (start + 1) + '–' + end + ' dari ' + filtered.length + ' data'
                : 'Tidak ada data';
            previous.disabled = currentPage <= 1;
            next.disabled = currentPage >= totalPages;
        }

        previous.addEventListener('click', function () {
            currentPage--;
            applyTableState();
        });
        next.addEventListener('click', function () {
            currentPage++;
            applyTableState();
        });
        search.addEventListener('input', function () {
            currentPage = 1;
            applyTableState();
        });
        applyTableState();
    });

    var profilePhotoInput = document.getElementById('profile-photo');
    var profileAvatarPreview = document.querySelector('[data-profile-avatar-preview]');
    if (profilePhotoInput && profileAvatarPreview) {
        var previewUrl = null;
        profilePhotoInput.addEventListener('change', function () {
            var file = profilePhotoInput.files && profilePhotoInput.files[0];
            if (!file || !file.type.startsWith('image/')) return;
            if (previewUrl) URL.revokeObjectURL(previewUrl);
            previewUrl = URL.createObjectURL(file);
            profileAvatarPreview.src = previewUrl;
        });
    }
}());
