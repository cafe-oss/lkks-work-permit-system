<?php

/**
 * PDF Styles Template
 * File: templates/pdf-styles.php
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<style>
    body { font-family: Arial, sans-serif; font-size: 12px; }
    .header { text-align: center; margin-bottom: 20px; }
    .permit-card { 
        border: 1px solid #000; 
        margin-bottom: 20px; 
        padding: 15px;
        page-break-inside: avoid;
    }
    .permit-title { 
        font-size: 18px; 
        font-weight: bold; 
        text-align: center; 
        margin-bottom: 15px;
    }
    .field-row { 
        margin-bottom: 8px; 
        clear: both;
    }
    .field-label { 
        font-weight: bold; 
        display: inline-block; 
        width: 120px;
    }
    .field-value { 
        display: inline-block;
    }
    .signature-section { 
        margin-top: 20px; 
        border-top: 1px solid #ccc; 
        padding-top: 10px;
    }
    .status { 
        font-weight: bold; 
        font-size: 14px; 
        text-transform: uppercase;
    }
    .status.approved { color: green; }
    .status.pending { color: orange; }
    .status.cancelled { color: red; }
        
   
</style>