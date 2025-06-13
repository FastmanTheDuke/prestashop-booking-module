{* Modal pour création/édition de réservation - Version complète *}
<div class="modal fade" id="reservation-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
                <h4 class="modal-title" id="reservation-modal-title">Nouvelle réservation</h4>
            </div>
            
            <div class="modal-body">
                <form id="reservation-form">
                    <input type="hidden" id="reservation-id" name="reservation_id">
                    
                    {* Informations de réservation *}
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h4 class="panel-title">Détails de la réservation</h4>
                        </div>
                        <div class="panel-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="reservation-booker-id">Élément à réserver <span class="required">*</span></label>
                                        <select id="reservation-booker-id" name="booker_id" class="form-control" required>
                                            <option value="">Sélectionner un élément...</option>
                                            {foreach from=$bookers item=booker}
                                                <option value="{$booker.id_booker}">{$booker.name|escape:'html':'UTF-8'}</option>
                                            {/foreach}
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="booking-reference">Référence de réservation</label>
                                        <input type="text" id="booking-reference" name="booking_reference" class="form-control" readonly>
                                        <small class="help-block">Générée automatiquement</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="reservation-date">Date <span class="required">*</span></label>
                                        <input type="date" id="reservation-date" name="date_reserved" class="form-control" required>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="reservation-hour-from">Heure de début <span class="required">*</span></label>
                                        <input type="time" id="reservation-hour-from" name="hour_from" class="form-control" required>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="reservation-hour-to">Heure de fin <span class="required">*</span></label>
                                        <input type="time" id="reservation-hour-to" name="hour_to" class="form-control" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="reservation-status">Statut</label>
                                        <select id="reservation-status" name="status" class="form-control">
                                            {foreach from=$statuses key=status_id item=status_label}
                                                <option value="{$status_id}">{$status_label|escape:'html':'UTF-8'}</option>
                                            {/foreach}
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="total-price">Prix total (€)</label>
                                        <input type="number" id="total-price" name="total_price" class="form-control" step="0.01" min="0">
                                        <small class="help-block">Laissez vide pour calcul automatique</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    {* Informations client *}
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h4 class="panel-title">Informations du client</h4>
                        </div>
                        <div class="panel-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="customer-firstname">Prénom <span class="required">*</span></label>
                                        <input type="text" id="customer-firstname" name="customer_firstname" class="form-control" required>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="customer-lastname">Nom <span class="required">*</span></label>
                                        <input type="text" id="customer-lastname" name="customer_lastname" class="form-control" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="customer-email">Email <span class="required">*</span></label>
                                        <input type="email" id="customer-email" name="customer_email" class="form-control" required>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="customer-phone">Téléphone</label>
                                        <input type="tel" id="customer-phone" name="customer_phone" class="form-control">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="customer-message">Message du client</label>
                                <textarea id="customer-message" name="customer_message" class="form-control" rows="3" 
                                         placeholder="Demandes spéciales, commentaires..."></textarea>
                            </div>
                        </div>
                    </div>
                    
                    {* Zone d'informations complémentaires *}
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h4 class="panel-title">
                                <a data-toggle="collapse" href="#advanced-options" role="button" aria-expanded="false">
                                    Options avancées <i class="icon-chevron-down"></i>
                                </a>
                            </h4>
                        </div>
                        <div class="collapse" id="advanced-options">
                            <div class="panel-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="deposit-amount">Montant de la caution (€)</label>
                                            <input type="number" id="deposit-amount" name="deposit_amount" class="form-control" step="0.01" min="0">
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="payment-status">Statut de paiement</label>
                                            <select id="payment-status" name="payment_status" class="form-control">
                                                <option value="0">En attente</option>
                                                <option value="1">Partiel (acompte)</option>
                                                <option value="2">Complet</option>
                                                <option value="3">Remboursé</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="admin-notes">Notes administratives</label>
                                    <textarea id="admin-notes" name="admin_notes" class="form-control" rows="2" 
                                             placeholder="Notes internes, non visibles par le client..."></textarea>
                                </div>
                                
                                <div class="checkbox">
                                    <label>
                                        <input type="checkbox" id="send-confirmation" name="send_confirmation" checked>
                                        Envoyer un email de confirmation au client
                                    </label>
                                </div>
                                
                                <div class="checkbox">
                                    <label>
                                        <input type="checkbox" id="create-order" name="create_order">
                                        Créer automatiquement une commande PrestaShop
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    {* Alertes et messages *}
                    <div id="reservation-alerts" class="alert-container" style="display: none;">
                        <div class="alert alert-info" id="availability-info">
                            <i class="icon-info-circle"></i>
                            <span class="message"></span>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="modal-footer">
                <div class="btn-group pull-left">
                    <button type="button" class="btn btn-info" id="check-availability">
                        <i class="icon-search"></i> Vérifier disponibilité
                    </button>
                </div>
                
                <div class="btn-group pull-right">
                    <button type="button" class="btn btn-default" data-dismiss="modal">
                        <i class="icon-times"></i> Annuler
                    </button>
                    <button type="button" class="btn btn-danger" id="delete-reservation-btn" style="display: none;">
                        <i class="icon-trash"></i> Supprimer
                    </button>
                    <button type="button" class="btn btn-primary" id="save-reservation" disabled>
                        <i class="icon-save"></i> Enregistrer
                    </button>
                </div>
                
                <div class="clearfix"></div>
            </div>
        </div>
    </div>
</div>

{* Modal pour les créneaux alternatifs *}
<div class="modal fade" id="alternatives-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
                <h4 class="modal-title">Créneaux alternatifs disponibles</h4>
            </div>
            
            <div class="modal-body">
                <p>Le créneau sélectionné n'est pas disponible. Voici des alternatives :</p>
                <div id="alternative-slots" class="list-group">
                    {* Contenu généré dynamiquement *}
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

{* Styles CSS pour le modal *}
<style>
.required {
    color: #e74c3c;
}

.panel-title a {
    text-decoration: none;
    color: inherit;
}

.panel-title a:hover {
    text-decoration: none;
}

.alert-container {
    margin-top: 15px;
}

.modal-footer .btn-group {
    margin-bottom: 0;
}

.form-group {
    margin-bottom: 15px;
}

.help-block {
    color: #737373;
    font-size: 0.9em;
}

#alternative-slots .list-group-item {
    cursor: pointer;
    transition: background-color 0.2s;
}

#alternative-slots .list-group-item:hover {
    background-color: #f5f5f5;
}

#alternative-slots .list-group-item.selected {
    background-color: #d4edda;
    border-color: #c3e6cb;
}

.loading-spinner {
    display: inline-block;
    width: 16px;
    height: 16px;
    border: 2px solid #f3f3f3;
    border-top: 2px solid #3498db;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Validation visuelle */
.form-control.is-valid {
    border-color: #28a745;
}

.form-control.is-invalid {
    border-color: #dc3545;
}

.invalid-feedback {
    color: #dc3545;
    font-size: 0.875em;
    margin-top: 0.25rem;
}

.valid-feedback {
    color: #28a745;
    font-size: 0.875em;
    margin-top: 0.25rem;
}
</style>