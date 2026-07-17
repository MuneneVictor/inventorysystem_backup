<?php
// stk_push.php - Main payment page
// This file serves as the entry point for the payment gateway
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PayHero - Secure M-Pesa Payments</title>
    
    <!-- Google Fonts: Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Font Awesome 6 (Free) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- Main Payment Container -->
    <div class="payment-container">
        <div class="payment-card glass">
            <!-- Header -->
            <div class="card-header">
                <div class="header-icon">
                    <i class="fas fa-mobile-screen-button"></i>
                </div>
                <h1>Pay with M-Pesa</h1>
                <p class="subtitle">Secure payment via PayHero</p>
            </div>

            <!-- Payment Form -->
            <form id="paymentForm" class="payment-form" novalidate>
                <!-- Phone Number Field -->
                <div class="form-group">
                    <label for="phone">
                        <i class="fas fa-phone"></i>
                        Phone Number
                    </label>
                    <div class="input-wrapper">
                        <span class="input-prefix">+254</span>
                        <input 
                            type="tel" 
                            id="phone" 
                            name="phone" 
                            placeholder="712 345 678"
                            maxlength="10"
                            autocomplete="tel"
                            required
                        >
                        <span class="input-suffix">
                            <i class="fas fa-check-circle" style="display:none;"></i>
                        </span>
                    </div>
                    <small class="field-hint">Enter phone number without country code</small>
                    <div class="field-error" id="phoneError"></div>
                </div>

                <!-- Amount Field -->
                <div class="form-group">
                    <label for="amount">
                        <i class="fas fa-coins"></i>
                        Amount (KES)
                    </label>
                    <div class="input-wrapper">
                        <span class="input-prefix">KES</span>
                        <input 
                            type="number" 
                            id="amount" 
                            name="amount" 
                            placeholder="0.00"
                            min="1"
                            step="1"
                            required
                        >
                        <span class="input-suffix">
                            <i class="fas fa-check-circle" style="display:none;"></i>
                        </span>
                    </div>
                    <div class="field-error" id="amountError"></div>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn-primary" id="payButton">
                    <i class="fas fa-lock"></i>
                    Pay Securely
                </button>

                <!-- Security Badge -->
                <div class="security-badge">
                    <i class="fas fa-shield-halved"></i>
                    <span>Secured by PayHero &bull; SSL Encrypted</span>
                </div>
            </form>

            <!-- Footer -->
            <div class="card-footer">
                <div class="payment-methods">
                    <i class="fas fa-credit-card"></i>
                    <i class="fas fa-mobile-screen"></i>
                    <i class="fas fa-building-columns"></i>
                </div>
                <p>&copy; 2026 PayHero. All rights reserved.</p>
            </div>
        </div>
    </div>

    <!-- Loading Modal Overlay -->
    <div class="modal-overlay" id="paymentModal">
        <div class="modal-content glass">
            <!-- Dynamic Icon Container -->
            <div class="modal-icon" id="modalIcon">
                <div class="spinner"></div>
            </div>
            
            <!-- Title -->
            <h2 id="modalTitle">Sending STK Push...</h2>
            
            <!-- Message -->
            <p id="modalMessage">Please wait while we contact the M-Pesa payment gateway.</p>
            
            <!-- Details Container -->
            <div class="modal-details" id="modalDetails" style="display:none;">
                <div class="detail-row">
                    <span class="detail-label">Reference</span>
                    <span class="detail-value" id="detailReference">-</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Status</span>
                    <span class="detail-value" id="detailStatus">-</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Checkout ID</span>
                    <span class="detail-value" id="detailCheckoutId">-</span>
                </div>
            </div>

            <!-- Action Button -->
            <button class="modal-btn" id="modalActionBtn" style="display:none;">
                Continue
            </button>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="assets/js/app.js"></script>
</body>
</html>