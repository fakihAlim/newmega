/**
 * E-Procurement Custom JavaScript
 * Global helpers and utilities
 */

$(document).ready(function () {
    // Initialize DataTables with default config
    if ($.fn.DataTable) {
        window.initDataTable = function(selector, options = {}) {
            const defaults = {
                responsive: true,
                pageLength: 25,
                language: {
                    search: "Cari:",
                    lengthMenu: "Tampilkan _MENU_ data",
                    info: "Menampilkan _START_ - _END_ dari _TOTAL_ data",
                    infoEmpty: "Tidak ada data",
                    infoFiltered: "(difilter dari _MAX_ total data)",
                    zeroRecords: "Tidak ada data yang cocok",
                    paginate: {
                        first: "Pertama",
                        last: "Terakhir",
                        next: "<i class='fas fa-chevron-right'></i>",
                        previous: "<i class='fas fa-chevron-left'></i>"
                    }
                },
                dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                     '<"row"<"col-sm-12"tr>>' +
                     '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>'
            };
            return $(selector).DataTable($.extend(true, defaults, options));
        };
    }

    // Initialize Select2 with default config
    if ($.fn.select2) {
        window.initSelect2 = function(selector, options = {}) {
            const defaults = {
                theme: 'bootstrap4',
                placeholder: 'Pilih...',
                allowClear: true,
                width: '100%'
            };
            return $(selector).select2($.extend(defaults, options));
        };
    }

    // SweetAlert2 confirmation dialog
    window.confirmAction = function(title, text, callback) {
        Swal.fire({
            title: title,
            text: text,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#f59e0b',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Ya, Lanjutkan',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                callback();
            }
        });
    };

    // SweetAlert2 success message
    window.showSuccess = function(message) {
        Swal.fire({
            icon: 'success',
            title: 'Berhasil!',
            text: message,
            timer: 2000,
            showConfirmButton: false
        });
    };

    // SweetAlert2 error message
    window.showError = function(message) {
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: message
        });
    };

    // Format number to Rupiah
    window.formatRupiah = function(number) {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }).format(number);
    };

    // Parse Rupiah string to number
    window.parseRupiah = function(str) {
        if (typeof str === 'number') return str;
        return parseInt(str.replace(/[^0-9-]/g, '')) || 0;
    };

    // Format input as Rupiah while typing
    $(document).on('input', '.input-rupiah', function() {
        let value = this.value.replace(/[^0-9]/g, '');
        if (value) {
            this.value = parseInt(value).toLocaleString('id-ID');
        }
    });

    // Format input as number (quantity)
    $(document).on('input', '.input-number', function() {
        let value = this.value.replace(/[^0-9.]/g, '');
        this.value = value;
    });

    // Auto-dismiss alerts after 5 seconds
    setTimeout(function() {
        $('.alert-dismissible').fadeOut(500, function() {
            $(this).remove();
        });
    }, 5000);

    // Delete confirmation with form submission
    $(document).on('click', '.btn-delete', function(e) {
        e.preventDefault();
        const url = $(this).data('url') || $(this).attr('href');
        const name = $(this).data('name') || 'item ini';
        
        confirmAction(
            'Hapus Data?',
            'Anda yakin ingin menghapus "' + name + '"? Tindakan ini tidak dapat dibatalkan.',
            function() {
                window.location.href = url;
            }
        );
    });

    // Tooltip initialization
    $('[data-toggle="tooltip"]').tooltip();
    
    // Duplicate Name Checker for Master Data
    let checkTimeout;
    $(document).on('input', '.check-duplicate', function() {
        clearTimeout(checkTimeout);
        const input = $(this);
        const query = input.val().trim();
        const type = input.data('type');
        const excludeId = input.data('id') || 0;
        const warningDiv = input.siblings('.duplicate-warning');
        
        if (query.length < 2) {
            warningDiv.hide();
            return;
        }
        
        checkTimeout = setTimeout(() => {
            $.get(APP_URL + '/api/check_duplicate.php', { type: type, q: query, id: excludeId }, function(res) {
                if (res.matches && res.matches.length > 0) {
                    warningDiv.html('<i class="fas fa-exclamation-triangle"></i> Ditemukan nama serupa, hati-hati duplikasi data:<br> - ' + res.matches.join('<br> - ')).fadeIn();
                } else {
                    warningDiv.fadeOut();
                }
            });
        }, 500); // Debounce 500ms
    });

    // Print support: show ALL DataTable rows when printing
    let _dtPageLengths = {};
    window.onbeforeprint = function() {
        $.fn.DataTable.tables({ visible: true, api: true }).every(function() {
            var dt = this;
            var id = dt.table().node().id || Math.random();
            _dtPageLengths[id] = dt.page.len();
            dt.page.len(-1).draw('page');
        });
    };
    window.onafterprint = function() {
        $.fn.DataTable.tables({ visible: true, api: true }).every(function() {
            var dt = this;
            var id = dt.table().node().id || Math.random();
            var len = _dtPageLengths[id] || 25;
            dt.page.len(len).draw('page');
        });
    };
});

