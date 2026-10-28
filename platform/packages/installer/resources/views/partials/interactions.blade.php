<script>
    (() => {
        const showPassword = @json(trans('packages/installer::ui.show_password_for'));
        const hidePassword = @json(trans('packages/installer::ui.hide_password_for'));
        let pending = false;

        document.querySelectorAll('[data-password-toggle]').forEach((button) => {
            const input = document.getElementById(button.dataset.passwordToggle);
            if (!input) return;
            button.hidden = false;
            button.addEventListener('click', () => {
                const visible = input.type === 'password';
                input.type = visible ? 'text' : 'password';
                const label = (visible ? hidePassword : showPassword).replace(':field', button.dataset.fieldLabel);
                button.setAttribute('aria-pressed', String(visible));
                button.setAttribute('aria-label', label);
                button.title = label;
            });
        });

        const connection = document.getElementById('database_connection');
        if (connection) {
            const nameLabel = document.querySelector('label[for="database_name"] [data-field-label-text]');
            const normalLabel = nameLabel.textContent;
            const sqliteLabel = @json(trans('packages/installer::ui.labels.sqlite_path'));
            const updateDatabaseFields = () => {
                const sqlite = connection.value === 'sqlite';
                ['database_hostname', 'database_port', 'database_username', 'database_password'].forEach((name) => {
                    const input = document.getElementById(name);
                    const field = input.closest('.installer-field');
                    field.hidden = sqlite;
                    input.required = !sqlite && name !== 'database_password';
                    field.querySelector('.installer-required').hidden = !input.required;
                });
                document.getElementById('database_name').maxLength = Number(sqlite
                    ? connection.dataset.sqlitePathMaxlength
                    : connection.dataset.databaseNameMaxlength);
                nameLabel.textContent = sqlite ? sqliteLabel : normalLabel;
                document.getElementById('database_name-hint').hidden = !sqlite;
            };
            connection.addEventListener('change', updateDatabaseFields);
            updateDatabaseFields();
        }

        document.querySelectorAll('.installer-theme-preview img').forEach((img) => {
            const fallback = () => {
                img.hidden = true;
                img.nextElementSibling.hidden = false;
            };
            img.addEventListener('error', fallback);
            if (img.complete && img.naturalWidth === 0) fallback();
        });

        const navigation = document.querySelectorAll('.installer-step a, .installer-actions a');
        const startPending = (message, trigger) => {
            pending = true;
            document.querySelector('.installer-pending').textContent = message;
            navigation.forEach((link) => link.setAttribute('aria-disabled', 'true'));
            trigger?.classList.add('is-pending');
            trigger?.setAttribute('aria-busy', 'true');
        };
        document.querySelectorAll('form[data-pending-message]').forEach((form) => {
            form.addEventListener('submit', (event) => {
                if (pending || event.defaultPrevented) {
                    event.preventDefault();
                    return;
                }
                form.dataset.submitting = 'true';
                form.setAttribute('aria-busy', 'true');
                const buttons = [...document.querySelectorAll('button[type="submit"]')].filter((button) => button.form === form);
                startPending(form.dataset.pendingMessage, buttons[0]);
                buttons.forEach((button) => button.disabled = true);
            });
        });
        navigation.forEach((link) => {
            link.addEventListener('click', (event) => {
                if (pending) {
                    event.preventDefault();
                    return;
                }
                if (link.dataset.navigationMessage && !event.defaultPrevented && event.button === 0 && !event.metaKey && !event.ctrlKey && !event.shiftKey && !event.altKey) {
                    startPending(link.dataset.navigationMessage, link);
                }
            });
        });
        window.addEventListener('pageshow', () => {
            pending = false;
            document.querySelectorAll('form[data-submitting]').forEach((form) => {
                delete form.dataset.submitting;
                form.removeAttribute('aria-busy');
            });
            document.querySelectorAll('.installer-button.is-pending').forEach((button) => {
                if (button.tagName === 'BUTTON') button.disabled = false;
                button.classList.remove('is-pending');
                button.removeAttribute('aria-busy');
            });
            navigation.forEach((link) => link.removeAttribute('aria-disabled'));
            document.querySelector('.installer-pending').textContent = '';
            if (connection) connection.dispatchEvent(new Event('change'));
        });
        const errorSummary = document.querySelector('[data-error-summary]');
        errorSummary?.focus();
        errorSummary?.querySelectorAll('a').forEach((link) => {
            link.addEventListener('click', () => {
                const target = document.getElementById(link.hash.slice(1));
                (target?.matches('input, select') ? target : target?.querySelector('input'))?.focus();
            });
        });
    })();
</script>
