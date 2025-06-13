<div class="panel">
    <div class="panel-heading">
        <i class="icon-calendar"></i> Calendrier des Réservations
    </div>
    <div class="panel-body">
        
        {* Filtres *}
        <div class="row" style="margin-bottom: 20px;">
            <div class="col-md-4">
                <label>Filtrer par élément :</label>
                <select id="booker-filter" class="form-control">
                    <option value="">Tous les éléments</option>
                    {foreach from=$bookers item=booker}
                        <option value="{$booker.id_booker}">{$booker.name}</option>
                    {/foreach}
                </select>
            </div>
            <div class="col-md-4">
                <label>Vue :</label>
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-default" id="btn-month-view">Mois</button>
                    <button type="button" class="btn btn-default active" id="btn-week-view">Semaine</button>
                    <button type="button" class="btn btn-default" id="btn-day-view">Jour</button>
                </div>
            </div>
            <div class="col-md-4">
                <label>Actions :</label>
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-primary" id="btn-refresh">
                        <i class="icon-refresh"></i> Actualiser
                    </button>
                    <button type="button" class="btn btn-success" id="btn-add-reservation">
                        <i class="icon-plus"></i> Nouvelle réservation
                    </button>
                </div>
            </div>
        </div>

        {* Légende des couleurs *}
        <div class="row" style="margin-bottom: 15px;">
            <div class="col-md-12">
                <small>
                    <span class="label" style="background-color: #ffc107; color: #000;">En attente</span>
                    <span class="label" style="background-color: #17a2b8;">Acceptée</span>
                    <span class="label" style="background-color: #28a745;">Payée</span>
                    <span class="label" style="background-color: #dc3545;">Annulée</span>
                    <span class="label" style="background-color: #6c757d;">Expirée</span>
                </small>
            </div>
        </div>

        {* Calendrier *}
        <div id="calendar-container">
            <div id="calendar"></div>
        </div>
        
        {* Loading indicator *}
        <div id="calendar-loading" style="display: none; text-align: center; padding: 20px;">
            <i class="icon-spinner icon-spin"></i> Chargement...
        </div>
    </div>
</div>

{* Modal pour les détails de réservation *}
<div class="modal fade" id="reservation-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title">Détails de la réservation</h4>
            </div>
            <div class="modal-body">
                <div id="reservation-details">
                    <!-- Contenu dynamique -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Fermer</button>
                <button type="button" class="btn btn-primary" id="btn-edit-reservation">Modifier</button>
                <button type="button" class="btn btn-danger" id="btn-delete-reservation">Supprimer</button>
            </div>
        </div>
    </div>
</div>

<style>
#calendar {
    max-width: 100%;
    margin: 0 auto;
}

.fc-event {
    cursor: pointer;
    font-size: 0.85em;
}

.fc-event:hover {
    opacity: 0.8;
}

.fc-toolbar {
    margin-bottom: 1em;
}

.fc-toolbar-chunk {
    display: flex;
    align-items: center;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .fc-toolbar {
        flex-direction: column;
        gap: 10px;
    }
    
    .fc-toolbar-chunk {
        justify-content: center;
    }
}
</style>