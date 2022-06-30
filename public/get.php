<?php
//
// Description
// -----------
// This method will return everything for the UI for Canada Day Logger
//
// Arguments
// ---------
// api_key:
// auth_token:
// tnid:        The ID of the tenant to get QSO for.
//
// Returns
// -------
//
function qruqsp_canadadaylog_get($ciniki) {
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
    // Check access to tnid as owner, or sys admin.
    //
    ciniki_core_loadMethod($ciniki, 'qruqsp', 'canadadaylog', 'private', 'checkAccess');
    $rc = qruqsp_canadadaylog_checkAccess($ciniki, $args['tnid'], 'qruqsp.canadadaylog.qsoList');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Load the settings
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbDetailsQuery');
    $rc = ciniki_core_dbDetailsQuery($ciniki, 'qruqsp_canadadaylog_settings', 'tnid', $args['tnid'], 'qruqsp.canadadaylog', 'settings', '');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.canadadaylog.17', 'msg'=>'', 'err'=>$rc['err']));
    }
    $settings = isset($rc['settings']) ? $rc['settings'] : array();

    //
    // Load the date format strings for the user
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'users', 'private', 'datetimeFormat');
    $datetime_format = ciniki_users_datetimeFormat($ciniki, 'php');

    //
    // Load the sections
    //
    ciniki_core_loadMethod($ciniki, 'qruqsp', 'canadadaylog', 'private', 'sectionsLoad');
    $rc = qruqsp_canadadaylog_sectionsLoad($ciniki, $args['tnid']);
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.canadadaylog.20', 'msg'=>'', 'err'=>$rc['err']));
    }
    $sections = $rc['sections'];
    $areas = $rc['areas'];
    
    //
    // Get the list of qsos
    //
    $strsql = "SELECT qruqsp_canadadaylog_qsos.id, "
        . "qruqsp_canadadaylog_qsos.qso_dt, "
        . "DATE_FORMAT(qruqsp_canadadaylog_qsos.qso_dt, '%b %d %H:%i') AS qso_dt_display, "
        . "qruqsp_canadadaylog_qsos.callsign, "
        . "qruqsp_canadadaylog_qsos.recv_rst, "
        . "qruqsp_canadadaylog_qsos.recv_prov_serial, "
        . "qruqsp_canadadaylog_qsos.sent_rst, "
        . "qruqsp_canadadaylog_qsos.band, "
        . "qruqsp_canadadaylog_qsos.mode, "
        . "qruqsp_canadadaylog_qsos.frequency, "
        . "qruqsp_canadadaylog_qsos.flags, "
        . "qruqsp_canadadaylog_qsos.operator "
        . "FROM qruqsp_canadadaylog_qsos "
        . "WHERE qruqsp_canadadaylog_qsos.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "AND YEAR(qso_dt) = 2022 "
        . "ORDER BY qso_dt DESC "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'qruqsp.canadadaylog', array(
        array('container'=>'qsos', 'fname'=>'id', 
            'fields'=>array('id', 'qso_dt', 'qso_dt_display', 'callsign', 'recv_rst', 'recv_prov_serial', 'sent_rst', 'band', 'mode', 'frequency', 'flags', 'operator'),
            ),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $qsos = isset($rc['qsos']) ? $rc['qsos'] : array();

    $rsp = array('stat'=>'ok', 'qsos'=>$qsos, 'areas'=>$areas, 'sections'=>$sections, 'settings'=>$settings);

    $bands = array();
    $modes = array(
        'CW' => 0,
        'PH' => 0,
//        'DIG' => 0,
        );
    $rsp['band'] = '';
    $rsp['mode'] = '';
    $rsp['frequency'] = '';
    $rsp['flags'] = 0;
    $rsp['mode_band_stats'] = array();
    foreach(['CW'=>'CW', 'PH'=>'PH', 'totals'=>'Totals'] as $k => $v) {
        $rsp['mode_band_stats'][$k] = array(
            'label' => $v,
            '160' => array('label' => '160 M', 'num_qsos' => 0),
            '80' => array('label' => '80 M', 'num_qsos' => 0),
            '40' => array('label' => '40 M', 'num_qsos' => 0),
            '20' => array('label' => '20 M', 'num_qsos' => 0),
            '15' => array('label' => '15 M', 'num_qsos' => 0),
            '10' => array('label' => '10 M', 'num_qsos' => 0),
            '6' => array('label' => '6 M', 'num_qsos' => 0),
            '2' => array('label' => '2 M', 'num_qsos' => 0),
            'totals' => array('label' => 'Totals', 'num_qsos' => 0),
            );
    }
    $rsp['section_band_stats'] = array();
    
    foreach($sections as $label => $section) {
        $rsp['section_band_stats'][$label] = array(
            'label' => $label,
            '160' => array('label' => '160 M', 'num_qsos' => 0),
            '80' => array('label' => '80 M', 'num_qsos' => 0),
            '40' => array('label' => '40 M', 'num_qsos' => 0),
            '20' => array('label' => '20 M', 'num_qsos' => 0),
            '15' => array('label' => '15 M', 'num_qsos' => 0),
            '10' => array('label' => '10 M', 'num_qsos' => 0),
            '6' => array('label' => '6 M', 'num_qsos' => 0),
            '2' => array('label' => '2 M', 'num_qsos' => 0),
            'totals' => array('label' => 'Totals', 'num_qsos' => 0),
            );
    }
    $rsp['section_band_stats']['totals'] = array(
        'label' => 'Totals',
        '160' => array('label' => '160 M', 'num_qsos' => 0),
        '80' => array('label' => '80 M', 'num_qsos' => 0),
        '40' => array('label' => '40 M', 'num_qsos' => 0),
        '20' => array('label' => '20 M', 'num_qsos' => 0),
        '15' => array('label' => '15 M', 'num_qsos' => 0),
        '10' => array('label' => '10 M', 'num_qsos' => 0),
        '6' => array('label' => '6 M', 'num_qsos' => 0),
        '2' => array('label' => '2 M', 'num_qsos' => 0),
        'totals' => array('label' => 'Totals', 'num_qsos' => 0),
        );

    //
    // There are 85 bits needed for the map, store as 3 32 bit intergers to make sure works on 32 bit systems
    //
    $map_bits = array(
        0 => 0,
        1 => 0,
        2 => 0,
        );

/*    $rsp['gota_stats'] = array();
    $rsp['gota_stats']['totals'] = array(
        'label' => 'Totals',
        'CW' => array('label' => 'CW', 'num_qsos' => 0),
        'DIG' => array('label' => 'DIG', 'num_qsos' => 0),
        'PH' => array('label' => 'PH', 'num_qsos' => 0),
        'totals' => array('label'=>'Totals', 'num_qsos' => 0),
        ); */
    //
    // Get stats
    //
    foreach($qsos as $qso) {
        if( $rsp['band'] == '' ) {
            $rsp['band'] = $qso['band'];
            $rsp['mode'] = $qso['mode'];
            $rsp['frequency'] = $qso['frequency'];
        }
/*        $section = $qso['section'];
        if( isset($rsp['sections'][$section]) ) {
            $rsp['sections'][$section]['num_qsos']++;
            if( isset($rsp['sections'][$section]['bit']) && $rsp['sections'][$section]['bit'] > 0 ) {
                $bit = $rsp['sections'][$section]['bit'];
                $mul = intdiv($bit-1, 32);
                $bit = $bit - (32 * $mul);
                $map_bits[$mul] |= pow(2, $bit-1);
            }
        } */
        if( !isset($bands[$qso['band']]) ) {
            $bands[$qso['band']] = 1;
        } else {
            $bands[$qso['band']]++;
        }
        if( !isset($modes[$qso['mode']]) ) {
            $modes[$qso['mode']] = 1;
        } else {
            $modes[$qso['mode']]++;
        }
        if( isset($rsp['mode_band_stats'][$qso['mode']][$qso['band']]) ) {
            $rsp['mode_band_stats'][$qso['mode']][$qso['band']]['num_qsos']++;
            $rsp['mode_band_stats'][$qso['mode']]['totals']['num_qsos']++;
            $rsp['mode_band_stats']['totals'][$qso['band']]['num_qsos']++;
            $rsp['mode_band_stats']['totals']['totals']['num_qsos']++;
        } else {
            // Should never happen, checked when entered
            error_log('unknown mode: ' . $qso['mode'] . ' band: ' . $qso['band']);
            error_log(print_r($qso,true));
        }
/*        if( isset($rsp['section_band_stats'][$qso['section']][$qso['band']]) ) {
            $rsp['section_band_stats'][$qso['section']][$qso['band']]['num_qsos']++;
            $rsp['section_band_stats'][$qso['section']]['totals']['num_qsos']++;
            $rsp['section_band_stats']['totals'][$qso['band']]['num_qsos']++;
            $rsp['section_band_stats']['totals']['totals']['num_qsos']++;
        } else {
            // Should never happen, checked when entered
            error_log('unknown section: ' . $qso['section'] . ' band: ' . $qso['band']);
            error_log(print_r($qso,true));
        } */
/*        if( ($qso['flags']&0x01) == 0x01 ) {
            if( $qso['operator'] == '' && isset($settings['callsign']) ) {
                $qso['operator'] = $settings['callsign'];
            }
            if( !isset($rsp['gota_stats'][$qso['operator']]) ) {
                $rsp['gota_stats'][$qso['operator']] = array(
                    'label' => $qso['operator'],
                    'CW' => array('label' => 'CW', 'num_qsos' => 0),
                    'DIG' => array('label' => 'DIG', 'num_qsos' => 0),
                    'PH' => array('label' => 'PH', 'num_qsos' => 0),
                    'totals' => array('label'=>'Totals', 'num_qsos' => 0),
                    );
            }
       
            if( isset($rsp['gota_stats'][$qso['operator']][$qso['mode']]['num_qsos']) ) {
                $rsp['gota_stats'][$qso['operator']][$qso['mode']]['num_qsos'] += 1;
                $rsp['gota_stats'][$qso['operator']]['totals']['num_qsos'] += 1;
                $rsp['gota_stats']['totals'][$qso['mode']]['num_qsos'] += 1;
            }
        } */
    }
    
//    $rsp['map_url'] = '/qruqsp-mods/canadadaylog/ui/maps/' 
//        . sprintf("%08X", $map_bits[2]) . '_' . sprintf("%08X", $map_bits[1]) . '_' . sprintf("%08X", $map_bits[0]) . '.png';

    //
    // Calculate score
    //
//    $qso_points = ($modes['CW']*2) + ($modes['DIG']*2) + $modes['PH'];
    $rsp['scores'] = array(
        array('label' => 'Phone Contacts', 'value' => $modes['PH']),
        array('label' => 'CW Contacts', 'value' => $modes['CW']),
//        array('label' => 'Contact Points', 'value' => $qso_points),
        );

    $rsp['mydetails'] = array(
        array('label' => 'Call Sign', 'value' => (isset($settings['callsign']) ? $settings['callsign'] : '')),
        array('label' => 'Province', 'value' => (isset($settings['province']) ? $settings['province'] : '')),
        );
/*
    //
    // Setup areas vertical
    //
    $vareas = array();
    $row = array();
    foreach($areas as $aid => $area) {
        $row[] = array('label' => $area['name']);
    }
    for($i = 0; $i < 13; $i++) {    
        $row = array();
        foreach($areas as $aid => $area) {
            if( isset($area['sections'][$i]['label']) ) {
                $row[] = array('label' => $area['sections'][$i]['label']);
            } else {
                $row[] = array('label' => '');
            }
        }
        $vareas[] = $row;
    }
    $rsp['vareas'] = $vareas;

    //
    // Setup map_sections
    //
    $map_sections = array();
    foreach($rsp['sections'] as $k => $section) {
        if( $section['num_qsos'] > 0 ) {
            $map_sections[] = $k;
        }
    }
    sort($map_sections);
    $rsp['map_sections'] = implode(',', $map_sections);

    $rsp['totals'] = array(
        'gota_stats' => array_shift($rsp['gota_stats']),
        'mode_band_stats' => array_pop($rsp['mode_band_stats']),
        'section_band_stats' => array_pop($rsp['section_band_stats']),
        );
*/

    //
    // Get the recent qsos
    //
    $rsp['recent'] = array_slice($qsos, 0, 25);

    return $rsp;
}
?>
