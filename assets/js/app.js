/**
 * app.js - PayHero Payment Module
 * Handles form validation, AJAX requests, and modal management
 */

(function() {
    'use strict';

    // ============================================
    // DOM Elements
    // ============================================
    const form = document.getElementById('paymentForm');
    const phoneInput = document.getElementById('phone');
    const amountInput = document.getElementById('amount');
    const payButton = document.getElementById('payButton');
    const phoneError = document.getElementById('phoneError');
    const amountError = document.getElementById('amountError');
    
    const modal = document.getElementById('paymentModal');
    const modalIcon = document.getElementById('modalIcon');
    const modalTitle = document.getElementById('modalTitle');
    const modalMessage = document.getElementById('modalMessage');
    const modalDetails = document.getElementById('modalDetails');
    const detailReference = document.getElementById('detailReference');
    const detailStatus = document.getElementById('detailStatus');
    const detailCheckoutId = document.getElementById('detailCheckoutId');
    const modalActionBtn = document.getElementById('modalActionBtn');

    // ============================================
    // State
    // ============================================
    let isProcessing = false;
    let pollingInterval = null;
    let pollAttempts = 0;
    const MAX_POLL_ATTEMPTS = 90; // 2 seconds * 90 = 3 minutes

    // ============================================
    // Utility Functions
    // ============================================
    function showError(field, message) {
        const errorEl = field === 'phone' ? phoneError : amountError;
        const wrapper = field === 'phone' ? phoneInput.closest('.input-wrapper') : amountInput.closest('.input-wrapper');
        errorEl.textContent = message;
        errorEl.classList.toggle('visible', message.length > 0);
        wrapper.classList.toggle('error', message.length > 0);
    }

    function clearErrors() {
        showError('phone', '');
        showError('amount', '');
        document.querySelectorAll('.input-wrapper').forEach(el => {
            el.classList.remove('error', 'success');
        });
    }

    function validatePhone(phone) {
        const digits = phone.replace(/\D/g, '');
        if (digits.length === 0) return 'Phone number is required.';
        if (digits.length < 9 || digits.length > 10) return 'Phone number must be 9-10 digits.';
        if (!digits.startsWith('07') && !digits.startsWith('01')) {
            return 'Phone number must start with 07 or 01.';
        }
        return '';
    }

    function validateAmount(amount) {
        if (amount === '' || amount === null || amount === undefined) return 'Amount is required.';
        const num = Number(amount);
        if (isNaN(num) || num < 1) return 'Amount must be at least 1 KES.';
        if (num > 150000) return 'Amount exceeds maximum limit of 150,000 KES.';
        if (!Number.isInteger(num)) return 'Amount must be a whole number.';
        return '';
    }

    function setInputSuccess(field) {
        const wrapper = field === 'phone' ? phoneInput.closest('.input-wrapper') : amountInput.closest('.input-wrapper');
        wrapper.classList.add('success');
        const icon = wrapper.querySelector('.input-suffix i');
        if (icon) icon.style.display = 'inline-block';
    }

    function resetInputStates() {
        document.querySelectorAll('.input-wrapper').forEach(el => {
            el.classList.remove('error', 'success');
            const icon = el.querySelector('.input-suffix i');
            if (icon) icon.style.display = 'none';
        });
    }

    function resetForm() {
        form.reset();
        resetInputStates();
        payButton.disabled = false;
        payButton.classList.remove('loading');
        isProcessing = false;
        stopPolling();
    }

    // ============================================
    // Modal Functions
    // ============================================
    function showModal() {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function hideModal() {
        modal.classList.remove('active');
        document.body.style.overflow = '';
        // Reset modal state
        modalIcon.innerHTML = '<div class="spinner"></div>';
        modalTitle.textContent = 'Sending STK Push...';
        modalMessage.textContent = 'Please wait while we contact the M-Pesa payment gateway.';
        modalDetails.style.display = 'none';
        modalActionBtn.style.display = 'none';
        modalActionBtn.className = 'modal-btn';
        // Reset status color
        const statusEl = document.getElementById('detailStatus');
        if (statusEl) statusEl.style.color = '';
    }

    function updateModalLoading() {
        modalIcon.innerHTML = '<div class="spinner"></div>';
        modalTitle.textContent = 'Sending STK Push...';
        modalMessage.textContent = 'Please wait while we contact the M-Pesa payment gateway.';
        modalDetails.style.display = 'none';
        modalActionBtn.style.display = 'none';
    }

    function updateModalSuccess(data) {
        modalIcon.innerHTML = '<i class="fas fa-circle-check icon-success"></i>';
        modalTitle.textContent = 'STK Push Sent';
        modalMessage.textContent = 'We have sent an M-Pesa payment request to your phone. Please check your phone and enter your PIN to complete the transaction.';

        // Show details
        modalDetails.style.display = 'block';
        detailReference.textContent = data.reference || 'N/A';
        detailStatus.textContent = 'Pending - Waiting for confirmation';
        detailStatus.style.color = '#ed8936'; // Orange for pending
        detailCheckoutId.textContent = data.checkout_request_id || 'N/A';

        modalActionBtn.textContent = 'Continue';
        modalActionBtn.className = 'modal-btn';
        modalActionBtn.style.display = 'inline-block';
        modalActionBtn.onclick = function() {
            hideModal();
            resetForm();
        };
    }

    function updateModalError(message) {
        modalIcon.innerHTML = '<i class="fas fa-circle-xmark icon-error"></i>';
        modalTitle.textContent = 'Unable to Send STK Push';
        modalMessage.textContent = message || 'An error occurred while processing your payment.';

        modalDetails.style.display = 'none';
        modalActionBtn.textContent = 'Try Again';
        modalActionBtn.className = 'modal-btn btn-danger';
        modalActionBtn.style.display = 'inline-block';
        modalActionBtn.onclick = function() {
            hideModal();
            resetForm();
            payButton.disabled = false;
            payButton.classList.remove('loading');
            isProcessing = false;
        };
        // Re-enable the button
        payButton.disabled = false;
        payButton.classList.remove('loading');
        isProcessing = false;
    }

    function updateModalPaymentSuccess(data) {
        modalIcon.innerHTML = '<i class="fas fa-circle-check icon-success"></i>';
        modalTitle.textContent = 'Payment Successful! ✓';
        modalMessage.textContent = 'Your payment has been processed successfully. Thank you!';

        modalDetails.style.display = 'block';
        detailReference.textContent = data.reference || 'N/A';
        detailStatus.textContent = 'Completed ✓';
        detailStatus.style.color = '#48bb78'; // Green for success
        detailCheckoutId.textContent = data.checkout_request_id || 'N/A';

        modalActionBtn.textContent = 'Continue';
        modalActionBtn.className = 'modal-btn';
        modalActionBtn.style.display = 'inline-block';
        modalActionBtn.onclick = function() {
            hideModal();
            resetForm();
        };
    }

    function updateModalPaymentCancelled() {
        modalIcon.innerHTML = '<i class="fas fa-circle-xmark icon-error"></i>';
        modalTitle.textContent = 'Payment Cancelled';
        modalMessage.textContent = 'You cancelled the payment on your phone. Would you like to try again?';

        modalDetails.style.display = 'none';
        modalActionBtn.textContent = 'Try Again';
        modalActionBtn.className = 'modal-btn btn-danger';
        modalActionBtn.style.display = 'inline-block';
        modalActionBtn.onclick = function() {
            hideModal();
            resetForm();
        };
    }

    function updateModalPaymentFailed(message) {
        modalIcon.innerHTML = '<i class="fas fa-circle-xmark icon-error"></i>';
        modalTitle.textContent = 'Payment Failed';
        modalMessage.textContent = message || 'The payment was not completed. Please try again.';

        modalDetails.style.display = 'none';
        modalActionBtn.textContent = 'Try Again';
        modalActionBtn.className = 'modal-btn btn-danger';
        modalActionBtn.style.display = 'inline-block';
        modalActionBtn.onclick = function() {
            hideModal();
            resetForm();
        };
    }

    function updateModalStatus(status, message) {
        // Update the status in the modal
        const statusEl = document.getElementById('detailStatus');
        if (statusEl) {
            statusEl.textContent = status;
            if (status === 'Completed ✓') {
                statusEl.style.color = '#48bb78';
            } else if (status === 'Failed' || status === 'Cancelled') {
                statusEl.style.color = '#fc8181';
            } else if (status === 'Pending - Waiting for confirmation' || status === 'Pending...') {
                statusEl.style.color = '#ed8936';
            }
        }
        if (message) {
            modalMessage.textContent = message;
        }
    }

    // ============================================
    // Polling Functions
    // ============================================
    function startPolling() {
        pollAttempts = 0;
        if (pollingInterval) {
            clearInterval(pollingInterval);
        }
        pollingInterval = setInterval(checkPaymentStatus, 2000);
        // Update message to show polling
        modalMessage.textContent = 'Waiting for payment confirmation... Please check your phone.';
        // Show that we're waiting
        const statusEl = document.getElementById('detailStatus');
        if (statusEl) {
            statusEl.textContent = 'Waiting...';
            statusEl.style.color = '#ed8936';
        }
    }

    function stopPolling() {
        if (pollingInterval) {
            clearInterval(pollingInterval);
            pollingInterval = null;
        }
        pollAttempts = 0;
    }

    async function checkPaymentStatus() {
        pollAttempts++;
        
        try {
            const response = await fetch('check_status.php', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json'
                },
                cache: 'no-cache'
            });

            if (!response.ok) {
                throw new Error('Failed to check payment status');
            }

            const data = await response.json();
            console.log('Status check:', data);

            // Handle different statuses
            if (data.status === 'success') {
                stopPolling();
                updateModalPaymentSuccess(data.data || {});
                return;
            } else if (data.status === 'cancelled') {
                stopPolling();
                updateModalPaymentCancelled();
                return;
            } else if (data.status === 'failed') {
                stopPolling();
                updateModalPaymentFailed('Payment failed. Please try again.');
                return;
            } else if (data.status === 'received') {
                // Callback received but processing
                updateModalStatus('Processing...', 'Payment callback received. Processing your transaction...');
                return;
            } else if (data.status === 'pending') {
                updateModalStatus('Pending...', 'Payment is still processing...');
                return;
            }

            // Still waiting
            if (pollAttempts >= MAX_POLL_ATTEMPTS) {
                stopPolling();
                updateModalPaymentFailed('Payment confirmation timed out. Please check your M-Pesa app for status.');
            } else {
                // Update waiting message with counter
                const seconds = pollAttempts * 2;
                modalMessage.textContent = 'Waiting for payment confirmation... (' + seconds + 's)';
                const statusEl = document.getElementById('detailStatus');
                if (statusEl) {
                    statusEl.textContent = 'Waiting... (' + seconds + 's)';
                }
            }
        } catch (error) {
            console.error('Polling error:', error);
            // Don't stop polling on network errors, keep trying
        }
    }

    // ============================================
    // Form Submission Handler
    // ============================================
    async function handleSubmit(e) {
        e.preventDefault();

        // Prevent double submission
        if (isProcessing) return;
        isProcessing = true;

        // Clear previous errors and states
        clearErrors();
        resetInputStates();

        // Get values
        const phone = phoneInput.value.trim();
        const amount = amountInput.value.trim();

        // Validate
        const phoneValidation = validatePhone(phone);
        const amountValidation = validateAmount(amount);

        let hasError = false;

        if (phoneValidation) {
            showError('phone', phoneValidation);
            hasError = true;
        } else {
            showError('phone', '');
            setInputSuccess('phone');
        }

        if (amountValidation) {
            showError('amount', amountValidation);
            hasError = true;
        } else {
            showError('amount', '');
            setInputSuccess('amount');
        }

        if (hasError) {
            isProcessing = false;
            return;
        }

        // Disable form
        payButton.disabled = true;
        payButton.classList.add('loading');

        // Show modal
        updateModalLoading();
        showModal();

        try {
            // Prepare data - phone without leading zero
            const cleanPhone = phone.replace(/\D/g, '');
            const formData = {
                phone: cleanPhone,
                amount: parseInt(amount, 10)
            };

            console.log('Sending payment request:', formData);

            // Send request with timeout
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 30000);

            const response = await fetch('process_payment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(formData),
                signal: controller.signal
            });

            clearTimeout(timeoutId);

            // Check if response is OK
            if (!response.ok) {
                let errorMessage = 'Payment request failed (HTTP ' + response.status + ')';
                try {
                    const errorData = await response.json();
                    if (errorData.error) {
                        errorMessage = errorData.error;
                    }
                } catch (e) {
                    try {
                        const text = await response.text();
                        if (text) {
                            errorMessage = text.substring(0, 200);
                        }
                    } catch (e2) {}
                }
                throw new Error(errorMessage);
            }

            // Check content type
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                console.error('Non-JSON response:', text);
                throw new Error('Server returned invalid response. Please check PHP error logs.');
            }

            const data = await response.json();
            console.log('Payment response:', data);

            if (data.success) {
                // Update modal with success
                updateModalSuccess(data.data);
                // Start polling for callback
                startPolling();
            } else {
                throw new Error(data.error || 'Payment request failed');
            }

        } catch (error) {
            console.error('Payment error:', error);
            if (error.name === 'AbortError') {
                updateModalError('Request timed out. Please check your connection and try again.');
            } else {
                updateModalError(error.message || 'An unexpected error occurred.');
            }
            // Reset form state
            payButton.disabled = false;
            payButton.classList.remove('loading');
            isProcessing = false;
        }
    }

    // ============================================
    // Input Event Handlers
    // ============================================
    function handlePhoneInput(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length > 10) value = value.slice(0, 10);
        e.target.value = value;

        // Clear error on input
        if (phoneError.classList.contains('visible')) {
            showError('phone', '');
            phoneInput.closest('.input-wrapper').classList.remove('error');
        }
    }

    function handleAmountInput(e) {
        let value = e.target.value.replace(/[^0-9]/g, '');
        if (value.length > 0 && parseInt(value, 10) > 150000) {
            value = '150000';
        }
        e.target.value = value;

        if (amountError.classList.contains('visible')) {
            showError('amount', '');
            amountInput.closest('.input-wrapper').classList.remove('error');
        }
    }

    // ============================================
    // Keyboard Shortcuts
    // ============================================
    function handleKeydown(e) {
        // Press Escape to close modal
        if (e.key === 'Escape' && modal.classList.contains('active')) {
            if (modalActionBtn.style.display !== 'none' && !modalActionBtn.disabled) {
                hideModal();
                resetForm();
            }
        }
    }

    // ============================================
    // Initialize
    // ============================================
    function init() {
        // Form submission
        form.addEventListener('submit', handleSubmit);

        // Input events
        phoneInput.addEventListener('input', handlePhoneInput);
        amountInput.addEventListener('input', handleAmountInput);

        // Keyboard shortcuts
        document.addEventListener('keydown', handleKeydown);

        // Close modal on overlay click
        modal.addEventListener('click', function(e) {
            if (e.target === this && modalActionBtn.style.display !== 'none' && !modalActionBtn.disabled) {
                hideModal();
                resetForm();
            }
        });

        console.log('PayHero Payment Module initialized.');
    }

    // Run initialization when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();