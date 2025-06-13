<?php
/**
 * Classe BookerAuth - Gestion des créneaux de disponibilité
 * Version 2.0 avec gestion avancée des disponibilités
 */

class BookerAuth extends ObjectModel
{
    /** @var int */
    public $id_auth;
    
    /** @var int */
    public $id_booker;
    
    /** @var string */
    public $date_start;
    
    /** @var string */
    public $date_end;
    
    /** @var bool */
    public $is_available = true;
    
    /** @var int */
    public $max_bookings = 1;
    
    /** @var int */
    public $current_bookings = 0;
    
    /** @var float */
    public $price_override;
    
    /** @var string */
    public $notes;
    
    /** @var string */
    public $date_add;
    
    /** @var string */
    public $date_upd;
    
    /**
     * @see ObjectModel::$definition
     */
    public static $definition = array(
        'table' => 'booker_auth',
        'primary' => 'id_auth',
        'fields' => array(
            'id_booker' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true),
            'date_start' => array('type' => self::TYPE_DATE, 'validate' => 'isDate', 'required' => true),
            'date_end' => array('type' => self::TYPE_DATE, 'validate' => 'isDate', 'required' => true),
            'is_available' => array('type' => self::TYPE_BOOL, 'validate' => 'isBool'),
            'max_bookings' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'),
            'current_bookings' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'),
            'price_override' => array('type' => self::TYPE_FLOAT, 'validate' => 'isPrice'),
            'notes' => array('type' => self::TYPE_HTML, 'validate' => 'isCleanHtml'),
            'date_add' => array('type' => self::TYPE_DATE, 'validate' => 'isDate'),
            'date_upd' => array('type' => self::TYPE_DATE, 'validate' => 'isDate')
        ),
    );
    
    /**
     * Obtenir le booker associé
     */
    public function getBooker()
    {
        return new Booker($this->id_booker);
    }
    
    /**
     * Vérifier si le créneau est complet
     */
    public function isFull()
    {
        return $this->current_bookings >= $this->max_bookings;
    }
    
    /**
     * Obtenir le nombre de places restantes
     */
    public function getRemainingSlots()
    {
        return max(0, $this->max_bookings - $this->current_bookings);
    }
    
    /**
     * Calculer le taux d'occupation
     */
    public function getOccupationRate()
    {
        if ($this->max_bookings == 0) {
            return 0;
        }
        return ($this->current_bookings / $this->max_bookings) * 100;
    }
    
    /**
     * Obtenir la durée du créneau en minutes
     */
    public function getDurationMinutes()
    {
        $start = strtotime($this->date_start);
        $end = strtotime($this->date_end);
        return ($end - $start) / 60;
    }
    
    /**
     * Obtenir la durée du créneau en heures
     */
    public function getDurationHours()
    {
        return $this->getDurationMinutes() / 60;
    }
    
    /**
     * Vérifier si le créneau chevauche avec une période donnée
     */
    public function overlaps($date_start, $date_end)
    {
        $this_start = strtotime($this->date_start);
        $this_end = strtotime($this->date_end);
        $check_start = strtotime($date_start);
        $check_end = strtotime($date_end);
        
        return ($this_start < $check_end) && ($this_end > $check_start);
    }
    
    /**
     * Vérifier si le créneau contient complètement une période donnée
     */
    public function contains($date_start, $date_end)
    {
        $this_start = strtotime($this->date_start);
        $this_end = strtotime($this->date_end);
        $check_start = strtotime($date_start);
        $check_end = strtotime($date_end);
        
        return ($this_start <= $check_start) && ($this_end >= $check_end);
    }
    
    /**
     * Obtenir le prix pour ce créneau
     */
    public function getPrice()
    {
        if ($this->price_override !== null && $this->price_override > 0) {
            return (float)$this->price_override;
        }
        
        $booker = $this->getBooker();
        return $booker ? $booker->price : 0;
    }
    
    /**
     * Réserver une place dans ce créneau
     */
    public function book()
    {
        if ($this->isFull() || !$this->is_available) {
            return false;
        }
        
        $this->current_bookings++;
        return $this->save();
    }
    
    /**
     * Libérer une place dans ce créneau
     */
    public function release()
    {
        if ($this->current_bookings > 0) {
            $this->current_bookings--;
            return $this->save();
        }
        return true;
    }
    
    /**
     * Obtenir les réservations de ce créneau
     */
    public function getReservations()
    {
        $sql = 'SELECT r.*, c.firstname, c.lastname 
                FROM `' . _DB_PREFIX_ . 'booker_auth_reserved` r
                LEFT JOIN `' . _DB_PREFIX_ . 'customer` c ON r.id_customer = c.id_customer
                WHERE r.id_auth = ' . (int)$this->id . '
                ORDER BY r.date_reserved ASC';
                
        return Db::getInstance()->executeS($sql);
    }
    
    /**
     * Vérifier les conflits avec d'autres créneaux du même booker
     */
    public function hasConflicts($exclude_self = true)
    {
        $sql = 'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth`
                WHERE id_booker = ' . (int)$this->id_booker . '
                AND (
                    (date_start <= "' . pSQL($this->date_start) . '" AND date_end > "' . pSQL($this->date_start) . '")
                    OR (date_start < "' . pSQL($this->date_end) . '" AND date_end >= "' . pSQL($this->date_end) . '")
                    OR (date_start >= "' . pSQL($this->date_start) . '" AND date_end <= "' . pSQL($this->date_end) . '")
                )';
                
        if ($exclude_self && $this->id) {
            $sql .= ' AND id_auth != ' . (int)$this->id;
        }
        
        return (bool)Db::getInstance()->getValue($sql);
    }
    
    /**
     * Activer/désactiver la disponibilité
     */
    public function setAvailable($available = true)
    {
        $this->is_available = (bool)$available;
        return $this->save();
    }
    
    /**
     * Dupliquer ce créneau vers une autre période
     */
    public function duplicate($new_date_start, $new_date_end = null)
    {
        // Calculer la nouvelle date de fin si pas fournie
        if (!$new_date_end) {
            $duration = strtotime($this->date_end) - strtotime($this->date_start);
            $new_date_end = date('Y-m-d H:i:s', strtotime($new_date_start) + $duration);
        }
        
        $duplicate = new self();
        $duplicate->id_booker = $this->id_booker;
        $duplicate->date_start = $new_date_start;
        $duplicate->date_end = $new_date_end;
        $duplicate->is_available = $this->is_available;
        $duplicate->max_bookings = $this->max_bookings;
        $duplicate->current_bookings = 0; // Nouveau créneau = 0 réservation
        $duplicate->price_override = $this->price_override;
        $duplicate->notes = $this->notes;
        $duplicate->date_add = date('Y-m-d H:i:s');
        $duplicate->date_upd = date('Y-m-d H:i:s');
        
        return $duplicate->save() ? $duplicate : false;
    }
    
    /**
     * Fractionner ce créneau en plusieurs créneaux plus petits
     */
    public function split($duration_minutes)
    {
        $start_time = strtotime($this->date_start);
        $end_time = strtotime($this->date_end);
        $duration_seconds = $duration_minutes * 60;
        
        $created_slots = [];
        $current_time = $start_time;
        
        while ($current_time < $end_time) {
            $slot_end = min($current_time + $duration_seconds, $end_time);
            
            // Créer le nouveau créneau
            $new_slot = new self();
            $new_slot->id_booker = $this->id_booker;
            $new_slot->date_start = date('Y-m-d H:i:s', $current_time);
            $new_slot->date_end = date('Y-m-d H:i:s', $slot_end);
            $new_slot->is_available = $this->is_available;
            $new_slot->max_bookings = $this->max_bookings;
            $new_slot->current_bookings = 0;
            $new_slot->price_override = $this->price_override;
            $new_slot->notes = $this->notes;
            $new_slot->date_add = date('Y-m-d H:i:s');
            $new_slot->date_upd = date('Y-m-d H:i:s');
            
            if ($new_slot->save()) {
                $created_slots[] = $new_slot;
            }
            
            $current_time = $slot_end;
        }
        
        // Supprimer le créneau original si des nouveaux ont été créés
        if (!empty($created_slots)) {
            $this->delete();
        }
        
        return $created_slots;
    }
    
    /**
     * Obtenir les créneaux par booker et période
     */
    public static function getByBookerAndPeriod($id_booker, $date_start, $date_end)
    {
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'booker_auth`
                WHERE id_booker = ' . (int)$id_booker . '
                AND date_start >= "' . pSQL($date_start) . '"
                AND date_end <= "' . pSQL($date_end) . '"
                ORDER BY date_start ASC';
                
        return Db::getInstance()->executeS($sql);
    }
    
    /**
     * Trouver les créneaux disponibles pour une réservation
     */
    public static function findAvailableSlots($id_booker, $date_start, $date_end, $required_duration_minutes = null)
    {
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'booker_auth`
                WHERE id_booker = ' . (int)$id_booker . '
                AND is_available = 1
                AND (max_bookings - current_bookings) > 0
                AND date_start <= "' . pSQL($date_start) . '"
                AND date_end >= "' . pSQL($date_end) . '"';
                
        if ($required_duration_minutes) {
            $sql .= ' AND TIMESTAMPDIFF(MINUTE, date_start, date_end) >= ' . (int)$required_duration_minutes;
        }
        
        $sql .= ' ORDER BY date_start ASC';
        
        return Db::getInstance()->executeS($sql);
    }
    
    /**
     * Générer des créneaux récurrents
     */
    public static function generateRecurring($params)
    {
        $required = ['id_booker', 'start_date', 'end_date', 'start_time', 'end_time', 'days', 'slot_duration'];
        foreach ($required as $field) {
            if (!isset($params[$field])) {
                throw new Exception('Paramètre requis manquant: ' . $field);
            }
        }
        
        $created_count = 0;
        $start = new DateTime($params['start_date']);
        $end = new DateTime($params['end_date']);
        
        for ($date = clone $start; $date <= $end; $date->modify('+1 day')) {
            $day_of_week = $date->format('w'); // 0 = dimanche
            
            if (in_array($day_of_week, $params['days'])) {
                $current_time = new DateTime($date->format('Y-m-d') . ' ' . $params['start_time']);
                $day_end_time = new DateTime($date->format('Y-m-d') . ' ' . $params['end_time']);
                
                while ($current_time < $day_end_time) {
                    $slot_end = clone $current_time;
                    $slot_end->modify('+' . $params['slot_duration'] . ' minutes');
                    
                    if ($slot_end <= $day_end_time) {
                        // Vérifier les conflits
                        if (!self::hasConflictForPeriod($params['id_booker'], $current_time->format('Y-m-d H:i:s'), $slot_end->format('Y-m-d H:i:s'))) {
                            $slot = new self();
                            $slot->id_booker = $params['id_booker'];
                            $slot->date_start = $current_time->format('Y-m-d H:i:s');
                            $slot->date_end = $slot_end->format('Y-m-d H:i:s');
                            $slot->max_bookings = $params['max_bookings'] ?? 1;
                            $slot->current_bookings = 0;
                            $slot->is_available = true;
                            $slot->price_override = $params['price_override'] ?? null;
                            $slot->notes = $params['notes'] ?? '';
                            $slot->date_add = date('Y-m-d H:i:s');
                            $slot->date_upd = date('Y-m-d H:i:s');
                            
                            if ($slot->save()) {
                                $created_count++;
                            }
                        }
                    }
                    
                    $current_time->modify('+' . $params['slot_duration'] . ' minutes');
                }
            }
        }
        
        return $created_count;
    }
    
    /**
     * Vérifier les conflits pour une période donnée
     */
    public static function hasConflictForPeriod($id_booker, $date_start, $date_end)
    {
        $sql = 'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth`
                WHERE id_booker = ' . (int)$id_booker . '
                AND (
                    (date_start <= "' . pSQL($date_start) . '" AND date_end > "' . pSQL($date_start) . '")
                    OR (date_start < "' . pSQL($date_end) . '" AND date_end >= "' . pSQL($date_end) . '")
                    OR (date_start >= "' . pSQL($date_start) . '" AND date_end <= "' . pSQL($date_end) . '")
                )';
                
        return (bool)Db::getInstance()->getValue($sql);
    }
    
    /**
     * Supprimer les créneaux par période
     */
    public static function deleteByPeriod($id_booker, $date_start, $date_end)
    {
        // Vérifier qu'il n'y a pas de réservations confirmées
        $sql_check = 'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth_reserved` r
                      JOIN `' . _DB_PREFIX_ . 'booker_auth` a ON r.id_auth = a.id_auth
                      WHERE a.id_booker = ' . (int)$id_booker . '
                      AND a.date_start >= "' . pSQL($date_start) . '"
                      AND a.date_end <= "' . pSQL($date_end) . '"
                      AND r.status IN (' . BookerAuthReserved::STATUS_VALIDATED . ', ' . BookerAuthReserved::STATUS_PAID . ')';
                      
        if (Db::getInstance()->getValue($sql_check)) {
            throw new Exception('Impossible de supprimer: des réservations confirmées existent pour cette période');
        }
        
        // Supprimer les créneaux
        $sql_delete = 'DELETE FROM `' . _DB_PREFIX_ . 'booker_auth`
                       WHERE id_booker = ' . (int)$id_booker . '
                       AND date_start >= "' . pSQL($date_start) . '"
                       AND date_end <= "' . pSQL($date_end) . '"';
                       
        return Db::getInstance()->execute($sql_delete);
    }
    
    /**
     * Obtenir les statistiques des disponibilités
     */
    public static function getStats($id_booker = null)
    {
        $where_booker = $id_booker ? ' WHERE id_booker = ' . (int)$id_booker : '';
        
        $stats = array();
        
        // Total des créneaux
        $stats['total'] = (int)Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth`' . $where_booker);
        
        // Créneaux disponibles
        $stats['available'] = (int)Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth` WHERE is_available = 1' . ($id_booker ? ' AND id_booker = ' . (int)$id_booker : ''));
        
        // Créneaux futurs
        $stats['future'] = (int)Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth` WHERE date_start > NOW()' . ($id_booker ? ' AND id_booker = ' . (int)$id_booker : ''));
        
        // Créneaux complets
        $stats['full'] = (int)Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth` WHERE current_bookings >= max_bookings' . ($id_booker ? ' AND id_booker = ' . (int)$id_booker : ''));
        
        // Taux d'occupation moyen
        $occupation_sql = 'SELECT AVG(current_bookings / max_bookings * 100) as avg_occupation 
                          FROM `' . _DB_PREFIX_ . 'booker_auth` 
                          WHERE max_bookings > 0' . ($id_booker ? ' AND id_booker = ' . (int)$id_booker : '');
        $stats['avg_occupation'] = round((float)Db::getInstance()->getValue($occupation_sql), 2);
        
        return $stats;
    }
    
    /**
     * Sauvegarder avec mise à jour des timestamps
     */
    public function save($null_values = false, $auto_date = true)
    {
        if ($auto_date) {
            if (!$this->id) {
                $this->date_add = date('Y-m-d H:i:s');
            }
            $this->date_upd = date('Y-m-d H:i:s');
        }
        
        return parent::save($null_values, $auto_date);
    }
}