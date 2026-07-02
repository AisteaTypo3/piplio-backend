(() => {
    const initWidget = (widget) => {
        const form = widget.querySelector('.piplio-interest-form');
        const feedback = widget.querySelector('.piplio-interest-feedback');
        const messages = widget.querySelector('.piplio-interest-messages');
        const submitButton = form ? form.querySelector('.piplio-interest-submit') : null;
        const toggleId = widget.id ? `${widget.id}-toggle` : '';
        const toggle = toggleId ? document.getElementById(toggleId) : null;
        const endpoint = form ? (form.dataset.endpoint || form.action) : '';
        const labelSubmitting = form ? (form.dataset.labelSubmitting || 'Saving ...') : 'Saving ...';
        const labelSubmit = form ? (form.dataset.labelSubmit || 'Submit') : 'Submit';
        const messageInvalid = form ? (form.dataset.messageInvalid || 'The AJAX response was invalid. Please clear caches and test again.') : 'The AJAX response was invalid. Please clear caches and test again.';
        const messageError = form ? (form.dataset.messageError || 'The request could not be stored.') : 'The request could not be stored.';
        const messageSuccess = form ? (form.dataset.messageSuccess || 'Thanks, your email has been saved.') : 'Thanks, your email has been saved.';

        if (!form || !feedback || !submitButton || form.dataset.ajax !== 'true' || !endpoint) {
            return;
        }

        const setFeedback = (message, type) => {
            if (messages) {
                messages.innerHTML = '';
            }
            feedback.hidden = !message;
            feedback.textContent = message || '';
            feedback.className = 'piplio-interest-feedback' + (type ? ' is-' + type : '');
        };

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            setFeedback('', '');
            submitButton.disabled = true;
            submitButton.textContent = labelSubmitting;

            try {
                const formData = new FormData(form);
                const payload = new URLSearchParams();
                for (const [key, value] of formData.entries()) {
                    if (typeof value === 'string') {
                        payload.append(key, value);
                    }
                }

                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    body: payload.toString(),
                    credentials: 'same-origin'
                });

                const contentType = response.headers.get('content-type') || '';
                if (!contentType.includes('application/json')) {
                    throw new Error(messageInvalid);
                }

                const result = await response.json();
                if (!response.ok || !result.ok) {
                    throw new Error(result.message || messageError);
                }

                setFeedback(result.message || messageSuccess, 'success');
                form.reset();
                if (toggle) {
                    toggle.checked = true;
                }
            } catch (error) {
                setFeedback(error instanceof Error ? error.message : messageError, 'error');
                if (toggle) {
                    toggle.checked = true;
                }
            } finally {
                submitButton.disabled = false;
                submitButton.textContent = labelSubmit;
            }
        });
    };

    const boot = () => {
        document.querySelectorAll('[data-piplio-widget]').forEach(initWidget);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }
})();
