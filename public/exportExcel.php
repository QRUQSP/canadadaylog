<?php
//
// Description
// -----------
// This method will create an excel file with all qso details
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
function qruqsp_canadadaylog_exportExcel($ciniki) {
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
        return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.canadadaylog.9', 'msg'=>'', 'err'=>$rc['err']));
    }
    $settings = isset($rc['settings']) ? $rc['settings'] : array();

    //
    // Load the date format strings for the user
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'users', 'private', 'datetimeFormat');
    $datetime_format = ciniki_users_datetimeFormat($ciniki, 'php');

    //
    // Get the list of qsos
    //
    $strsql = "SELECT qruqsp_canadadaylog_qsos.id, "
        . "qruqsp_canadadaylog_qsos.qso_dt, "
        . "DATE_FORMAT(qruqsp_canadadaylog_qsos.qso_dt, '%Y-%m-%d %H%i') AS qso_dt_display, "
        . "qruqsp_canadadaylog_qsos.callsign, "
        . "qruqsp_canadadaylog_qsos.class, "
        . "qruqsp_canadadaylog_qsos.section, "
        . "qruqsp_canadadaylog_qsos.band, "
        . "qruqsp_canadadaylog_qsos.mode, "
        . "qruqsp_canadadaylog_qsos.frequency, "
        . "IF((qruqsp_canadadaylog_qsos.flags&0x01) = 0x01, 'Yes', 'No') AS gota, "
        . "qruqsp_canadadaylog_qsos.operator, "
        . "qruqsp_canadadaylog_qsos.notes "
        . "FROM qruqsp_canadadaylog_qsos "
        . "WHERE qruqsp_canadadaylog_qsos.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "AND YEAR(qso_dt) = 2021 "
        . "ORDER BY qso_dt ASC "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'qruqsp.canadadaylog', array(
        array('container'=>'qsos', 'fname'=>'id', 
            'fields'=>array('id', 'qso_dt', 'qso_dt_display', 'callsign', 'class', 'section', 'band', 'mode', 'frequency', 
                'gota', 'operator', 'notes'),
            ),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $qsos = isset($rc['qsos']) ? $rc['qsos'] : array();

    //
    // Create excel file
    //
    require($ciniki['config']['core']['lib_dir'] . '/PHPExcel/PHPExcel.php');
    $objPHPExcel = new PHPExcel();
    $objPHPExcelWorksheet = $objPHPExcel->setActiveSheetIndex(0);

    $columns = array(
        'qso_dt_display' => 'Date/Time',
        'callsign' => 'Call Sign',
        'class' => 'Class',
        'section' => 'Section',
        'band' => 'Band',
        'mode' => 'Mode',
        );
    if( isset($settings['category-operator']) && $settings['category-operator'] == 'MULTI-OP' ) {
        $columns['gota'] = 'GOTA';
        $columns['operator'] = 'Operator';
    }
    if( isset($settings['ui-notes']) && $settings['ui-notes'] == 'yes' ) {
        $columns['notes'] = 'Notes';
    }
    $row = 1;
    $col = 0;
    foreach($columns as $k => $v) {
        $objPHPExcelWorksheet->setCellValueByColumnAndRow($col++, $row, $v, false);
    }
    $objPHPExcelWorksheet->getStyle('A1:' . PHPExcel_Cell::stringFromColumnIndex($col) . '1')->getFont()->setBold(true);
    $objPHPExcelWorksheet->freezePane('A2');

    $row++;

    foreach($qsos as $qso) {
        $col = 0;
        foreach($columns as $k => $v) {
            $objPHPExcelWorksheet->setCellValueByColumnAndRow($col, $row, $qso[$k], false);
            $col++;
        }
        $row++;
    }

    PHPExcel_Shared_Font::setAutoSizeMethod(PHPExcel_Shared_Font::AUTOSIZE_METHOD_EXACT);

    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="CanadaDayContacts.xls"');
    header('Cache-Control: max-age=0');

    $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
    $objWriter->save('php://output');

    return array('stat'=>'exit');
}
?>
