<?php
/**
 * Webhook Stripe pour le module de réservations PrestaShop
 * Traite les événements de paiement en temps réel
 */

// Démarrage rapide pour éviter les timeouts
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

// Configuration PrestaShop
define('_PS_ADMIN_DIR_', getcwd());
include(dirname(__FILE__) . '/../../config/config.inc.php');
include(dirname(__FILE__) . '/../../init.php');

// Inclusion des classes nécessaires
require_once(dirname(__FILE__) . '/../classes/StripePaymentManager.php');

class StripeWebhookHandler
{
    private $stripe_manager;
    private $log_file;
    
    public function __construct()
    {
        $this->stripe_manager = new StripePaymentManager();
        $this->log_file = dirname(__FILE__) . '/../logs/stripe_webhook.log';
        
        // Créer le dossier de logs si nécessaire
        $log_dir = dirname($this->log_file);
        if (!file_exists($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
    }
    
    /**
     * Point d'entrée principal du webhook
     */
    public function handle()
    {
        try {
            // Lire le payload
            $payload = @file_get_contents('php://input');
            $signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
            
            if (empty($payload) || empty($signature)) {
                $this->logError('Payload ou signature manquante');
                http_response_code(400);
                die('Bad Request');
            }
            
            $this->logInfo('Webhook reçu - Taille: ' . strlen($payload) . ' bytes');
            
            // Traiter le webhook
            $result = $this->stripe_manager->handleWebhook($payload, $signature);
            
            if ($result['success']) {
                $this->logInfo('Webhook traité avec succès');
                http_response_code(200);
                echo json_encode(['status' => 'success']);
            } else {
                $this->logError('Erreur traitement webhook: ' . ($result['error'] ?? 'Erreur inconnue'));
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => $result['error'] ?? 'Erreur inconnue']);
            }
            
        } catch (Exception $e) {
            $this->logError('Exception webhook: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Internal Server Error']);
        }
    }
    
    /**
     * Logger les informations
     */
    private function logInfo($message)
    {
        $this->writeLog('INFO', $message);
        PrestaShopLogger::addLog('[STRIPE WEBHOOK INFO] ' . $message, 1);
    }
    
    /**
     * Logger les erreurs
     */
    private function logError($message)
    {
        $this->writeLog('ERROR', $message);
        PrestaShopLogger::addLog('[STRIPE WEBHOOK ERROR] ' . $message, 3);
    }
    
    /**
     * Écrire dans le fichier de log
     */
    private function writeLog($level, $message)
    {
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[$timestamp] [$level] $message" . PHP_EOL;
        
        file_put_contents($this->log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }
}

// Vérifier que le module Stripe est configuré
if (!Configuration::get('BOOKING_STRIPE_ENABLED')) {
    http_response_code(404);
    die('Stripe not enabled');
}

// Vérifier la méthode HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method Not Allowed');
}

// Traiter le webhook
$handler = new StripeWebhookHandler();
$handler->handle();