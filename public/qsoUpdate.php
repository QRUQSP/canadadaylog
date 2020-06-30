<?php
//
// Description
// ===========
//
// Arguments
// ---------
//
// Returns
// -------
//
function qruqsp_canadadaylog_qsoUpdate(&$ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        'qso_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'QSO'),
        'qso_dt'=>array('required'=>'no', 'blank'=>'no', 'type'=>'datetime', 'name'=>'UTC Date Time of QSO'),
        'callsign'=>array('required'=>'no', 'blank'=>'no', 'name'=>'Call Sign'),
        'recv_rst'=>array('required'=>'no', 'blank'=>'no', 'name'=>'Received RS(T)'),
        'recv_prov_serial'=>array('required'=>'no', 'blank'=>'no', 'name'=>'Received Province Serial'),
        'sent_rst'=>array('required'=>'no', 'blank'=>'no', 'name'=>'Sent RS(T)'),
        'band'=>array('required'=>'no', 'blank'=>'no', 'name'=>'Band'),
        'mode'=>array('required'=>'no', 'blank'=>'no', 'name'=>'Mode'),
        'frequency'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Frequency'),
        'operator'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Operator'),
        'flags'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Options'),
        'notes'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Notes'),
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
    $rc = qruqsp_canadadaylog_checkAccess($ciniki, $args['tnid'], 'qruqsp.canadadaylog.qsoUpdate');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Uppercase the fields
    //
    if( isset($args['callsign']) ) {
        $args['callsign'] = strtoupper($args['callsign']);
    }
    if( isset($args['recv_prov_serial']) ) {
        $args['recv_prov_serial'] = trim(strtoupper($args['recv_prov_serial']));
    }
    if( isset($args['mode']) && !in_array($args['mode'], array('CW', 'PH')) ) {
        return array('stat'=>'warn', 'err'=>array('code'=>'qruqsp.canadadaylog.28', 'msg'=>'Please choose a mode'));
    }

    //
    // Load existing qso
    //
    $strsql = "SELECT qruqsp_canadadaylog_qsos.id, "
        . "qruqsp_canadadaylog_qsos.qso_dt, "
        . "qruqsp_canadadaylog_qsos.callsign, "
        . "qruqsp_canadadaylog_qsos.recv_rst, "
        . "qruqsp_canadadaylog_qsos.recv_prov_serial, "
        . "qruqsp_canadadaylog_qsos.sent_rst, "
        . "qruqsp_canadadaylog_qsos.band, "
        . "qruqsp_canadadaylog_qsos.mode, "
        . "qruqsp_canadadaylog_qsos.frequency, "
        . "qruqsp_canadadaylog_qsos.operator, "
        . "qruqsp_canadadaylog_qsos.notes "
        . "FROM qruqsp_canadadaylog_qsos "
        . "WHERE qruqsp_canadadaylog_qsos.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "AND qruqsp_canadadaylog_qsos.id = '" . ciniki_core_dbQuote($ciniki, $args['qso_id']) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'qruqsp.canadadaylog', 'qso');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.canadadaylog.30', 'msg'=>'Unable to load contact', 'err'=>$rc['err']));
    }
    if( !isset($rc['qso']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.canadadaylog.31', 'msg'=>'Unable to find requested contact'));
    }
    $qso = $rc['qso'];
    
    //
    // Load the settings
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbDetailsQuery');
    $rc = ciniki_core_dbDetailsQuery($ciniki, 'qruqsp_canadadaylog_settings', 'tnid', $args['tnid'], 'qruqsp.canadadaylog', 'settings', '');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.canadadaylog.19', 'msg'=>'Unable to load settings', 'err'=>$rc['err']));
    }
    $settings = isset($rc['settings']) ? $rc['settings'] : array();

    //
    // Check if allow-dupes is set and no
    //
    if( isset($settings['allow-dupes']) && $settings['allow-dupes'] == 'no' ) {
        //
        // Check for dupe
        //
        ciniki_core_loadMethod($ciniki, 'qruqsp', 'canadadaylog', 'private', 'checkDupe');
        $rc = qruqsp_canadadaylog_checkDupe($ciniki, $args['tnid'], array(
            'id' => $args['qso_id'],
            'callsign' => (isset($args['callsign']) ? $args['callsign'] : $qso['callsign']),
            'band' => (isset($args['band']) ? $args['band'] : $qso['band']),
            'mode' => (isset($args['mode']) ? $args['mode'] : $qso['mode']),
            ));
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.canadadaylog.29', 'msg'=>'Unable to check for dupe', 'err'=>$rc['err']));
        }
        if( isset($rc['dupe']) && $rc['dupe'] == 'yes' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.canadadaylog.30', 'msg'=>'Duplicate contact.'));
        }
    }

    //
    // Start transaction
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionStart');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionRollback');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionCommit');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbAddModuleHistory');
    $rc = ciniki_core_dbTransactionStart($ciniki, 'qruqsp.canadadaylog');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Update the QSO in the database
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');
    $rc = ciniki_core_objectUpdate($ciniki, $args['tnid'], 'qruqsp.canadadaylog.qso', $args['qso_id'], $args, 0x04);
    if( $rc['stat'] != 'ok' ) {
        ciniki_core_dbTransactionRollback($ciniki, 'qruqsp.canadadaylog');
        return $rc;
    }

    //
    // Commit the transaction
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

    //
    // Update the web index if enabled
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'hookExec');
    ciniki_core_hookExec($ciniki, $args['tnid'], 'ciniki', 'web', 'indexObject', array('object'=>'qruqsp.canadadaylog.qso', 'object_id'=>$args['qso_id']));

    return array('stat'=>'ok');
}
?>
