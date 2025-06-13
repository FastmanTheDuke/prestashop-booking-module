<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmation de réservation</title>
    <style>
        /* Reset CSS */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f8f9fa;
        }
        
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        /* Header */
        .email-header {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 30px 20px;
            text-align: center;
        }
        
        .header-icon {
            font-size: 48px;
            margin-bottom: 15px;
            display: block;
        }
        
        .header-title {
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 10px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .header-subtitle {
            font-size: 16px;
            opacity: 0.9;
            font-weight: 300;
        }
        
        /* Content */
        .email-content {
            padding: 30px 20px;
        }
        
        .greeting {
            font-size: 18px;
            margin-bottom: 20px;
            color: #333;
        }
        
        .reservation-card {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-left: 4px solid #007bff;
            border-radius: 6px;
            padding: 25px;
            margin: 25px 0;
        }
        
        .reservation-title {
            color: #007bff;
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        
        .reservation-title::before {
            content: "📅";
            margin-right: 10px;
            font-size: 24px;
        }
        
        .reservation-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .detail-label {
            font-weight: 600;
            color: #6c757d;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .detail-value {
            font-size: 16px;
            color: #333;
            font-weight: 500;
        }
        
        .booking-reference {
            font-family: 'Courier New', monospace;
            background: #007bff;
            color: white;
            padding: 6px 12px;
            border-radius: 4px;
            font-weight: bold;
            display: inline-block;
        }
        
        .amount-highlight {
            font-size: 18px;
            color: #28a745;
            font-weight: 700;
        }
        
        /* Instructions */
        .instructions-section {
            margin: 30px 0;
            padding: 20px;
            background: #e3f2fd;
            border: 1px solid #2196f3;
            border-radius: 6px;
        }
        
        .instructions-title {
            color: #1976d2;
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        
        .instructions-title::before {
            content: "ℹ️";
            margin-right: 10px;
        }
        
        .instructions-list {
            list-style: none;
            padding: 0;
        }
        
        .instructions-list li {
            padding: 8px 0;
            padding-left: 25px;
            position: relative;
            color: #1976d2;
        }
        
        .instructions-list li::before {
            content: "✓";
            position: absolute;
            left: 0;
            top: 8px;
            color: #28a745;
            font-weight: bold;
        }
        
        /* Contact */
        .contact-section {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 6px;
            padding: 20px;
            margin: 25px 0;
        }
        
        .contact-title {
            color: #856404;
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .contact-info {
            color: #856404;
            line-height: 1.8;
        }
        
        .contact-info a {
            color: #856404;
            text-decoration: none;
            font-weight: 600;
        }
        
        /* Footer */
        .email-footer {
            background: #343a40;
            color: white;
            padding: 25px 20px;
            text-align: center;
        }
        
        .footer-logo {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .footer-text {
            font-size: 14px;
            opacity: 0.8;
            line-height: 1.6;
        }
        
        .footer-links {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #495057;
        }
        
        .footer-links a {
            color: #17a2b8;
            text-decoration: none;
            margin: 0 10px;
            font-size: 12px;
        }
        
        /* Responsive */
        @media (max-width: 600px) {
            .email-container {
                margin: 0;
                border-radius: 0;
            }
            
            .reservation-details {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .email-content {
                padding: 20px 15px;
            }
            
            .header-title {
                font-size: 24px;
            }
        }
        
        /* Print styles */
        @media print {
            body {
                background-color: white;
            }
            
            .email-container {
                box-shadow: none;
                border: 1px solid #ddd;
            }
            
            .email-header {
                background: #28a745 !important;
                -webkit-print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="email-header">
            <div class="header-icon">✅</div>
            <div class="header-title">Réservation Confirmée !</div>
            <div class="header-subtitle">Votre paiement a été traité avec succès</div>
        </div>
        
        <!-- Content -->
        <div class="email-content">
            <div class="greeting">
                Bonjour {customer_firstname} {customer_lastname},
            </div>
            
            <p>Nous avons le plaisir de vous confirmer que votre réservation a été enregistrée et payée avec succès. Vous trouverez ci-dessous tous les détails de votre réservation.</p>
            
            <!-- Reservation Details -->
            <div class="reservation-card">
                <div class="reservation-title">
                    Détails de votre réservation
                </div>
                
                <div class="reservation-details">
                    <div class="detail-item">
                        <div class="detail-label">Référence</div>
                        <div class="detail-value">
                            <span class="booking-reference">{booking_reference}</span>
                        </div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-label">Élément réservé</div>
                        <div class="detail-value">{booker_name}</div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-label">Date</div>
                        <div class="detail-value">{reservation_date}</div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-label">Horaire</div>
                        <div class="detail-value">{reservation_time}</div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-label">Montant payé</div>
                        <div class="detail-value amount-highlight">{total_amount}</div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-label">Statut</div>
                        <div class="detail-value" style="color: #28a745; font-weight: 700;">
                            ✅ Confirmée et payée
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Instructions -->
            <div class="instructions-section">
                <div class="instructions-title">
                    Instructions importantes
                </div>
                <ul class="instructions-list">
                    <li>Présentez-vous à l'heure prévue avec votre référence de réservation</li>
                    <li>Munissez-vous d'une pièce d'identité valide</li>
                    <li>En cas de retard, contactez-nous immédiatement</li>
                    <li>La caution sera automatiquement remboursée après utilisation</li>
                    <li>Respectez les conditions d'utilisation communiquées</li>
                </ul>
            </div>
            
            <!-- Contact -->
            <div class="contact-section">
                <div class="contact-title">Besoin d'aide ?</div>
                <div class="contact-info">
                    Notre équipe est à votre disposition :<br>
                    📧 Email : <a href="mailto:{shop_email}">{shop_email}</a><br>
                    🏪 Boutique : {shop_name}<br>
                    <span style="display: {customer_service_phone}">📞 Urgences : <a href="tel:{customer_service_phone}">{customer_service_phone}</a></span>
                </div>
            </div>
            
            <p style="margin-top: 30px; font-size: 14px; color: #6c757d;">
                <strong>Important :</strong> Conservez cet email comme justificatif de votre réservation. 
                Vous pouvez l'imprimer ou le présenter depuis votre mobile.
            </p>
        </div>
        
        <!-- Footer -->
        <div class="email-footer">
            <div class="footer-logo">{shop_name}</div>
            <div class="footer-text">
                Merci de nous faire confiance pour vos réservations.<br>
                Nous nous réjouissons de vous accueillir !
            </div>
            <div class="footer-links">
                <a href="#">Conditions générales</a>
                <a href="#">Politique de confidentialité</a>
                <a href="#">Nous contacter</a>
            </div>
        </div>
    </div>
</body>
</html>