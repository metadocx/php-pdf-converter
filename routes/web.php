<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Metadocx\Reporting\Converters\PDF\PDFConverter;

Route::post("/Metadocx/Convert/PDF", function(Request $request) {

    $oPDFConverter = new PDFConverter();    
    $oPDFConverter->loadOptions($request->input("PDFOptions"));    
    $sFileName = $oPDFConverter->convert($request->input("HTML"));
    if ($sFileName !== false) {       
        $headers = ["Content-Type"=> "application/pdf"];
        return response()
                ->download($sFileName, "Report.pdf", $headers);
                ///->deleteFileAfterSend(true);
    } 

});