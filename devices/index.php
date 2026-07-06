<?php 
require_once "vimarktech/includes/header.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    
    <!-- SEO Meta Tags -->
    <meta name="description" content="Vimark Tech - The #1 POS system for computer shops and repair centers in Kenya. Cloud-based and standalone inventory management software. Free installation in Nairobi. Start 7-day trial.">
    <meta name="keywords" content="POS system Kenya, inventory management software Kenya, computer shop management system, repair shop software Kenya, IT business management system, business management software Kenya, spare parts management system, sales and inventory system Kenya">
    <meta name="author" content="Vimark Tech">
    <meta name="robots" content="index, follow">
    
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    
    <!-- SEO-Optimized Title (under 60 chars) -->
    <title>Vimark Tech - #1 POS & Inventory Management System for Computer Shops in Kenya</title>
    
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="canonical" href="https://vimarktech.com/" />
    
    <!-- Open Graph tags for social sharing -->
    <meta property="og:title" content="Vimark Tech - #1 POS & Business Management System for Kenyan Computer Shops" />
    <meta property="og:description" content="Cloud-based and standalone POS system for computer shops, repair centers, and IT businesses in Kenya. Free installation in Nairobi. Start free trial." />
    <meta property="og:image" content="https://vimarktech.com/assets/og-image.jpg" />
    <meta property="og:url" content="https://vimarktech.com/" />
    <meta property="og:type" content="website" />
    <meta property="og:locale" content="en_KE" />
    
    <!-- Twitter Card tags -->
    <meta name="twitter:card" content="summary_large_image"/>
    <meta name="twitter:title" content="Vimark Tech - #1 POS & Inventory System for Computer Shops in Kenya" />
    <meta name="twitter:description" content="Manage inventory, sales, and repairs in one place. Cloud or standalone system. Free installation in Nairobi." />
    <meta name="twitter:image" content="https://vimarktech.com/assets/og-image.jpg" />
    
    <!-- Schema Markup (Structured Data) -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "SoftwareApplication",
      "name": "Vimark Tech",
      "applicationCategory": "BusinessApplication",
      "operatingSystem": "Web, Windows, Linux",
      "description": "Complete POS system, inventory management software, and repair shop management system for computer shops, repair centers, and IT businesses in Kenya.",
      "url": "https://vimarktech.com",
      "softwareVersion": "2.0",
      "offers": [
        {
          "@type": "Offer",
          "name": "Cloud Plan",
          "price": "4000",
          "priceCurrency": "KES",
          "availability": "https://schema.org/InStock"
        },
        {
          "@type": "Offer",
          "name": "Standalone System",
          "price": "60000",
          "priceCurrency": "KES",
          "availability": "https://schema.org/InStock"
        }
      ],
      "provider": {
        "@type": "Organization",
        "name": "Vimark Tech",
        "url": "https://vimarktech.com",
        "logo": "https://vimarktech.com/assets/logo.png",
        "contactPoint": {
          "@type": "ContactPoint",
          "telephone": "+254711529618",
          "contactType": "sales",
          "email": "support@vimarktech.com",
          "availableLanguage": ["English", "Swahili"]
        },
        "address": {
          "@type": "PostalAddress",
          "addressLocality": "Nairobi",
          "addressCountry": "KE"
        }
      },
      "aggregateRating": {
        "@type": "AggregateRating",
        "ratingValue": "4.8",
        "ratingCount": "50"
      }
    }
    </script>
    
    <!-- Organization Schema -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "Organization",
      "name": "Vimark Tech",
      "url": "https://vimarktech.com",
      "logo": "https://vimarktech.com/assets/logo.png",
      "sameAs": [
        "https://www.instagram.com/_vic.mn"
      ],
      "email": "support@vimarktech.com",
      "telephone": "+254711529618",
      "address": {
        "@type": "PostalAddress",
        "addressLocality": "Nairobi",
        "addressCountry": "KE"
      }
    }
    </script>
    
    <!-- LocalBusiness Schema -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "LocalBusiness",
      "name": "Vimark Tech",
      "description": "POS system, inventory management software, repair shop management system and business management software for computer shops, repair centers and IT businesses in Kenya. Includes POS, inventory control, repair tracking, spare parts management and analytics.",
      "url": "https://vimarktech.com",
      "telephone": "+254711529618",
      "email": "support@vimarktech.com",
      "address": {
        "@type": "PostalAddress",
        "addressLocality": "Nairobi",
        "addressCountry": "KE"
      },
      "priceRange": "KES 4,000 - KES 60,000",
      "areaServed": {
        "@type": "Country",
        "name": "Kenya"
      },
      "hasOfferCatalog": {
        "@type": "OfferCatalog",
        "name": "Business Management Solutions",
        "itemListElement": [
          {
            "@type": "Offer",
            "itemOffered": {
              "@type": "Service",
              "name": "Cloud POS System",
              "description": "Monthly subscription business management system"
            },
            "price": "4000",
            "priceCurrency": "KES"
          },
          {
            "@type": "Offer",
            "itemOffered": {
              "@type": "Service",
              "name": "Standalone System",
              "description": "One-time purchase with full ownership"
            },
            "price": "60000",
            "priceCurrency": "KES"
          }
        ]
      }
    }
    </script>
    
    <!-- Breadcrumb Schema -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "BreadcrumbList",
      "itemListElement": [
        {
          "@type": "ListItem",
          "position": 1,
          "name": "Home",
          "item": "https://vimarktech.com/"
        },
        {
          "@type": "ListItem",
          "position": 2,
          "name": "Features",
          "item": "https://vimarktech.com/#features"
        },
        {
          "@type": "ListItem",
          "position": 3,
          "name": "Pricing",
          "item": "https://vimarktech.com/#pricing"
        }
      ]
    }
    </script>
    
    <!-- FAQ Schema -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "FAQPage",
      "mainEntity": [
        {
          "@type": "Question",
          "name": "What is a POS system for a computer shop?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "A POS (Point of Sale) system for a computer shop is software that helps manage sales, inventory tracking, customer transactions, and repair services in one integrated platform."
          }
        },
        {
          "@type": "Question",
          "name": "Is Vimark Tech suitable for repair shops?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "Yes, Vimark Tech includes comprehensive repair tracking software for laptop and phone repair shops, including job logging, customer management, and spare parts tracking."
          }
        },
        {
          "@type": "Question",
          "name": "Can I purchase the standalone system?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "Yes, we offer a standalone business management system for KES 60,000 one-time payment with full ownership, unlimited users, and free installation in Nairobi."
          }
        },
        {
          "@type": "Question",
          "name": "Do you offer installation in Kenya?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "Yes, we provide free on-site installation for businesses in Nairobi and free remote installation for businesses elsewhere in Kenya."
          }
        },
        {
          "@type": "Question",
          "name": "Is my business data secure?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "Absolutely. Each business has its own isolated account with bank-grade encryption, secure login systems, and automatic backups."
          }
        }
      ]
    }
    </script>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --red: #8B0000;
            --red-light: #c0392b;
            --red-gradient: linear-gradient(135deg, #8B0000, #c0392b);
            --green: #27ae60;
            --green-light: #2ecc71;
            --bg: #ffffff;
            --bg-alt: #f8f9fa;
            --text: #1a1a2e;
            --text-light: #666;
            --card: #ffffff;
            --border: #eee;
            --shadow: 0 5px 25px rgba(0,0,0,0.05);
            --shadow-hover: 0 20px 50px rgba(0,0,0,0.1);
            --radius: 16px;
            --transition: 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body.dark {
            --bg: #0a0a16;
            --bg-alt: #0f0f1c;
            --text: #ffffff;
            --text-light: #aaa;
            --card: #151528;
            --border: #222238;
            --shadow: 0 5px 25px rgba(0,0,0,0.2);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            overflow-x: hidden;
            line-height: 1.6;
            width: 100%;
        }

        .reveal {
            opacity: 0;
            transform: translateY(30px);
            transition: opacity 0.8s cubic-bezier(0.2, 0.9, 0.4, 1.1), transform 0.8s cubic-bezier(0.2, 0.9, 0.4, 1.1);
            will-change: opacity, transform;
        }
        .reveal.revealed {
            opacity: 1;
            transform: translateY(0);
        }
        .reveal-delay-1 { transition-delay: 0.1s; }
        .reveal-delay-2 { transition-delay: 0.2s; }
        .reveal-delay-3 { transition-delay: 0.3s; }
        .reveal-delay-4 { transition-delay: 0.4s; }
        .reveal-delay-5 { transition-delay: 0.5s; }

        nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 5%;
            background: rgba(255,255,255,0.92);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            box-shadow: 0 1px 30px rgba(0,0,0,0.04);
            position: sticky;
            top: 0;
            z-index: 1000;
            transition: var(--transition);
            border-bottom: 1px solid transparent;
        }
        body.dark nav {
            background: rgba(10,10,22,0.92);
            border-bottom: 1px solid rgba(255,255,255,0.04);
        }
        nav.scrolled {
            border-bottom: 1px solid var(--border);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 0;
            text-decoration: none;
            flex-shrink: 0;
        }
        .logo-img {
            width: 38px;
            height: 38px;
            border-radius: 8px;
            object-fit: contain;
            flex-shrink: 0;
        }
        .logo-icon-fallback {
            width: 38px;
            height: 38px;
            background: var(--red-gradient);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 800;
            font-size: 1rem;
            box-shadow: 0 4px 12px rgba(139,0,0,0.25);
            flex-shrink: 0;
        }
        .logo-text h1 {
            font-size: 1.25rem;
            font-weight: 800;
            background: var(--red-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1.1;
            white-space: nowrap;
        }
        .logo-text span {
            font-size: 0.65rem;
            color: var(--text-light);
            display: block;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            font-weight: 600;
            white-space: nowrap;
        }

        .nav-links {
            display: flex;
            gap: 30px;
            align-items: center;
        }
        .nav-links a {
            text-decoration: none;
            color: var(--text);
            font-weight: 500;
            font-size: 0.9rem;
            position: relative;
            padding: 4px 0;
        }
        .nav-links a::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--red);
            border-radius: 2px;
            transition: width 0.25s;
        }
        .nav-links a:hover::after {
            width: 100%;
        }
        .nav-links a:hover {
            color: var(--red);
        }

        .nav-buttons {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .btn {
            padding: 10px 22px;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.85rem;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            font-family: inherit;
            white-space: nowrap;
            letter-spacing: 0.2px;
            position: relative;
            overflow: hidden;
        }
        .btn:active { transform: scale(0.96); }

        .btn-login {
            background: transparent;
            border: 2px solid var(--border);
            color: var(--text);
        }
        .btn-login:hover {
            border-color: var(--red);
            color: var(--red);
            background: rgba(139,0,0,0.03);
            transform: translateY(-2px);
        }
        .btn-register, .btn-primary {
            background: var(--red-gradient);
            color: white;
            box-shadow: 0 4px 18px rgba(139,0,0,0.25);
        }
        .btn-register:hover, .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(139,0,0,0.35);
        }
        .btn-outline {
            background: transparent;
            border: 2px solid var(--red);
            color: var(--red);
        }
        .btn-outline:hover {
            background: var(--red);
            color: white;
            transform: translateY(-2px);
        }
        .btn-success {
            background: linear-gradient(135deg, #219a52, #27ae60);
            color: white;
            box-shadow: 0 4px 18px rgba(39,174,96,0.25);
        }
        .btn-success:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(39,174,96,0.35);
        }

        .theme-toggle {
            background: var(--bg-alt);
            border: 1px solid var(--border);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
            color: var(--text);
            font-size: 1.1rem;
        }
        .theme-toggle:hover {
            transform: rotate(20deg);
        }
        
        .nav-phone {
            display: flex;
            align-items: center;
            gap: 6px;
            background: rgba(39,174,96,0.1);
            padding: 6px 12px;
            border-radius: 40px;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--green);
            text-decoration: none;
            transition: var(--transition);
        }
        .nav-phone:hover {
            background: var(--green);
            color: white;
        }
        .nav-phone i {
            font-size: 0.9rem;
        }

        .hero {
            background: linear-gradient(180deg, #fafbfc 0%, #ffffff 40%, #f8f9fb 100%);
            padding: 70px 5%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
            gap: 40px;
            position: relative;
            overflow: hidden;
        }
        body.dark .hero {
            background: linear-gradient(180deg, #0a0a16 0%, #0f0f1c 40%, #0a0a16 100%);
        }
        .hero::before {
            content: '';
            position: absolute;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(139,0,0,0.04) 0%, transparent 70%);
            top: -150px;
            right: -150px;
            border-radius: 50%;
            animation: floatBubble 8s ease-in-out infinite;
        }
        .hero::after {
            content: '';
            position: absolute;
            width: 350px;
            height: 350px;
            background: radial-gradient(circle, rgba(39,174,96,0.04) 0%, transparent 70%);
            bottom: -100px;
            left: -100px;
            border-radius: 50%;
            animation: floatBubble 10s ease-in-out infinite reverse;
        }
        @keyframes floatBubble {
            0%, 100% { transform: translate(0, 0); }
            50% { transform: translate(30px, -30px); }
        }

        .hero-content {
            flex: 1;
            min-width: 280px;
            position: relative;
            z-index: 1;
            text-align: center;
        }
        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(139,0,0,0.06);
            color: var(--red);
            padding: 7px 16px;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 700;
            margin-bottom: 20px;
            letter-spacing: 0.5px;
            border: 1px solid rgba(139,0,0,0.1);
        }
        .hero-badge .pulse-dot {
            width: 8px;
            height: 8px;
            background: var(--green);
            border-radius: 50%;
            animation: pulseDot 2s infinite;
        }
        @keyframes pulseDot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(1.6); }
        }
        .hero-content h1 {
            font-size: 3.4rem;
            font-weight: 800;
            margin-bottom: 18px;
            line-height: 1.15;
            letter-spacing: -1px;
        }
        .hero-gradient {
            background: var(--red-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .hero-content p {
            font-size: 1.1rem;
            color: var(--text-light);
            margin-bottom: 28px;
            line-height: 1.7;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
        }
        .hero-buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .dashboard-preview {
            margin-top: 30px;
            text-align: center;
        }
        .dashboard-img {
            max-width: 100%;
            border-radius: var(--radius);
            box-shadow: var(--shadow-hover);
            border: 1px solid var(--border);
            background: var(--card);
            padding: 8px;
        }
        
        .comparison-table {
            background: var(--card);
            border-radius: var(--radius);
            overflow: hidden;
            border: 1px solid var(--border);
            margin: 30px auto;
            max-width: 900px;
        }
        .comparison-row {
            display: flex;
            border-bottom: 1px solid var(--border);
        }
        .comparison-row:last-child {
            border-bottom: none;
        }
        .comparison-feature {
            flex: 1;
            padding: 16px 20px;
            font-weight: 600;
            background: var(--bg-alt);
        }
        .comparison-option {
            flex: 1;
            padding: 16px 20px;
            text-align: center;
        }
        .comparison-option:first-of-type {
            border-right: 1px solid var(--border);
        }
        .comparison-option i.fa-check { color: var(--green); margin-right: 6px; }
        .comparison-option i.fa-times { color: #ccc; }
        .comparison-header {
            background: var(--red-gradient);
            color: white;
        }
        .comparison-header .comparison-feature,
        .comparison-header .comparison-option {
            font-weight: 700;
            font-size: 1.1rem;
        }
        .mpesa-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(39,174,96,0.12);
            padding: 6px 16px;
            border-radius: 40px;
            color: var(--green);
            font-weight: 700;
            font-size: 0.85rem;
        }

        #how-it-works {
            background: var(--bg);
            padding: 60px 5%;
            position: relative;
        }
        .section-label {
            display: inline-block;
            background: rgba(139,0,0,0.06);
            color: var(--red);
            padding: 5px 14px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 10px;
        }
        .section-title {
            text-align: center;
            margin-bottom: 50px;
        }
        .section-title h2 {
            font-size: 2.3rem;
            font-weight: 800;
            margin-bottom: 12px;
            letter-spacing: -0.5px;
        }
        .section-title p {
            color: var(--text-light);
            max-width: 600px;
            margin: 0 auto;
        }

        .steps-container {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 25px;
            position: relative;
        }
        .steps-container::before {
            content: '';
            position: absolute;
            top: 55px;
            left: 12%;
            right: 12%;
            height: 2px;
            background: linear-gradient(90deg, transparent, #e0d0d0 20%, var(--red) 50%, #e0d0d0 80%, transparent);
            z-index: 0;
        }
        body.dark .steps-container::before {
            background: linear-gradient(90deg, transparent, #3a3030 20%, var(--red) 50%, #3a3030 80%, transparent);
        }

        .step-card {
            text-align: center;
            position: relative;
            z-index: 1;
        }
        .step-card-inner {
            background: var(--card);
            border-radius: var(--radius);
            padding: 30px 18px 25px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            transition: var(--transition);
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            overflow: hidden;
        }
        .step-card-inner::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--red-gradient);
            transform: scaleX(0);
            transition: transform 0.4s;
        }
        .step-card-inner:hover::before {
            transform: scaleX(1);
        }
        .step-card-inner:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-hover);
            border-color: transparent;
        }
        .step-number {
            width: 75px;
            height: 75px;
            background: var(--card);
            border: 3px solid var(--border);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--red);
            position: relative;
            z-index: 2;
            transition: var(--transition);
            margin-bottom: 18px;
        }
        .step-card-inner:hover .step-number {
            background: var(--red);
            color: #fff;
            border-color: var(--red);
            box-shadow: 0 10px 30px rgba(139,0,0,0.3);
            animation: swing 0.6s ease;
        }
        @keyframes swing {
            0%, 100% { transform: rotate(0); }
            25% { transform: rotate(6deg); }
            50% { transform: rotate(-4deg); }
            75% { transform: rotate(3deg); }
        }
        .step-check {
            position: absolute;
            bottom: -3px;
            right: -3px;
            width: 26px;
            height: 26px;
            background: var(--green);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 0.65rem;
            border: 3px solid var(--bg);
            z-index: 3;
            opacity: 0;
            transform: scale(0);
            transition: var(--transition);
        }
        .step-card-inner:hover .step-check {
            opacity: 1;
            transform: scale(1);
        }
        .step-card-inner h3 {
            font-size: 1.05rem;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: -0.2px;
        }
        .step-card-inner p {
            font-size: 0.85rem;
            color: var(--text-light);
            line-height: 1.6;
        }
        .step-tag {
            display: inline-block;
            background: var(--bg-alt);
            padding: 3px 10px;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--text-light);
            margin-top: 12px;
            letter-spacing: 0.5px;
        }

        #connection-showcase {
            background: var(--bg-alt);
            padding: 60px 5%;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .connection-orbit {
            position: relative;
            width: 280px;
            height: 280px;
            margin: 30px auto 0;
        }
        .orbit-center {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 80px;
            height: 80px;
            background: var(--red-gradient);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 2rem;
            box-shadow: 0 0 60px rgba(139,0,0,0.3);
            z-index: 2;
            animation: centerPulse 2s ease-in-out infinite;
        }
        @keyframes centerPulse {
            0%, 100% { box-shadow: 0 0 40px rgba(139,0,0,0.3); }
            50% { box-shadow: 0 0 80px rgba(139,0,0,0.5); }
        }
        .orbit-ring {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            border: 1.5px dashed rgba(139,0,0,0.25);
            border-radius: 50%;
            animation: orbitSpin 12s linear infinite;
        }
        .orbit-ring:nth-child(2) { width: 180px; height: 180px; animation-duration: 10s; animation-direction: reverse; }
        .orbit-ring:nth-child(3) { width: 260px; height: 260px; animation-duration: 14s; }
        @keyframes orbitSpin {
            0% { transform: translate(-50%, -50%) rotate(0deg); }
            100% { transform: translate(-50%, -50%) rotate(360deg); }
        }
        .orbit-item {
            position: absolute;
            width: 45px;
            height: 45px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.1rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
            animation: orbitItemFloat 3s ease-in-out infinite;
        }
        .orbit-item:nth-child(4) { top: -20px; left: 50%; transform: translateX(-50%); background: #3b82f6; animation-delay: 0s; }
        .orbit-item:nth-child(5) { bottom: -20px; left: 50%; transform: translateX(-50%); background: #f59e0b; animation-delay: 0.8s; }
        .orbit-item:nth-child(6) { left: -20px; top: 50%; transform: translateY(-50%); background: var(--green); animation-delay: 1.6s; }
        .orbit-item:nth-child(7) { right: -20px; top: 50%; transform: translateY(-50%); background: #8b5cf6; animation-delay: 2.4s; }
        @keyframes orbitItemFloat {
            0%, 100% { transform: translateX(-50%) translateY(0); }
            50% { transform: translateX(-50%) translateY(-8px); }
        }
        .orbit-item:nth-child(5) { animation-name: orbitItemFloatBottom; }
        @keyframes orbitItemFloatBottom {
            0%, 100% { transform: translateX(-50%) translateY(0); }
            50% { transform: translateX(-50%) translateY(8px); }
        }
        .orbit-item:nth-child(6) { animation-name: orbitItemFloatLeft; }
        @keyframes orbitItemFloatLeft {
            0%, 100% { transform: translateY(-50%) translateX(0); }
            50% { transform: translateY(-50%) translateX(-8px); }
        }
        .orbit-item:nth-child(7) { animation-name: orbitItemFloatRight; }
        @keyframes orbitItemFloatRight {
            0%, 100% { transform: translateY(-50%) translateX(0); }
            50% { transform: translateY(-50%) translateX(8px); }
        }
        .connection-dots {
            display: flex;
            justify-content: center;
            gap: 60px;
            flex-wrap: wrap;
            margin-top: 40px;
        }
        .connection-dot {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            animation: bounceIn 0.6s ease backwards;
        }
        .connection-dot:nth-child(1) { animation-delay: 0s; }
        .connection-dot:nth-child(2) { animation-delay: 0.15s; }
        .connection-dot:nth-child(3) { animation-delay: 0.3s; }
        .connection-dot:nth-child(4) { animation-delay: 0.45s; }
        @keyframes bounceIn {
            0% { opacity: 0; transform: translateY(30px); }
            60% { opacity: 1; transform: translateY(-8px); }
            100% { opacity: 1; transform: translateY(0); }
        }
        .connection-dot .dot-circle {
            width: 55px;
            height: 55px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.4rem;
            transition: var(--transition);
        }
        .connection-dot .dot-circle:hover {
            transform: scale(1.15);
        }
        .connection-dot span {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-light);
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 22px;
        }
        .feature-card {
            background: var(--card);
            padding: 30px 25px;
            border-radius: var(--radius);
            transition: var(--transition);
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            text-align: center;
        }
        .feature-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-hover);
            border-color: transparent;
        }
        .feature-icon {
            width: 64px;
            height: 64px;
            background: var(--red-gradient);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 18px;
            font-size: 1.6rem;
            color: #fff;
            box-shadow: 0 6px 20px rgba(139,0,0,0.2);
        }
        .feature-card h3 {
            margin-bottom: 10px;
            font-size: 1.15rem;
            font-weight: 700;
        }
        .feature-card p {
            color: var(--text-light);
            line-height: 1.6;
            font-size: 0.9rem;
        }

        .about-content {
            display: flex;
            gap: 40px;
            align-items: flex-start;
            flex-wrap: wrap;
        }
        .about-text {
            flex: 1;
            min-width: 280px;
        }
        .about-text h3 {
            font-size: 1.4rem;
            margin-bottom: 16px;
            font-weight: 700;
        }
        .about-text p {
            color: var(--text-light);
            line-height: 1.8;
            margin-bottom: 18px;
        }
        .about-list {
            list-style: none;
            margin-top: 16px;
        }
        .about-list li {
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .about-list i {
            color: var(--green);
        }

        .pricing-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 22px;
            max-width: 1300px;
            margin: 0 auto;
            align-items: start;
        }
        .price-card {
            background: var(--card);
            border-radius: var(--radius);
            padding: 32px 22px;
            text-align: center;
            border: 1px solid var(--border);
            position: relative;
            box-shadow: var(--shadow);
            transition: var(--transition);
        }
        .price-card.featured {
            border: 2px solid var(--red);
            box-shadow: 0 10px 35px rgba(139,0,0,0.12);
        }
        .price-card.purchase-card {
            border: 2px solid var(--green);
            background: linear-gradient(180deg, var(--card) 0%, rgba(39,174,96,0.03) 100%);
        }
        .price-card:hover {
            transform: translateY(-5px);
        }
        .popular-badge, .purchase-badge {
            position: absolute;
            top: -12px;
            left: 50%;
            transform: translateX(-50%);
            color: #fff;
            padding: 5px 16px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .popular-badge { background: var(--red); }
        .purchase-badge { background: var(--green); }
        .price-card h3 {
            font-size: 1.3rem;
            margin-bottom: 10px;
            font-weight: 700;
        }
        .price {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--red);
            margin-bottom: 2px;
        }
        .price-card.purchase-card .price { color: var(--green); }
        .price-period {
            color: var(--text-light);
            font-size: 0.82rem;
            margin-bottom: 16px;
        }
        .price-save {
            background: rgba(39,174,96,0.1);
            color: var(--green);
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 0.72rem;
            display: inline-block;
            margin-bottom: 18px;
            font-weight: 600;
        }
        .price-card ul {
            list-style: none;
            margin: 20px 0;
            text-align: left;
        }
        .price-card li {
            padding: 9px 0;
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-light);
            font-size: 0.88rem;
        }
        .price-card li i { color: var(--green); }
        .price-card .btn { width: 100%; justify-content: center; }

        .faq-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
            gap: 14px;
            max-width: 850px;
            margin: 0 auto;
        }
        .faq-item {
            background: var(--card);
            padding: 18px 20px;
            border-radius: 12px;
            border: 1px solid var(--border);
            cursor: pointer;
            transition: var(--transition);
        }
        .faq-item:hover { border-color: var(--red); }
        .faq-question {
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .faq-answer {
            display: none;
            margin-top: 14px;
            color: var(--text-light);
            line-height: 1.7;
            font-size: 0.9rem;
        }
        .faq-answer.active { display: block; }

        .cta-section {
            background: var(--red-gradient);
            color: #fff;
            text-align: center;
            border-radius: 20px;
            margin: 40px 5%;
            padding: 55px 30px;
        }
        .cta-section h2 {
            font-size: 2rem;
            margin-bottom: 12px;
        }
        .cta-section p {
            margin-bottom: 24px;
            opacity: 0.92;
        }
        .cta-section .btn {
            background: #fff;
            color: var(--red);
        }
        .cta-section .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
        }

        footer {
            background: #0f0f1e;
            color: #fff;
            padding: 50px 5% 20px;
        }
        .footer-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 35px;
            margin-bottom: 35px;
        }
        .footer-col h4 {
            margin-bottom: 18px;
            font-size: 1rem;
            font-weight: 700;
        }
        .footer-col a {
            display: block;
            color: #aaa;
            text-decoration: none;
            margin-bottom: 8px;
            font-size: 0.88rem;
            transition: var(--transition);
            cursor: pointer;
        }
        .footer-col a:hover { color: var(--red-light); }
        .footer-col i { color: var(--red-light); margin-right: 6px; }
        .footer-col h3 {
            font-size: 0.7rem;
            color: #777;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 14px 0 6px;
        }
        .social-links {
            display: flex;
            gap: 12px;
            margin-top: 16px;
        }
        .social-links a {
            width: 38px;
            height: 38px;
            background: rgba(255,255,255,0.08);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
            color: #aaa;
        }
        .social-links a:hover {
            background: var(--red-light);
            color: #fff;
            transform: translateY(-3px);
        }
        .footer-bottom {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid rgba(255,255,255,0.08);
        }
        .copyright-text { font-size: 0.82rem; color: #666; }
        .empowering-text {
            font-size: 0.85rem;
            color: #777;
            font-style: italic;
            letter-spacing: 0.5px;
            margin-top: 6px;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.75);
            backdrop-filter: blur(4px);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }
        .modal.active { display: flex; }
        .modal-content {
            background: var(--card);
            max-width: 750px;
            width: 92%;
            max-height: 85vh;
            border-radius: var(--radius);
            overflow: hidden;
            animation: modalIn 0.35s ease;
        }
        @keyframes modalIn {
            from { opacity: 0; transform: scale(0.9) translateY(20px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }
        .modal-header {
            background: var(--red-gradient);
            padding: 18px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header h3 { color: #fff; font-size: 1.1rem; }
        .modal-close {
            background: none;
            border: none;
            color: #fff;
            font-size: 1.5rem;
            cursor: pointer;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
        }
        .modal-close:hover { background: rgba(255,255,255,0.15); }
        .modal-body {
            padding: 28px;
            overflow-y: auto;
            max-height: 60vh;
        }
        .modal-body h4 {
            color: var(--red);
            margin: 18px 0 8px;
            font-size: 1.05rem;
            font-weight: 700;
        }
        .modal-body h4:first-of-type { margin-top: 0; }
        .modal-body p { color: var(--text-light); line-height: 1.8; margin-bottom: 12px; }
        .modal-body ul { margin-left: 20px; margin-bottom: 14px; }
        .modal-body li { color: var(--text-light); line-height: 1.8; margin-bottom: 6px; }
        .contact-email { color: var(--red); font-weight: 600; }
        .purchase-contact-info a { color: var(--green); text-decoration: none; }
        .purchase-contact-info a:hover { color: var(--red); }

        .whatsapp-float {
            position: fixed;
            bottom: 28px;
            right: 28px;
            z-index: 1000;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .whatsapp-icon {
            background: #25D366;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 6px 22px rgba(37,211,102,0.35);
            transition: var(--transition);
            position: relative;
        }
        .whatsapp-icon i { font-size: 28px; color: #fff; }
        .whatsapp-icon::before {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            background: #25D366;
            border-radius: 50%;
            opacity: 0.35;
            animation: pulseRing 1.6s ease-out infinite;
        }
        @keyframes pulseRing {
            0% { transform: scale(1); opacity: 0.35; }
            100% { transform: scale(1.4); opacity: 0; }
        }
        .whatsapp-message {
            background: var(--card);
            color: var(--text);
            padding: 10px 18px;
            border-radius: 50px;
            font-size: 0.82rem;
            font-weight: 500;
            box-shadow: var(--shadow);
            white-space: nowrap;
        }
        .whatsapp-message i { color: #25D366; margin-right: 5px; }
        .whatsapp-float:hover .whatsapp-icon { transform: scale(1.1); }

        .mobile-menu-btn {
            display: none;
            font-size: 1.3rem;
            cursor: pointer;
            width: 38px;
            height: 38px;
            border-radius: 8px;
            background: var(--bg-alt);
            border: 1px solid var(--border);
            align-items: center;
            justify-content: center;
            color: var(--text);
        }

        @media (max-width: 992px) {
            .nav-links {
                display: none;
                position: absolute;
                top: 70px;
                left: 0;
                right: 0;
                background: var(--card);
                flex-direction: column;
                padding: 20px 5%;
                box-shadow: var(--shadow-hover);
                border-bottom: 1px solid var(--border);
                z-index: 999;
            }
            .nav-links.active {
                display: flex;
            }
            .mobile-menu-btn {
                display: flex;
            }
            .nav-buttons {
                gap: 8px;
            }
            .btn {
                padding: 8px 14px;
                font-size: 0.8rem;
            }
            .steps-container {
                grid-template-columns: repeat(2, 1fr);
            }
            .steps-container::before { display: none; }
            .hero-content h1 {
                font-size: 2.5rem;
            }
            .comparison-row {
                flex-wrap: wrap;
            }
            .comparison-feature, .comparison-option {
                flex: 100%;
                text-align: left;
                border-bottom: 1px solid var(--border);
            }
            .comparison-option:first-of-type {
                border-right: none;
            }
        }
        @media (max-width: 768px) {
            nav {
                padding: 10px 4%;
            }
            .logo-img, .logo-icon-fallback {
                width: 32px;
                height: 32px;
            }
            .logo-text h1 {
                font-size: 1rem;
            }
            .logo-text span {
                font-size: 0.55rem;
            }
            .hero {
                flex-direction: column;
                text-align: center;
                padding: 50px 5%;
            }
            .hero-content h1 {
                font-size: 2rem;
            }
            .hero-content p {
                max-width: 100%;
            }
            .hero-buttons {
                justify-content: center;
            }
            section, .cta-section {
                padding: 45px 5%;
            }
            .section-title h2 {
                font-size: 1.8rem;
            }
            .cta-section {
                padding: 35px 18px;
                margin: 30px 4%;
            }
            .cta-section h2 {
                font-size: 1.5rem;
            }
            .faq-grid {
                grid-template-columns: 1fr;
            }
            .pricing-grid {
                grid-template-columns: 1fr;
                max-width: 420px;
                margin: 0 auto;
            }
            .about-content {
                flex-direction: column;
            }
            .features-grid {
                grid-template-columns: 1fr;
            }
            .steps-container {
                grid-template-columns: 1fr;
                gap: 16px;
            }
            .connection-dots {
                gap: 30px;
            }
            .whatsapp-message {
                display: none;
            }
        }
        @media (max-width: 480px) {
            .hero-content h1 {
                font-size: 1.7rem;
            }
        }
        @media (max-width: 380px) {
            .btn {
                padding: 7px 12px;
                font-size: 0.7rem;
            }
            .nav-phone span {
                display: none;
            }
            .nav-phone {
                padding: 6px 10px;
            }
        }
    </style>
</head>
<body>
    <!-- ============ MODALS ============ -->
    <div id="privacyModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-lock"></i> Privacy Policy</h3>
                <button class="modal-close" onclick="closeModal('privacyModal')">&times;</button>
            </div>
            <div class="modal-body">
                <h4>Our Commitment to You</h4>
                <p>At Vimark Tech, your privacy matters to us. We are committed to protecting your personal and business information with the highest standards of care and transparency.</p>
                <h4>What Information We Collect</h4>
                <p>To provide you with the best service possible, we collect basic business information such as your business name, email address, phone number, and physical address. We also collect user information including names and roles within your business.</p>
                <h4>How We Use Your Information</h4>
                <p>Your information helps us deliver and improve our services, process your transactions smoothly, communicate important updates, and keep your account secure. We only use your data to make your experience better, and we never sell your personal information to third parties.</p>
                <h4>Payment & Refund Policy</h4>
                <p>Subscription payments are processed securely. Please note that all subscription fees are non-refundable once paid. Subscriptions do not auto-renew, giving you full control over when to renew. You will receive a reminder before your subscription expires, and you can manually renew at any time from your account dashboard.</p>
                <h4>Your Data is Safe With Us</h4>
                <p>We implement strong security measures to protect your data. We use encryption, secure login systems, and automatic backups. Your trust means everything to us.</p>
                <h4>Questions About Privacy?</h4>
                <p>Contact us at <span class="contact-email">support@vimarktech.com</span> and we'll get back to you promptly.</p>
            </div>
        </div>
    </div>

    <div id="termsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-file-contract"></i> Terms of Service</h3>
                <button class="modal-close" onclick="closeModal('termsModal')">&times;</button>
            </div>
            <div class="modal-body">
                <h4>Welcome to Vimark Tech!</h4>
                <p>We're delighted to have you as part of our community. These terms are designed to create a fair and positive experience for everyone using our platform.</p>
                <h4>Getting Started</h4>
                <p>When you create an account, please provide accurate information so we can serve you better. You're responsible for keeping your login credentials safe, just like you would with your bank account.</p>
                <h4>Subscription & Payment Terms</h4>
                <p>You get a 7 day free trial when you first sign up, no credit card required! After your trial, our affordable subscription plans help us continue providing excellent service. Please note:</p>
                <ul>
                    <li>Subscription fees are non-refundable once paid</li>
                    <li>Subscriptions do not auto-renew. You have full control to manually renew when needed</li>
                    <li>You will receive email reminders before your subscription expires</li>
                    <li>You can upgrade or downgrade your plan at any time with prorated adjustments</li>
                </ul>
                <h4>Purchasing the Standalone System</h4>
                <p>For businesses that prefer complete control, we offer a standalone version of our system. This is a one-time purchase that includes:</p>
                <ul>
                    <li>Complete system installation on your own server (local or cloud)</li>
                    <li>Full database control and management</li>
                    <li>Support for one business account with unlimited users</li>
                    <li>Free installation assistance for Nairobi businesses (physical or remote)</li>
                    <li>Free remote installation for businesses outside Nairobi</li>
                    <li>Lifetime access with no recurring fees</li>
                </ul>
                <h4>Questions or Concerns?</h4>
                <p>Contact us at <span class="contact-email">support@vimarktech.com</span> or call +254 711 529 618.</p>
            </div>
        </div>
    </div>

    <div id="cookieModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-cookie-bite"></i> Cookie Policy</h3>
                <button class="modal-close" onclick="closeModal('cookieModal')">&times;</button>
            </div>
            <div class="modal-body">
                <h4>What Are Cookies?</h4>
                <p>Think of cookies as little helpers that remember your preferences. They're small text files that make your experience on our platform smoother and more personalized.</p>
                <h4>How We Use Cookies</h4>
                <p>We use cookies to keep you logged in, remember your theme preferences (light or dark mode), and understand how you use our platform so we can make it better for you.</p>
                <h4>Your Choice</h4>
                <p>You can control cookies through your browser settings. However, disabling essential cookies may affect some platform features.</p>
            </div>
        </div>
    </div>

    <div id="securityModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-shield-alt"></i> Data Security</h3>
                <button class="modal-close" onclick="closeModal('securityModal')">&times;</button>
            </div>
            <div class="modal-body">
                <h4>Your Security is Our Priority</h4>
                <p>We understand that your business data is valuable. That's why we've built multiple layers of security to keep it safe.</p>
                <h4>Encryption Protection</h4>
                <p>All data sent between your browser and our servers is encrypted using industry standard SSL technology. Your passwords are hashed and salted, meaning even we can't see them.</p>
                <h4>Isolated Business Data</h4>
                <p>Each business has its own completely separate data environment. Your information is never mixed with or accessible to other businesses on our platform.</p>
            </div>
        </div>
    </div>

    <div id="purchaseModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-shopping-cart"></i> Purchase Standalone System</h3>
                <button class="modal-close" onclick="closeModal('purchaseModal')">&times;</button>
            </div>
            <div class="modal-body">
                <h4>Own the Complete System</h4>
                <p>For businesses that prefer full control over their data and infrastructure, we offer a standalone version of Vimark Tech. This is a one-time purchase with no recurring fees. <strong>KES 60,000 One-Time Payment.</strong></p>
                <h4>What's Included:</h4>
                <ul>
                    <li><i class="fas fa-check-circle" style="color:#27ae60;"></i> Complete system installation on your own server (local or cloud)</li>
                    <li><i class="fas fa-check-circle" style="color:#27ae60;"></i> Full database control and management</li>
                    <li><i class="fas fa-check-circle" style="color:#27ae60;"></i> Support for one business account with unlimited users</li>
                    <li><i class="fas fa-check-circle" style="color:#27ae60;"></i> All features included (inventory, sales, repairs, analytics)</li>
                    <li><i class="fas fa-check-circle" style="color:#27ae60;"></i> Free on-site installation for businesses in Nairobi</li>
                    <li><i class="fas fa-check-circle" style="color:#27ae60;"></i> Free remote installation for businesses outside Nairobi</li>
                    <li><i class="fas fa-check-circle" style="color:#27ae60;"></i> Lifetime access with free updates for 1 year</li>
                </ul>
                <div class="purchase-contact-info">
                <h4>How to Purchase:</h4>
                <p>Contact us directly to arrange purchase and installation:</p>
                <ul>
                    <li><i class="fas fa-phone"></i> Call: <strong><a href="tel:+254711529618" target="_blank">+254 711 529 618</a></strong></li>
                    <li><i class="fab fa-whatsapp"></i> WhatsApp: <strong><a href="https://wa.me/254711529618" target="_blank">+254 711 529 618</a></strong></li>
                    <li><i class="fas fa-envelope"></i> Email: <strong><a href="mailto:support@vimarktech.com" target="_blank">support@vimarktech.com</a></strong></li>
                </ul>
                </div>
                <p style="margin-top: 15px; padding: 10px; background: #e8f5e9; border-radius: 8px; text-align: center;">
                    <i class="fas fa-gift" style="color:#27ae60;"></i> <strong>Special Offer:</strong> Free installation and setup assistance included!
                </p>
            </div>
        </div>
    </div>

    <div id="helpCenterModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-question-circle"></i> Help Center</h3>
                <button class="modal-close" onclick="closeModal('helpCenterModal')">&times;</button>
            </div>
            <div class="modal-body">
                <h4>How Can We Help You?</h4>
                <p>We're here to make your experience with Vimark Tech as smooth as possible. Here are some common topics:</p>
                <h4>Getting Started with Cloud System</h4>
                <p>New to Vimark Tech? We recommend starting with our quick setup guide. Register your business, verify your email, and you'll automatically get a 7 day free trial to explore all features.</p>
                <h4>Purchasing the Standalone System</h4>
                <p>Want to own the complete system? Contact us to purchase the standalone version. We'll handle installation on your preferred server (local or cloud) and customize it for your business needs.</p>
                <h4>Still Need Help?</h4>
                <p>Our support team is available 24/7. Use the Contact Support option to reach us, and we'll respond as quickly as possible.</p>
            </div>
        </div>
    </div>

    <div id="contactSupportModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-headset"></i> Contact Support</h3>
                <button class="modal-close" onclick="closeModal('contactSupportModal')">&times;</button>
            </div>
            <div class="modal-body">
                <h4>We're Here to Help!</h4>
                <p>Have a question or need assistance? Don't hesitate to reach out. We're committed to responding quickly and solving your issues.</p>
                <h4>Phone Support</h4>
                <p><i class="fas fa-phone"></i> Call us at: <strong>+254 711 529 618</strong><br>Available Monday to Friday, 8 AM to 6 PM</p>
                <h4>WhatsApp Support</h4>
                <p><i class="fab fa-whatsapp"></i> Chat with us on WhatsApp: <strong>+254 711 529 618</strong><br>Quick responses for urgent matters</p>
                <h4>Email Support</h4>
                <p><i class="fas fa-envelope"></i> Send us an email: <strong>support@vimarktech.com</strong><br>We typically respond within 24 hours</p>
            </div>
        </div>
    </div>

    <div id="systemStatusModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-chart-line"></i> System Status</h3>
                <button class="modal-close" onclick="closeModal('systemStatusModal')">&times;</button>
            </div>
            <div class="modal-body">
                <h4>All Systems Operational <i class="fas fa-check-circle" style="color:#27ae60;"></i></h4>
                <p>Our platform is running smoothly and all features are available. We monitor our systems 24/7 to ensure you have uninterrupted access.</p>
            </div>
        </div>
    </div>

    <div id="documentationModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-book"></i> Documentation</h3>
                <button class="modal-close" onclick="closeModal('documentationModal')">&times;</button>
            </div>
            <div class="modal-body">
                <h4>User Guides & Tutorials</h4>
                <p>We've created comprehensive guides to help you get the most out of Vimark Tech.</p>
                <h4>Quick Start Guide</h4>
                <p>New to Vimark Tech? This guide walks you through account setup, first login, and exploring the dashboard. Your free trial starts automatically after email verification.</p>
            </div>
        </div>
    </div>

    <!-- ============ NAVIGATION ============ -->
    <nav id="navbar">
        <a href="#home" class="logo">
            <img src="/favicon.ico" alt="Vimark Tech Logo - POS system for computer shops Kenya" class="logo-img" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
            <div class="logo-text">
                <h1>VIMARK TECH</h1>
                <span>Business Platform</span>
            </div>
        </a>
        <div class="mobile-menu-btn" onclick="toggleMenu()">
            <i class="fas fa-bars"></i>
        </div>
        <div class="nav-links" id="navLinks">
            <a href="#home">Home</a>
            <a href="#features">Features</a>
            <a href="#why-choose-us">Why Choose Us</a>
            <a href="#pricing">Pricing</a>
            <a href="#faq">FAQ</a>
            <a href="#contact">Contact</a>
        </div>
        <div class="nav-buttons">
            <button class="theme-toggle" onclick="toggleTheme()"><i class="fas fa-moon"></i></button>
            <button class="btn btn-login" onclick="location.href='vimarktech/auth/login'">Login</button>
            <button class="btn btn-register" onclick="location.href='vimarktech/auth/register_business'">Register</button>
        </div>
    </nav>

    <!-- ============ HERO ============ -->
    <section id="home" class="hero reveal">
        <div class="hero-content reveal">
            <div class="hero-badge"><span class="pulse-dot"></span> Kenya's Leading Business Management System</div>
            <h1>The #1 Business Management System for <span class="hero-gradient">Computer Shops & Repair Centers</span> in Kenya</h1>
            <p>Choose between our flexible cloud system or purchase the complete standalone system. Manage inventory, sales, and repairs from one powerful dashboard, built for IT businesses in Kenya. No tech skills needed. <i class="fab fa-amazon-pay"></i> M-Pesa accepted.</p>
            <div class="hero-buttons">
                <button class="btn btn-register" onclick="location.href='vimarktech/auth/register_business'"><i class="fas fa-cloud"></i> Start Free 7-Day Trial</button>
                <button class="btn btn-success" onclick="openModal('purchaseModal')"><i class="fas fa-database"></i> Buy Standalone System</button>
            </div>
            <div class="dashboard-preview">
                <!-- Dashboard image space preserved -->
            </div>
        </div>
    </section>

    <!-- ============ CONNECTION SHOWCASE (Visual) ============ -->
    <section id="connection-showcase">
        <div class="section-title reveal">
            <div class="section-label">Stay Connected</div>
            <h2>Manage Inventory, Sales & Repairs — All in One System</h2>
            <p>Vimark Tech connects every part of your business — inventory, sales, repairs, and team — in one seamless ecosystem.</p>
        </div>
        <div class="connection-orbit reveal">
            <div class="orbit-center"><i class="fas fa-building"></i></div>
            <div class="orbit-ring"></div>
            <div class="orbit-ring"></div>
            <div class="orbit-item"><i class="fas fa-laptop"></i></div>
            <div class="orbit-item"><i class="fas fa-cash-register"></i></div>
            <div class="orbit-item"><i class="fas fa-tools"></i></div>
            <div class="orbit-item"><i class="fas fa-chart-line"></i></div>
        </div>
        <div class="connection-dots">
            <div class="connection-dot reveal reveal-delay-1">
                <div class="dot-circle" style="background:#3b82f6;"><i class="fas fa-boxes"></i></div>
                <span>Inventory</span>
            </div>
            <div class="connection-dot reveal reveal-delay-2">
                <div class="dot-circle" style="background:#f59e0b;"><i class="fas fa-shopping-cart"></i></div>
                <span>Sales</span>
            </div>
            <div class="connection-dot reveal reveal-delay-3">
                <div class="dot-circle" style="background:#27ae60;"><i class="fas fa-wrench"></i></div>
                <span>Repairs</span>
            </div>
            <div class="connection-dot reveal reveal-delay-4">
                <div class="dot-circle" style="background:#8b5cf6;"><i class="fas fa-chart-pie"></i></div>
                <span>Analytics</span>
            </div>
        </div>
    </section>

    <!-- ============ FEATURES SECTION ============ -->
    <section id="features">
        <div class="section-title reveal">
            <h2>Everything Your Business Needs in One System</h2>
            <p>From computer sales to repair tracking, Vimark Tech handles it all, whether you choose our cloud platform or standalone system</p>
        </div>
        <div class="features-grid">
            <div class="feature-card reveal">
                <div class="feature-icon"><i class="fas fa-laptop"></i></div>
                <h3>Device Inventory & Stock Management</h3>
                <p>Always know exactly what stock you have — laptops, phones, accessories — updated in real time. Track every computer, laptop, and IT product in your store with our powerful inventory management software.</p>
            </div>
            <div class="feature-card reveal reveal-delay-1">
                <div class="feature-icon"><i class="fas fa-microchip"></i></div>
                <h3>Spare Parts Management System</h3>
                <p>Track batteries, screens, keyboards and all spare parts so you never run out during a repair job. Manage motherboards, RAMs, SSDs, and accessories easily with our spare parts management system.</p>
            </div>
            <div class="feature-card reveal reveal-delay-2">
                <div class="feature-icon"><i class="fas fa-cash-register"></i></div>
                <h3>Sales & POS System</h3>
                <p>Process sales fast, generate professional receipts, and see your revenue grow in real time. Complete sales history with advanced analytics and reporting. The perfect POS system for computer shops.</p>
            </div>
            <div class="feature-card reveal reveal-delay-3">
                <div class="feature-icon"><i class="fas fa-tools"></i></div>
                <h3>Repair Tracking Software</h3>
                <p>Log every repair job from intake to completion — know which technician has what job and how long it's taking. Track customer information and repair costs with our comprehensive system.</p>
            </div>
            <div class="feature-card reveal reveal-delay-4">
                <div class="feature-icon"><i class="fas fa-chart-line"></i></div>
                <h3>Smart Analytics & Reporting</h3>
                <p>Daily, weekly and monthly reports on your sales, repairs and inventory so you can make smart decisions. Get detailed insights on business performance with our Business management system.</p>
            </div>
            <div class="feature-card reveal reveal-delay-5">
                <div class="feature-icon"><i class="fas fa-users"></i></div>
                <h3>Multi-User Access & Roles</h3>
                <p>Add your team members with their own logins — the standalone system supports unlimited users with role-based permissions. Cloud plan supports up to 3 users.</p>
            </div>
        </div>
    </section>

    <!-- ============ WHO IS VIMARK TECH FOR? ============ -->
    <section id="who-its-for" style="background: var(--bg-alt); padding: 60px 5%;">
        <div class="section-title reveal">
            <div class="section-label">Perfect For</div>
            <h2>Who Is Vimark Tech For?</h2>
            <p>Our business management software is designed specifically for technology businesses in Kenya</p>
        </div>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; max-width: 1000px; margin: 0 auto;">
            <div style="background: var(--card); padding: 25px 20px; border-radius: var(--radius); text-align: center; border: 1px solid var(--border); transition: var(--transition);">
                <i class="fas fa-laptop" style="font-size: 2rem; color: var(--red); margin-bottom: 12px; display: block;"></i>
                <h3 style="font-size: 1rem; font-weight: 700;">Computer Shops</h3>
                <p style="font-size: 0.8rem; color: var(--text-light); margin-top: 8px;">Complete POS and inventory management</p>
            </div>
            <div style="background: var(--card); padding: 25px 20px; border-radius: var(--radius); text-align: center; border: 1px solid var(--border); transition: var(--transition);">
                <i class="fas fa-tools" style="font-size: 2rem; color: var(--red); margin-bottom: 12px; display: block;"></i>
                <h3 style="font-size: 1rem; font-weight: 700;">Laptop Repair Centers</h3>
                <p style="font-size: 0.8rem; color: var(--text-light); margin-top: 8px;">Repair tracking and customer management</p>
            </div>
            <div style="background: var(--card); padding: 25px 20px; border-radius: var(--radius); text-align: center; border: 1px solid var(--border); transition: var(--transition);">
                <i class="fas fa-mobile-alt" style="font-size: 2rem; color: var(--red); margin-bottom: 12px; display: block;"></i>
                <h3 style="font-size: 1rem; font-weight: 700;">Phone Repair Shops</h3>
                <p style="font-size: 0.8rem; color: var(--text-light); margin-top: 8px;">Spare parts and repair job tracking</p>
            </div>
            <div style="background: var(--card); padding: 25px 20px; border-radius: var(--radius); text-align: center; border: 1px solid var(--border); transition: var(--transition);">
                <i class="fas fa-store" style="font-size: 2rem; color: var(--red); margin-bottom: 12px; display: block;"></i>
                <h3 style="font-size: 1rem; font-weight: 700;">Electronics Stores</h3>
                <p style="font-size: 0.8rem; color: var(--text-light); margin-top: 8px;">Sales and inventory for electronics</p>
            </div>
            <div style="background: var(--card); padding: 25px 20px; border-radius: var(--radius); text-align: center; border: 1px solid var(--border); transition: var(--transition);">
                <i class="fas fa-building" style="font-size: 2rem; color: var(--red); margin-bottom: 12px; display: block;"></i>
                <h3 style="font-size: 1rem; font-weight: 700;">IT Retailers</h3>
                <p style="font-size: 0.8rem; color: var(--text-light); margin-top: 8px;">Complete IT business management</p>
            </div>
            <div style="background: var(--card); padding: 25px 20px; border-radius: var(--radius); text-align: center; border: 1px solid var(--border); transition: var(--transition);">
                <i class="fas fa-chart-line" style="font-size: 2rem; color: var(--red); margin-bottom: 12px; display: block;"></i>
                <h3 style="font-size: 1rem; font-weight: 700;">Technology Businesses</h3>
                <p style="font-size: 0.8rem; color: var(--text-light); margin-top: 8px;">Scalable business management solution</p>
            </div>
        </div>
    </section>

    <!-- ============ WHY BUSINESSES CHOOSE VIMARK TECH ============ -->
    <section id="why-choose-us" style="padding: 60px 5%;">
        <div class="section-title reveal">
            <div class="section-label">Key Benefits</div>
            <h2>Why Businesses Choose Vimark Tech</h2>
            <p>Discover what makes our business management system the top choice for IT businesses in Kenya</p>
        </div>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 22px; max-width: 1200px; margin: 0 auto;">
            <div style="background: var(--card); padding: 28px 22px; border-radius: var(--radius); border: 1px solid var(--border); text-align: center; transition: var(--transition);">
                <div style="width: 60px; height: 60px; background: var(--red-gradient); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 18px;"><i class="fas fa-boxes" style="color: white; font-size: 1.5rem;"></i></div>
                <h3 style="margin-bottom: 10px;">Inventory Management</h3>
                <p style="color: var(--text-light);">Real-time stock tracking, low stock alerts, and complete inventory visibility across all locations.</p>
            </div>
            <div style="background: var(--card); padding: 28px 22px; border-radius: var(--radius); border: 1px solid var(--border); text-align: center; transition: var(--transition);">
                <div style="width: 60px; height: 60px; background: var(--red-gradient); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 18px;"><i class="fas fa-cash-register" style="color: white; font-size: 1.5rem;"></i></div>
                <h3 style="margin-bottom: 10px;">Sales & POS</h3>
                <p style="color: var(--text-light);">Fast checkout, professional receipts, and detailed sales history with advanced analytics.</p>
            </div>
            <div style="background: var(--card); padding: 28px 22px; border-radius: var(--radius); border: 1px solid var(--border); text-align: center; transition: var(--transition);">
                <div style="width: 60px; height: 60px; background: var(--red-gradient); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 18px;"><i class="fas fa-wrench" style="color: white; font-size: 1.5rem;"></i></div>
                <h3 style="margin-bottom: 10px;">Repair Tracking</h3>
                <p style="color: var(--text-light);">Complete job logging, technician assignment, status tracking, and customer history.</p>
            </div>
            <div style="background: var(--card); padding: 28px 22px; border-radius: var(--radius); border: 1px solid var(--border); text-align: center; transition: var(--transition);">
                <div style="width: 60px; height: 60px; background: var(--red-gradient); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 18px;"><i class="fas fa-microchip" style="color: white; font-size: 1.5rem;"></i></div>
                <h3 style="margin-bottom: 10px;">Spare Parts Management</h3>
                <p style="color: var(--text-light);">Track every component, set reorder levels, and never run out of critical parts.</p>
            </div>
            <div style="background: var(--card); padding: 28px 22px; border-radius: var(--radius); border: 1px solid var(--border); text-align: center; transition: var(--transition);">
                <div style="width: 60px; height: 60px; background: var(--red-gradient); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 18px;"><i class="fas fa-users" style="color: white; font-size: 1.5rem;"></i></div>
                <h3 style="margin-bottom: 10px;">Multi-User Access</h3>
                <p style="color: var(--text-light);">Role-based permissions for your team. Standalone system supports unlimited users.</p>
            </div>
            <div style="background: var(--card); padding: 28px 22px; border-radius: var(--radius); border: 1px solid var(--border); text-align: center; transition: var(--transition);">
                <div style="width: 60px; height: 60px; background: var(--red-gradient); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 18px;"><i class="fas fa-chart-line" style="color: white; font-size: 1.5rem;"></i></div>
                <h3 style="margin-bottom: 10px;">Analytics & Reporting</h3>
                <p style="color: var(--text-light);">Actionable insights on sales, inventory turnover, and repair completion rates.</p>
            </div>
        </div>
    </section>

    <!-- ============ CLOUD VS STANDALONE COMPARISON ============ -->
    <section id="deployment-comparison" style="background: var(--bg-alt); padding: 60px 5%;">
        <div class="section-title reveal">
            <div class="section-label">Choose Your Deployment</div>
            <h2>Cloud System vs Standalone System</h2>
            <p>Two powerful ways to run Vimark Tech — pick the one that fits your business</p>
        </div>
        <div class="comparison-table reveal">
            <div class="comparison-row comparison-header">
                <div class="comparison-feature">Feature</div>
                <div class="comparison-option"><i class="fas fa-cloud"></i> Cloud System (Subscription)</div>
                <div class="comparison-option"><i class="fas fa-database"></i> Standalone System (One-Time)</div>
            </div>
            <div class="comparison-row">
                <div class="comparison-feature">Pricing Model</div>
                <div class="comparison-option">From KES 4,000/month</div>
                <div class="comparison-option">KES 60,000 One-Time Payment</div>
            </div>
            <div class="comparison-row">
                <div class="comparison-feature">Hosting</div>
                <div class="comparison-option"><i class="fas fa-check"></i> On our secure cloud</div>
                <div class="comparison-option"><i class="fas fa-check"></i> Your own server (local/cloud)</div>
            </div>
            <div class="comparison-row">
                <div class="comparison-feature">Data Control</div>
                <div class="comparison-option"><i class="fas fa-check"></i> Managed by us</div>
                <div class="comparison-option"><i class="fas fa-check"></i> Full database control</div>
            </div>
            <div class="comparison-row">
                <div class="comparison-feature">User Accounts</div>
                <div class="comparison-option">Up to 3 users</div>
                <div class="comparison-option"><i class="fas fa-check"></i> Unlimited users</div>
            </div>
            <div class="comparison-row">
                <div class="comparison-feature">Ownership</div>
                <div class="comparison-option">Subscription access</div>
                <div class="comparison-option"><i class="fas fa-check"></i> Complete ownership forever</div>
            </div>
            <div class="comparison-row">
                <div class="comparison-feature">Installation Support</div>
                <div class="comparison-option">Instant access online</div>
                <div class="comparison-option"><i class="fas fa-check"></i> Free on-site (Nairobi) / Remote (outside)</div>
            </div>
            <div class="comparison-row">
                <div class="comparison-feature">Security</div>
                <div class="comparison-option"><i class="fas fa-check"></i> Enterprise-grade security</div>
                <div class="comparison-option"><i class="fas fa-check"></i> You control security</div>
            </div>
            <div class="comparison-row">
                <div class="comparison-feature">Best For</div>
                <div class="comparison-option">Quick start, low upfront cost</div>
                <div class="comparison-option">Complete ownership, long-term savings</div>
            </div>
        </div>
        <div style="text-align: center; margin-top: 30px;">
            <div style="display: flex; justify-content: center; gap: 15px; flex-wrap: wrap;">
                <span class="mpesa-badge"><i class="fab fa-amazon-pay"></i> M-Pesa Accepted</span>
                <span class="mpesa-badge"><i class="fas fa-wifi"></i> Free Installation for Nairobi</span>
                <span class="mpesa-badge"><i class="fas fa-ban"></i> No Auto-Renewals</span>
                <span class="mpesa-badge"><i class="fas fa-shield-alt"></i> SSL Secure</span>
                <span class="mpesa-badge"><i class="fas fa-headset"></i> Local Kenyan Support</span>
            </div>
        </div>
    </section>

    <!-- ============ HOW IT WORKS ============ -->
    <section id="how-it-works">
        <div class="section-title reveal">
            <div class="section-label">Get Started in Minutes</div>
            <h2>Get Started in 4 Simple Steps — No Technical Skills Needed</h2>
            <p>Setting up your Vimark Tech account is quick and straightforward. Just follow these simple steps and start managing your business from one powerful dashboard.</p>
        </div>
        <div class="steps-container">
            <div class="step-card reveal">
                <div class="step-card-inner">
                    <div class="step-number">1<div class="step-check"><i class="fas fa-check"></i></div></div>
                    <h3>Enter Your Business Details</h3>
                    <p>Go to Register in the navigation bar, Provide your business name, phone number, email address, and business location. It takes less than a minute.</p>
                    <span class="step-tag">Business Info</span>
                </div>
            </div>
            <div class="step-card reveal reveal-delay-1">
                <div class="step-card-inner">
                    <div class="step-number">2<div class="step-check"><i class="fas fa-check"></i></div></div>
                    <h3>Verify Your Email</h3>
                    <p>We'll send a verification code to your email. Enter the code to confirm your email address is valid.</p>
                    <span class="step-tag">Email Verification</span>
                </div>
            </div>
            <div class="step-card reveal reveal-delay-2">
                <div class="step-card-inner">
                    <div class="step-number">3<div class="step-check"><i class="fas fa-check"></i></div></div>
                    <h3>Create Admin Account</h3>
                    <p>Set up your admin user account with a strong password. This is your master account for managing everything.</p>
                    <span class="step-tag">Admin Setup</span>
                </div>
            </div>
            <div class="step-card reveal reveal-delay-3">
                <div class="step-card-inner">
                    <div class="step-number">4<div class="step-check"><i class="fas fa-check"></i></div></div>
                    <h3>Log In & Start Managing</h3>
                    <p>Log into your dashboard and add your business data and instantly access inventory, sales, repairs, and analytics, all in one place.</p>
                    <span class="step-tag">You're In!</span>
                </div>
            </div>
        </div>
    </section>

    <!-- ============ ABOUT (Two Options) ============ -->
    <section id="about">
        <div class="section-title reveal">
            <h2>Two Ways to Get Vimark Tech — Pick What Works for You</h2>
            <p>Join successful computer sales and repair businesses across Kenya already using our platform or purchase the standalone system for complete control</p>
        </div>
        <div class="about-content">
            <div class="about-text reveal">
                <h3><i class="fas fa-cloud"></i> Option 1: Cloud System</h3>
                <p>Pay a monthly subscription and access your business dashboard from any browser, anywhere. Perfect for shops that want to get started quickly. Starts at KES 4,000/month with a 7-day free trial. No credit card required.</p>
                <h3><i class="fas fa-database"></i> Option 2: Standalone System</h3>
                <p>Pay once (KES 60,000) and own the full system forever. Install it on your own computer or server with no monthly fees. Best for established businesses that want full data control. Free installation included for Nairobi businesses. <strong>Complete ownership with unlimited users.</strong></p>
                <ul class="about-list">
                    <li><i class="fas fa-check-circle"></i> Built for Kenya — M-Pesa payments, Nairobi support, KES pricing</li>
                    <li><i class="fas fa-check-circle"></i> No auto-renewals — you control when you pay</li>
                    <li><i class="fas fa-check-circle"></i> Free on-site installation for Nairobi businesses</li>
                    <li><i class="fas fa-check-circle"></i> 7-day free trial — explore every feature before you pay</li>
                    <li><i class="fas fa-check-circle"></i> Your data stays private — each business has a completely separate account</li>
                    <li><i class="fas fa-check-circle"></i> Cloud system works on any phone or computer — no installation needed</li>
                </ul>
            </div>
            <div class="about-text reveal reveal-delay-1">
                <h3>Why Businesses in Kenya Choose Vimark Tech</h3>
                <p>We're not just a software company, we're your business partners. Every feature we build is designed to help you save time, reduce costs, and increase profits.</p>
                <p>With Vimark Tech, you get a complete business management solution that grows with you. From a small computer shop in Nairobi to a large IT enterprise serving all of Kenya, our platform scales with your needs.</p>
                <p><strong>Choose the option that works best for your business!</strong></p>
            </div>
        </div>
    </section>

    <!-- ============ PRICING ============ -->
    <section id="pricing">
        <div class="section-title reveal">
            <h2>Affordable Plans for Every Business in Kenya</h2>
            <p>Choose between our flexible cloud subscription — no auto-renewals, you stay in control.</p>
        </div>
        <div class="pricing-grid">
            <div class="price-card reveal">
                <h3>Basic Plan</h3>
                <div class="price">KES 4,000</div>
                <div class="price-period">per month</div>
                <ul>
                    <li><i class="fas fa-check"></i> Full system access</li>
                    <li><i class="fas fa-check"></i> Unlimited inventory items</li>
                    <li><i class="fas fa-check"></i> Unlimited sales & repairs</li>
                    <li><i class="fas fa-check"></i> Up to 3 user accounts</li>
                    <li><i class="fas fa-check"></i> Email support</li>
                    <li><i class="fas fa-check"></i> Monthly updates</li>
                </ul>
                <button class="btn btn-outline" onclick="location.href='vimarktech/auth/register_business'">Start Free Trial</button>
            </div>
            <div class="price-card featured reveal reveal-delay-1">
                <div class="popular-badge">Best Value</div>
                <h3>Standard Plan</h3>
                <div class="price">KES 7,000</div>
                <div class="price-period">for 2 months</div>
                <div class="price-save">Save KES 1,000</div>
                <ul>
                    <li><i class="fas fa-check"></i> Full system access</li>
                    <li><i class="fas fa-check"></i> Unlimited inventory items</li>
                    <li><i class="fas fa-check"></i> Unlimited sales & repairs</li>
                    <li><i class="fas fa-check"></i> Up to 3 user accounts</li>
                    <li><i class="fas fa-check"></i> Priority email support</li>
                    <li><i class="fas fa-check"></i> Monthly updates</li>
                </ul>
                <button class="btn btn-register" onclick="location.href='vimarktech/auth/register_business'">Start Free Trial</button>
            </div>
            <div class="price-card reveal reveal-delay-2">
                <h3>Advanced Plan</h3>
                <div class="price">KES 10,500</div>
                <div class="price-period">for 3 months</div>
                <div class="price-save">Save KES 1,500</div>
                <ul>
                    <li><i class="fas fa-check"></i> Full system access</li>
                    <li><i class="fas fa-check"></i> Unlimited inventory items</li>
                    <li><i class="fas fa-check"></i> Unlimited sales & repairs</li>
                    <li><i class="fas fa-check"></i> Up to 3 user accounts</li>
                    <li><i class="fas fa-check"></i> Priority phone support</li>
                    <li><i class="fas fa-check"></i> Monthly updates</li>
                </ul>
                <button class="btn btn-outline" onclick="location.href='vimarktech/auth/register_business'">Start Free Trial</button>
            </div>
        </div>
        <div style="text-align: center; margin-top: 20px; font-size: 0.85rem;">
            <i class="fas fa-ban" style="color: var(--red);"></i> No auto-renewals — you control when to renew · <i class="fas fa-credit-card"></i> M-Pesa Accepted · <i class="fas fa-shield-alt"></i> SSL Secure
        </div>
    </section>

    <!-- ============ FAQ ============ -->
    <section id="faq">
        <div class="section-title reveal">
            <h2>Frequently Asked Questions</h2>
            <p>Got questions? We've got answers — and you can always WhatsApp us for help: +254 711 529 618</p>
        </div>
        <div class="faq-grid">
            <div class="faq-item reveal">
                <div class="faq-question" onclick="toggleFAQ(this)"><span>What is a POS system for a computer shop?</span><i class="fas fa-chevron-down"></i></div>
                <div class="faq-answer">A POS (Point of Sale) system for a computer shop is software that helps manage sales, inventory tracking, customer transactions, and repair services in one integrated platform. Vimark Tech is the leading POS system for computer shops in Kenya.</div>
            </div>
            <div class="faq-item reveal reveal-delay-1">
                <div class="faq-question" onclick="toggleFAQ(this)"><span>What is inventory management software?</span><i class="fas fa-chevron-down"></i></div>
                <div class="faq-answer">Inventory management software helps businesses track stock levels, manage product variations, set reorder alerts, and generate inventory reports. Vimark Tech provides complete inventory management software for IT businesses in Kenya.</div>
            </div>
            <div class="faq-item reveal reveal-delay-2">
                <div class="faq-question" onclick="toggleFAQ(this)"><span>Is Vimark Tech suitable for repair shops?</span><i class="fas fa-chevron-down"></i></div>
                <div class="faq-answer">Yes, Vimark Tech includes comprehensive repair tracking software for laptop and phone repair shops, including job logging, customer management, spare parts tracking, and technician assignment.</div>
            </div>
            <div class="faq-item reveal reveal-delay-3">
                <div class="faq-question" onclick="toggleFAQ(this)"><span>Can I purchase the standalone system?</span><i class="fas fa-chevron-down"></i></div>
                <div class="faq-answer">Yes, we offer a standalone business management system for KES 60,000 one-time payment with full ownership, unlimited users, complete database control, and free installation.</div>
            </div>
            <div class="faq-item reveal reveal-delay-4">
                <div class="faq-question" onclick="toggleFAQ(this)"><span>Do you offer installation in Kenya?</span><i class="fas fa-chevron-down"></i></div>
                <div class="faq-answer">Yes, we provide free on-site installation for businesses in Nairobi and free remote installation for businesses elsewhere in Kenya. We support both cloud and standalone deployments.</div>
            </div>
            <div class="faq-item reveal reveal-delay-5">
                <div class="faq-question" onclick="toggleFAQ(this)"><span>Is my business data secure?</span><i class="fas fa-chevron-down"></i></div>
                <div class="faq-answer">Absolutely. Each business has its own isolated account with bank-grade encryption, secure login systems, and automatic backups. For the standalone system, you have full control over your data security.</div>
            </div>
            <div class="faq-item reveal reveal-delay-6">
                <div class="faq-question" onclick="toggleFAQ(this)"><span>How does the cloud system work?</span><i class="fas fa-chevron-down"></i></div>
                <div class="faq-answer">Our cloud system allows you to access your business dashboard from any browser or device with an internet connection. No installation required — just register, verify your email, and start your 7-day free trial immediately.</div>
            </div>
            <div class="faq-item reveal reveal-delay-7">
                <div class="faq-question" onclick="toggleFAQ(this)"><span>Do subscriptions auto-renew?</span><i class="fas fa-chevron-down"></i></div>
                <div class="faq-answer">No, subscriptions do not auto-renew. This gives you full control over when to renew. You will receive email reminders before your subscription expires, and you can manually renew at any time from your account dashboard. All subscription fees are non-refundable once paid.</div>
            </div>
            <div class="faq-item reveal reveal-delay-8">
                <div class="faq-question" onclick="toggleFAQ(this)"><span>What payment methods do you accept?</span><i class="fas fa-chevron-down"></i></div>
                <div class="faq-answer">We accept M-Pesa, bank transfers, and credit or debit cards. All payments are processed securely. For the standalone system purchase, please contact us directly to arrange payment.</div>
            </div>
        </div>
    </section>

    <!-- ============ CTA ============ -->
    <div class="cta-section reveal">
        <h2>Ready to Run a More Organised Business?</h2>
        <p>Join businesses across Kenya already using Vimark Tech. Start your free trial today — no credit card, no commitment, no technical setup needed.</p>
        <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
            <button class="btn" onclick="location.href='vimarktech/auth/register_business'"><i class="fas fa-cloud"></i> Start Free 7-Day Trial</button>
            <button class="btn" style="background: white; color: #27ae60;" onclick="openModal('purchaseModal')"><i class="fas fa-database"></i> Buy Standalone System</button>
        </div>
        <p style="margin-top: 20px; font-size: 0.8rem;"><i class="fas fa-wifi"></i> Free installation · <i class="fas fa-credit-card"></i> No credit card required for free trial · <i class="fab fa-whatsapp"></i> WhatsApp support available</p>
    </div>

    <!-- ============ FOOTER ============ -->
    <footer id="contact">
        <div class="footer-grid">
            <div class="footer-col reveal">
                <h4>VIMARK TECH</h4>
                <p>The complete business management solution for computer sales, repairs, and all IT products businesses in Kenya and beyond. Available as cloud or standalone system.</p>
                <div class="social-links">
                    <a href="#"><i class="fab fa-linkedin-in"></i></a>
                    <a href="https://www.instagram.com/_vic.mn" target="_blank"><i class="fab fa-instagram"></i></a>
                </div>
            </div>
            <div class="footer-col reveal reveal-delay-1">
                <h4>Product</h4>
                <a href="#features">Features</a>
                <a href="#why-choose-us">Why Choose Us</a>
                <a href="#pricing">Pricing</a>
                <a href="#faq">FAQ</a>
            </div>
            <div class="footer-col reveal reveal-delay-2">
                <h4>Support</h4>
                <a onclick="openModal('helpCenterModal')">Help Center</a>
                <a onclick="openModal('contactSupportModal')">Contact Support</a>
                <a onclick="openModal('systemStatusModal')">System Status</a>
                <a onclick="openModal('documentationModal')">Documentation</a>
            </div>
            <div class="footer-col reveal reveal-delay-3">
                <h4>Legal</h4>
                <a onclick="openModal('privacyModal')">Privacy Policy</a>
                <a onclick="openModal('termsModal')">Terms of Service</a>
                <a onclick="openModal('cookieModal')">Cookie Policy</a>
                <a onclick="openModal('securityModal')">Data Security</a>
            </div>
            <div class="footer-col reveal reveal-delay-4">
                <h4>Contact</h4>
                <h3>CALL US</h3>
                <p><a href="tel:+254711529618"><i class="fas fa-phone"></i> +254 711 529 618</a></p>
                <h3>WHATSAPP</h3>
                <p><a href="https://wa.me/254711529618"><i class="fab fa-whatsapp"></i> +254 711 529 618</a></p>
                <h3>EMAIL US</h3>
                <p><i class="fas fa-envelope"></i> support@vimarktech.com</p>
                <h3>LOCATION</h3>
                <p><i class="fas fa-map-marker-alt"></i> Nairobi, Kenya</p>
            </div>
        </div>
        <div class="footer-bottom">
            <p class="copyright-text">&copy; <?php echo date("Y"); ?> Vimark Tech. All rights reserved.</p>
            <p class="empowering-text">Empowering technology businesses through innovation!</p>
        </div>
    </footer>

    <!-- ============ WHATSAPP FLOAT ============ -->
    <div class="whatsapp-float" onclick="openWhatsApp()">
        <div class="whatsapp-icon"><i class="fab fa-whatsapp"></i></div>
        <div class="whatsapp-message"><i class="fas fa-comment-dots"></i> Need help? Chat with us!</div>
    </div>

    <script>
        // Theme Toggle
        function toggleTheme() {
            document.body.classList.toggle('dark');
            const icon = document.querySelector('.theme-toggle i');
            if (document.body.classList.contains('dark')) {
                icon.classList.replace('fa-moon', 'fa-sun');
                localStorage.setItem('theme', 'dark');
            } else {
                icon.classList.replace('fa-sun', 'fa-moon');
                localStorage.setItem('theme', 'light');
            }
        }
        // Load saved theme
        if (localStorage.getItem('theme') === 'dark') {
            document.body.classList.add('dark');
            document.querySelector('.theme-toggle i').classList.replace('fa-moon', 'fa-sun');
        }

        // Nav scroll effect
        window.addEventListener('scroll', () => {
            document.getElementById('navbar').classList.toggle('scrolled', window.scrollY > 20);
        });

        // Mobile Menu
        function toggleMenu() {
            document.getElementById('navLinks').classList.toggle('active');
        }

        // FAQ Toggle
        function toggleFAQ(element) {
            const answer = element.nextElementSibling;
            const icon = element.querySelector('i');
            document.querySelectorAll('.faq-answer').forEach(a => { if (a !== answer) a.classList.remove('active'); });
            document.querySelectorAll('.faq-question i').forEach(i => { if (i !== icon) { i.classList.replace('fa-chevron-up', 'fa-chevron-down'); } });
            answer.classList.toggle('active');
            icon.classList.toggle('fa-chevron-down');
            icon.classList.toggle('fa-chevron-up');
        }

        // Modal Functions
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
            document.body.style.overflow = '';
        }
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
                document.body.style.overflow = '';
            }
        }

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                if (href === '#' || href === '#home') { e.preventDefault(); window.scrollTo({ top: 0, behavior: 'smooth' }); }
                else if (href && href.startsWith('#') && href !== '#') {
                    e.preventDefault();
                    const target = document.querySelector(href);
                    if (target) target.scrollIntoView({ behavior: 'smooth' });
                }
                if (window.innerWidth <= 992) document.getElementById('navLinks').classList.remove('active');
            });
        });

        window.addEventListener('resize', () => {
            if (window.innerWidth > 992) document.getElementById('navLinks').classList.remove('active');
        });

        function openWhatsApp() {
            window.open(`https://wa.me/254711529618?text=${encodeURIComponent("Hello! I'm interested in Vimark Tech System. Can you help me with more information?")}`, '_blank');
        }

        // Hash detection for modals
        document.addEventListener('DOMContentLoaded', function() {
            const hash = window.location.hash;
            if (hash === '#terms-of-service') { setTimeout(() => { openModal('termsModal'); history.replaceState(null, null, window.location.pathname); }, 500); }
            else if (hash === '#privacy-policy') { setTimeout(() => { openModal('privacyModal'); history.replaceState(null, null, window.location.pathname); }, 500); }
        });

        // Scroll Reveal Intersection Observer
        document.addEventListener('DOMContentLoaded', function() {
            const revealElements = document.querySelectorAll('.reveal');
            if (revealElements.length > 0) {
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            entry.target.classList.add('revealed');
                            observer.unobserve(entry.target);
                        }
                    });
                }, { threshold: 0.15, rootMargin: "0px 0px -30px 0px" });
                revealElements.forEach(element => { observer.observe(element); });
            }
        });
    </script>
</body>
</html>
