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
        var paymentTrigger = event.target.closest('[data-payment-confirm]');
        if (paymentTrigger) {
            var paymentModal = document.getElementById('payment-confirmation-modal');
            var paymentForm = document.getElementById('payment-confirmation-form');
            if (paymentModal && paymentForm) {
                paymentForm.reset();
                var referenceField = paymentModal.querySelector('[data-payment-reference-field]');
                if (referenceField) referenceField.hidden = true;
                document.getElementById('payment-confirmation-id').value = paymentTrigger.getAttribute('data-payment-id');
                document.getElementById('payment-confirmation-nisn').value = paymentTrigger.getAttribute('data-payment-nisn');
                document.getElementById('payment-confirmation-summary').textContent =
                    paymentTrigger.getAttribute('data-payment-month') + ' · ' +
                    paymentTrigger.getAttribute('data-payment-amount') + ' · ' +
                    paymentTrigger.getAttribute('data-payment-student');
                showModal(paymentModal);
                document.getElementById('payment-method').focus();
            }
            return;
        }

        var passwordToggle = event.target.closest('[data-password-toggle]');
        if (passwordToggle) {
            var passwordInput = document.getElementById(passwordToggle.getAttribute('data-password-toggle'));
            if (passwordInput) {
                var showPassword = passwordInput.type === 'password';
                passwordInput.type = showPassword ? 'text' : 'password';
                var passwordLabel = passwordToggle.querySelector('[data-password-label]');
                if (passwordLabel) {
                    passwordLabel.textContent = showPassword ? 'Sembunyikan' : 'Lihat';
                } else {
                    passwordToggle.textContent = showPassword ? 'Sembunyikan' : 'Lihat';
                }
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

    var paymentMethod = document.getElementById('payment-method');
    var paymentReferenceField = document.querySelector('[data-payment-reference-field]');
    if (paymentMethod && paymentReferenceField) {
        paymentMethod.addEventListener('change', function () {
            paymentReferenceField.hidden = !['bank_transfer', 'qris'].includes(paymentMethod.value);
        });
    }

    var reminderType = document.getElementById('reminder-type');
    var reminderDaysField = document.querySelector('[data-reminder-days]');
    if (reminderType && reminderDaysField) {
        var updateReminderDaysVisibility = function () {
            reminderDaysField.hidden = reminderType.value !== 'upcoming';
        };
        reminderType.addEventListener('change', updateReminderDaysVisibility);
        updateReminderDaysVisibility();
    }

    var firstBillingMonth = document.querySelector('[data-spp-first-month]');
    var schedulePreview = document.querySelector('[data-spp-schedule-preview]');
    if (firstBillingMonth && schedulePreview) {
        var updateSchedulePreview = function () {
            var match = /^(\d{4})-(\d{2})$/.exec(firstBillingMonth.value);
            if (!match) {
                schedulePreview.textContent = 'Pilih bulan pertama untuk melihat pratinjau jadwal.';
                return;
            }
            var start = new Date(Number(match[1]), Number(match[2]) - 1, 1);
            var end = new Date(Number(match[1]), Number(match[2]) - 1 + 35, 1);
            var formatter = new Intl.DateTimeFormat('id-ID', { month: 'long', year: 'numeric' });
            schedulePreview.textContent = '36 jadwal, dari ' + formatter.format(start) + ' sampai ' +
                formatter.format(end) + '; jatuh tempo setiap tanggal 10.';
        };
        firstBillingMonth.addEventListener('input', updateSchedulePreview);
        firstBillingMonth.addEventListener('change', updateSchedulePreview);
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            document.querySelectorAll('.modal.show').forEach(hideModal);
            closeSidebar();
        }
    });

    document.querySelectorAll('.table-responsive table').forEach(function (table, index) {
        var wrap = table.closest('.table-responsive');
        var body = table.tBodies[0];
        if (table.getAttribute('data-server-controls') === 'true') return;
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
                sortableHeaders.push({ header: header, button: button, indicator: indicator, column: column });
                button.addEventListener('click', function () {
                    if (sortColumn === column) {
                        sortDirection *= -1;
                    } else {
                        sortColumn = column;
                        sortDirection = 1;
                    }
                    currentPage = 1;
                    sortableHeaders.forEach(function (item) {
                        var headerLabel = item.button.textContent.replace('↕', '').replace('↑', '').replace('↓', '').trim();
                        item.header.removeAttribute('aria-sort');
                        item.indicator.textContent = '↕';
                        item.button.setAttribute('aria-label', 'Urutkan berdasarkan ' + headerLabel);
                        if (item.column === sortColumn) {
                            item.header.setAttribute('aria-sort', sortDirection === 1 ? 'ascending' : 'descending');
                            item.indicator.textContent = sortDirection === 1 ? '↑' : '↓';
                            item.button.setAttribute('aria-label', 'Urutan ' + headerLabel + (sortDirection === 1 ? ' menaik' : ' menurun'));
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

            var totalPages = paginate ? Math.max(1, Math.ceil(filtered.length / pageSize)) : 1;
            currentPage = Math.min(currentPage, totalPages);
            var start = filtered.length && paginate ? (currentPage - 1) * pageSize : 0;
            var end = paginate ? Math.min(start + pageSize, filtered.length) : filtered.length;
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

    var schoolLogoInput = document.querySelector('[data-school-logo-input]');
    var schoolLogoPreviews = document.querySelectorAll('[data-school-logo-preview]');
    var schoolLogoFeedback = document.querySelector('[data-school-logo-feedback]');
    var schoolLogoRemoval = document.querySelector('[data-school-remove-logo]');
    if (schoolLogoInput && schoolLogoPreviews.length) {
        var schoolLogoPreviewUrl = null;
        var schoolLogoRequest = 0;
        var schoolLogoOriginalSources = Array.prototype.map.call(schoolLogoPreviews, function (preview) {
            return preview.getAttribute('src');
        });
        var setSchoolLogoFeedback = function (message, state) {
            if (!schoolLogoFeedback) return;
            schoolLogoFeedback.textContent = message;
            schoolLogoFeedback.classList.toggle('is-error', state === 'error');
            schoolLogoFeedback.classList.toggle('is-success', state === 'success');
        };
        var resetSchoolLogoPreview = function () {
            if (schoolLogoPreviewUrl) URL.revokeObjectURL(schoolLogoPreviewUrl);
            schoolLogoPreviewUrl = null;
            schoolLogoPreviews.forEach(function (preview, index) {
                preview.src = schoolLogoRemoval && schoolLogoRemoval.checked
                    ? 'img/logo-spp.svg'
                    : schoolLogoOriginalSources[index];
            });
        };
        if (schoolLogoRemoval) {
            schoolLogoRemoval.addEventListener('change', function () {
                schoolLogoRequest += 1;
                schoolLogoInput.value = '';
                resetSchoolLogoPreview();
                setSchoolLogoFeedback(
                    schoolLogoRemoval.checked
                        ? 'Logo Portal SPP akan digunakan setelah disimpan.'
                        : 'Logo sekolah saat ini akan dipertahankan.',
                    'success'
                );
            });
        }
        schoolLogoInput.addEventListener('change', function () {
            schoolLogoRequest += 1;
            var request = schoolLogoRequest;
            var file = schoolLogoInput.files && schoolLogoInput.files[0];
            if (!file) return;
            if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
                schoolLogoInput.value = '';
                resetSchoolLogoPreview();
                setSchoolLogoFeedback('Format harus JPG, PNG, atau WebP.', 'error');
                return;
            }
            if (file.size < 1 || file.size > 2 * 1024 * 1024) {
                schoolLogoInput.value = '';
                resetSchoolLogoPreview();
                setSchoolLogoFeedback('Ukuran logo harus lebih dari 0 dan maksimal 2 MB.', 'error');
                return;
            }
            if (schoolLogoRemoval) schoolLogoRemoval.checked = false;

            var candidateUrl = URL.createObjectURL(file);
            var image = new Image();
            image.onload = function () {
                if (request !== schoolLogoRequest) {
                    URL.revokeObjectURL(candidateUrl);
                    return;
                }
                var pixelCount = image.naturalWidth * image.naturalHeight;
                if (image.naturalWidth > 4000 || image.naturalHeight > 4000 || pixelCount > 8000000) {
                    URL.revokeObjectURL(candidateUrl);
                    schoolLogoInput.value = '';
                    resetSchoolLogoPreview();
                    setSchoolLogoFeedback('Dimensi logo maksimal 4.000 px per sisi dan 8 megapiksel.', 'error');
                    return;
                }
                if (schoolLogoPreviewUrl) URL.revokeObjectURL(schoolLogoPreviewUrl);
                schoolLogoPreviewUrl = candidateUrl;
                schoolLogoPreviews.forEach(function (preview) {
                    preview.src = schoolLogoPreviewUrl;
                });
                setSchoolLogoFeedback(
                    'Pratinjau valid · ' + image.naturalWidth + ' × ' + image.naturalHeight +
                    ' px · ' + (file.size / 1024 / 1024).toFixed(2) + ' MB.',
                    'success'
                );
            };
            image.onerror = function () {
                URL.revokeObjectURL(candidateUrl);
                if (request !== schoolLogoRequest) return;
                schoolLogoInput.value = '';
                resetSchoolLogoPreview();
                setSchoolLogoFeedback('File gambar tidak dapat dibaca sebagai logo.', 'error');
            };
            image.src = candidateUrl;
        });
    }

    var schoolPreviewInputs = {};
    document.querySelectorAll('[data-school-preview-input]').forEach(function (input) {
        schoolPreviewInputs[input.getAttribute('data-school-preview-input')] = input;
        input.addEventListener('input', updateSchoolIdentityPreview);
    });

    function updateSchoolIdentityPreview() {
        document.querySelectorAll('[data-school-preview-value]').forEach(function (preview) {
            var key = preview.getAttribute('data-school-preview-value');
            var inputKey = key === 'document-npsn' || key === 'login-subtitle' ? 'npsn' :
                (key === 'document-website' ? 'website' : key);
            var input = schoolPreviewInputs[inputKey];
            if (!input) return;
            var value = input.value.trim();
            if (key === 'login-subtitle') {
                preview.textContent = value ? 'NPSN ' + value + ' · KEUANGAN SEKOLAH' : 'KEUANGAN SEKOLAH';
                return;
            }
            value = value || preview.getAttribute('data-school-preview-empty') || '';
            preview.textContent = (preview.getAttribute('data-school-preview-prefix') || '') + value;
        });
    }
    updateSchoolIdentityPreview();

    document.addEventListener('submit', function (event) {
        var restoreForm = event.target.closest('[data-school-restore-form]');
        if (!restoreForm) return;
        var schoolName = restoreForm.getAttribute('data-school-restore-name');
        if (!window.confirm('Pulihkan identitas "' + schoolName + '"? Perubahan saat ini tetap masuk ke riwayat dan dapat dipulihkan kembali.')) {
            event.preventDefault();
        }
    });
}());
