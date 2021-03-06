<?php
//
// Description
// -----------
// This method will add a new qso for the tenant.
//
// Arguments
// ---------
// api_key:
// auth_token:
// tnid:        The ID of the tenant to add the QSO to.
//
// Returns
// -------
//
function qruqsp_canadadaylog_qsoAdd(&$ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        'qso_dt'=>array('required'=>'no', 'blank'=>'yes', 'type'=>'datetime', 'name'=>'UTC Date Time of QSO'),
        'callsign'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Call Sign'),
        'recv_rst'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Received RST'),
        'recv_prov_serial'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Received Province/Serial'),
        'sent_rst'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Sent RST'),
        'band'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Band'),
        'mode'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Mode'),
        'frequency'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Frequency'),
        'operator'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Operator'),
        'flags'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Options'),
        'notes'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Notes'),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];
    $args['callsign'] = strtoupper($args['callsign']);
    $args['recv_prov_serial'] = strtoupper($args['recv_prov_serial']);

    if( !isset($args['qso_dt']) || $args['qso_dt'] == '' ) {
        $dt = new DateTime('now', new DateTimezone('UTC'));
        $args['qso_dt'] = $dt->format('Y-m-d H:i:s');
    }

    if( !in_array($args['mode'], array('CW', 'PH', 'DIG')) ) {
        return array('stat'=>'warn', 'err'=>array('code'=>'qruqsp.canadadaylog.13', 'msg'=>'Please choose a mode'));
    }

    //
    // Check access to tnid as owner
    //
    ciniki_core_loadMethod($ciniki, 'qruqsp', 'canadadaylog', 'private', 'checkAccess');
    $rc = qruqsp_canadadaylog_checkAccess($ciniki, $args['tnid'], 'qruqsp.canadadaylog.qsoAdd');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

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
        $rc = qruqsp_canadadaylog_checkDupe($ciniki, $args['tnid'], $args);
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.canadadaylog.29', 'msg'=>'Error', 'err'=>$rc['err']));
        }
        if( isset($rc['dupe']) && $rc['dupe'] == 'yes' ) {
            return array('stat'=>'warn', 'err'=>array('code'=>'qruqsp.canadadaylog.30', 'msg'=>'Duplicate contact!'));
        }
    }
/*
    //
    // Get the current map sections
    //
    $cache_map_sections = array();
    if( isset($settings['cache_map_sections']) ) {
        $cache_map_sections = explode(',', $settings['cache_map_sections']);
    }

    //
    // Load the sections
    //
    ciniki_core_loadMethod($ciniki, 'qruqsp', 'canadadaylog', 'private', 'sectionsLoad');
    $rc = qruqsp_canadadaylog_sectionsLoad($ciniki, $args['tnid']);
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.canadadaylog.10', 'msg'=>'Unable to load sections', 'err'=>$rc['err']));
    }
    $sections = $rc['sections'];

    //
    // Check the section is valid
    //
    if( !isset($sections[$args['section']]) ) {
        return array('stat'=>'warn', 'err'=>array('code'=>'qruqsp.canadadaylog.11', 'msg'=>'Invalid section'));
    }
*/
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
    // Add the qso to the database
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectAdd');
    $rc = ciniki_core_objectAdd($ciniki, $args['tnid'], 'qruqsp.canadadaylog.qso', $args, 0x04);
    if( $rc['stat'] != 'ok' ) {
        ciniki_core_dbTransactionRollback($ciniki, 'qruqsp.canadadaylog');
        return $rc;
    }
    $qso_id = $rc['id'];

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
    ciniki_core_hookExec($ciniki, $args['tnid'], 'ciniki', 'web', 'indexObject', array('object'=>'qruqsp.canadadaylog.qso', 'object_id'=>$qso_id));
/*
    //
    // Update the map if new section
    //
    if( $args['section'] != 'DX' && !in_array($args['section'], $cache_map_sections) ) {
        //
        // Check cache
        //
        ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'hooks', 'cacheDir');
        $rc = ciniki_tenants_hooks_cacheDir($ciniki, $args['tnid'], array());
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.canadadaylog.12', 'msg'=>'Unable to load cached map', 'err'=>$rc['err']));
        }
        $cache_file = $rc['cache_dir'] . '/fielddaymap.jpg';

        $map = new Imagick($cache_file);
        $map->setImageCompressionQuality(60);
        
        if( isset($args['section']) ) {
            $overlay = new Imagick($ciniki['config']['qruqsp.core']['modules_dir'] . '/canadadaylog/maps/' . $args['section'] . '.png');
            $overlay->paintTransparentImage("rgb(111,196,249)", 0, 3000);
            $map->compositeImage($overlay, Imagick::COMPOSITE_DEFAULT, 0, 0);
        }
        $map->writeImage($cache_file);

        $cache_map_sections[] = $args['section'];
        sort($cache_map_sections);

        //
        // Update the settings
        //
        $strsql = "INSERT INTO qruqsp_canadadaylog_settings (tnid, detail_key, detail_value, date_added, last_updated) "
            . "VALUES ('" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "'"
            . ", 'cache_map_sections'"
            . ", '" . ciniki_core_dbQuote($ciniki, implode(',', $cache_map_sections)) . "'"
            . ", UTC_TIMESTAMP(), UTC_TIMESTAMP()) "
            . "ON DUPLICATE KEY UPDATE detail_value = '" . ciniki_core_dbQuote($ciniki, implode(',', $cache_map_sections)) . "' "
            . ", last_updated = UTC_TIMESTAMP() "
            . "";
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbInsert');
        $rc = ciniki_core_dbInsert($ciniki, $strsql, 'qruqsp.canadadaylog');
        if( $rc['stat'] != 'ok' ) {
            ciniki_core_dbTransactionRollback($ciniki, 'qruqsp.canadadaylog');
            return $rc;
        }

//        $ciniki['session']['qruqsp.canadadaylog']['map_sections'][] = $args['section'];
//        sort($ciniki['session']['qruqsp.canadadaylog']['map_sections']);
    }
*/
    ciniki_core_loadMethod($ciniki, 'qruqsp', 'canadadaylog', 'public', 'get');
    return qruqsp_canadadaylog_get($ciniki);
}
?>
