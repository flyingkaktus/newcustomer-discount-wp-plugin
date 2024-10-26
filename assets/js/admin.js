(function($) {
    'use strict';

    // Main Admin Class
    class NCDAdmin {
        constructor() {
            this.initializeComponents();
            this.bindEvents();
        }

        initializeComponents() {
            this.$testEmailForm = $('.ncd-test-email-form');
            this.$customerTable = $('.ncd-customers-table');
            this.$filterForm = $('.ncd-filter-form');
            this.$logoForm = $('.ncd-logo-form');
            this.$notices = $('.ncd-notice');
        }

        bindEvents() {
            // Test Email Form
            this.$testEmailForm.on('submit', (e) => this.handleTestEmailSubmit(e));

            // Customer Table Actions
            this.$customerTable.on('click', '.ncd-send-discount', (e) => this.handleDiscountSend(e));

            // Filter Form
            this.$filterForm.on('change', 'select, input', () => this.handleFilterChange());

            // Logo Form
            this.$logoForm.on('submit', (e) => this.handleLogoSubmit(e));
            this.$logoForm.on('change', '#ncd-logo-file', (e) => this.handleFileSelect(e));

            // Delete Logo
            $('.ncd-delete-logo').on('click', (e) => this.handleLogoDelete(e));

            // Auto-dismiss notices
            this.initializeNoticeDismissal();
        }

        handleTestEmailSubmit(e) {
            e.preventDefault();
            const $form = $(e.currentTarget);
            const email = $form.find('input[name="test_email"]').val();

            if (!this.validateEmail(email)) {
                this.showNotice('Bitte geben Sie eine gültige E-Mail-Adresse ein.', 'error');
                return;
            }

            if (!confirm(`Möchten Sie eine Test-E-Mail an ${email} senden?`)) {
                return;
            }

            this.submitForm($form);
        }

        handleDiscountSend(e) {
            e.preventDefault();
            const $button = $(e.currentTarget);
            const email = $button.data('email');

            if (!confirm(`Möchten Sie wirklich einen Rabattcode an ${email} senden?`)) {
                return;
            }

            const $form = $button.closest('form');
            this.submitForm($form);
        }

        handleFilterChange() {
            this.$filterForm.submit();
        }

        handleLogoSubmit(e) {
            const $form = $(e.currentTarget);
            const fileInput = $form.find('#ncd-logo-file')[0];
            const base64Input = $form.find('textarea[name="logo_base64"]');

            if (fileInput.files.length === 0 && !base64Input.val()) {
                e.preventDefault();
                this.showNotice('Bitte wählen Sie eine Datei aus oder geben Sie einen Base64-String ein.', 'error');
            }
        }

        handleFileSelect(e) {
            const file = e.target.files[0];
            if (!file) return;

            if (!this.validateFileType(file)) {
                this.showNotice('Bitte nur PNG oder JPEG Dateien hochladen.', 'error');
                e.target.value = '';
                return;
            }

            if (!this.validateFileSize(file)) {
                this.showNotice('Die Datei darf maximal 2MB groß sein.', 'error');
                e.target.value = '';
                return;
            }

            // Optional: Preview image
            this.previewImage(file);
        }

        handleLogoDelete(e) {
            e.preventDefault();
            if (!confirm('Möchten Sie das Logo wirklich löschen?')) {
                return;
            }
            const $form = $(e.currentTarget).closest('form');
            $form.append('<input type="hidden" name="delete_logo" value="1">');
            $form.submit();
        }

        submitForm($form) {
            $form.addClass('ncd-loading');
            
            // If using AJAX, uncomment the following:
            /*
            const formData = new FormData($form[0]);
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: (response) => {
                    if (response.success) {
                        this.showNotice(response.data.message, 'success');
                    } else {
                        this.showNotice(response.data.message, 'error');
                    }
                },
                error: () => {
                    this.showNotice('Ein Fehler ist aufgetreten. Bitte versuchen Sie es erneut.', 'error');
                },
                complete: () => {
                    $form.removeClass('ncd-loading');
                }
            });
            */

            // For now, just submit the form normally
            $form.submit();
        }

        validateEmail(email) {
            const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
            return re.test(email.toLowerCase());
        }

        validateFileType(file) {
            const allowedTypes = ['image/jpeg', 'image/png'];
            return allowedTypes.includes(file.type);
        }

        validateFileSize(file) {
            const maxSize = 2 * 1024 * 1024; // 2MB
            return file.size <= maxSize;
        }

        previewImage(file) {
            const reader = new FileReader();
            reader.onload = (e) => {
                $('.ncd-logo-preview').attr('src', e.target.result);
            };
            reader.readAsDataURL(file);
        }

        showNotice(message, type = 'success') {
            const $notice = $(`
                <div class="ncd-notice ncd-notice-${type} ncd-fade">
                    <p>${message}</p>
                </div>
            `);

            $('.ncd-notices').prepend($notice);
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                $notice.fadeOut(() => $notice.remove());
            }, 5000);
        }

        initializeNoticeDismissal() {
            setTimeout(() => {
                this.$notices.fadeOut();
            }, 5000);
        }
    }

    // Initialize on document ready
    $(document).ready(() => {
        new NCDAdmin();
    });

})(jQuery);