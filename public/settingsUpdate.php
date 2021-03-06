<?php
//
// Description
// -----------
// This method will update one or more settings for the field day log module.
//
// Arguments
// ---------
// user_id:         The user making the request
// 
// Returns
// -------
//
function qruqsp_canadadaylog_settingsUpdate(&$ciniki) {
    //  
    // Find all the required and optional arguments
    //  
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'), 
        )); 
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }   
    $args = $rc['args'];
    
    //  
    // Make sure this module is activated, and
    // check permission to run this function for this tenant
    //  
    ciniki_core_loadMethod($ciniki, 'qruqsp', 'canadadaylog', 'private', 'checkAccess');
    $rc = qruqsp_canadadaylog_checkAccess($ciniki, $args['tnid'], 'qruqsp.canadadaylog.settingsUpdate'); 
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }   

    //
    // Grab the settings for the tenant from the database
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbDetailsQuery');
    $rc = ciniki_core_dbDetailsQuery($ciniki, 'qruqsp_canadadaylog_settings', 'tnid', $args['tnid'], 'qruqsp.canadadaylog', 'settings', '');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $settings = $rc['settings'];

    //  
    // Turn off autocommit
    //  
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionStart');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionRollback');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionCommit');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuote');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbInsert');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbAddModuleHistory');
    $rc = ciniki_core_dbTransactionStart($ciniki, 'qruqsp.canadadaylog');
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }   

    //
    // The list of allowed fields for updating
    //
    $changelog_fields = array(
        'callsign',
        'province',
        'ui-notes',
        'allow-dupes',
        'category-mode',
        'category-operator',
        'category-assisted',
        'category-power',
        'category-station',
        'category-transmitter',
        'club',
        'name',
        'address',
        'city',
        'state',
        'postal',
        'country',
        );
    //
    // Check each valid setting and see if a new value was passed in the arguments for it.
    // Insert or update the entry in the qruqsp_canadadaylog_settings table
    //
    foreach($changelog_fields as $field) {
        if( isset($ciniki['request']['args'][$field]) 
            && (!isset($settings[$field]) || $ciniki['request']['args'][$field] != $settings[$field]) 
            ) {
            if( in_array($field, ['callsign', 'class', 'section']) ) {
                $ciniki['request']['args'][$field] = strtoupper($ciniki['request']['args'][$field]);
            }
            $strsql = "INSERT INTO qruqsp_canadadaylog_settings (tnid, detail_key, detail_value, date_added, last_updated) "
                . "VALUES ('" . ciniki_core_dbQuote($ciniki, $ciniki['request']['args']['tnid']) . "'"
                . ", '" . ciniki_core_dbQuote($ciniki, $field) . "'"
                . ", '" . ciniki_core_dbQuote($ciniki, $ciniki['request']['args'][$field]) . "'"
                . ", UTC_TIMESTAMP(), UTC_TIMESTAMP()) "
                . "ON DUPLICATE KEY UPDATE detail_value = '" . ciniki_core_dbQuote($ciniki, $ciniki['request']['args'][$field]) . "' "
                . ", last_updated = UTC_TIMESTAMP() "
                . "";
            $rc = ciniki_core_dbInsert($ciniki, $strsql, 'qruqsp.canadadaylog');
            if( $rc['stat'] != 'ok' ) {
                ciniki_core_dbTransactionRollback($ciniki, 'qruqsp.canadadaylog');
                return $rc;
            }
            ciniki_core_dbAddModuleHistory($ciniki, 'qruqsp.canadadaylog', 'qruqsp_canadadaylog_history', $args['tnid'], 
                2, 'qruqsp_canadadaylog_settings', $field, 'detail_value', $ciniki['request']['args'][$field]);
            $ciniki['syncqueue'][] = array('push'=>'qruqsp.canadadaylog.setting', 
                'args'=>array('id'=>$field));
        }
    }

    //
    // Commit the database changes
    //
    $rc = ciniki_core_dbTransactionCommit($ciniki, 'qruqsp.canadadaylog');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Update the last_change date in the tenant modules
    // Ignore the result, as we don't want to stop user updates if this fails.
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'private', 'updateModuleChangeDate');
    ciniki_tenants_updateModuleChangeDate($ciniki, $args['tnid'], 'qruqsp', 'canadadaylog');

    return array('stat'=>'ok');
}
?>
