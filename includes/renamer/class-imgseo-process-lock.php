<?php
/**
 * Process lock management class
 *
 * @package ImgSEO
 * @since 1.1.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ImgSEO_Process_Lock
 * Gestisce i lock dei processi per evitare l'esecuzione continua
 */
class ImgSEO_Process_Lock {
    /**
     * Nome della option per il blocco globale
     */
    const GLOBAL_LOCK_OPTION = 'imgseo_global_process_lock';
    
    /**
     * Prefisso per le transient dei job
     */
    const JOB_LOCK_PREFIX = 'imgseo_stop_job_';
    
    /**
     * Durata del blocco globale in secondi (10 minuti)
     */
    const LOCK_DURATION = 600;
    
    /**
     * Imposta un blocco globale su tutti i processi
     * 
     * @return bool True se il blocco è stato impostato
     */
    public static function set_global_lock() {
        // Imposta una option per indicare il blocco globale
        update_option(self::GLOBAL_LOCK_OPTION, time());
        
        // Imposta una transient come backup (facile da controllare senza query al DB)
        set_transient('imgseo_global_stop', 'yes', self::LOCK_DURATION);
        
        // Cancella tutti i cron job pianificati per ImgSEO
        wp_clear_scheduled_hook(IMGSEO_CRON_HOOK);
        wp_clear_scheduled_hook('imgseo_check_stuck_jobs');
        
        return true;
    }
    
    /**
     * Rimuove il blocco globale
     * 
     * @return bool True se il blocco è stato rimosso
     */
    public static function remove_global_lock() {
        delete_option(self::GLOBAL_LOCK_OPTION);
        delete_transient('imgseo_global_stop');
        return true;
    }
    
    /**
     * Controlla se c'è un blocco globale attivo
     * 
     * @return bool True se c'è un blocco globale
     */
    public static function is_globally_locked() {
        // Prima controlla la transient che è più veloce
        if (get_transient('imgseo_global_stop') === 'yes') {
            return true;
        }
        
        // Poi controlla la option
        $lock_time = get_option(self::GLOBAL_LOCK_OPTION, 0);
        
        // Se il blocco è più vecchio di LOCK_DURATION, lo consideriamo scaduto
        if ($lock_time > 0 && time() - $lock_time < self::LOCK_DURATION) {
            return true;
        }
        
        // Se il blocco è scaduto, lo rimuoviamo
        if ($lock_time > 0) {
            self::remove_global_lock();
        }
        
        return false;
    }
    
    /**
     * Imposta un blocco per un job specifico
     * 
     * @param string $job_id ID del job da bloccare
     * @return bool True se il blocco è stato impostato
     */
    public static function set_job_lock($job_id) {
        if (empty($job_id)) {
            return false;
        }
        
        // Imposta una transient per il job
        set_transient(self::JOB_LOCK_PREFIX . $job_id, 'yes', self::LOCK_DURATION);
        
        return true;
    }
    
    /**
     * Controlla se c'è un blocco per un job specifico
     * 
     * @param string $job_id ID del job da controllare
     * @return bool True se il job è bloccato
     */
    public static function is_job_locked($job_id) {
        if (empty($job_id)) {
            return false;
        }
        
        return get_transient(self::JOB_LOCK_PREFIX . $job_id) === 'yes';
    }
    
    /**
     * Controlla se qualsiasi processo dovrebbe fermarsi
     * Verifica sia il blocco globale che quello specifico del job
     * 
     * @param string $job_id ID del job da controllare (opzionale)
     * @return bool True se il processo dovrebbe fermarsi
     */
    public static function should_stop($job_id = '') {
        // Prima controlla il blocco globale
        if (self::is_globally_locked()) {
            return true;
        }
        
        // Poi controlla il blocco specifico del job se fornito
        if (!empty($job_id) && self::is_job_locked($job_id)) {
            return true;
        }
        
        return false;
    }
}
