<?php
//
// Description
// -----------
// Check current qsos for matching callsign, band and mode.
// 
// Arguments
// ---------
// ciniki: 
// tnid:            The ID of the current tenant.
// 
// Returns
// ---------
// 
function qruqsp_canadadaylog_checkDupe(&$ciniki, $tnid, $args) {

    //
    // Check for existing qso
    //
    $strsql = "SELECT qruqsp_canadadaylog_qsos.id, "
        . "qruqsp_canadadaylog_qsos.qso_dt, "
        . "qruqsp_canadadaylog_qsos.callsign, "
        . "qruqsp_canadadaylog_qsos.class, "
        . "qruqsp_canadadaylog_qsos.section, "
        . "qruqsp_canadadaylog_qsos.band, "
        . "qruqsp_canadadaylog_qsos.mode, "
        . "qruqsp_canadadaylog_qsos.frequency, "
        . "qruqsp_canadadaylog_qsos.operator, "
        . "qruqsp_canadadaylog_qsos.notes "
        . "FROM qruqsp_canadadaylog_qsos "
        . "WHERE qruqsp_canadadaylog_qsos.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "AND qruqsp_canadadaylog_qsos.callsign = '" . ciniki_core_dbQuote($ciniki, $args['callsign']) . "' "
        . "AND qruqsp_canadadaylog_qsos.band = '" . ciniki_core_dbQuote($ciniki, $args['band']) . "' "
        . "AND qruqsp_canadadaylog_qsos.mode = '" . ciniki_core_dbQuote($ciniki, $args['mode']) . "' "
        . "";
    if( isset($args['id']) && $args['id'] != '' ) {
        $strsql .= "AND qruqsp_canadadaylog_qsos.id <> '" . ciniki_core_dbQuote($ciniki, $args['id']) . "' ";
    }
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'qruqsp.canadadaylog', 'qso');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.canadadaylog.30', 'msg'=>'Unable to load contact', 'err'=>$rc['err']));
    }
    if( isset($rc['rows']) && count($rc['rows']) > 0 ) {
        return array('stat'=>'ok', 'dupe'=>'yes');
    }

    return array('stat'=>'ok', 'dupe'=>'no');
}
?>
