<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Metadocx\Reporting\Converters\PDF\PDFConverter;

Route::post("/Metadocx/Convert/PDF", function(Request $request) {

    $oPDFConverter = new PDFConverter();    
    $oPDFConverter->loadOptions($request->input("PDFOptions"));    
    $oPDFConverter->setConvertToImages($request->input("ConvertToImages"));
    $aData = $oPDFConverter->convert($request->input("CoverPage"), $request->input("HTML"));
    if (is_array($aData)) {
        return response()->json($aData);
    } elseif ($aData !== false && is_string($aData)) {       
        // Single file download
        $headers = ["Content-Type"=> "application/pdf"];
        return response()
                ->download($aData, "Report.pdf", $headers)
                ->deleteFileAfterSend(true);
    } 

});