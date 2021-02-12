<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if ($_FILES["myCsv"]["size"] > 10485760) {
        die ('Twój plik jest zbyt duży. Maksymalny rozmiar pliku to 10MB <a href="index.html">Zacznij od nowa</a>');
      }
    $poland = "";
    $germany = "";
    $france = "";
    $britain = "";
    $czech = "";
    $italy = "";
    $spain = "";
    
    if (isset($_POST["Poland"])) {
        $poland = "POLAND";
    }
    if (isset($_POST["Germany"])) {
        $germany = "GERMANY";
    }
    if (isset($_POST["France"])) {
        $france = "FRANCE";
    }
    if (isset($_POST["Britain"])) {
        $britain = "UNITED KINGDOM";
    }
    if (isset($_POST["Czech"])) {
        $czech = "CZECH REPUBLIC";
    }
    if (isset($_POST["Italy"])) {
        $italy = "ITALY";
    }
    if (isset($_POST["Spain"])) {
        $spain = "SPAIN";
    }
    $fileCsv = basename($_FILES["myCsv"]["name"]);
    move_uploaded_file($_FILES["myCsv"]["tmp_name"], $fileCsv);
    $ext = pathinfo($fileCsv, PATHINFO_EXTENSION);
    if ($ext == 'csv') {
    function array_to_csv_download($array, $filename, $delimiter) {
        // open raw memory as file so no temp files needed, you might run out of memory though
        $f = fopen('php://memory', 'w'); 
        // loop over the input array
        foreach ($array as $line) { 
            // generate csv lines from the inner arrays
            fputcsv($f, $line, $delimiter); 
        }
        // reset the file pointer to the start of the file
        fseek($f, 0);
        // tell the browser it's going to be a csv file
        header('Content-Type: application/csv');
        // tell the browser we want to save it instead of displaying it
        header('Content-Disposition: attachment; filename="'.$filename.'";');
        // make php send the generated csv lines to the browser
        fpassthru($f);
        
    }
    //Wyłączamy pokazywanie błędów
    error_reporting(E_ERROR | E_PARSE);

    //GLOBALNE ZMIENNE

    //wersja $rows bez sumowania
    // $rows = array(
    //     array("Data sprzedazy", "Nr faktury", "Link do pobrania faktury", "Jurysdykcja podatkowa" ,"Waluta transakcji", "Wartosc netto w PLN", "Wartosc VAT w PLN", "Nr tabeli NBP", "Kurs NBP", "Data kursu NBP")
    // );

    //Wersja $rows z sumowaniem
    $rows = array(
        array("Data sprzedazy", "Nr faktury", "Link do pobrania faktury", "Jurysdykcja podatkowa" ,"Waluta transakcji", "Wartosc netto w PLN", "Wartosc VAT w PLN", "Nr tabeli NBP", "Kurs NBP", "Data kursu NBP", "Suma netto w PLN", "Suma VAT w PLN")
    );

    //POBIERAMY PLIK CSV
    $dataCsv = array_map('str_getcsv', file($fileCsv));
    if (count($dataCsv[0])!==95) {
        unlink($fileCsv);
        die('Dodałeś nieprawidłowy raport. Przezcytaj instrukcję obsługi konwertora! <a href="index.html">Zacznij od nowa</a>');
    };
    
    //DŁUGOŚĆ TABELI
    $dataCsv_length = count($dataCsv);

    //POZYCJI W TABELI
    $dateIndex = 11; //data sprzedaży
    $invoiceIndex = 82;
    $priceNettoIndex = 29;
    $priceVatIndex = 42;
    $countryVatIndex = 80;
    $currencyIndex = 53;
    $invoiceUrlIndex = 88;
    //Ilość miejsc po przecinku
    $decimalPlaces = 2;

    //DATA POCZĄTKOWA
    $startDate = $dataCsv[$dataCsv_length-1][$dateIndex];
    $startDateFix = explode("-",$startDate);//convert string to array
    $startDate=$startDateFix[2]."-".$startDateFix[1]."-".$startDateFix[0];//change date format
    $ts = strtotime($startDate);
    $startDate = date('Y-m-d', strtotime('-1 day', $ts));
    $url = 'http://api.nbp.pl/api/exchangerates/rates/a/eur/'.$startDate.'/?format=xml';
    $xml = simplexml_load_file($url);
    $errorCounter = 0;
    while (!is_object($xml) && $errorCounter<=5) {
        $ts = strtotime($startDate);
        $startDate = date('Y-m-d', strtotime('-1 day', $ts));
        $url = 'http://api.nbp.pl/api/exchangerates/rates/a/eur/'.$startDate.'/?format=xml';
        $xml = simplexml_load_file($url);
        $errorCounter++;
    }
 
    //DATA KOŃCOWA
    $endDate = $dataCsv[1][$dateIndex];
    $endDateFix = explode("-",$endDate);//convert string to array
    $endDate=$endDateFix[2]."-".$endDateFix[1]."-".$endDateFix[0];//change date format
    
    //POBIERAMY TABELĘ KURSÓW EUR NBP JSON
    $urlNbpEur = 'http://api.nbp.pl/api/exchangerates/rates/a/eur/'.$startDate.'/'.$endDate.'/?format=json';
    $jsonEur = file_get_contents($urlNbpEur);
    $jsonEur_data =  array_values(json_decode($jsonEur, true));

     //POBIERAMY TABELĘ KURSÓW GBP NBP JSON
     $urlNbpGbp = 'http://api.nbp.pl/api/exchangerates/rates/a/gbp/'.$startDate.'/'.$endDate.'/?format=json';
     $jsonGbp = file_get_contents($urlNbpGbp);
     $jsonGbp_data =  array_values(json_decode($jsonGbp, true));
    
    //WŁASNA TABELA KURSÓW EUR
    for ($i = 0; $i < count($jsonEur_data[3]); $i++) {
        $noEur[$i] = $jsonEur_data[3][$i]["no"];
        $rateEur[$i] = $jsonEur_data[3][$i]["mid"];
        $dateEur[$i] = $jsonEur_data[3][$i]["effectiveDate"];
    }

    //WŁASNA TABELA KURSÓW GBP
    for ($i = 0; $i < count($jsonGbp_data[3]); $i++) {
        $noGbp[$i] = $jsonGbp_data[3][$i]["no"];
        $rateGbp[$i] = $jsonGbp_data[3][$i]["mid"];
        $dateGbp[$i] = $jsonGbp_data[3][$i]["effectiveDate"];
    }
    
    $lengthCounter = 0;
    //WŁASNA TABELA RAPORTU
    for ($i = $dataCsv_length-1; $i > 0; $i--) {
        $country = $dataCsv[$i][$countryVatIndex];
        if ($dataCsv[$i][$priceNettoIndex]!=="" && (($country==$poland) || ($country==$germany) || ($country==$france) || ($country==$britain) || ($country==$czech) || ($country==$italy) || ($country==$spain))){        
            $str = $dataCsv[$i][$dateIndex];
            $dateFix = explode("-",$str);
            $dataCsv[$i][11]=$dateFix[2]."-".$dateFix[1]."-".$dateFix[0];
            $dateSale[$i] = $dataCsv[$i][$dateIndex];
            $priceNetto[$i] = $dataCsv[$i][$priceNettoIndex];
            $priceVat[$i] = $dataCsv[$i][$priceVatIndex];
            $invoiceNo[$i] = $dataCsv[$i][$invoiceIndex];
            $countryVat[$i] = $dataCsv[$i][$countryVatIndex];
            $currency[$i] = $dataCsv[$i][$currencyIndex];
            $invoiceUrl[$i] = $dataCsv[$i][$invoiceUrlIndex];
            $rate = 1;
            $days=1;
            $lengthCounter++;
            if ($currency[$i]=="EUR") {
            for ($j = 0; $j < count($jsonEur_data[3]); $j++) {
                $ts3 = strtotime($dateSale[$i]);
                $dateSaleFix = date('Y-m-d', strtotime('-'.$days.' day', $ts3));
                if (is_int(array_search($dateSaleFix,$dateEur))) {
                    $indeks = array_search($dateSaleFix,$dateEur);
                    $rateNbp[$i] = $rateEur[$indeks];
                    $dateNbp[$i] = $dateEur[$indeks];
                    $noNbp[$i] = $noEur[$indeks];
                    $days=1;
                } else {
                    $days++;
                }
            } 
            } else if ($currency[$i]=="GBP") {
                for ($j = 0; $j < count($jsonGbp_data[3]); $j++) {
                    $ts3 = strtotime($dateSale[$i]);
                    $dateSaleFix = date('Y-m-d', strtotime('-'.$days.' day', $ts3));
                    if (is_int(array_search($dateSaleFix,$dateGbp))) {
                        $indeks = array_search($dateSaleFix,$dateGbp);
                        $rateNbp[$i] = $rateGbp[$indeks];
                        $dateNbp[$i] = $dateGbp[$indeks];
                        $noNbp[$i] = $noGbp[$indeks];
                        $days=1;
                    } else {
                        $days++;
                    }
            }


            }
            $priceNettoPln[$i] = round(($priceNetto[$i] * $rateNbp[$i]),$decimalPlaces);
            $priceVatPln[$i] = round(($priceVat[$i] * $rateNbp[$i]),$decimalPlaces);
            //Sumowanie
            $totalNetto = $totalNetto + $priceNettoPln[$i];
            $totalVat = $totalVat + $priceVatPln[$i];
            
            //Tworzymy tabelę z danymi po konwertacji
            $rows[$lengthCounter] = array();
            $rows[$lengthCounter][0] = $dateSale[$i];
            $rows[$lengthCounter][1] = $invoiceNo[$i];
            $rows[$lengthCounter][2] = $invoiceUrl[$i];
            $rows[$lengthCounter][3] = $countryVat[$i];
            $rows[$lengthCounter][4] = $currency[$i];
            $rows[$lengthCounter][5] = $priceNettoPln[$i];
            $rows[$lengthCounter][6] = $priceVatPln[$i];
            $rows[$lengthCounter][7] = $noNbp[$i];
            $rows[$lengthCounter][8] = $rateNbp[$i];
            $rows[$lengthCounter][9] = $dateNbp[$i];
            
            

        }
    }

    //Zapisywanie sumy do raportu
    $rows[1][10] = $totalNetto;
    $rows[1][11] = $totalVat;
    //EXPORT KONWERTOWANEGO RAPORTU DO PLIKU CSV
    array_to_csv_download($rows, "konwertowany_raport"."_".$dataCsv[$dataCsv_length-1][$dateIndex]."_".$dataCsv[1][$dateIndex].".csv", ',');
    unlink($fileCsv); 
    
    } else {
        unlink($fileCsv);
        die('Błąd! Obsługiwany format pliku to CSV. Załącz plik z rozszerzeniem .csv <a href="index.html">Zacznij od nowa</a>');
    };
} else {
    unlink($fileCsv);
    die('Błąd dostępu!<a href="index.html">Przejdź do strony głównej</a>');
};
?>